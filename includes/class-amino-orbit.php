<?php
/**
 * Session Radar core.
 *
 * Session existence is supplied by WordPress itself; activity is a voluntary,
 * short-lived admin-side signal. This class does not log page views or track
 * anonymous visitors, and never stores raw auth tokens.
 *
 * @package AminoOrbit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Amino_Orbit {
	private const OPTION = 'session_radar_settings';
	private const META = '_session_radar_presence';
	private const NONCE = 'amino_orbit_ajax';
	private const CLEANUP_HOOK = 'session_radar_daily_cleanup';
	private const MAX_USERS = 200;
	private const MAX_SCANNED_SESSIONS = 500;
	private const MAX_ROWS = 40;

	/** Register WordPress hooks. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'migrate_legacy_privacy' ), 0 );
		add_action( 'init', array( __CLASS__, 'load_translations' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_personal_data_eraser' ) );
		add_action( 'update_option_session_radar_settings', array( __CLASS__, 'clear_presence_on_disable' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_amino_orbit_ping', array( __CLASS__, 'ajax_ping' ) );
		add_action( 'wp_ajax_amino_orbit_refresh', array( __CLASS__, 'ajax_refresh' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_presence' ) );
	}

	/** Register bundled locale files at init, never before. */
	public static function load_translations(): void {
		load_plugin_textdomain( 'session-radar', false, dirname( plugin_basename( AMINO_ORBIT_FILE ) ) . '/languages' );
	}

	/**
	 * One-time privacy migration for installs upgraded from pre-opt-in versions.
	 *
	 * The missing activity_enabled key means monitoring has never explicitly
	 * been approved. Clear legacy observations and persist OFF without changing
	 * refresh rate, viewer roles or other settings. Native sessions stay intact.
	 */
	public static function migrate_legacy_privacy(): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		if ( array_key_exists( 'activity_enabled', $stored ) ) {
			return;
		}
		// A failed deletion must not permanently mark the migration complete.
		// WordPress also returns false when there are simply no rows to delete.
		if ( ! delete_metadata( 'user', 0, self::META, '', true ) ) {
			// A failed deletion can also mean that no matching metadata exists.
			// Check only one user through WordPress's standard query API.
			global $wpdb;
			$remaining = get_users(
				array(
					'meta_key' => self::META,
					'fields' => 'ID',
					'number' => 1,
					'count_total' => false,
				)
			);
			if ( ! empty( $remaining ) || $wpdb->last_error ) {
				return; // Keep the missing-key sentinel so the next request retries.
			}
		}
		$stored['activity_enabled'] = 0;
		update_option( self::OPTION, $stored );
	}

	/** Set up the WordPress cron cleanup without modifying existing settings. */
	public static function activate(): void {
		// One-time, non-destructive settings migration from the pre-approval
		// amino-orbit slug. Keep data isolated from the old uninstall routine.
		if ( false === get_option( self::OPTION, false ) ) {
			$previous = get_option( 'amino_orbit_settings', false );
			if ( is_array( $previous ) ) {
				update_option( self::OPTION, $previous, false );
			}
		}
		// Historical short-lived activity observations are intentionally not
		// copied: they will be collected anew only if monitoring was enabled.
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/** Stop the scheduled cleanup on deactivation. */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/** Retrieve validated options, preserving safe defaults. */
	private static function options(): array {
		$defaults = array(
			'viewer_roles' => array(),
			'inactive_minutes' => 5,
			'refresh_seconds' => 60,
			'show_ip' => 0,
			'activity_enabled' => 0,
		);
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/** Admins always retain access; other roles must be explicitly opted in. */
	private static function can_view(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$options = self::options();
		$allowed = is_array( $options['viewer_roles'] ) ? $options['viewer_roles'] : array();
		$user = wp_get_current_user();
		return (bool) array_intersect( (array) $user->roles, $allowed );
	}

	/** Only administrators may change settings. */
	public static function register_settings_page(): void {
		add_options_page(
			__( 'Session Radar', 'session-radar' ),
			__( 'Session Radar', 'session-radar' ),
			'manage_options',
			'session-radar',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'session_radar_settings',
			self::OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default' => array(
					'viewer_roles' => array(),
					'inactive_minutes' => 5,
					'refresh_seconds' => 60,
							'show_ip' => 0,
					'activity_enabled' => 0,
				),
			)
		);
	}

	/** Validate allowed roles and restrict numeric settings to a small safe set. */
	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$roles = array_keys( wp_roles()->roles );
		$submitted = isset( $input['viewer_roles'] ) && is_array( $input['viewer_roles'] )
			? array_map( 'sanitize_key', array_filter( $input['viewer_roles'], 'is_string' ) )
			: array();
		$viewer_roles = array_values( array_intersect( $submitted, $roles ) );
		$viewer_roles = array_values( array_diff( $viewer_roles, array( 'administrator' ) ) );
		$inactivity = isset( $input['inactive_minutes'] ) ? absint( $input['inactive_minutes'] ) : 5;
		$refresh = isset( $input['refresh_seconds'] ) ? absint( $input['refresh_seconds'] ) : 60;
		return array(
			'viewer_roles' => $viewer_roles,
			'inactive_minutes' => in_array( $inactivity, array( 5, 10, 15, 30 ), true ) ? $inactivity : 5,
			'refresh_seconds' => in_array( $refresh, array( 30, 60, 120, 180 ), true ) ? $refresh : 60,
			'show_ip' => empty( $input['show_ip'] ) ? 0 : 1,
			'activity_enabled' => isset( $input['activity_enabled'] ) && ( 1 === $input['activity_enabled'] || '1' === $input['activity_enabled'] ) ? 1 : 0,
		);
	}

	/**
	 * Turning off monitoring immediately removes all plugin-only activity
	 * timestamps. Native WordPress login sessions are never affected.
	 */
	public static function clear_presence_on_disable( $old, $new ): void {
		if ( is_array( $old ) && is_array( $new ) && ! empty( $old['activity_enabled'] ) && empty( $new['activity_enabled'] ) ) {
			delete_metadata( 'user', 0, self::META, '', true );
		}
	}

	/**
	 * Suggest transparent, editable privacy-policy text using WordPress Core.
	 * This is not a claim that turning monitoring on establishes lawful consent.
	 */
	public static function register_privacy_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$paragraphs = array(
			__( 'If enabled, Session Radar records the time of recent interactions in the WordPress administration area for authenticated user sessions. It does not record public-site visits or send data to external services.', 'session-radar' ),
			__( 'Activity older than 24 hours is no longer shown. Stored records are deleted on later interactions or during WordPress scheduled cleanup, which may run later. Turning monitoring off deletes Orbit activity records immediately. WordPress manages login sessions separately.', 'session-radar' ),
		);
		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			$html .= '<p>' . esc_html( $paragraph ) . '</p>';
		}
		wp_add_privacy_policy_content( 'Session Radar', $html );
	}

	/**
	 * Make plugin-only activity available through WordPress's personal-data export.
	 * The plugin does not export native WordPress login session data.
	 */
	public static function register_personal_data_exporter( array $exporters ): array {
		$exporters['session-radar'] = array(
			'exporter_friendly_name' => __( 'Session Radar admin activity', 'session-radar' ),
			'callback' => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	/** Offer the corresponding eraser through the native WordPress privacy workflow. */
	public static function register_personal_data_eraser( array $erasers ): array {
		$erasers['session-radar'] = array(
			'eraser_friendly_name' => __( 'Session Radar admin activity', 'session-radar' ),
			'callback' => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export only recent observation times, not opaque session identifiers, IPs,
	 * native login tokens or data belonging to another email address.
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$result = array( 'data' => array(), 'done' => true );
		if ( 1 !== $page ) {
			return $result;
		}
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return $result;
		}
		$presence = get_user_meta( $user->ID, self::META, true );
		if ( ! is_array( $presence ) ) {
			return $result;
		}
		$now  = time();
		$seen = array_filter(
			array_values( $presence ),
			static fn( $timestamp ): bool => is_numeric( $timestamp )
				&& (int) $timestamp >= $now - DAY_IN_SECONDS
				&& (int) $timestamp <= $now
		);
		rsort( $seen, SORT_NUMERIC );
		foreach ( array_slice( $seen, 0, 32 ) as $index => $timestamp ) {
			$result['data'][] = array(
				'group_id' => 'amino-orbit-activity',
				'group_label' => __( 'Session Radar admin activity', 'session-radar' ),
				'item_id' => 'amino-orbit-presence-' . $index,
				'data' => array(
					array(
						'name' => __( 'Last observed admin interaction', 'session-radar' ),
						'value' => wp_date( 'Y-m-d H:i:s T', (int) $timestamp ),
					),
				),
			);
		}
		return $result;
	}

	/**
	 * Erase all Orbit-specific metadata for the requested WordPress account.
	 * Core's privacy workflow validates and authorizes the request.
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		$result = array(
			'items_removed' => false,
			'items_retained' => false,
			'messages' => array(),
			'done' => true,
		);
		if ( 1 !== $page ) {
			return $result;
		}
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return $result;
		}
		// Asking for all values also detects an empty or malformed metadata row.
		$existing = get_user_meta( $user->ID, self::META, false );
		if ( ! $existing ) {
			return $result;
		}
		$result['items_removed'] = (bool) delete_user_meta( $user->ID, self::META );
		if ( ! $result['items_removed'] ) {
			$result['items_retained'] = true;
			$result['messages'][] = __( 'Could not remove Session Radar activity data.', 'session-radar' );
		}
		return $result;
	}

	/** Register dashboard widget for permitted logged-in roles only. */
	public static function register_widget(): void {
		if ( ! self::can_view() ) {
			return;
		}
		wp_add_dashboard_widget(
			'amino_orbit_widget',
			__( 'Session Radar · Presence', 'session-radar' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/** Enqueue activity beacons only after site opt-in; keep widget refresh independent. */
	public static function enqueue_assets( $hook ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$dashboard = ( 'index.php' === $hook && self::can_view() );
		$settings  = ( 'settings_page_session-radar' === $hook );
		$options   = self::options();
		if ( $dashboard || $settings ) {
			wp_enqueue_style(
				'amino-orbit-admin',
				plugins_url( 'assets/admin.css', AMINO_ORBIT_FILE ),
				array(),
				AMINO_ORBIT_VERSION
			);
		}
		// A visitor with no widget access never needs the script when opted out.
		if ( ! $dashboard && empty( $options['activity_enabled'] ) ) {
			return;
		}
		wp_enqueue_script(
			'amino-orbit-admin',
			plugins_url( 'assets/admin.js', AMINO_ORBIT_FILE ),
			array(),
			AMINO_ORBIT_VERSION,
			true
		);
		wp_localize_script(
			'amino-orbit-admin',
			'aminoOrbitConfig',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE ),
				'canView'       => $dashboard,
				'refreshMs'     => $options['refresh_seconds'] * 1000,
				'trackActivity' => ! empty( $options['activity_enabled'] ),
				'labels'        => array(
					'refreshing' => __( 'Updating sessions…', 'session-radar' ),
					'updated'    => __( 'Sessions updated', 'session-radar' ),
					'failed'     => __( 'Refresh failed', 'session-radar' ),
				),
			)
		);
	}

	/** An opaque identifier for a WordPress session, never the token itself. */
	private static function fingerprint( array $session ): string {
		$parts = array(
			(int) ( $session['login'] ?? 0 ),
			(int) ( $session['expiration'] ?? 0 ),
			(string) ( $session['ip'] ?? '' ),
			(string) ( $session['ua'] ?? '' ),
		);
		return hash_hmac( 'sha256', wp_json_encode( $parts ), wp_salt( 'auth' ) );
	}

	/**
	 * Derive an opaque presence ID from WordPress's own hashed token verifier.
	 *
	 * The verifier is already a one-way SHA-256 digest in the native session
	 * store. A second keyed digest keeps Orbit's metadata unrelated to it.
	 * Even sessions created in the same second get distinct presence IDs.
	 */
	private static function verifier_fingerprint( string $verifier ): string {
		return hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) );
	}

	/** Authenticated user's last observed admin interaction. */
	public static function ajax_ping(): void {
		if ( ! is_user_logged_in() || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		// Server-side guard: bypassed or old scripts cannot submit activity when disabled.
		if ( empty( self::options()['activity_enabled'] ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
			return;
		}
		$token = wp_get_session_token();
		if ( '' === $token ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}
		$user_id = get_current_user_id();
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$session = $manager->get( $token );
		if ( ! is_array( $session ) || empty( $session['expiration'] ) || (int) $session['expiration'] <= time() ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}
		$now = time();
		$key = self::fingerprint( $session );
		// Only the built-in meta session manager exposes a stable verifier key.
		// Leave alternative session managers on the metadata-based fallback.
		if ( 'WP_User_Meta_Session_Tokens' === get_class( $manager ) ) {
			$verifier = hash( 'sha256', $token );
			$native = get_user_meta( $user_id, 'session_tokens', true );
			if ( is_array( $native ) && array_key_exists( $verifier, $native ) ) {
				$key = self::verifier_fingerprint( $verifier );
			}
		}
		$presence = get_user_meta( $user_id, self::META, true );
		$presence = is_array( $presence ) ? $presence : array();
		$presence = array_filter(
			$presence,
			static function ( $seen ) use ( $now ): bool {
				return is_numeric( $seen ) && (int) $seen >= $now - DAY_IN_SECONDS;
			}
		);
		if ( ! isset( $presence[ $key ] ) || (int) $presence[ $key ] < $now - 45 ) {
			$presence[ $key ] = $now;
			arsort( $presence, SORT_NUMERIC );
			update_user_meta( $user_id, self::META, array_slice( $presence, 0, 32, true ) );
		}
		wp_send_json_success( array( 'tracked' => true ) );
	}

	/** Securely refresh the visible panel via authenticated admin-ajax. */
	public static function ajax_refresh(): void {
		if ( ! self::can_view() || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		ob_start();
		self::render_widget();
		$html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html ) );
	}

	/** Normalize user agent to simple labels, not a fingerprinting library. */
	private static function device( string $ua ): string {
		if ( '' === $ua ) {
			return __( 'Unknown device', 'session-radar' );
		}
		$browser = __( 'Browser', 'session-radar' );
		if ( false !== stripos( $ua, 'Edg/' ) ) {
			$browser = 'Edge';
		} elseif ( false !== stripos( $ua, 'Firefox/' ) || false !== stripos( $ua, 'FxiOS/' ) ) {
			$browser = 'Firefox';
		} elseif ( false !== stripos( $ua, 'Chrome/' ) || false !== stripos( $ua, 'CriOS/' ) ) {
			$browser = 'Chrome';
		} elseif ( false !== stripos( $ua, 'Safari/' ) ) {
			$browser = 'Safari';
		}
		$os = '';
		if ( false !== stripos( $ua, 'Android' ) ) {
			$os = 'Android';
		} elseif ( false !== stripos( $ua, 'iPhone' ) || false !== stripos( $ua, 'iPad' ) ) {
			$os = 'iOS';
		} elseif ( false !== stripos( $ua, 'Windows' ) ) {
			$os = 'Windows';
		} elseif ( false !== stripos( $ua, 'Mac OS' ) || false !== stripos( $ua, 'Macintosh' ) ) {
			$os = 'macOS';
		} elseif ( false !== stripos( $ua, 'Linux' ) ) {
			$os = 'Linux';
		}
		return $os ? $browser . ' · ' . $os : $browser;
	}

	/** Read current WordPress sessions; never infer a sign-out from idle time. */
	private static function sessions( bool &$truncated ): array {
		$truncated = false;
		$now = time();
		$options = self::options();
		$users = get_users(
			array(
				'meta_key' => 'session_tokens',
				'fields' => 'all',
				'count_total' => false,
				'number' => self::MAX_USERS + 1,
				'orderby' => 'ID',
				'order' => 'ASC',
			)
		);
		// Fetch one extra user so the interface can disclose incomplete results.
		$truncated = count( $users ) > self::MAX_USERS;
		$users = array_slice( $users, 0, self::MAX_USERS );
		$rows = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			// Do not disclose previously recorded activity after monitoring is off.
			$presence = ! empty( $options['activity_enabled'] ) ? get_user_meta( $user->ID, self::META, true ) : array();
			$presence = is_array( $presence ) ? $presence : array();
			$manager = WP_Session_Tokens::get_instance( $user->ID );
			$sessions = array();

			if ( 'WP_User_Meta_Session_Tokens' === get_class( $manager ) ) {
				// WordPress's public get_all() drops session-verifier keys.
				// Read the same native usermeta without ever exposing token hashes.
				$native = get_user_meta( $user->ID, 'session_tokens', true );
				foreach ( is_array( $native ) ? $native : array() as $verifier => $session ) {
					if ( ! is_string( $verifier ) || ! preg_match( '/^[a-f0-9]{64}$/D', $verifier ) ) {
						continue;
					}
					$session = is_int( $session ) ? array( 'expiration' => $session ) : $session;
					$sessions[] = array(
						'data' => $session,
						'key'  => self::verifier_fingerprint( $verifier ),
					);
				}
			} else {
				// Custom WordPress session managers have no guaranteed verifier API.
				foreach ( $manager->get_all() as $session ) {
					$sessions[] = array(
						'data' => $session,
						'key'  => is_array( $session ) ? self::fingerprint( $session ) : '',
					);
				}
			}

			foreach ( $sessions as $entry ) {
				$session = $entry['data'];
				if ( ! is_array( $session ) || (int) ( $session['expiration'] ?? 0 ) <= $now ) {
					continue;
				}
				$key = $entry['key'];
				$seen = isset( $presence[ $key ] ) ? absint( $presence[ $key ] ) : 0;
				$seen = $seen >= $now - DAY_IN_SECONDS ? $seen : 0;
				$status = 'session';
				if ( $seen > 0 ) {
					$status = $seen >= $now - (int) $options['inactive_minutes'] * MINUTE_IN_SECONDS ? 'active' : 'idle';
				}
				$rows[] = array(
					'user' => $user,
					'login' => absint( $session['login'] ?? 0 ),
					'last_seen' => $seen,
					'status' => $status,
					'device' => self::device( (string) ( $session['ua'] ?? '' ) ),
					'ip' => (string) ( $session['ip'] ?? '' ),
				);
				if ( count( $rows ) >= self::MAX_SCANNED_SESSIONS ) {
					$truncated = true;
					break 2;
				}
			}
		}
		$priority = array( 'active' => 0, 'idle' => 1, 'session' => 2 );
		usort(
			$rows,
			static function ( array $a, array $b ) use ( $priority ): int {
				return ( $priority[ $a['status'] ] <=> $priority[ $b['status'] ] ) ?: ( $b['last_seen'] <=> $a['last_seen'] ) ?: ( $b['login'] <=> $a['login'] );
			}
		);
		return $rows;
	}

	/** Render the main dashboard panel. */
	public static function render_widget(): void {
		if ( ! self::can_view() ) {
			return;
		}
		$truncated = false;
		$rows = self::sessions( $truncated );
		$total = count( $rows );
		$active = count( array_filter( $rows, static fn( array $row ): bool => 'active' === $row['status'] ) );
		$user_ids = array();
		foreach ( $rows as $row ) {
			$user_ids[ $row['user']->ID ] = true;
		}
		$options = self::options();
		// Only expose role choices already represented by the capped visible rows.
		$visible_roles = array();
		foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $visible_row ) {
			foreach ( (array) $visible_row['user']->roles as $role ) {
				$role_definition = wp_roles()->roles[ $role ] ?? null;
				if ( is_array( $role_definition ) ) {
					$visible_roles[ $role ] = translate_user_role( $role_definition['name'] );
				}
			}
		}
		asort( $visible_roles, SORT_NATURAL | SORT_FLAG_CASE );
		?>
		<div class="amino-orbit" id="amino-orbit-panel">
			<div class="amino-orbit__header">
				<div class="amino-orbit__heading">
					<div class="amino-orbit__symbol" aria-hidden="true"><span></span></div>
					<div><strong>SESSION <em>RADAR</em></strong><small><?php esc_html_e( 'PRESENCE MONITOR', 'session-radar' ); ?></small></div>
				</div>
				<span class="amino-orbit__live" aria-label="<?php echo esc_attr__( 'Automatic refresh, not real-time presence', 'session-radar' ); ?>" title="<?php echo esc_attr__( 'Automatic refresh, not real-time presence', 'session-radar' ); ?>"><i aria-hidden="true"></i><?php esc_html_e( 'Auto', 'session-radar' ); ?></span>
				<div class="amino-orbit__stats">
					<div><strong><?php echo esc_html( (string) $total ); ?></strong><small><?php esc_html_e( 'Sessions', 'session-radar' ); ?></small></div>
					<div><strong><?php echo esc_html( (string) $active ); ?></strong><small><?php esc_html_e( 'Active sessions', 'session-radar' ); ?></small></div>
					<div><strong><?php echo esc_html( (string) count( $user_ids ) ); ?></strong><small><?php esc_html_e( 'Users', 'session-radar' ); ?></small></div>
				</div>
			</div>
			<div class="amino-orbit__body">
				<?php if ( ! $rows ) : ?>
					<p class="amino-orbit__empty"><?php esc_html_e( 'No open WordPress sessions detected.', 'session-radar' ); ?></p>
				<?php else : ?>
					<div class="amino-orbit__filters" role="group" aria-label="<?php echo esc_attr__( 'Authenticated WordPress sessions', 'session-radar' ); ?>">
						<button type="button" class="amino-orbit__filter" data-orbit-status="all" aria-pressed="true"><?php esc_html_e( 'Sessions', 'session-radar' ); ?></button>
						<button type="button" class="amino-orbit__filter" data-orbit-status="active" aria-pressed="false"><?php esc_html_e( 'Active', 'session-radar' ); ?></button>
						<button type="button" class="amino-orbit__filter" data-orbit-status="idle" aria-pressed="false"><?php esc_html_e( 'Inactive', 'session-radar' ); ?></button>
						<button type="button" class="amino-orbit__filter" data-orbit-status="session" aria-pressed="false"><?php esc_html_e( 'Session open', 'session-radar' ); ?></button>
						<span class="amino-orbit__filter-count" aria-live="polite" aria-atomic="true"></span>
					</div>
					<div class="amino-orbit__sort-wrap">
						<label for="amino-orbit-sort"><?php esc_html_e( 'Sort sessions', 'session-radar' ); ?></label>
						<select id="amino-orbit-sort" class="amino-orbit__sort" aria-controls="amino-orbit-list">
							<option value="suggested"><?php esc_html_e( 'Suggested order', 'session-radar' ); ?></option>
							<option value="newest"><?php esc_html_e( 'Newest login', 'session-radar' ); ?></option>
							<option value="oldest"><?php esc_html_e( 'Oldest login', 'session-radar' ); ?></option>
						</select>
					</div>
					<div class="amino-orbit__role-wrap">
						<label for="amino-orbit-role"><?php esc_html_e( 'Filter by role', 'session-radar' ); ?></label>
						<select id="amino-orbit-role" class="amino-orbit__role-filter" aria-controls="amino-orbit-list">
							<option value=""><?php esc_html_e( 'All roles', 'session-radar' ); ?></option>
							<?php foreach ( $visible_roles as $role_slug => $role_label ) : ?>
								<option value="<?php echo esc_attr( sanitize_key( $role_slug ) ); ?>"><?php echo esc_html( $role_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="amino-orbit__search-wrap">
						<label for="amino-orbit-search"><?php esc_html_e( 'Search sessions', 'session-radar' ); ?></label>
						<input id="amino-orbit-search" class="amino-orbit__search" type="search" aria-controls="amino-orbit-list" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr__( 'Name, role or device', 'session-radar' ); ?>" />
					</div>
					<div class="amino-orbit__display-tools">
						<button type="button" class="amino-orbit__reset"><?php esc_html_e( 'Reset filters', 'session-radar' ); ?></button>
						<button type="button" class="amino-orbit__compact-toggle" aria-pressed="false"><?php esc_html_e( 'Compact view', 'session-radar' ); ?></button>
					</div>
					<p class="amino-orbit__empty-filter" role="status" hidden><?php esc_html_e( 'No sessions match these filters.', 'session-radar' ); ?></p>
					<ul id="amino-orbit-list" class="amino-orbit__list" aria-label="<?php echo esc_attr__( 'Authenticated WordPress sessions', 'session-radar' ); ?>">
						<?php foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $row_index => $row ) : ?>
							<?php
							$user = $row['user'];
							$role_labels = array();
							foreach ( (array) $user->roles as $role ) {
								$role_definition = wp_roles()->roles[ $role ] ?? null;
								$role_labels[] = is_array( $role_definition ) ? translate_user_role( $role_definition['name'] ) : $role;
							}
							$label = array(
								'active' => __( 'Active', 'session-radar' ),
								'idle' => __( 'Inactive', 'session-radar' ),
								'session' => __( 'Session open', 'session-radar' ),
							);
							$initials = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( (string) $user->display_name, 0, 2 ) ) : strtoupper( substr( (string) $user->display_name, 0, 2 ) );
							?>
							<li class="amino-orbit__user" data-orbit-roles="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_key', (array) $user->roles ) ) ); ?>" data-orbit-status="<?php echo esc_attr( $row['status'] ); ?>" data-orbit-login="<?php echo esc_attr( (string) absint( $row['login'] ) ); ?>" data-orbit-order="<?php echo esc_attr( (string) $row_index ); ?>">
								<div class="amino-orbit__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
								<div class="amino-orbit__identity">
									<strong><?php echo esc_html( $user->display_name ); ?></strong>
									<small><?php echo esc_html( implode( ', ', $role_labels ) ); ?></small>
									<span class="amino-orbit__device"><?php echo esc_html( $row['device'] ); ?></span>
									<?php if ( current_user_can( 'manage_options' ) && ! empty( $options['show_ip'] ) && filter_var( $row['ip'], FILTER_VALIDATE_IP ) ) : ?>
										<span class="amino-orbit__ip">IP : <?php echo esc_html( $row['ip'] ); ?></span>
									<?php endif; ?>
								</div>
								<div class="amino-orbit__timing">
									<span class="amino-orbit__status amino-orbit__status--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $label[ $row['status'] ] ); ?></span>
									<?php if ( $row['login'] ) : ?>
										<small title="<?php echo esc_attr( wp_date( 'd/m/Y H:i:s T', $row['login'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: WordPress login time in site timezone. */ __( 'Logged in: %s', 'session-radar' ), wp_date( 'd/m H:i', $row['login'] ) ) ); ?></small>
									<?php endif; ?>
									<?php if ( $row['last_seen'] ) : ?>
										<small title="<?php echo esc_attr__( 'Last activity detected in WordPress admin', 'session-radar' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: human-readable time difference. */ __( 'Seen %s ago', 'session-radar' ), human_time_diff( $row['last_seen'], time() ) ) ); ?></small>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $truncated || $total > self::MAX_ROWS ) : ?>
					<p class="amino-orbit__note"><?php esc_html_e( 'Filters and sorting apply only to displayed sessions.', 'session-radar' ); ?></p>
				<?php endif; ?>
				<?php if ( $truncated ) : ?>
					<p class="amino-orbit__note"><?php esc_html_e( 'Partial results: the session scan limit has been reached.', 'session-radar' ); ?></p>
				<?php endif; ?>
				<?php if ( $total > self::MAX_ROWS ) : ?>
					<p class="amino-orbit__note"><?php echo esc_html( sprintf( /* translators: %d: maximum number of displayed sessions. */ __( 'Showing up to %d sessions.', 'session-radar' ), self::MAX_ROWS ) ); ?></p>
				<?php endif; ?>
			</div>
			<div class="amino-orbit__footer">
				<span><?php echo esc_html( sprintf( /* translators: %d: refresh interval in seconds. */ __( 'Refresh: %d s', 'session-radar' ), (int) $options['refresh_seconds'] ) ); ?></span>
				<button type="button" class="amino-orbit__refresh-now"><?php esc_html_e( 'Refresh now', 'session-radar' ); ?></button>
				<span class="amino-orbit__refresh-feedback" role="status" aria-live="polite" aria-atomic="true"></span>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=session-radar' ) ); ?>"><?php esc_html_e( 'Settings', 'session-radar' ); ?> <span aria-hidden="true">↗</span></a>
				<?php endif; ?>
			</div>
			<p class="amino-orbit__disclaimer"><?php esc_html_e( 'Active means recent activity in wp-admin. An open session does not mean the user is at their screen.', 'session-radar' ); ?></p>
		</div>
		<?php
	}

	/** Standalone administrator-only settings page. */
	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = self::options();
		?>
		<div class="wrap amino-orbit-settings">
			<h1><?php esc_html_e( 'Session Radar', 'session-radar' ); ?></h1>
			<p><?php esc_html_e( 'WordPress sessions and recent admin activity, without tracking public-site browsing.', 'session-radar' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'session_radar_settings' ); ?>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Who can see the widget?', 'session-radar' ); ?></h2>
					<p><?php esc_html_e( 'Administrators always have access. Optionally grant access to additional roles.', 'session-radar' ); ?></p>
					<fieldset class="amino-orbit-settings__roles"><legend class="screen-reader-text"><?php esc_html_e( 'Additional roles allowed to see the widget', 'session-radar' ); ?></legend>
						<?php foreach ( wp_roles()->roles as $slug => $details ) : ?>
							<?php if ( 'administrator' !== $slug ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[viewer_roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $options['viewer_roles'], true ) ); ?> /> <?php echo esc_html( translate_user_role( $details['name'] ) ); ?></label>
							<?php endif; ?>
						<?php endforeach; ?>
					</fieldset>
				</div>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Presence and refresh', 'session-radar' ); ?></h2>
					<p><label for="amino-orbit-inactive"><?php esc_html_e( 'Inactivity threshold', 'session-radar' ); ?></label></p>
					<select id="amino-orbit-inactive" name="<?php echo esc_attr( self::OPTION ); ?>[inactive_minutes]">
						<?php foreach ( array( 5, 10, 15, 30 ) as $minute ) : ?><option value="<?php echo esc_attr( (string) $minute ); ?>" <?php selected( (int) $options['inactive_minutes'], $minute ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of minutes. */ __( '%d minutes', 'session-radar' ), $minute ) ); ?></option><?php endforeach; ?>
					</select>
					<p><label for="amino-orbit-refresh"><?php esc_html_e( 'Widget refresh interval', 'session-radar' ); ?></label></p>
					<select id="amino-orbit-refresh" name="<?php echo esc_attr( self::OPTION ); ?>[refresh_seconds]">
						<?php foreach ( array( 30, 60, 120, 180 ) as $second ) : ?><option value="<?php echo esc_attr( (string) $second ); ?>" <?php selected( (int) $options['refresh_seconds'], $second ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of seconds. */ __( '%d seconds', 'session-radar' ), $second ) ); ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Privacy', 'session-radar' ); ?></h2>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[activity_enabled]" value="1" <?php checked( ! empty( $options['activity_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable admin activity signals', 'session-radar' ); ?></label>
					<p class="description"><?php esc_html_e( 'When enabled, this site stores a last-interaction timestamp for authenticated WordPress admin sessions. Inform affected users before activating this feature.', 'session-radar' ); ?></p>
					<p class="description"><?php esc_html_e( 'Activity signals are disabled until an administrator explicitly enables them. Authentication sessions remain visible without activity tracking.', 'session-radar' ); ?></p>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_ip]" value="1" <?php checked( ! empty( $options['show_ip'] ) ); ?> /> <?php esc_html_e( 'Show session IP addresses to administrators only', 'session-radar' ); ?></label>
					<p class="description"><?php esc_html_e( 'Disabled by default. Session Radar does not collect additional IP addresses; it reads data already associated with WordPress sessions.', 'session-radar' ); ?></p>
					<p class="description"><?php esc_html_e( 'Activity timestamps expire after 24 hours and are cleaned daily by WP-Cron. Anonymous visitors are not tracked.', 'session-radar' ); ?></p>
				</div>
				<?php submit_button( __( 'Save settings', 'session-radar' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Prune expired presence metadata with a stable user list. */
	public static function cleanup_presence(): void {
		$now = time();
		// Snapshot IDs first: deleting a meta row during offset pagination would
		// otherwise skip accounts later in the result set.
		$ids = get_users(
			array(
				'meta_key' => self::META,
				'fields' => 'ID',
				'number' => 5000,
				'orderby' => 'ID',
			)
		);
		foreach ( $ids as $user_id ) {
			$original = get_user_meta( (int) $user_id, self::META, true );
			$original = is_array( $original ) ? $original : array();
			$presence = array_filter(
				$original,
				static fn( $seen ): bool => is_numeric( $seen ) && (int) $seen >= $now - DAY_IN_SECONDS
			);
			if ( ! $presence ) {
				delete_user_meta( (int) $user_id, self::META, $original );
			} elseif ( $presence !== $original ) {
				// Avoid redundant daily writes and avoid overwriting a newer ping.
				update_user_meta( (int) $user_id, self::META, $presence, $original );
			}
		}
	}
}
