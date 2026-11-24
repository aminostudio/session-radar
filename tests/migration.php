<?php
/** SessiVue migration from Session Radar and Amino Orbit. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/../');
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['radar_options'] = [
 'session_radar_settings' => ['viewer_roles'=>['editor'],'inactive_minutes'=>10,'refresh_seconds'=>120,'show_ip'=>0,'activity_enabled'=>1],
 'amino_orbit_settings' => ['viewer_roles'=>[],'inactive_minutes'=>30,'refresh_seconds'=>60,'show_ip'=>0,'activity_enabled'=>0],
];
$GLOBALS['radar_scheduled'] = [];
function get_option($key, $default=false) { return $GLOBALS['radar_options'][$key] ?? $default; }
function update_option($key,$value,$autoload=null): bool { $GLOBALS['radar_options'][$key]=$value; return true; }
function wp_next_scheduled($hook) { return $GLOBALS['radar_scheduled'][$hook] ?? false; }
function wp_schedule_event($time,$recurrence,$hook): bool { $GLOBALS['radar_scheduled'][$hook]=$time; return true; }
function radar_check(bool $ok,string $msg): void { if (!$ok) { fwrite(STDERR,"FAIL: $msg\n"); exit(1); } echo "PASS: $msg\n"; }
require __DIR__ . '/../includes/class-amino-orbit.php';
Amino_Orbit::activate();
radar_check(get_option('sessivue_settings') === get_option('session_radar_settings'),
 'Session Radar settings take priority, preserving activity opt-in');
radar_check(isset($GLOBALS['radar_scheduled']['sessivue_daily_cleanup']),
 'Only SessiVue cleanup is scheduled');
radar_check(!isset($GLOBALS['radar_scheduled']['session_radar_daily_cleanup']),
 'Legacy cleanup hook remains separate');
unset($GLOBALS['radar_options']['session_radar_settings'],$GLOBALS['radar_options']['amino_orbit_settings']);
radar_check(get_option('sessivue_settings')['inactive_minutes'] === 10,
 'SessiVue settings survive uninstall of old plugins');
$GLOBALS['radar_options']['session_radar_settings']=['inactive_minutes'=>30];
Amino_Orbit::activate();
radar_check(get_option('sessivue_settings')['inactive_minutes'] === 10,
 'Subsequent activation never overwrites new settings');
unset($GLOBALS['radar_options']['sessivue_settings'],$GLOBALS['radar_options']['session_radar_settings']);
$GLOBALS['radar_options']['amino_orbit_settings']=['activity_enabled'=>0,'inactive_minutes'=>15];
Amino_Orbit::activate();
radar_check(get_option('sessivue_settings') === get_option('amino_orbit_settings'),
 'Amino Orbit fallback import is isolated');
echo "PASS: SessiVue option migration\n";
