<?php
/**
 * Validate true WordPress 6.4 locale loading in a FRESH WP-CLI bootstrap.
 *
 * Unlike switch_to_locale() in an existing CLI process, starting WordPress
 * with WPLANG already set exercises normal pre-6.7 gettext registration with WordPress language packs.
 * This fixture is strictly forbidden against production or LAB.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}
if ( 'Session Radar CI' !== get_option( 'blogname' ) ||
    'http://127.0.0.1:8080' !== untrailingslashit( home_url() ) ) {
    WP_CLI::error( 'Refusing minimum locale test outside disposable CI.' );
}

$locale = (string) getenv( 'ORBIT_MIN_TEST_LOCALE' );
$expected = array(
    'fr_FR' => array(
        'session' => 'Session ouverte',
        'refresh' => 'Actualisation : 30 s',
    ),
    'es_ES' => array(
        'session' => 'Sesión abierta',
        'refresh' => 'Actualización: 30 s',
    ),
);
if ( ! isset( $expected[ $locale ] ) ) {
    WP_CLI::error( 'A supported FR/ES locale must be requested by CI.' );
}
if ( get_locale() !== $locale ) {
    WP_CLI::error( 'WordPress is not booted in the requested locale: ' . get_locale() );
}

$actual = __( 'Session open', 'session-radar' );
if ( $expected[ $locale ]['session'] !== $actual ) {
    WP_CLI::error(
        sprintf(
            'Boot-locale gettext mismatch for %s: expected %s, got %s',
            $locale,
            $expected[ $locale ]['session'],
            $actual
        )
    );
}
WP_CLI::log( 'PASS: WordPress 6.4 loads language-pack ' . $locale . ' locale in a fresh bootstrap.' );

$admin = get_user_by( 'login', 'orbit_ci_admin' );
if ( ! $admin instanceof WP_User ) {
    WP_CLI::error( 'Missing disposable CI administrator.' );
}
wp_set_current_user( (int) $admin->ID );
ob_start();
Amino_Orbit::render_widget();
$html = (string) ob_get_clean();
if ( ! str_contains( $html, $expected[ $locale ]['refresh'] ) ) {
    WP_CLI::error( 'Widget refresh label was not translated in ' . $locale );
}
WP_CLI::success( 'Real WordPress 6.4 ' . $locale . ' widget output is translated.' );
