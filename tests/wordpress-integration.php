<?php
/**
 * WordPress integration checks: uses genuine WordPress APIs, real user roles
 * and native session-token persistence in a disposable CI database.
 *
 * Run from a temporary WordPress installation:
 *   wp eval-file /path/to/tests/wordpress-integration.php --path=/tmp/sessivue-wp
 *
 * NEVER execute on Amino #LAB or a production WordPress database.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "This fixture requires disposable WordPress WP-CLI.\n" );
    exit( 1 );
}
if ( ! defined( 'SESSIVUE_VERSION' ) ) {
    WP_CLI::error( 'Sessivue is not loaded: activate the plugin first.' );
}

function sessivue_integration_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        WP_CLI::error( 'FAIL: ' . $message );
    }
    WP_CLI::log( 'PASS: ' . $message );
}

function sessivue_integration_render(): string {
    ob_start();
    Sessivue_Plugin::render_widget();
    return (string) ob_get_clean();
}

// All identifiers and session records below live only in GitHub Actions.
$admin = get_user_by( 'login', 'sessivue_ci_admin' );
sessivue_integration_assert( $admin instanceof WP_User, 'Disposable CI admin exists' );
$editor_id = wp_insert_user(
    array(
        'user_login' => 'sessivue_ci_editor',
        'user_pass'  => wp_generate_password( 32, true, true ),
        'user_email' => 'editor@sessivue-ci.invalid',
        'role'       => 'editor',
    )
);
sessivue_integration_assert( ! is_wp_error( $editor_id ), 'Disposable editor created in CI database' );
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
sessivue_integration_assert(
    is_string( $token_one ) && is_string( $token_two ) && $token_one !== $token_two,
    'Two independent real WordPress sessions for one account'
);
sessivue_integration_assert( is_array( $admin_manager->get( $token_one ) ), 'WordPress stores native session metadata' );

// Make the collision case deterministic: two different WordPress token
// verifiers refer to identical login/expiry/IP/browser metadata.
$native_sessions = get_user_meta( $admin_id, 'session_tokens', true );
$verifier_one = hash( 'sha256', $token_one );
$verifier_two = hash( 'sha256', $token_two );
sessivue_integration_assert(
    isset( $native_sessions[ $verifier_one ], $native_sessions[ $verifier_two ] ),
    'WordPress stores independent verifiers for both same-account sessions'
);
$native_sessions[ $verifier_two ] = $native_sessions[ $verifier_one ];
update_user_meta( $admin_id, 'session_tokens', $native_sessions );
sessivue_integration_assert(
    $admin_manager->get( $token_one ) === $admin_manager->get( $token_two ),
    'Both live sessions deliberately share identical metadata'
);

wp_set_current_user( $admin_id );
update_option(
    'sessivue_settings',
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
$admin_html = sessivue_integration_render();
$render_ms  = round( ( microtime( true ) - $start ) * 1000, 2 );
$queries    = $wpdb->num_queries - $queries_before;

sessivue_integration_assert(
    str_contains( $admin_html, '<strong>3</strong><small>Sessions</small>' ),
    'Widget counts three real WordPress sessions'
);
sessivue_integration_assert(
    str_contains( $admin_html, '<strong>2</strong><small>Users</small>' ),
    'Two distinct users counted without conflating their sessions'
);
sessivue_integration_assert( ! str_contains( $admin_html, '192.0.2.42' ), 'IP is hidden by default' );
sessivue_integration_assert(
    3 === substr_count( $admin_html, 'class="sessivue__user"' ),
    'All three valid sessions appear'
);
WP_CLI::log( "INFO: Disposable three-session widget render: {$render_ms} ms; {$queries} WordPress DB queries (informational only)." );

// One activity fingerprint must not mark other sessions as active.
$private_fingerprint = ( new ReflectionClass( 'Sessivue_Plugin' ) )->getMethod( 'verifier_fingerprint' );
$private_fingerprint->setAccessible( true );
$fingerprint = $private_fingerprint->invoke( null, $verifier_one );
$second_fingerprint = $private_fingerprint->invoke( null, $verifier_two );
sessivue_integration_assert(
    $fingerprint !== $second_fingerprint,
    'Different WordPress token verifiers have distinct Sessivue presence IDs'
);
update_user_meta( $admin_id, '_sessivue_presence', array( $fingerprint => time() ) );
$activity_html = sessivue_integration_render();
sessivue_integration_assert(
    1 === substr_count( $activity_html, 'sessivue__status--active' ) &&
    2 === substr_count( $activity_html, 'sessivue__status--session' ),
    'One active session is distinguished from two open sessions'
);

// Viewer permissions are computed by WordPress capabilities and user roles.
wp_set_current_user( $editor_id );
sessivue_integration_assert( '' === sessivue_integration_render(), 'Editor cannot see widget without permission' );
$enabled = get_option( 'sessivue_settings' );
$enabled['viewer_roles'] = array( 'editor' );
$enabled['show_ip'] = 1;
update_option( 'sessivue_settings', $enabled );

$editor_html = sessivue_integration_render();
sessivue_integration_assert(
    str_contains( $editor_html, 'sessivue_ci_editor' ) && ! str_contains( $editor_html, '192.0.2.42' ),
    'Editor can view sessions but can never see IP addresses'
);
wp_set_current_user( $admin_id );
$ip_html = sessivue_integration_render();
sessivue_integration_assert( str_contains( $ip_html, '192.0.2.42' ), 'Admin can opt in to WordPress-native IP visibility' );

$admin_manager->destroy( $token_two );
$remaining_html = sessivue_integration_render();
sessivue_integration_assert(
    str_contains( $remaining_html, '<strong>2</strong><small>Sessions</small>' ),
    'Native WordPress logout immediately removes the destroyed session'
);

// Dynamic locale switching in the same CLI bootstrap varies across old
// WordPress Core versions. For 6.4, the CI pipeline instead boots a *fresh*
// WordPress process under each installed locale and checks real gettext and
// widget HTML using tests/minimum-locale.php.
if ( '1' === getenv( 'SESSIVUE_CHECK_LOCALE_IN_FRESH_BOOT' ) ) {
    WP_CLI::log( 'INFO: Dynamic locale switch assertions replaced by independent FR/ES WordPress bootstrap tests in CI.' );
} else {
// WordPress locale switching exercises standard WordPress.org-style language packs, not mocks.
sessivue_integration_assert(
    'Session open' === __( 'Session open', 'sessivue' ),
    'English source messages are always available without an extra language pack'
);
sessivue_integration_assert( switch_to_locale( 'fr_FR' ), 'WordPress switches to the installed French locale' );
sessivue_integration_assert(
    'Session ouverte' === __( 'Session open', 'sessivue' ),
    'French language-pack translations load through WordPress gettext'
);
sessivue_integration_assert(
    str_contains( sessivue_integration_render(), 'Actualisation : 30 s' ),
    'French session widget renders the configured refresh label'
);
restore_previous_locale();

sessivue_integration_assert( switch_to_locale( 'es_ES' ), 'WordPress switches to the installed Spanish locale' );
sessivue_integration_assert(
    'Sesión abierta' === __( 'Session open', 'sessivue' ),
    'Spanish language-pack translations load through WordPress gettext'
);
sessivue_integration_assert(
    str_contains( sessivue_integration_render(), 'Actualización: 30 s' ),
    'Spanish session widget renders the configured refresh label'
);
restore_previous_locale();
}

sessivue_integration_assert(
    false === has_action( 'wp_ajax_nopriv_sessivue_refresh' ) &&
    false === has_action( 'wp_ajax_nopriv_sessivue_ping' ),
    'No unauthenticated AJAX endpoints are registered'
);
sessivue_integration_assert(
    false !== has_action( 'wp_ajax_sessivue_refresh' ) &&
    false !== has_action( 'wp_ajax_sessivue_ping' ),
    'Authenticated AJAX endpoints are registered'
);

// Test WordPress's genuine privacy hooks with the same real CI user/session.
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
sessivue_integration_assert(
    isset( $exporters['sessivue']['callback'], $erasers['sessivue']['callback'] ),
    'WordPress registers the Sessivue privacy exporter and eraser'
);
$exported = call_user_func( $exporters['sessivue']['callback'], 'admin@sessivue-ci.invalid', 1 );
sessivue_integration_assert(
    true === $exported['done'] && 1 === count( $exported['data'] ),
    'Core privacy exporter finds exactly one active Sessivue observation'
);
sessivue_integration_assert(
    ! str_contains( wp_json_encode( $exported ), $fingerprint ) &&
    ! str_contains( wp_json_encode( $exported ), $token_one ),
    'Core privacy exporter does not reveal fingerprints or native auth tokens'
);
$erased = call_user_func( $erasers['sessivue']['callback'], 'admin@sessivue-ci.invalid', 1 );
sessivue_integration_assert(
    $erased['items_removed'] && ! $erased['items_retained'] &&
    ! get_user_meta( $admin_id, '_sessivue_presence', true ),
    'Core privacy eraser deletes plugin-specific user activity'
);
sessivue_integration_assert(
    is_array( $admin_manager->get( $token_one ) ),
    'WordPress authentication session survives personal-data erasure'
);
sessivue_integration_assert(
    array() === call_user_func( $exporters['sessivue']['callback'], 'admin@sessivue-ci.invalid', 1 )['data'],
    'After erasure, a second export contains no Sessivue activity'
);

// Emulate a legacy pre-opt-in option payload on genuine WordPress. The
// first browser bootstrap has already occurred, so trigger the migration
// explicitly here with its original settings preserved.
$before = get_option( 'sessivue_settings', array() );
$before['activity_enabled'] = 0;
update_option( 'sessivue_settings', $before );
unset( $before['activity_enabled'] );
update_option( 'sessivue_settings', $before );
update_user_meta( $admin_id, '_sessivue_presence', array( 'legacy-stale' => time() - 20 ) );
Sessivue_Plugin::migrate_legacy_privacy();
$after = get_option( 'sessivue_settings', array() );
sessivue_integration_assert(
    0 === $after['activity_enabled'] &&
    $before['viewer_roles'] === $after['viewer_roles'] &&
    $before['refresh_seconds'] === $after['refresh_seconds'],
    'Legacy privacy migration records OFF while preserving settings'
);
sessivue_integration_assert(
    ! get_user_meta( $admin_id, '_sessivue_presence', true ) &&
    is_array( $admin_manager->get( $token_one ) ),
    'Legacy activity purged without ending native WordPress login session'
);
update_user_meta( $admin_id, '_sessivue_presence', array( 'fresh-mark' => time() ) );
Sessivue_Plugin::migrate_legacy_privacy();
sessivue_integration_assert(
    (bool) get_user_meta( $admin_id, '_sessivue_presence', true ),
    'Migration is idempotent and does not erase data on each request'
);
delete_user_meta( $admin_id, '_sessivue_presence' );

WP_CLI::success( 'Real WordPress integration checks passed; all data is confined to CI.' );
