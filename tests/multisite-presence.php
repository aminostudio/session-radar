<?php
/**
 * Two-blog shared-usermeta regression for WordPress Multisite isolation.
 * Standalone PHP fixtures mimic the WordPress usermeta and network option stores.
 */
define( 'ABSPATH', '/tmp/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

final class SessiVue_JSON_Result extends Exception {
	public array $data;
	public function __construct( array $data ) {
		parent::__construct( 'JSON sent' );
		$this->data = $data;
	}
}
class WP_User {
	public int $ID;
	public string $user_email;
	public array $roles = array( 'administrator' );
	public function __construct( int $id, string $email ) {
		$this->ID = $id;
		$this->user_email = $email;
	}
}
class WP_Session_Tokens {
	protected int $id;
	public function __construct( int $id ) { $this->id = $id; }
	public static function get_instance( int $id ) { return new WP_User_Meta_Session_Tokens( $id ); }
}
class WP_User_Meta_Session_Tokens extends WP_Session_Tokens {
	public function get( string $token ) {
		return get_user_meta( $this->id, 'session_tokens', true )[ hash( 'sha256', $token ) ] ?? false;
	}
	public function get_all() {
		return array_values( get_user_meta( $this->id, 'session_tokens', true ) );
	}
}

$now = time();
$token = 'disposable-test-token';
$verifier = hash( 'sha256', $token );
$shared_user = new WP_User( 7, 'shared@example.invalid' );
$GLOBALS['multi'] = array(
	'blog' => 2,
	'users' => array( 7 => $shared_user ),
	'options' => array( 2 => array( 'activity_enabled' => 1 ), 3 => array( 'activity_enabled' => 1 ) ),
	'network_options' => array(),
	'metadata' => array(
		7 => array(
			'session_tokens' => array(
				$verifier => array(
					'login' => $now - 600, 'expiration' => $now + 3600,
					'ip' => '192.0.2.8', 'ua' => 'Firefox Windows',
				),
			),
			'_sessivue_presence' => array( 'ambiguous-unscoped-old-record' => $now - 5 ),
		),
	),
	'failed_delete_key' => '',
	'global_deletes' => array(),
	'queried_blogs' => array(),
);
$GLOBALS['wpdb'] = (object) array( 'last_error' => '' );

function get_current_blog_id() { return $GLOBALS['multi']['blog']; }
function get_option( $name, $fallback = array() ) {
	return $GLOBALS['multi']['options'][ get_current_blog_id() ] ?? $fallback;
}
function update_option( $name, $value ) {
	$GLOBALS['multi']['options'][ get_current_blog_id() ] = $value;
	return true;
}
function get_site_option( $name, $fallback = false ) {
	return $GLOBALS['multi']['network_options'][ $name ] ?? $fallback;
}
function update_site_option( $name, $value ) {
	$GLOBALS['multi']['network_options'][ $name ] = $value;
	return true;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_user_by( $field, $value ) {
	if ( 'email' !== $field ) { return false; }
	foreach ( $GLOBALS['multi']['users'] as $user ) {
		if ( $user->user_email === $value ) { return $user; }
	}
	return false;
}
function get_users( $args ) {
	$GLOBALS['multi']['queried_blogs'][] = $args['blog_id'] ?? null;
	$ids = array();
	foreach ( $GLOBALS['multi']['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ||
			array_key_exists( $args['meta_key'], $GLOBALS['multi']['metadata'][ $id ] ?? array() ) ) {
			$ids[] = $id;
		}
	}
	return 'ID' === ( $args['fields'] ?? '' )
		? array_slice( $ids, 0, $args['number'] ?? 5000 )
		: array_map( static fn( $id ) => $GLOBALS['multi']['users'][ $id ], $ids );
}
function get_user_meta( $id, $key, $single ) {
	$metadata = $GLOBALS['multi']['metadata'][ $id ] ?? array();
	if ( ! array_key_exists( $key, $metadata ) ) {
		return $single ? '' : array();
	}
	return $single ? $metadata[ $key ] : array( $metadata[ $key ] );
}
function update_user_meta( $id, $key, $value, $previous = '' ) {
	if ( '' !== $previous && get_user_meta( $id, $key, true ) !== $previous ) { return false; }
	$GLOBALS['multi']['metadata'][ $id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $id, $key, $previous = '' ) {
	if ( ! isset( $GLOBALS['multi']['metadata'][ $id ][ $key ] ) ) { return false; }
	if ( '' !== $previous && get_user_meta( $id, $key, true ) !== $previous ) { return false; }
	unset( $GLOBALS['multi']['metadata'][ $id ][ $key ] );
	return true;
}
function delete_metadata( $type, $object_id, $key, $value, $delete_all ) {
	$GLOBALS['multi']['global_deletes'][] = $key;
	if ( $key === $GLOBALS['multi']['failed_delete_key'] ) { return false; }
	$deleted = false;
	foreach ( array_keys( $GLOBALS['multi']['metadata'] ) as $id ) {
		if ( array_key_exists( $key, $GLOBALS['multi']['metadata'][ $id ] ) ) {
			unset( $GLOBALS['multi']['metadata'][ $id ][ $key ] );
			$deleted = true;
		}
	}
	return $deleted;
}
function is_user_logged_in() { return true; }
function get_current_user_id() { return 7; }
function check_ajax_referer( $nonce, $field, $die ) { return true; }
function wp_get_session_token() { return 'disposable-test-token'; }
function wp_send_json_success( $data ) { throw new SessiVue_JSON_Result( $data ); }
function wp_send_json_error( $data, $code = 200 ) { throw new SessiVue_JSON_Result( $data ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_salt( $scheme ) { return 'test-salt-do-not-reuse'; }
function __( $text, $domain ) { return $text; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function absint( $value ) { return abs( (int) $value ); }
function verify( bool $ok, string $message ): void {
	if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
	echo "PASS: $message\n";
}
function ping(): array {
	try { Amino_Orbit::ajax_ping(); } catch ( SessiVue_JSON_Result $e ) { return $e->data; }
	return array();
}
function rows(): array {
	$truncated = false;
	$method = ( new ReflectionClass( 'Amino_Orbit' ) )->getMethod( 'sessions' );
	return $method->invokeArgs( null, array( &$truncated ) );
}

require dirname( __DIR__ ) . '/includes/class-amino-orbit.php';
$site2 = '_sessivue_presence_site_2';
$site3 = '_sessivue_presence_site_3';
$legacy = '_sessivue_presence';

verify( ! empty( ping()['tracked'] ), 'Site 2 accepts current admin activity' );
verify( ! empty( get_user_meta( 7, $site2, true ) ) &&
	! get_user_meta( 7, $site3, true ), 'Ping writes only the origin site metadata' );
verify( 1 === count( rows() ) && 'active' === rows()[0]['status'],
	'Site 2 session displays only activity recorded on site 2' );

$GLOBALS['multi']['blog'] = 3;
verify( 1 === count( rows() ) && 'session' === rows()[0]['status'],
	'Site 3 does not display site 2 activity or unscoped legacy data' );
verify( array() === Amino_Orbit::export_personal_data( 'shared@example.invalid' )['data'],
	'Site 3 privacy exporter excludes site 2 and ambiguous legacy data' );
verify( ! empty( ping()['tracked'] ), 'Site 3 can store its own activity' );
verify( ! empty( get_user_meta( 7, $site3, true ) ), 'Both site keys coexist for one network user' );

$erase = Amino_Orbit::erase_personal_data( 'shared@example.invalid' );
verify( $erase['items_removed'] && ! get_user_meta( 7, $site3, true ) &&
	! empty( get_user_meta( 7, $site2, true ) ),
	'Site 3 erasure preserves site 2 metadata and native auth' );
verify( isset( get_user_meta( 7, 'session_tokens', true )[ $verifier ] ),
	'Core session token data is unchanged by SessiVue erasure' );

update_user_meta( 7, $site3, array( 'old' => $now - DAY_IN_SECONDS - 60 ) );
Amino_Orbit::cleanup_presence();
verify( ! get_user_meta( 7, $site3, true ) &&
	! empty( get_user_meta( 7, $site2, true ) ),
	'Site 3 cleanup removes only site 3 expired records' );

update_user_meta( 7, $site3, array( 'recent' => $now ) );
Amino_Orbit::clear_presence_on_disable(
	array( 'activity_enabled' => 1 ), array( 'activity_enabled' => 0 )
);
verify( ! get_user_meta( 7, $site3, true ) &&
	! empty( get_user_meta( 7, $site2, true ) ),
	'Disabling activity on site 3 never deletes site 2 data' );
$GLOBALS['multi']['options'][3]['activity_enabled'] = 0;
update_user_meta( 7, $site3, array( 'retry' => $now ) );
Amino_Orbit::cleanup_presence();
verify( ! get_user_meta( 7, $site3, true ) && ! empty( get_user_meta( 7, $site2, true ) ),
	'Disabled site 3 cron retry purges only site 3 observations' );

$GLOBALS['multi']['blog'] = 2;
verify( count( Amino_Orbit::export_personal_data( 'shared@example.invalid' )['data'] ) === 1,
	'Site 2 exporter still sees only its own observations' );
verify( ! empty( get_user_meta( 7, $legacy, true ) ),
	'Per-site erasure, disable, cleanup and exporting leave unattributed legacy rows untouched' );

$GLOBALS['multi']['failed_delete_key'] = $legacy;
Amino_Orbit::purge_unscoped_legacy_presence();
verify( ! get_site_option( 'sessivue_unscoped_legacy_purged_v1' ) &&
	! empty( get_user_meta( 7, $legacy, true ) ),
	'Failed one-time network-wide legacy purge retries rather than claiming success' );
$GLOBALS['multi']['failed_delete_key'] = '';
Amino_Orbit::purge_unscoped_legacy_presence();
verify( get_site_option( 'sessivue_unscoped_legacy_purged_v1' ) &&
	! get_user_meta( 7, $legacy, true ) &&
	! empty( get_user_meta( 7, $site2, true ) ),
	'Ambiguous old rows are discarded once without affecting attributable site 2 data' );
$before = count( $GLOBALS['multi']['global_deletes'] );
Amino_Orbit::purge_unscoped_legacy_presence();
verify( $before === count( $GLOBALS['multi']['global_deletes'] ),
	'Completed network-wide legacy migration does not repeat' );

// Missing opt-in on site 3 must affect only site 3, even with site 2 enabled.
$GLOBALS['multi']['blog'] = 3;
$GLOBALS['multi']['options'][3] = array( 'viewer_roles' => array( 'editor' ) );
update_user_meta( 7, $site3, array( 'previous-preconsent' => $now ) );
Amino_Orbit::migrate_legacy_privacy();
verify( ! get_user_meta( 7, $site3, true ) &&
	0 === $GLOBALS['multi']['options'][3]['activity_enabled'] &&
	! empty( get_user_meta( 7, $site2, true ) ),
	'Site 3 pre-consent migration purges only site 3 and preserves site 2 opt-in' );
echo "All Multisite presence isolation tests passed.\n";
