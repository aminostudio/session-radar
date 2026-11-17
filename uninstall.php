<?php
/** Remove all Session Radar settings and its short-lived activity metadata. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'session_radar_settings' );
delete_metadata( 'user', 0, '_session_radar_presence', '', true );
wp_clear_scheduled_hook( 'session_radar_daily_cleanup' );
