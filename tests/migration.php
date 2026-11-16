<?php
/** Public slug migration and backward-compatible settings contract. */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['radar_options'] = [
    'amino_orbit_settings' => [
        'viewer_roles' => ['editor'],
        'inactive_minutes' => 10,
        'refresh_seconds' => 120,
        'show_ip' => 0,
        'activity_enabled' => 0,
    ],
];
$GLOBALS['radar_scheduled'] = [];
function get_option($key, $default = false) { return $GLOBALS['radar_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null): bool {
    $GLOBALS['radar_options'][$key] = $value;
    return true;
}
function wp_next_scheduled($hook) { return $GLOBALS['radar_scheduled'][$hook] ?? false; }
function wp_schedule_event($time, $recurrence, $hook): bool {
    $GLOBALS['radar_scheduled'][$hook] = $time;
    return true;
}
function radar_check(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

require __DIR__ . '/../includes/class-amino-orbit.php';
Amino_Orbit::activate();
radar_check(
    get_option('session_radar_settings') === get_option('amino_orbit_settings'),
    'first activation copies existing settings exactly, including privacy opt-in'
);
radar_check(
    isset($GLOBALS['radar_scheduled']['session_radar_daily_cleanup']),
    'new plugin schedules only its own cleanup'
);
radar_check(
    !isset($GLOBALS['radar_scheduled']['amino_orbit_daily_cleanup']),
    'legacy cleanup hook remains separate'
);
// Previous plugin uninstall removes its former option, not the new one.
unset($GLOBALS['radar_options']['amino_orbit_settings']);
radar_check(
    get_option('session_radar_settings')['inactive_minutes'] === 10,
    'new settings survive removal of the previous plugin'
);
$GLOBALS['radar_options']['amino_orbit_settings'] = ['inactive_minutes' => 30];
Amino_Orbit::activate();
radar_check(
    get_option('session_radar_settings')['inactive_minutes'] === 10,
    'subsequent activations never overwrite new settings'
);
echo "PASS: Session Radar option migration\n";
