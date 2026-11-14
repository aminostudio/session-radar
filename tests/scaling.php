<?php
/**
 * Ephemeral WordPress scale fixture, invoked ONLY in disposable GitHub Actions.
 *
 * Runs after browser integration checks to avoid disrupting the browser tests.
 * No CI accounts or data are ever added to Amino #LAB or production.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}

if ( 'Amino Orbit CI' !== get_option( 'blogname' ) ||
    'http://127.0.0.1:8080' !== untrailingslashit( home_url() ) ) {
    WP_CLI::error( 'Refusing to run a scale test outside disposable WordPress CI.' );
}

function orbit_scale_assert( bool $yes, string $description ): void {
    if ( ! $yes ) {
        WP_CLI::error( 'FAIL: ' . $description );
    }
    WP_CLI::log( 'PASS: ' . $description );
}

function orbit_scale_sessions( int $number, string $salt ): array {
    $now = time();
    $sessions = array();
    for ( $n = 0; $n < $number; $n++ ) {
        // These verifier-like hashes are synthetic test data, not auth secrets.
        $verifier = hash( 'sha256', $salt . '-test-session-' . $n );
        $sessions[ $verifier ] = array(
            'expiration' => $now + HOUR_IN_SECONDS,
            'login'      => $now - 60,
            'ip'         => '192.0.2.50',
            'ua'         => 'Mozilla/5.0 Firefox/159 Windows',
        );
    }
    return $sessions;
}

function orbit_scale_measure( int $iterations = 5, bool $cold_cache = false ): array {
    global $wpdb;
    $sample = array();
    $queries = array();
    $html = '';
    for ( $n = 0; $n < $iterations; $n++ ) {
        // Repeated cold passes expose query cost hidden by warm object caches.
        if ( $cold_cache ) {
            wp_cache_flush();
        }
        $before_queries = (int) $wpdb->num_queries;
        $start = hrtime( true );
        ob_start();
        Amino_Orbit::render_widget();
        $html = (string) ob_get_clean();
        $sample[] = round( ( hrtime( true ) - $start ) / 1e6, 3 );
        $queries[] = $wpdb->num_queries - $before_queries;
    }
    sort( $sample, SORT_NUMERIC );
    sort( $queries, SORT_NUMERIC );
    return array(
        'median_ms'   => $sample[ (int) floor( $iterations / 2 ) ],
        'max_ms'      => max( $sample ),
        'median_sql'  => $queries[ (int) floor( $iterations / 2 ) ],
        'html'        => $html,
    );
}

$admin = get_user_by( 'login', 'orbit_ci_admin' );
orbit_scale_assert( $admin instanceof WP_User, 'Disposable CI admin exists' );
wp_set_current_user( $admin->ID );

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

// Case A: 510 native-looking sessions held by one account.
$fake_sessions = orbit_scale_sessions( 510, 'cap-a' );
update_user_meta( $admin->ID, 'session_tokens', $fake_sessions );
$one = orbit_scale_measure();
$one_cold = orbit_scale_measure( 5, true );
orbit_scale_assert(
    str_contains( $one['html'], '<strong>500</strong><small>Sessions</small>' ),
    'The widget caps its scanned sessions at 500'
);
orbit_scale_assert(
    40 === substr_count( $one['html'], 'class="amino-orbit__user"' ),
    'The dashboard displays at most 40 session rows'
);
orbit_scale_assert(
    str_contains( $one['html'], 'Partial results: the session scan limit has been reached.' ),
    'The 500-session limit is clearly disclosed'
);
WP_CLI::log(
    sprintf(
        'BENCHMARK: 510 synthetic native records, 500 scanned (warm): median %.3f ms, maximum %.3f ms, median %d DB queries over 5 sequential renders.',
        $one['median_ms'],
        $one['max_ms'],
        $one['median_sql']
    )
);
WP_CLI::log(
    sprintf(
        'BENCHMARK: 510 synthetic records (cold-cache): median %.3f ms, maximum %.3f ms, median %d DB queries over 5 sequential renders.',
        $one_cold['median_ms'], $one_cold['max_ms'], $one_cold['median_sql']
    )
);
orbit_scale_assert( $one_cold['median_sql'] > 0, 'Cold-cache session scans execute real SQL queries' );

// Case B: more accounts with session metadata than our 200-account scan cap.
delete_user_meta( $admin->ID, 'session_tokens' );
$editor = get_user_by( 'login', 'orbit_ci_editor' );
if ( $editor ) {
    delete_user_meta( $editor->ID, 'session_tokens' );
}
for ( $n = 1; $n <= 205; $n++ ) {
    $id = wp_insert_user(
        array(
            'user_login' => 'orbit_scale_' . $n,
            'user_pass'  => wp_generate_password( 24 ),
            'user_email' => 'orbit-scale-' . $n . '@orbit-ci.invalid',
            'role'       => 'subscriber',
        )
    );
    if ( is_wp_error( $id ) ) {
        WP_CLI::error( 'Disposable user setup failed for index ' . $n );
    }
    update_user_meta( (int) $id, 'session_tokens', orbit_scale_sessions( 1, 'user-' . $n ) );
}
wp_cache_flush();
$two = orbit_scale_measure();
$two_cold = orbit_scale_measure( 5, true );
orbit_scale_assert(
    str_contains( $two['html'], '<strong>200</strong><small>Sessions</small>' ),
    'Only 200 of 205 accounts are included in summary totals'
);
orbit_scale_assert(
    str_contains( $two['html'], 'Partial results: the session scan limit has been reached.' ),
    'The 200-account truncation is disclosed'
);
orbit_scale_assert(
    40 === substr_count( $two['html'], 'class="amino-orbit__user"' ),
    'The 40-session display cap still holds with 205 users'
);
WP_CLI::log(
    sprintf(
        'BENCHMARK: 205 disposable users with one native record each, 200 scanned (warm): median %.3f ms, maximum %.3f ms, median %d DB queries over 5 sequential renders.',
        $two['median_ms'],
        $two['max_ms'],
        $two['median_sql']
    )
);
WP_CLI::log(
    sprintf(
        'BENCHMARK: 205 disposable accounts (cold-cache): median %.3f ms, maximum %.3f ms, median %d DB queries over 5 sequential renders.',
        $two_cold['median_ms'], $two_cold['max_ms'], $two_cold['median_sql']
    )
);
orbit_scale_assert( $two_cold['median_sql'] > 0, 'Cold-cache user scans execute real SQL queries' );

global $wpdb;
$index = $wpdb->get_results(
    'SHOW INDEX FROM ' . $wpdb->usermeta . " WHERE Column_name = 'meta_key'",
    ARRAY_A
);
orbit_scale_assert( ! empty( $index ), 'WordPress usermeta has an index on meta_key' );
WP_CLI::log( 'BENCHMARK CAVEAT: Sequential warm/cold-cache CI renders on tiny hardware: not a concurrency load benchmark.' );
WP_CLI::success( 'Bounded WordPress scale checks complete; all data ephemeral.' );
