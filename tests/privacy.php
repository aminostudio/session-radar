<?php
/**
 * Privacy opt-in regression checks using isolated WordPress test doubles.
 * No external account, database or WordPress installation is touched.
 */
define( 'ABSPATH', '/tmp/' );
$GLOBALS['sessivue_privacy'] = array(
    'settings' => array(),
    'deletions' => array(),
    'updates' => array(),
    'policy' => array(),
    'deletion_result' => true,
    'records_remaining' => false,
);
class Sessivue_Privacy_Roles {
    public array $roles = array(
        'administrator' => array( 'name' => 'Administrator' ),
        'editor' => array( 'name' => 'Editor' ),
    );
}
function wp_roles() { return new Sessivue_Privacy_Roles(); }
function get_option( $name, $default = array() ) { return $GLOBALS['sessivue_privacy']['settings']; }
function update_option( $name, $value ) {
    $GLOBALS['sessivue_privacy']['settings'] = $value;
    $GLOBALS['sessivue_privacy']['updates'][] = array( $name, $value );
    return true;
}
function wp_parse_args( $data, $defaults ) { return array_merge( $defaults, $data ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_add_privacy_policy_content( $name, $content ) {
    $GLOBALS['sessivue_privacy']['policy'][] = array( $name, $content );
}
function delete_metadata( $type, $id, $key, $value, $all ) {
    $GLOBALS['sessivue_privacy']['deletions'][] = array( $type, $id, $key, $value, $all );
    return $GLOBALS['sessivue_privacy']['deletion_result'];
}
function get_users( $args ) {
    $GLOBALS['sessivue_privacy']['last_user_query'] = $args;
    return $GLOBALS['sessivue_privacy']['records_remaining'] ? array( 12 ) : array();
}
class Sessivue_Privacy_DB {
    public string $usermeta = 'wp_usermeta';
    public string $last_error = '';
    public function prepare( $query, $key ) { return str_replace( '%s', "'" . $key . "'", $query ); }
    public function get_var( $query ) { return $GLOBALS['sessivue_privacy']['records_remaining'] ? '12' : null; }
}
$GLOBALS['wpdb'] = new Sessivue_Privacy_DB();
function check( bool $ok, string $message ): void {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
    echo 'PASS: ' . $message . "\n";
}
require dirname( __DIR__ ) . '/includes/class-sessivue.php';

$private = new ReflectionClass( 'Sessivue_Plugin' );
$option_method = $private->getMethod( 'options' );
$option_method->setAccessible( true );
$options = $option_method->invoke( null );
check( 0 === $options['activity_enabled'], 'Monitoring is OFF when no setting has been saved' );

$no_checkbox = Sessivue_Plugin::sanitize_settings( array( 'show_ip' => 1 ) );
check( 0 === $no_checkbox['activity_enabled'], 'Missing checkbox cannot enable monitoring' );

$explicit = Sessivue_Plugin::sanitize_settings( array( 'activity_enabled' => '1' ) );
check( 1 === $explicit['activity_enabled'], 'Explicit administrator checkbox enables monitoring' );

$nested = Sessivue_Plugin::sanitize_settings( array( 'activity_enabled' => array( '1' ) ) );
check( 0 === $nested['activity_enabled'], 'Nested payload cannot enable monitoring' );

$invalid = Sessivue_Plugin::sanitize_settings( array( 'activity_enabled' => 'yes' ) );
check( 0 === $invalid['activity_enabled'], 'Unexpected values cannot silently opt in' );

Sessivue_Plugin::clear_presence_on_disable( array( 'activity_enabled' => 1 ), array( 'activity_enabled' => 0 ) );
check( 1 === count( $GLOBALS['sessivue_privacy']['deletions'] ), 'Turning monitoring off triggers one purge' );
check(
    array( 'user', 0, '_sessivue_presence', '', true ) === $GLOBALS['sessivue_privacy']['deletions'][0],
    'Purge deletes only all Sessivue activity records, never WordPress login tokens'
);
Sessivue_Plugin::clear_presence_on_disable( array( 'activity_enabled' => 0 ), array( 'activity_enabled' => 1 ) );
Sessivue_Plugin::clear_presence_on_disable( array( 'activity_enabled' => 0 ), array( 'activity_enabled' => 0 ) );
check( 1 === count( $GLOBALS['sessivue_privacy']['deletions'] ), 'Enabling or saving disabled settings never purges' );

// Upgrade from v0.3.5: there was no consent checkbox and old presence may
// remain in usermeta even though v0.3.7 renders it as Session open.
$GLOBALS['sessivue_privacy']['settings'] = array(
    'viewer_roles' => array( 'editor' ),
    'refresh_seconds' => 30,
    'inactive_minutes' => 5,
    'show_ip' => 0,
);
$GLOBALS['sessivue_privacy']['deletions'] = array();
Sessivue_Plugin::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['sessivue_privacy']['deletions'] ),
    'First legacy migration purges existing Sessivue activity' );
