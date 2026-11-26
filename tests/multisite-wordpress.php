<?php
/**
 * Run with wp eval-file on a disposable, real WordPress Multisite network.
 * Two blogs share WP usermeta but must never share SessiVue presence.
 */
if ( ! is_multisite() ) {
	fwrite( STDERR, "FAIL: real Multisite installation required\n" );
	exit( 1 );
}
function sessivue_ms_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
	echo "PASS: $message\n";
}
$sites = get_sites( array( 'fields' => 'ids', 'number' => 5, 'orderby' => 'id', 'order' => 'ASC' ) );
sessivue_ms_assert( count( $sites ) >= 2, 'Two blogs exist on this network' );
$blog_a = (int) $sites[0];
$blog_b = (int) $sites[1];
sessivue_ms_assert( $blog_a !== $blog_b, 'Blogs have distinct current blog IDs' );

$user = get_user_by( 'login', 'orbit_ci_admin' );
sessivue_ms_assert( $user instanceof WP_User, 'Network administrator exists' );
$user_id = $user->ID;
if ( ! is_user_member_of_blog( $user_id, $blog_b ) ) {
	add_user_to_blog( $blog_b, $user_id, 'administrator' );
}
$token = WP_Session_Tokens::get_instance( $user_id )->create( time() + HOUR_IN_SECONDS );
$verifier = hash( 'sha256', $token );
$fingerprint = hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) );
$key_a = '_sessivue_presence_site_' . $blog_a;
$key_b = '_sessivue_presence_site_' . $blog_b;

switch_to_blog( $blog_a );
update_option( 'sessivue_settings', array( 'activity_enabled' => 1, 'inactive_minutes' => 5 ) );
update_user_meta( $user_id, $key_a, array( $fingerprint => time() ) );
sessivue_ms_assert( get_current_blog_id() === $blog_a, 'Context starts in blog A' );
sessivue_ms_assert( 1 === count( Amino_Orbit::export_personal_data( $user->user_email )['data'] ),
	'Blog A exporter sees its own recent activity' );
restore_current_blog();

switch_to_blog( $blog_b );
update_option( 'sessivue_settings', array( 'activity_enabled' => 1, 'inactive_minutes' => 5 ) );
sessivue_ms_assert( array() === Amino_Orbit::export_personal_data( $user->user_email )['data'],
	'Blog B does not export blog A activity' );
// Native user sessions are network-wide; blog B must not treat blog A's ping as a recent interaction.
$session_method = ( new ReflectionClass( 'Amino_Orbit' ) )->getMethod( 'sessions' );
$session_method->setAccessible( true );
$truncated = false;
$rows = $session_method->invokeArgs( null, array( &$truncated ) );
$matches = array_values( array_filter( $rows, static fn( $row ) => $row['user']->ID === $user_id ) );
sessivue_ms_assert( count( $matches ) >= 1 && 'session' === $matches[0]['status'],
	'Blog B widget shows open session, not activity recorded by blog A' );
update_user_meta( $user_id, $key_b, array( $fingerprint => time() ) );
sessivue_ms_assert( 1 === count( Amino_Orbit::export_personal_data( $user->user_email )['data'] ),
	'Blog B exporter sees only blog B activity after a local observation' );
$erased = Amino_Orbit::erase_personal_data( $user->user_email );
sessivue_ms_assert( $erased['items_removed'] && ! get_user_meta( $user_id, $key_b, true ),
	'Blog B privacy erasure removes its own observation' );
sessivue_ms_assert( ! empty( get_user_meta( $user_id, $key_a, true ) ),
	'Blog B erasure does not remove blog A observation' );

update_user_meta( $user_id, $key_b, array( 'expired' => time() - DAY_IN_SECONDS - 100 ) );
Amino_Orbit::cleanup_presence();
sessivue_ms_assert( ! get_user_meta( $user_id, $key_b, true ) &&
	! empty( get_user_meta( $user_id, $key_a, true ) ),
	'Blog B cron cleanup removes only expired blog B observation' );
update_user_meta( $user_id, $key_b, array( 'recent' => time() ) );
update_option( 'sessivue_settings', array( 'activity_enabled' => 0 ) );
sessivue_ms_assert( ! get_user_meta( $user_id, $key_b, true ) &&
	! empty( get_user_meta( $user_id, $key_a, true ) ),
	'Blog B monitoring opt-out removes only blog B metadata' );
restore_current_blog();

switch_to_blog( $blog_a );
sessivue_ms_assert( 1 === count( Amino_Orbit::export_personal_data( $user->user_email )['data'] ),
	'Blog A continues to export its own activity after blog B opts out' );

// The old global key contains no site ID. It must not be read by either site,
// and the standalone one-time legacy migration must delete it independently.
delete_site_option( 'sessivue_unscoped_legacy_purged_v1' );
update_user_meta( $user_id, '_sessivue_presence', array( $fingerprint => time() ) );
Amino_Orbit::purge_unscoped_legacy_presence();
sessivue_ms_assert( ! get_user_meta( $user_id, '_sessivue_presence', true ) &&
	! empty( get_user_meta( $user_id, $key_a, true ) ),
	'Unscoped pre-upgrade records discarded once, with blog A scoped data intact' );
restore_current_blog();

WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
delete_user_meta( $user_id, $key_a );
delete_user_meta( $user_id, $key_b );
echo "Real WordPress Multisite two-blog isolation tests passed.\n";
