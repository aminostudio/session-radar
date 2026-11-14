<?php
/**
 * Privacy opt-in regression checks using isolated WordPress test doubles.
 * No external account, database or WordPress installation is touched.
 */
define( 'ABSPATH', '/tmp/' );
$GLOBALS['orbit_privacy'] = array(
    'settings' => array(),
    'deletions' => array(),
    'updates' => array(),
    'policy' => array(),
);
class Orbit_Privacy_Roles {
    public array $roles = array(
        'administrator' => array( 'name' => 'Administrator' ),
        'editor' => array( 'name' => 'Editor' ),
    );
}
function wp_roles() { return new Orbit_Privacy_Roles(); }
function get_option( $name, $default = array() ) { return $GLOBALS['orbit_privacy']['settings']; }
function update_option( $name, $value ) {
    $GLOBALS['orbit_privacy']['settings'] = $value;
    $GLOBALS['orbit_privacy']['updates'][] = array( $name, $value );
    return true;
}
function wp_parse_args( $data, $defaults ) { return array_merge( $defaults, $data ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_add_privacy_policy_content( $name, $content ) {
    $GLOBALS['orbit_privacy']['policy'][] = array( $name, $content );
}
function delete_metadata( $type, $id, $key, $value, $all ) {
    $GLOBALS['orbit_privacy']['deletions'][] = array( $type, $id, $key, $value, $all );
    return true;
}
function check( bool $ok, string $message ): void {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
    echo 'PASS: ' . $message . "\n";
}
require dirname( __DIR__ ) . '/includes/class-amino-orbit.php';

$private = new ReflectionClass( 'Amino_Orbit' );
$option_method = $private->getMethod( 'options' );
$option_method->setAccessible( true );
$options = $option_method->invoke( null );
check( 0 === $options['activity_enabled'], 'Monitoring is OFF when no setting has been saved' );

$no_checkbox = Amino_Orbit::sanitize_settings( array( 'show_ip' => 1 ) );
check( 0 === $no_checkbox['activity_enabled'], 'Missing checkbox cannot enable monitoring' );

$explicit = Amino_Orbit::sanitize_settings( array( 'activity_enabled' => '1' ) );
check( 1 === $explicit['activity_enabled'], 'Explicit administrator checkbox enables monitoring' );

$nested = Amino_Orbit::sanitize_settings( array( 'activity_enabled' => array( '1' ) ) );
check( 0 === $nested['activity_enabled'], 'Nested payload cannot enable monitoring' );

$invalid = Amino_Orbit::sanitize_settings( array( 'activity_enabled' => 'yes' ) );
check( 0 === $invalid['activity_enabled'], 'Unexpected values cannot silently opt in' );

Amino_Orbit::clear_presence_on_disable( array( 'activity_enabled' => 1 ), array( 'activity_enabled' => 0 ) );
check( 1 === count( $GLOBALS['orbit_privacy']['deletions'] ), 'Turning monitoring off triggers one purge' );
check(
    array( 'user', 0, '_amino_orbit_presence', '', true ) === $GLOBALS['orbit_privacy']['deletions'][0],
    'Purge deletes only all Orbit activity records, never WordPress login tokens'
);
Amino_Orbit::clear_presence_on_disable( array( 'activity_enabled' => 0 ), array( 'activity_enabled' => 1 ) );
Amino_Orbit::clear_presence_on_disable( array( 'activity_enabled' => 0 ), array( 'activity_enabled' => 0 ) );
check( 1 === count( $GLOBALS['orbit_privacy']['deletions'] ), 'Enabling or saving disabled settings never purges' );

// Upgrade from v0.3.5: there was no consent checkbox and old presence may
// remain in usermeta even though v0.3.7 renders it as Session open.
$GLOBALS['orbit_privacy']['settings'] = array(
    'viewer_roles' => array( 'editor' ),
    'refresh_seconds' => 30,
    'inactive_minutes' => 5,
    'show_ip' => 0,
);
$GLOBALS['orbit_privacy']['deletions'] = array();
Amino_Orbit::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['orbit_privacy']['deletions'] ),
    'First legacy migration purges existing Orbit activity' );
check( 1 === count( $GLOBALS['orbit_privacy']['updates'] ),
    'Migration persists its completed state once' );
check(
    0 === $GLOBALS['orbit_privacy']['settings']['activity_enabled'] &&
    array( 'editor' ) === $GLOBALS['orbit_privacy']['settings']['viewer_roles'] &&
    30 === $GLOBALS['orbit_privacy']['settings']['refresh_seconds'],
    'Legacy migration preserves prior viewer and refresh settings while opting out'
);
Amino_Orbit::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['orbit_privacy']['deletions'] ) &&
    1 === count( $GLOBALS['orbit_privacy']['updates'] ),
    'Repeated page requests do not repeat deletion or option writes'
);
$GLOBALS['orbit_privacy']['settings']['activity_enabled'] = 1;
Amino_Orbit::migrate_legacy_privacy();
check( 1 === count( $GLOBALS['orbit_privacy']['deletions'] ),
    'Explicitly enabled activity is never purged by migration'
);

Amino_Orbit::register_privacy_policy_text();
check( 1 === count( $GLOBALS['orbit_privacy']['policy'] ), 'A WordPress privacy policy guide entry is registered' );
$policy = $GLOBALS['orbit_privacy']['policy'][0];
check( 'Amino Orbit' === $policy[0], 'Policy guide credits the correct plugin' );
check( 2 === substr_count( $policy[1], '<p>' ), 'Suggested policy contains two escaped paragraphs' );
check(
    str_contains( $policy[1], '24 hours' ) &&
    str_contains( $policy[1], 'WordPress' ) &&
    str_contains( $policy[1], 'external services' ),
    'Suggested policy discloses retention, native sessions and lack of external transfers'
);
echo "Privacy opt-in checks passed.\n";