check( 1 === count( $GLOBALS['sessivue_privacy']['updates'] ),
    'Migration persists its completed state once' );
check(
    0 === $GLOBALS['sessivue_privacy']['settings']['activity_enabled'] &&
    array( 'editor' ) === $GLOBALS['sessivue_privacy']['settings']['viewer_roles'] &&
    30 === $GLOBALS['sessivue_privacy']['settings']['refresh_seconds'],
    'Legacy migration preserves prior viewer and refresh settings while opting out'
);
Sessivue_Plugin::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['sessivue_privacy']['deletions'] ) &&
    1 === count( $GLOBALS['sessivue_privacy']['updates'] ),
    'Repeated page requests do not repeat deletion or option writes'
);
$GLOBALS['sessivue_privacy']['settings']['activity_enabled'] = 1;
Sessivue_Plugin::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['sessivue_privacy']['deletions'] ),
    'Explicitly enabled activity is never purged by migration'
);

// A failed database deletion must leave the missing-key sentinel in place.
$GLOBALS['sessivue_privacy']['settings'] = array( 'viewer_roles' => array( 'editor' ) );
$GLOBALS['sessivue_privacy']['deletion_result'] = false;
$GLOBALS['sessivue_privacy']['records_remaining'] = true;
$updates_before = count( $GLOBALS['sessivue_privacy']['updates'] );
Sessivue_Plugin::migrate_legacy_privacy();
check(
    1 === $GLOBALS['sessivue_privacy']['last_user_query']['number'] &&
    false === $GLOBALS['sessivue_privacy']['last_user_query']['count_total'] &&
    'ID' === $GLOBALS['sessivue_privacy']['last_user_query']['fields'],
    'Migration checks a single user via WordPress query API'
);
check(
    ! array_key_exists( 'activity_enabled', $GLOBALS['sessivue_privacy']['settings'] ) &&
    $updates_before === count( $GLOBALS['sessivue_privacy']['updates'] ),
    'Failed purge leaves migration pending for the next request'
);
$GLOBALS['sessivue_privacy']['deletion_result'] = true;
$GLOBALS['sessivue_privacy']['records_remaining'] = false;
Sessivue_Plugin::migrate_legacy_privacy();
check( 0 === $GLOBALS['sessivue_privacy']['settings']['activity_enabled'],
    'A subsequent successful purge completes the deferred migration' );

// WordPress returns false for a no-op deletion when no old records exist.
$GLOBALS['sessivue_privacy']['settings'] = array();
$GLOBALS['sessivue_privacy']['deletion_result'] = false;
Sessivue_Plugin::migrate_legacy_privacy();
check( 0 === $GLOBALS['sessivue_privacy']['settings']['activity_enabled'],
    'No legacy records is a successful, idempotent migration' );
$GLOBALS['sessivue_privacy']['deletion_result'] = true;

Sessivue_Plugin::register_privacy_policy_text();
check( 1 === count( $GLOBALS['sessivue_privacy']['policy'] ), 'A WordPress privacy policy guide entry is registered' );
$policy = $GLOBALS['sessivue_privacy']['policy'][0];
check( 'Sessivue' === $policy[0], 'Policy guide credits the correct plugin' );
check( 2 === substr_count( $policy[1], '<p>' ), 'Suggested policy contains two escaped paragraphs' );
check(
    str_contains( $policy[1], '24 hours' ) &&
    str_contains( $policy[1], 'WordPress' ) &&
    str_contains( $policy[1], 'external services' ),
    'Suggested policy discloses retention, native sessions and lack of external transfers'
);
echo "Privacy opt-in checks passed.\n";
