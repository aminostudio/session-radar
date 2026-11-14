<?php
/**
 * WordPress integration checks: uses genuine WordPress APIs, real user roles
 * and native session-token persistence in a disposable CI database.
 *
 * Run from a temporary WordPress installation:
 *   wp eval-file /path/to/tests/wordpress-integration.php --path=/tmp/orbit-wp
 *
 * NEVER execute on Amino #LAB or a production WordPress database.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "This fixture requires disposable WordPress WP-CLI.\n" );
    exit( 1 );
}
if ( ! defined( 'AMINO_ORBIT_VERSION' ) ) {
    WP_CLI::error( 'Amino Orbit is not loaded: activate the plugin first.' );
}

function orbit_integration_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        WP_CLI::error( 'FAIL: ' . $message );
    }
    WP_CLI::log( 'PASS: ' . $message );
}

function orbit_integration_render(): string {
    ob_start();
    Amino_Orbit::render_widget();
    return (string) ob_get_clean();
}

// All identifiers and session records below live only in GitHub Actions.
$admin = get_user_by( 'login', 'orbit_ci_admin' );
orbit_integration_assert( $admin instanceof WP_User, 'Disposable CI admin exists' );
$editor_id = wp_insert_user(
    array(
        'user_login' => 'orbit_ci_editor',
        'user_pass'  => wp_generate_password( 32, true, true ),
        'user_email' => 'editor@orbit-ci.invalid',
        'role'       => 'editor',
    )
);
orbit_integration_assert( ! is_wp_error( $editor_id ), 'Disposable editor created in CI database' );
$editor_id = (int) $editor_id;
$admin_id  = (int) $admin->ID;
$_SERVER['REMOTE_ADDR']     = '192.0.2.42';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/159.0 Windows NT 10.0';

$admin_manager  = WP_Session_Tokens::get_instance( $admin_id );
$editor_manager = WP_Session_Tokens::get_instance( $editor_id );
$same_expiry = time() + HOUR_IN_SECONDS;
$token_one = $admin_manager->create( $same_expiry );
$token_two = $admin_manager->create( $same_expiry );
$editor_token = $editor_manager->create( time() + HOUR_IN_SECONDS );
orbit_integration_assert(
    is_string( $token_one ) && is_string( $token_two ) && $token_one !== $token_two,
    'Two independent real WordPress sessions for one account'
);
orbit_integration_assert( is_array( $admin_manager->get( $token_one ) ), 'WordPress stores native session metadata' );

// Make the collision case deterministic: two different WordPress token
// verifiers refer to identical login/expiry/IP/browser metadata.
$native_sessions = get_user_meta( $admin_id, 'session_tokens', true );
$verifier_one = hash( 'sha256', $token_one );
$verifier_two = hash( 'sha256', $token_two );
orbit_integration_assert(
    isset( $native_sessions[ $verifier_one ], $native_sessions[ $verifier_two ] ),
    'WordPress stores independent verifiers for both same-account sessions'
);
$native_sessions[ $verifier_two ] = $native_sessions[ $verifier_one ];
update_user_meta( $admin_id, 'session_tokens', $native_sessions );
orbit_integration_assert(
    $admin_manager->get( $token_one ) === $admin_manager->get( $token_two ),
    'Both live sessions deliberately share identical metadata'
);

wp_set_current_user( $admin_id );
update_option(
    'amino_orbit_settings',
    array(
        'viewer_roles'     => array(),
        'inactive_minutes' => 5,
        'refresh_seconds'  => 30,
        'show_ip'          => 0,
        'activity_enabled' => 1,
    )
);
$start = microtime( true );
global $wpdb;
$queries_before = $wpdb->num_queries;
$admin_html = orbit_integration_render();
$render_ms  = round( ( microtime( true ) - $start ) * 1000, 2 );
$queries    = $wpdb->num_queries - $queries_before;

orbit_integration_assert(
    str_contains( $admin_html, '<strong>3</strong><small>Sessions</small>' ),
    'Widget counts three real WordPress sessions'
);
orbit_integration_assert(
    str_contains( $admin_html, '<strong>2</strong><small>Users</small>' ),
    'Two distinct users counted without conflating their sessions'
);
orbit_integration_assert( ! str_contains( $admin_html, '192.0.2.42' ), 'IP is hidden by default' );
orbit_integration_assert(
    3 === substr_count( $admin_html, 'class="amino-orbit__user"' ),
    'All three valid sessions appear'
);
WP_CLI::log( "INFO: Disposable three-session widget render: {$render_ms} ms; {$queries} WordPress DB queries (informational only)." );

// One activity fingerprint must not mark other sessions as active.
$private_fingerprint = ( new ReflectionClass( 'Amino_Orbit' ) )->getMethod( 'verifier_fingerprint' );
$private_fingerprint->setAccessible( true );
$fingerprint = $private_fingerprint->invoke( null, $verifier_one );
$second_fingerprint = $private_fingerprint->invoke( null, $verifier_two );
orbit_integration_assert(
    $fingerprint !== $second_fingerprint,
    'Different WordPress token verifiers have distinct Orbit presence IDs'
);
update_user_meta( $admin_id, '_amino_orbit_presence', array( $fingerprint => time() ) );
$activity_html = orbit_integration_render();
orbit_integration_assert(
    1 === substr_count( $activity_html, 'amino-orbit__status--active' ) &&
    2 === substr_count( $activity_html, 'amino-orbit__status--session' ),
    'One active session is distinguished from two open sessions'
);

// Viewer permissions are computed by WordPress capabilities and user roles.
wp_set_current_user( $editor_id );
orbit_integration_assert( '' === orbit_integration_render(), 'Editor cannot see widget without permission' );
$enabled = get_option( 'amino_orbit_settings' );
$enabled['viewer_roles'] = array( 'editor' );
$enabled['show_ip'] = 1;
update_option( 'amino_orbit_settings', $enabled );

$editor_html = orbit_integration_render();
orbit_integration_assert(
    str_contains( $editor_html, 'orbit_ci_editor' ) && ! str_contains( $editor_html, '192.0.2.42' ),
    'Editor can view sessions but can never see IP addresses'
);
wp_set_current_user( $admin_id );
$ip_html = orbit_integration_render();
orbit_integration_assert( str_contains( $ip_html, '192.0.2.42' ), 'Admin can opt in to WordPress-native IP visibility' );

$admin_manager->destroy( $token_two );
$remaining_html = orbit_integration_render();
orbit_integration_assert(
    str_contains( $remaining_html, '<strong>2</strong><small>Sessions</small>' ),
    'Native WordPress logout immediately removes the destroyed session'
);

// Dynamic locale switching in the same CLI bootstrap varies across old
// WordPress Core versions. For 6.4, the CI pipeline instead boots a *fresh*
// WordPress process under each installed locale and checks real gettext and
// widget HTML using tests/minimum-locale.php.
if ( '1' === getenv( 'ORBIT_CHECK_LOCALE_IN_FRESH_BOOT' ) ) {
    WP_CLI::log( 'INFO: Dynamic locale switch assertions replaced by independent FR/ES WordPress bootstrap tests in CI.' );
} else {
// WordPress locale switching exercises actual bundled .mo files, not mocks.
orbit_integration_assert( switch_to_locale( 'fr_FR' ), 'WordPress switches to the installed French locale' );
orbit_integration_assert(
    'Session ouverte' === __( 'Session open', 'amino-orbit' ),
    'Bundled French translations load through WordPress gettext'
);
orbit_integration_assert(
    str_contains( orbit_integration_render(), 'Actualisation : 30 s' ),
    'French session widget renders the configured refresh label'
);
restore_previous_locale();

orbit_integration_assert( switch_to_locale( 'es_ES' ), 'WordPress switches to the installed Spanish locale' );
orbit_integration_assert(
    'Sesión abierta' === __( 'Session open', 'amino-orbit' ),
    'Bundled Spanish translations load through WordPress gettext'
);
orbit_integration_assert(
    str_contains( orbit_integration_render(), 'Actualización: 30 s' ),
    'Spanish session widget renders the configured refresh label'
);
restore_previous_locale();
}

orbit_integration_assert(
    false === has_action( 'wp_ajax_nopriv_amino_orbit_refresh' ) &&
    false === has_action( 'wp_ajax_nopriv_amino_orbit_ping' ),
    'No unauthenticated AJAX endpoints are registered'
);
orbit_integration_assert(
    false !== has_action( 'wp_ajax_amino_orbit_refresh' ) &&
    false !== has_action( 'wp_ajax_amino_orbit_ping' ),
    'Authenticated AJAX endpoints are registered'
);

// Test WordPress's genuine privacy hooks with the same real CI user/session.
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
orbit_integration_assert(
    isset( $exporters['amino-orbit']['callback'], $erasers['amino-orbit']['callback'] ),
    'WordPress registers the Orbit privacy exporter and eraser'
);
$exported = call_user_func( $exporters['amino-orbit']['callback'], 'admin@orbit-ci.invalid', 1 );
orbit_integration_assert(
    true === $exported['done'] && 1 === count( $exported['data'] ),
    'Core privacy exporter finds exactly one active Orbit observation'
);
orbit_integration_assert(
    ! str_contains( wp_json_encode( $exported ), $fingerprint ) &&
    ! str_contains( wp_json_encode( $exported ), $token_one ),
    'Core privacy exporter does not reveal fingerprints or native auth tokens'
);
$erased = call_user_func( $erasers['amino-orbit']['callback'], 'admin@orbit-ci.invalid', 1 );
orbit_integration_assert(
    $erased['items_removed'] && ! $erased['items_retained'] &&
    ! get_user_meta( $admin_id, '_amino_orbit_presence', true ),
    'Core privacy eraser deletes plugin-specific user activity'
);
orbit_integration_assert(
    is_array( $admin_manager->get( $token_one ) ),
    'WordPress authentication session survives personal-data erasure'
);
orbit_integration_assert(
    array() === call_user_func( $exporters['amino-orbit']['callback'], 'admin@orbit-ci.invalid', 1 )['data'],
    'After erasure, a second export contains no Orbit activity'
);

WP_CLI::success( 'Real WordPress integration checks passed; all data is confined to CI.' );
