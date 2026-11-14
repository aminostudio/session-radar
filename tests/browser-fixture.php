<?php
/**
 * Browser QA fixture. Only permitted inside the GitHub Actions disposable WP.
 *
 * ORBIT_BROWSER_FIXTURE_MODE=setup|idle|allow-editor|locale-fr|locale-es|locale-en|monitoring-off|monitoring-on wp eval-file tests/browser-fixture.php
 *
 * This file MUST NOT be run against Amino #LAB or any real WordPress site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}
$site = (string) home_url();
if ( 'Amino Orbit CI' !== get_option( 'blogname' ) || ! preg_match( '~^http://127\\.0\\.0\\.1:8080/?$~', $site ) ) {
    WP_CLI::error( 'Browser fixture refused: requires disposable local CI WordPress.' );
}
$mode  = (string) getenv( 'ORBIT_BROWSER_FIXTURE_MODE' );
$admin = get_user_by( 'login', 'orbit_ci_admin' );
if ( ! $admin instanceof WP_User ) {
    WP_CLI::error( 'Missing disposable CI administrator.' );
}
if ( 'setup' === $mode ) {
    $editor = get_user_by( 'login', 'orbit_ci_editor' );
    if ( ! $editor instanceof WP_User ) {
        WP_CLI::error( 'Missing disposable CI editor.' );
    }
    wp_set_password( 'temporary-editor-ci-password', $editor->ID );
    update_option(
        'amino_orbit_settings',
        array(
            'viewer_roles'     => array(),
            'inactive_minutes' => 5,
            'refresh_seconds'  => 30,
            'show_ip'          => 0,
            'activity_enabled' => 1,
        )
    );
    delete_user_meta( $admin->ID, '_amino_orbit_presence' );
    WP_CLI::success( 'Disposable browser setup complete, with admin-only widget.' );
 } elseif ( 'allow-editor' === $mode ) {
    update_option(
        'amino_orbit_settings',
        array(
            'viewer_roles'     => array( 'editor' ),
            'inactive_minutes' => 5,
            'refresh_seconds'  => 30,
            'show_ip'          => 1,
            'activity_enabled' => 1,
        )
    );
    WP_CLI::success( 'Editor widget access enabled in disposable CI only.' );
} elseif ( in_array( $mode, array( 'locale-fr', 'locale-es', 'locale-en' ), true ) ) {
    // Exercise the real WordPress per-user admin locale over HTTP.
    $locales = array(
        'locale-fr' => 'fr_FR',
        'locale-es' => 'es_ES',
        'locale-en' => '',
    );
    $locale = $locales[ $mode ];
    if ( '' === $locale ) {
        delete_user_meta( $admin->ID, 'locale' );
    } else {
        update_user_meta( $admin->ID, 'locale', $locale );
    }
    WP_CLI::success( 'Disposable administrator locale set to ' . ( $locale ?: 'default' ) . '.' );
} elseif ( 'monitoring-off' === $mode ) {
    $before = get_user_meta( $admin->ID, '_amino_orbit_presence', true );
    if ( ! is_array( $before ) || ! $before ) {
        WP_CLI::error( 'Expected real browser presence before opting out.' );
    }
    $options = get_option( 'amino_orbit_settings', array() );
    $options['activity_enabled'] = 0;
    update_option( 'amino_orbit_settings', $options );
    $after = get_user_meta( $admin->ID, '_amino_orbit_presence', true );
    if ( $after ) {
        WP_CLI::error( 'Disabling monitoring did not remove existing activity.' );
    }
    WP_CLI::success( 'Monitoring disabled and all plugin activity metadata purged.' );
} elseif ( 'monitoring-on' === $mode ) {
    $options = get_option( 'amino_orbit_settings', array() );
    $options['activity_enabled'] = 1;
    update_option( 'amino_orbit_settings', $options );
    WP_CLI::success( 'Explicit monitoring opt-in restored for disposable CI.' );
} elseif ( 'idle' === $mode ) {
    $sessions = get_user_meta( $admin->ID, 'session_tokens', true );
    $presence = array();
    foreach ( is_array( $sessions ) ? $sessions : array() as $verifier => $session ) {
        if ( is_string( $verifier ) && preg_match( '/^[a-f0-9]{64}$/D', $verifier ) ) {
            $key = hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) );
            $presence[ $key ] = time() - 10 * MINUTE_IN_SECONDS;
        }
    }
    if ( ! $presence ) {
        WP_CLI::error( 'No real native session found for idle fixture.' );
    }
    update_user_meta( $admin->ID, '_amino_orbit_presence', $presence );
    WP_CLI::success( 'All native admin sessions marked inactive in disposable CI.' );
} else {
    WP_CLI::error( 'Unknown browser fixture mode.' );
}
