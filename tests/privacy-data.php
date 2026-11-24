<?php
/**
 * Isolated tests for Core privacy exporter/eraser callback contracts.
 * No WordPress installation or site data is touched.
 */
define( 'ABSPATH', '/tmp/' );
define( 'DAY_IN_SECONDS', 86400 );

class WP_User {
    public int $ID;
    public string $user_email;

    public function __construct( int $id, string $email ) {
        $this->ID         = $id;
        $this->user_email = $email;
    }
}

$now = time();
$GLOBALS['sessivue_personal_data'] = array(
    'users' => array(
        'admin@example.test' => new WP_User( 5, 'admin@example.test' ),
        'editor@example.test' => new WP_User( 9, 'editor@example.test' ),
    ),
    'metadata' => array(
        5 => array(
            '_sessivue_presence' => array(
                'hmac-not-a-real-session-token-1' => $now - 10,
                'hmac-not-a-real-session-token-2' => $now - 60,
                'expired' => $now - 90000,
                'future' => $now + 5000,
                'invalid' => 'invalid',
            ),
            'session_tokens' => array( 'native-token-verifier' => array( 'expiration' => $now + 3600 ) ),
        ),
        9 => array( '_sessivue_presence' => array( 'another-user' => $now - 20 ) ),
    ),
    'delete_failure' => false,
    'hooks' => array(),
);
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['sessivue_personal_data']['hooks'][$hook][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['sessivue_personal_data']['hooks'][$hook][] = $callback;
}
function __( $text, $domain ) { return $text; }
function get_user_by( $field, $value ) {
    return 'email' === $field ? ( $GLOBALS['sessivue_personal_data']['users'][$value] ?? false ) : false;
}
function get_user_meta( $user_id, $key, $single ) {
    $metadata = $GLOBALS['sessivue_personal_data']['metadata'][$user_id] ?? array();
    if ( ! array_key_exists( $key, $metadata ) ) {
        return $single ? '' : array();
    }
    return $single ? $metadata[$key] : array( $metadata[$key] );
}
function delete_user_meta( $user_id, $key ) {
    if ( $GLOBALS['sessivue_personal_data']['delete_failure'] ) {
        return false;
    }
    if ( ! isset( $GLOBALS['sessivue_personal_data']['metadata'][$user_id][$key] ) ) {
        return false;
    }
    unset( $GLOBALS['sessivue_personal_data']['metadata'][$user_id][$key] );
    return true;
}
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function check( bool $ok, string $message ): void {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: $message\n" );
        exit( 1 );
    }
    echo "PASS: $message\n";
}

require dirname( __DIR__ ) . '/includes/class-sessivue.php';
Sessivue_Plugin::init();
check(
    count( $GLOBALS['sessivue_personal_data']['hooks']['wp_privacy_personal_data_exporters'] ?? array() ) === 1 &&
    count( $GLOBALS['sessivue_personal_data']['hooks']['wp_privacy_personal_data_erasers'] ?? array() ) === 1,
    'WordPress privacy exporter and eraser filters registered exactly once'
);

$exporters = Sessivue_Plugin::register_personal_data_exporter( array() );
$erasers   = Sessivue_Plugin::register_personal_data_eraser( array() );
check(
    isset( $exporters['sessivue']['exporter_friendly_name'], $exporters['sessivue']['callback'] ) &&
    is_callable( $exporters['sessivue']['callback'] ),
    'Core exporter registration has callable and friendly name'
);
check(
    isset( $erasers['sessivue']['eraser_friendly_name'], $erasers['sessivue']['callback'] ) &&
    is_callable( $erasers['sessivue']['callback'] ),
    'Core eraser registration has callable and friendly name'
);

$missing = Sessivue_Plugin::export_personal_data( 'unknown@example.test' );
check( array() === $missing['data'] && true === $missing['done'], 'Unknown email exports no metadata' );
$export = Sessivue_Plugin::export_personal_data( 'admin@example.test' );
check( true === $export['done'] && 2 === count( $export['data'] ), 'Only two unexpired valid timestamps are exported' );
check(
    $export['data'][0]['item_id'] !== $export['data'][1]['item_id'] &&
    'Last observed admin interaction' === $export['data'][0]['data'][0]['name'],
    'Exported WordPress data groups contain named, unique observations'
);
$json = json_encode( $export );
check(
    ! str_contains( $json, 'hmac-not-a-real-session-token' ) &&
    ! str_contains( $json, 'native-token-verifier' ) &&
    ! str_contains( $json, '192.0.2.' ),
    'Export never includes HMAC IDs, native session tokens or IP information'
);
check(
    array() === Sessivue_Plugin::export_personal_data( 'admin@example.test', 2 )['data'],
    'Exporter declares done after its sole bounded page'
);
check(
    1 === count( Sessivue_Plugin::export_personal_data( 'editor@example.test' )['data'] ),
    'Other user receives only their own presence observations'
);

$result = Sessivue_Plugin::erase_personal_data( 'unknown@example.test' );
check( ! $result['items_removed'] && ! $result['items_retained'] && $result['done'],
    'Unknown email has no data to erase' );
$result = Sessivue_Plugin::erase_personal_data( 'admin@example.test' );
check( $result['items_removed'] && ! $result['items_retained'] && $result['done'],
    'Eraser reports successful metadata removal' );
check(
    ! isset( $GLOBALS['sessivue_personal_data']['metadata'][5]['_sessivue_presence'] ) &&
    isset( $GLOBALS['sessivue_personal_data']['metadata'][5]['session_tokens'] ) &&
    isset( $GLOBALS['sessivue_personal_data']['metadata'][9]['_sessivue_presence'] ),
    'Eraser preserves native login sessions and other users activity'
);
$result = Sessivue_Plugin::erase_personal_data( 'admin@example.test' );
check( ! $result['items_removed'] && ! $result['items_retained'], 'Repeated erasure is harmless' );
check(
    ! Sessivue_Plugin::erase_personal_data( 'editor@example.test', 2 )['items_removed'],
    'Eraser handles a later page without deleting anything'
);

$GLOBALS['sessivue_personal_data']['delete_failure'] = true;
$result = Sessivue_Plugin::erase_personal_data( 'editor@example.test' );
check(
    ! $result['items_removed'] && $result['items_retained'] &&
    1 === count( $result['messages'] ) && $result['done'],
    'Failed deletion reports retained data and a human-readable message'
);

echo "WordPress privacy export/erase isolated tests passed.\n";
