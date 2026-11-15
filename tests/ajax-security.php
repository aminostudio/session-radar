<?php
/**
 * Isolated AJAX authorization and data-leak regression checks.
 *
 * WordPress functions below are explicit test doubles, not production hooks.
 * No WordPress installation or accounts are created by this test.
 */
define( 'ABSPATH', '/tmp/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

final class Orbit_Ajax_Response extends RuntimeException {
    public bool $success;
    public array $data;
    public int $http_status;
    public function __construct( bool $success, array $data, int $http_status ) {
        parent::__construct( 'JSON response intercepted' );
        $this->success     = $success;
        $this->data        = $data;
        $this->http_status = $http_status;
    }
}

class WP_User {
    public int $ID;
    public string $display_name;
    public array $roles;
    public function __construct( int $id, string $name, array $roles ) {
        $this->ID           = $id;
        $this->display_name = $name;
        $this->roles        = $roles;
    }
}
class Orbit_Test_Roles {
    public array $roles = array(
        'administrator' => array( 'name' => 'Administrator' ),
        'editor'        => array( 'name' => 'Editor' ),
    );
}
class WP_Session_Tokens {
    private int $user_id;
    public function __construct( int $user_id ) { $this->user_id = $user_id; }
    public static function get_instance( $id ) { return new self( (int) $id ); }
    public function get( $token ) {
        if ( $token !== 'opaque-session-token' ) { return false; }
        return $GLOBALS['orbit']['sessions'][ $this->user_id ][0] ?? false;
    }
    public function get_all(): array {
        return $GLOBALS['orbit']['sessions'][ $this->user_id ] ?? array();
    }
}

$now = time();
$GLOBALS['orbit'] = array(
    'logged_in' => true,
    'admin'     => false,
    'nonce'     => true,
    'roles'     => array( 'editor' ),
    'token'     => 'opaque-session-token',
    'settings'  => array( 'viewer_roles' => array(), 'show_ip' => 1, 'refresh_seconds' => 30, 'activity_enabled' => 1 ),
    'user'      => new WP_User( 7, 'Test Editor', array( 'editor' ) ),
    'sessions'  => array(
        7 => array(
            array(
                'login'      => $now - 100,
                'expiration' => $now + 3600,
                'ua'         => 'Mozilla/5.0 Firefox/159 Windows NT 10.0',
                'ip'         => '192.0.2.77',
            ),
        ),
    ),
    'presence'  => array(),
    'writes'    => 0,
);

function current_user_can( $cap ) { return $GLOBALS['orbit']['admin']; }
function is_user_logged_in() { return $GLOBALS['orbit']['logged_in']; }
function wp_get_current_user() { return $GLOBALS['orbit']['user']; }
function get_current_user_id() { return $GLOBALS['orbit']['user']->ID; }
function check_ajax_referer( $action, $arg, $die ) { return $GLOBALS['orbit']['nonce']; }
function wp_get_session_token() { return $GLOBALS['orbit']['token']; }
function get_option( $name, $fallback = array() ) { return $GLOBALS['orbit']['settings']; }
function wp_parse_args( $settings, $defaults ) { return array_merge( $defaults, $settings ); }
function wp_roles() { return new Orbit_Test_Roles(); }
function get_users( $args ) { return $GLOBALS['orbit']['sessions'] ? array( $GLOBALS['orbit']['user'] ) : array(); }
function get_user_meta( $id, $key, $single ) { return $GLOBALS['orbit']['presence'][ $id ] ?? array(); }
function update_user_meta( $id, $key, $value ) {
    ++$GLOBALS['orbit']['writes'];
    $GLOBALS['orbit']['presence'][ $id ] = $value;
}
function absint( $x ) { return abs( (int) $x ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_salt( $purpose ) { return 'isolated-test-auth-salt'; }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function human_time_diff( $from, $to ) { return (string) floor( ( $to - $from ) / 60 ) . ' minutes'; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function __( $text, $domain ) { return $text; }
function esc_html_e( $text, $domain ) { echo esc_html( $text ); }
function esc_attr__( $text, $domain ) { return esc_attr( $text ); }
function admin_url( $url ) { return 'https://example.test/wp-admin/' . $url; }
function translate_user_role( $role ) { return $role; }
function wp_send_json_error( $data, $http_status = 200 ) {
    throw new Orbit_Ajax_Response( false, $data, $http_status );
}
function wp_send_json_success( $data, $http_status = 200 ) {
    throw new Orbit_Ajax_Response( true, $data, $http_status );
}
function verify( bool $condition, string $description ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $description\n" );
        exit( 1 );
    }
    echo "PASS: $description\n";
}
function response( string $method ): Orbit_Ajax_Response {
    try {
        call_user_func( array( 'Amino_Orbit', $method ) );
    } catch ( Orbit_Ajax_Response $response ) {
        return $response;
    }
    throw new RuntimeException( 'Missing JSON response from ' . $method );
}

require dirname( __DIR__ ) . '/includes/class-amino-orbit.php';

// Unauthorized reads must fail even when the AJAX nonce is valid.
$r = response( 'ajax_refresh' );
verify( ! $r->success && 403 === $r->http_status, 'Unapproved editor cannot list authenticated sessions' );

$GLOBALS['orbit']['logged_in'] = false;
$r = response( 'ajax_refresh' );
verify( ! $r->success && 403 === $r->http_status, 'Anonymous session cannot refresh widget' );
$r = response( 'ajax_ping' );
verify( ! $r->success && 403 === $r->http_status, 'Anonymous session cannot report activity' );
verify( 0 === $GLOBALS['orbit']['writes'], 'Forbidden requests do not write presence metadata' );

$GLOBALS['orbit']['logged_in'] = true;
$GLOBALS['orbit']['settings']['viewer_roles'] = array( 'editor' );
$GLOBALS['orbit']['nonce'] = false;
$r = response( 'ajax_refresh' );
verify( ! $r->success && 403 === $r->http_status, 'Authorized role still requires a valid refresh nonce' );
$r = response( 'ajax_ping' );
verify( ! $r->success && 403 === $r->http_status, 'Authenticated ping still requires valid nonce' );
verify( 0 === $GLOBALS['orbit']['writes'], 'Invalid nonces do not update stored presence' );

$GLOBALS['orbit']['nonce'] = true;
$r = response( 'ajax_refresh' );
verify( $r->success && isset( $r->data['html'] ), 'Authorized editor can receive the dashboard widget' );
verify( str_contains( $r->data['html'], 'Test Editor' ), 'Authorized editor sees current valid sessions' );
verify( ! str_contains( $r->data['html'], '192.0.2.77' ), 'Native IP never exposed to editor, even if enabled' );
verify( str_contains( $r->data['html'], 'Refresh: 30 s' ), 'Editor widget uses configured refresh setting' );

$GLOBALS['orbit']['admin'] = true;
$r = response( 'ajax_refresh' );
verify( $r->success && str_contains( $r->data['html'], '192.0.2.77' ), 'Administrator can opt in to native session IP display' );
$GLOBALS['orbit']['admin'] = false;

$r = response( 'ajax_ping' );
verify( $r->success && true === $r->data['tracked'], 'Valid authenticated interaction is recorded' );
verify( 1 === $GLOBALS['orbit']['writes'], 'First ping creates one presence write' );
$stored = $GLOBALS['orbit']['presence'][7];
verify( 1 === count( $stored ), 'One derived session identifier is stored' );
verify( ! str_contains( json_encode( $stored ), 'opaque-session-token' ), 'Raw WordPress session token is never stored' );
verify( 64 === strlen( array_key_first( $stored ) ), 'Session identity uses 64-character HMAC digest' );
$r = response( 'ajax_ping' );
verify( $r->success && 1 === $GLOBALS['orbit']['writes'], 'Rapid repeat ping does not duplicate writes' );

$GLOBALS['orbit']['token'] = '';
$r = response( 'ajax_ping' );
verify( $r->success && false === $r->data['tracked'], 'Missing WordPress session token is not tracked' );
verify( 1 === $GLOBALS['orbit']['writes'], 'Missing token creates no metadata' );

$GLOBALS['orbit']['token'] = 'opaque-session-token';
$GLOBALS['orbit']['sessions'][7][0]['expiration'] = time() - 2;
$r = response( 'ajax_ping' );
verify( $r->success && false === $r->data['tracked'], 'Expired WordPress session is not tracked' );
verify( 1 === $GLOBALS['orbit']['writes'], 'Expired session does not extend presence metadata' );

// Explicit site-wide opt-out must be enforced on the server, including
// malicious or outdated authenticated clients that still submit valid nonces.
$GLOBALS['orbit']['sessions'][7][0]['expiration'] = time() + 3600;
$GLOBALS['orbit']['settings']['activity_enabled'] = 0;
$r = response( 'ajax_ping' );
verify( $r->success && false === $r->data['tracked'], 'Disabled monitoring declines valid authenticated pings' );
verify( 1 === $GLOBALS['orbit']['writes'], 'Disabled monitoring never writes presence metadata' );
verify( str_contains( response( 'ajax_refresh' )->data['html'], 'Session open' ), 'Disabled monitoring shows open sessions without old activity status' );

echo "All AJAX security checks passed.\n";
