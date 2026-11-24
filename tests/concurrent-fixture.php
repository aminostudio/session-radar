<?php
/**
 * Disposable load-test accounts. Run exclusively in GitHub Actions on localhost.
 * This script must never be executed against Amino #LAB or a live WordPress site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}

if ( 'SessiVue CI' !== get_option( 'blogname' ) ||
    'http://127.0.0.1:8080' !== untrailingslashit( home_url() ) ) {
    WP_CLI::error( 'Refusing to create load-test users outside disposable CI WordPress.' );
}

// The preceding browser suite explicitly enabled IP display for a privacy
// test. Restore the production-like privacy defaults before load testing.
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

$created = 0;
for ( $i = 1; $i <= 8; $i++ ) {
    $login = sprintf( 'orbit_load_admin_%02d', $i );
    if ( get_user_by( 'login', $login ) ) {
        WP_CLI::error( 'Load-test admin already exists; fixture must run only once.' );
    }

    $id = wp_insert_user(
        array(
            'user_login' => $login,
            'user_pass'  => 'temporary-ci-load-password-not-reused',
            'user_email' => sprintf( 'orbit-load-%02d@orbit-ci.invalid', $i ),
            'role'       => 'administrator',
        )
    );
    if ( is_wp_error( $id ) ) {
        WP_CLI::error( 'Failed to create disposable administrator ' . $login );
    }
    $created++;
}

WP_CLI::log( 'PASS: Created ' . $created . ' distinct disposable WordPress administrators.' );
WP_CLI::success( 'Concurrent AJAX load fixture ready on local WordPress only.' );
