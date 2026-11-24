<?php
/** Remove all Sessivue settings and its short-lived activity metadata. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sessivue_settings' );
delete_metadata( 'user', 0, '_sessivue_presence', '', true );
wp_clear_scheduled_hook( 'sessivue_daily_cleanup' );
