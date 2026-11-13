<?php
/** Remove all Amino Orbit settings and its short-lived activity metadata. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'amino_orbit_settings' );
delete_metadata( 'user', 0, '_amino_orbit_presence', '', true );
wp_clear_scheduled_hook( 'amino_orbit_daily_cleanup' );
