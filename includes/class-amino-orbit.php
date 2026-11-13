<?php
/**
 * Amino Orbit core.
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
	private const OPTION = 'amino_orbit_settings';
	private const META = '_amino_orbit_presence';
	private const NONCE = 'amino_orbit_ajax';
	private const CLEANUP_HOOK = 'amino_orbit_daily_cleanup';
	private const MAX_USERS = 200;
	private const MAX_ROWS = 40;

	/** Register WordPress hooks. */
	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_amino_orbit_ping', array( __CLASS__, 'ajax_ping' ) );
		add_action( 'wp_ajax_amino_orbit_refresh', array( __CLASS__, 'ajax_refresh' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_presence' ) );
	}

	/** Set up the WordPress cron cleanup without modifying existing settings. */
	public static function activate(): void {
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
			__( 'Amino Orbit', 'amino-orbit' ),
			__( 'Amino Orbit', 'amino-orbit' ),
			'manage_options',
			'amino-orbit',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'amino_orbit_settings',
			self::OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default' => array(
					'viewer_roles' => array(),
					'inactive_minutes' => 5,
					'refresh_seconds' => 60,
					'show_ip' => 0,
				),
			)
		);
	}

	/** Validate allowed roles and restrict numeric settings to a small safe set. */
	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$roles = array_keys( wp_roles()->roles );
		$submitted = isset( $input['viewer_roles'] ) && is_array( $input['viewer_roles'] ) ? array_map( 'sanitize_key', $input['viewer_roles'] ) : array();
		$viewer_roles = array_values( array_intersect( $submitted, $roles ) );
		$viewer_roles = array_values( array_diff( $viewer_roles, array( 'administrator' ) ) );
		$inactivity = isset( $input['inactive_minutes'] ) ? absint( $input['inactive_minutes'] ) : 5;
		$refresh = isset( $input['refresh_seconds'] ) ? absint( $input['refresh_seconds'] ) : 60;
		return array(
			'viewer_roles' => $viewer_roles,
			'inactive_minutes' => in_array( $inactivity, array( 5, 10, 15, 30 ), true ) ? $inactivity : 5,
			'refresh_seconds' => in_array( $refresh, array( 30, 60, 120, 180 ), true ) ? $refresh : 60,
			'show_ip' => empty( $input['show_ip'] ) ? 0 : 1,
		);
	}

	/** Register dashboard widget for permitted logged-in roles only. */
	public static function register_widget(): void {
		if ( ! self::can_view() ) {
			return;
		}
		wp_add_dashboard_widget(
			'amino_orbit_widget',
			__( 'Amino Orbit · Présence', 'amino-orbit' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/** Load presence tracking on wp-admin, and display assets only where needed. */
	public static function enqueue_assets( $hook ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$dashboard = ( 'index.php' === $hook && self::can_view() );
		$settings = ( 'settings_page_amino-orbit' === $hook );
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'canView' => $dashboard,
				'refreshMs' => self::options()['refresh_seconds'] * 1000,
			)
		);
		if ( $dashboard || $settings ) {
			wp_enqueue_style(
				'amino-orbit-admin',
				plugins_url( 'assets/admin.css', AMINO_ORBIT_FILE ),
				array(),
				AMINO_ORBIT_VERSION
			);
		}
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

	/** Authenticated user's last observed admin interaction. */
	public static function ajax_ping(): void {
		if ( ! is_user_logged_in() || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		$token = wp_get_session_token();
		if ( '' === $token ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}
		$user_id = get_current_user_id();
		$session = WP_Session_Tokens::get_instance( $user_id )->get( $token );
		if ( ! is_array( $session ) || empty( $session['expiration'] ) || (int) $session['expiration'] <= time() ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}
		$now = time();
		$key = self::fingerprint( $session );
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
			return __( 'Appareil inconnu', 'amino-orbit' );
		}
		$browser = __( 'Navigateur', 'amino-orbit' );
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
	private static function sessions(): array {
		$now = time();
		$options = self::options();
		$users = get_users(
			array(
				'meta_key' => 'session_tokens',
				'fields' => 'all',
				'number' => self::MAX_USERS,
				'orderby' => 'ID',
				'order' => 'ASC',
			)
		);
		$rows = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			$presence = get_user_meta( $user->ID, self::META, true );
			$presence = is_array( $presence ) ? $presence : array();
			foreach ( WP_Session_Tokens::get_instance( $user->ID )->get_all() as $session ) {
				if ( ! is_array( $session ) || (int) ( $session['expiration'] ?? 0 ) <= $now ) {
					continue;
				}
				$key = self::fingerprint( $session );
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
		$rows = self::sessions();
		$total = count( $rows );
		$active = count( array_filter( $rows, static fn( array $row ): bool => 'active' === $row['status'] ) );
		$user_ids = array();
		foreach ( $rows as $row ) {
			$user_ids[ $row['user']->ID ] = true;
		}
		$options = self::options();
		?>
		<div class="amino-orbit" id="amino-orbit-panel">
			<div class="amino-orbit__header">
				<div class="amino-orbit__heading">
					<div class="amino-orbit__symbol" aria-hidden="true"><span></span></div>
					<div><strong>Amino <em>ORBIT</em></strong><small><?php esc_html_e( 'PRESENCE MONITOR', 'amino-orbit' ); ?></small></div>
				</div>
				<span class="amino-orbit__live" title="<?php echo esc_attr__( 'Rafraîchissement automatique, pas de connexion en temps réel', 'amino-orbit' ); ?>"><i aria-hidden="true"></i><?php esc_html_e( 'Auto', 'amino-orbit' ); ?></span>
				<div class="amino-orbit__stats">
					<div><strong><?php echo esc_html( (string) $total ); ?></strong><small><?php esc_html_e( 'Sessions', 'amino-orbit' ); ?></small></div>
					<div><strong><?php echo esc_html( (string) $active ); ?></strong><small><?php esc_html_e( 'Actives', 'amino-orbit' ); ?></small></div>
					<div><strong><?php echo esc_html( (string) count( $user_ids ) ); ?></strong><small><?php esc_html_e( 'Utilisateurs', 'amino-orbit' ); ?></small></div>
				</div>
			</div>
			<div class="amino-orbit__body">
				<?php if ( ! $rows ) : ?>
					<p class="amino-orbit__empty"><?php esc_html_e( 'Aucune session WordPress ouverte détectée.', 'amino-orbit' ); ?></p>
				<?php else : ?>
					<ul class="amino-orbit__list">
						<?php foreach ( array_slice( $rows, 0, self::MAX_ROWS ) as $row ) : ?>
							<?php
							$user = $row['user'];
							$role_labels = array();
							foreach ( (array) $user->roles as $role ) {
								$role_definition = wp_roles()->roles[ $role ] ?? null;
								$role_labels[] = is_array( $role_definition ) ? translate_user_role( $role_definition['name'] ) : $role;
							}
							$label = array(
								'active' => __( 'Actif', 'amino-orbit' ),
								'idle' => __( 'Inactif', 'amino-orbit' ),
								'session' => __( 'Session ouverte', 'amino-orbit' ),
							);
							$initials = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( (string) $user->display_name, 0, 2 ) ) : strtoupper( substr( (string) $user->display_name, 0, 2 ) );
							?>
							<li class="amino-orbit__user">
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
										<small title="<?php echo esc_attr( wp_date( 'd/m/Y H:i:s T', $row['login'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: WordPress login time in site timezone. */ __( 'Connexion : %s', 'amino-orbit' ), wp_date( 'd/m H:i', $row['login'] ) ) ); ?></small>
									<?php endif; ?>
									<?php if ( $row['last_seen'] ) : ?>
										<small title="<?php echo esc_attr__( 'Dernière activité détectée dans l’administration WordPress', 'amino-orbit' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: human-readable time difference. */ __( 'Vu il y a %s', 'amino-orbit' ), human_time_diff( $row['last_seen'], time() ) ) ); ?></small>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $total > self::MAX_ROWS ) : ?>
					<p class="amino-orbit__note"><?php echo esc_html( sprintf( /* translators: %d: maximum number of displayed sessions. */ __( 'Affichage limité à %d sessions.', 'amino-orbit' ), self::MAX_ROWS ) ); ?></p>
				<?php endif; ?>
			</div>
			<div class="amino-orbit__footer">
				<span><?php echo esc_html( sprintf( /* translators: %d: refresh interval in seconds. */ __( 'Actualisation : %d s', 'amino-orbit' ), (int) $options['refresh_seconds'] ) ); ?></span>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=amino-orbit' ) ); ?>"><?php esc_html_e( 'Réglages', 'amino-orbit' ); ?> <span aria-hidden="true">↗</span></a>
				<?php endif; ?>
			</div>
			<p class="amino-orbit__disclaimer"><?php esc_html_e( 'Actif = activité récente dans wp-admin. Une session ouverte ne signifie pas que la personne est devant son écran.', 'amino-orbit' ); ?></p>
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
			<h1><?php esc_html_e( 'Amino Orbit', 'amino-orbit' ); ?></h1>
			<p><?php esc_html_e( 'Sessions WordPress et activité récente dans l’administration, sans suivi de la navigation publique.', 'amino-orbit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'amino_orbit_settings' ); ?>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Qui peut voir le widget ?', 'amino-orbit' ); ?></h2>
					<p><?php esc_html_e( 'Les administrateurs ont toujours accès. Autorisez, si besoin, des rôles supplémentaires.', 'amino-orbit' ); ?></p>
					<div class="amino-orbit-settings__roles">
						<?php foreach ( wp_roles()->roles as $slug => $details ) : ?>
							<?php if ( 'administrator' !== $slug ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[viewer_roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $options['viewer_roles'], true ) ); ?> /> <?php echo esc_html( translate_user_role( $details['name'] ) ); ?></label>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Présence et actualisation', 'amino-orbit' ); ?></h2>
					<p><label for="amino-orbit-inactive"><?php esc_html_e( 'Seuil d’inactivité', 'amino-orbit' ); ?></label></p>
					<select id="amino-orbit-inactive" name="<?php echo esc_attr( self::OPTION ); ?>[inactive_minutes]">
						<?php foreach ( array( 5, 10, 15, 30 ) as $minute ) : ?><option value="<?php echo esc_attr( (string) $minute ); ?>" <?php selected( (int) $options['inactive_minutes'], $minute ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of minutes. */ __( '%d minutes', 'amino-orbit' ), $minute ) ); ?></option><?php endforeach; ?>
					</select>
					<p><label for="amino-orbit-refresh"><?php esc_html_e( 'Rafraîchissement du widget', 'amino-orbit' ); ?></label></p>
					<select id="amino-orbit-refresh" name="<?php echo esc_attr( self::OPTION ); ?>[refresh_seconds]">
						<?php foreach ( array( 30, 60, 120, 180 ) as $second ) : ?><option value="<?php echo esc_attr( (string) $second ); ?>" <?php selected( (int) $options['refresh_seconds'], $second ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of seconds. */ __( '%d secondes', 'amino-orbit' ), $second ) ); ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="amino-orbit-settings__card">
					<h2><?php esc_html_e( 'Confidentialité', 'amino-orbit' ); ?></h2>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_ip]" value="1" <?php checked( ! empty( $options['show_ip'] ) ); ?> /> <?php esc_html_e( 'Afficher les IP des sessions aux administrateurs uniquement', 'amino-orbit' ); ?></label>
					<p class="description"><?php esc_html_e( 'Désactivé par défaut. Amino Orbit ne collecte pas d’IP supplémentaires ; il lit les informations déjà associées aux sessions WordPress.', 'amino-orbit' ); ?></p>
					<p class="description"><?php esc_html_e( 'Les horodatages d’activité expirent au bout de 24 heures et sont purgés quotidiennement via WP-Cron. Aucune activité des visiteurs anonymes n’est suivie.', 'amino-orbit' ); ?></p>
				</div>
				<?php submit_button( __( 'Enregistrer les réglages', 'amino-orbit' ) ); ?>
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
			$presence = get_user_meta( (int) $user_id, self::META, true );
			$presence = is_array( $presence ) ? $presence : array();
			$presence = array_filter(
				$presence,
				static fn( $seen ): bool => is_numeric( $seen ) && (int) $seen >= $now - DAY_IN_SECONDS
			);
			if ( $presence ) {
				update_user_meta( (int) $user_id, self::META, $presence );
			} else {
				delete_user_meta( (int) $user_id, self::META );
			}
		}
	}
}
