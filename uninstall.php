<?php
/** Remove all SessiVue settings and its short-lived activity metadata. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sessivue_settings' );
// Only the current site's activity is owned by this per-site uninstall context.
delete_metadata( 'user', 0, '_sessivue_presence_site_' . (int) get_current_blog_id(), '', true );
wp_clear_scheduled_hook( 'sessivue_daily_cleanup' );
