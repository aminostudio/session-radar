<?php
/** Isolated regression tests for daily cleanup. No live WordPress required. */
define( 'ABSPATH', '/tmp/' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['state'] = array( 'meta' => array(), 'updates' => 0, 'deletes' => 0, 'options' => array( 'activity_enabled' => 1 ) );
function get_option( $key, $default = array() ) { return $GLOBALS['state']['options']; }
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function get_users( $args ) {
    assert( $args['meta_query'][0]['key'] === '_session_radar_presence' && $args['meta_query'][0]['compare'] === 'EXISTS' );
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
verify( false === update_user_meta( 1, '_session_radar_presence', array( 'old' => $now ), $previous ), 'Conditional update does not overwrite newer pings' );
verify( isset( $GLOBALS['state']['meta'][1]['new_ping'] ), 'New ping is preserved' );
// Monitoring opt-out must purge plugin-only records on the scheduled retry.
function delete_metadata( $type, $id, $key, $value, $all ) {
    if ( 'user' !== $type || '_session_radar_presence' !== $key || ! $all ) return false;
    $GLOBALS['state']['meta'] = array();
    return true;
}
$GLOBALS['state']['options']['activity_enabled'] = 0;
Amino_Orbit::cleanup_presence();
verify( $GLOBALS['state']['meta'] === array(), 'Disabled monitoring retries complete presence purge' );
echo "All daily cleanup tests passed.\n";
