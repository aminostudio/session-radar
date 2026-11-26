<?php
/** Isolated regression tests for daily cleanup. No live WordPress required. */
define( 'ABSPATH', '/tmp/' );
function get_current_blog_id() { return 1; }
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['state'] = array( 'meta' => array(), 'updates' => 0, 'deletes' => 0 );
function get_option( $name, $default = array() ) { return array( 'activity_enabled' => 1 ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_users( $args ) {
    assert( $args['meta_key'] === '_sessivue_presence_site_1' );
    assert( $args['fields'] === 'ID' );
    return array_slice( array_keys( $GLOBALS['state']['meta'] ), 0, $args['number'] );
}
function get_user_meta( $id, $key, $single ) {
    return $GLOBALS['state']['meta'][ $id ] ?? array();
}
function update_user_meta( $id, $key, $value, $previous = '' ) {
    ++$GLOBALS['state']['updates'];
    if ( '' !== $previous && ( $GLOBALS['state']['meta'][ $id ] ?? null ) !== $previous ) {
        return false;
    }
    $GLOBALS['state']['meta'][ $id ] = $value;
    return true;
}
function delete_user_meta( $id, $key, $previous = '' ) {
    ++$GLOBALS['state']['deletes'];
    if ( '' !== $previous && ( $GLOBALS['state']['meta'][ $id ] ?? null ) !== $previous ) {
        return false;
    }
    unset( $GLOBALS['state']['meta'][ $id ] );
    return true;
}
function verify( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
    echo "PASS: $message\n";
}
require dirname( __DIR__ ) . '/includes/class-amino-orbit.php';
$now = time();
$GLOBALS['state']['meta'] = array(
    1 => array( 'recent' => $now - 40 ),
    2 => array( 'recent' => $now - 50, 'expired' => $now - DAY_IN_SECONDS - 50 ),
    3 => array( 'expired' => $now - DAY_IN_SECONDS - 50 ),
);
Amino_Orbit::cleanup_presence();
verify( $GLOBALS['state']['updates'] === 1, 'Only modified metadata is updated' );
verify( $GLOBALS['state']['deletes'] === 1, 'Only entirely expired metadata is deleted' );
verify( isset( $GLOBALS['state']['meta'][1]['recent'], $GLOBALS['state']['meta'][2]['recent'] ) && ! isset( $GLOBALS['state']['meta'][2]['expired'] ), 'Recent metadata survives cleanup' );
Amino_Orbit::cleanup_presence();
verify( $GLOBALS['state']['updates'] === 1 && $GLOBALS['state']['deletes'] === 1, 'Repeated cleanup performs no writes on unchanged records' );
$previous = $GLOBALS['state']['meta'][1];
$GLOBALS['state']['meta'][1]['new_ping'] = $now;
verify( false === update_user_meta( 1, '_sessivue_presence_site_1', array( 'old' => $now ), $previous ), 'Conditional update does not overwrite newer pings' );
verify( isset( $GLOBALS['state']['meta'][1]['new_ping'] ), 'New ping is preserved' );
echo "All daily cleanup tests passed.\n";
