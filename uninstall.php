<?php
/**
 * Remove only SessiVue-owned storage on uninstall.
 *
 * A full plugin uninstall is global: on Multisite iterate each blog and
 * delete only that blog's own settings, presence key and cron event.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_site_data = static function (): void {
	delete_option( 'sessivue_settings' );
	delete_metadata( 'user', 0, '_sessivue_presence_site_' . (int) get_current_blog_id(), '', true );
	wp_clear_scheduled_hook( 'sessivue_daily_cleanup' );
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		$delete_site_data();
		restore_current_blog();
	}
} else {
	$delete_site_data();
}

// Only ambiguous pre-site-scoping observations are shared; never map them to a blog.
delete_metadata( 'user', 0, '_sessivue_presence', '', true );
delete_site_option( 'sessivue_unscoped_legacy_purged_v1' );
