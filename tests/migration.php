<?php
/** Isolated migration from Session Radar / Amino ORBIT to Sessivue. */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['sessivue_options'] = [
    'active_plugins' => ['session-radar/session-radar.php'],
    'session_radar_settings' => [
        'viewer_roles' => ['editor'],
        'inactive_minutes' => 10,
        'refresh_seconds' => 120,
        'show_ip' => 0,
        'activity_enabled' => 1,
    ],
    'amino_orbit_settings' => ['inactive_minutes' => 30],
];
$GLOBALS['sessivue_scheduled'] = [];
function get_option($key, $default = false) { return $GLOBALS['sessivue_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null): bool {
    $GLOBALS['sessivue_options'][$key] = $value;
    return true;
}
function wp_next_scheduled($hook) { return $GLOBALS['sessivue_scheduled'][$hook] ?? false; }
function wp_schedule_event($time, $recurrence, $hook): bool {
    $GLOBALS['sessivue_scheduled'][$hook] = $time;
    return true;
}
function esc_html__($message, $domain) { return $message; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException($message); }
function sessivue_check(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}
require __DIR__ . '/../includes/class-sessivue.php';

// Do not allow duplicate session monitors to be active.
$blocked = false;
try {
    Sessivue_Plugin::activate();
} catch (RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'Deactivate the previous session plugin');
}
sessivue_check($blocked, 'activation blocked while Session Radar remains enabled');
sessivue_check(get_option('sessivue_settings') === false,
    'blocked activation does not copy or alter existing settings');

// Keep old plugin installed but inactive while importing options.
$GLOBALS['sessivue_options']['active_plugins'] = [];
Sessivue_Plugin::activate();
sessivue_check(
    get_option('sessivue_settings') === get_option('session_radar_settings'),
    'Sessivue copies explicitly approved settings into its isolated option'
);
sessivue_check(
    isset($GLOBALS['sessivue_scheduled']['sessivue_daily_cleanup']) &&
    !isset($GLOBALS['sessivue_scheduled']['session_radar_daily_cleanup']),
    'Sessivue schedules only its own cleanup hook'
);
// The previous plugin uninstall removes only its former settings.
unset($GLOBALS['sessivue_options']['session_radar_settings']);
sessivue_check(get_option('sessivue_settings')['inactive_minutes'] === 10,
    'Sessivue settings survive removal of the previous plugin');
$GLOBALS['sessivue_options']['amino_orbit_settings'] = ['inactive_minutes' => 30];
Sessivue_Plugin::activate();
sessivue_check(get_option('sessivue_settings')['inactive_minutes'] === 10,
    'subsequent activation never overwrites the Sessivue settings');

// Older installations without explicit monitoring consent stay OFF.
unset($GLOBALS['sessivue_options']['sessivue_settings']);
unset($GLOBALS['sessivue_options']['session_radar_settings']);
$GLOBALS['sessivue_options']['amino_orbit_settings'] = ['inactive_minutes' => 20];
Sessivue_Plugin::activate();
sessivue_check(
    get_option('sessivue_settings')['activity_enabled'] === 0 &&
    get_option('sessivue_settings')['inactive_minutes'] === 20,
    'legacy options import retains preferences without silently opting into tracking'
);
echo "PASS: Sessivue upgrade migration suite\n";
