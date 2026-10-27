<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Plugin {
	use Panda_Seasons_Runtime;

	const CALENDAR_POST_TYPE = 'panda_calendar';
	const DOOR_POST_TYPE     = 'panda_door';
	const CRON_HOOK          = 'panda_seasons_process_email_calendars';

	public static function boot() {
		load_plugin_textdomain( 'panda-seasons', false, dirname( plugin_basename( PANDA_SEASONS_FILE ) ) . '/languages' );
		Panda_Seasons_Schema::maybe_upgrade();
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( 'Panda_Seasons_Migrator', 'maybe_migrate' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'parent_file', array( __CLASS__, 'admin_parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'admin_submenu_file' ) );
		add_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
		add_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'screen_options_show_screen', array( __CLASS__, 'hide_calendar_screen_options' ), 10, 2 );
		add_action( 'wp_ajax_panda_seasons_save_editor_door', array( __CLASS__, 'save_editor_door_ajax' ) );
		add_action( 'wp_ajax_panda_seasons_save_calendar', array( __CLASS__, 'save_calendar_ajax' ) );
		add_action( 'wp_ajax_panda_seasons_track_door_click', array( __CLASS__, 'track_door_click' ) );
		add_action( 'wp_ajax_nopriv_panda_seasons_track_door_click', array( __CLASS__, 'track_door_click' ) );
		add_action( 'admin_post_panda_seasons_preview', array( __CLASS__, 'preview_page' ) );
		add_action( 'admin_post_panda_seasons_export_template', array( __CLASS__, 'export_template' ) );
		add_action( 'admin_post_panda_seasons_import_template', array( __CLASS__, 'import_template' ) );
		add_action( 'admin_post_panda_seasons_refresh_template_library', array( __CLASS__, 'refresh_template_library' ) );
		add_action( 'admin_post_panda_seasons_import_library_template', array( __CLASS__, 'import_library_template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_filter( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_columns', array( __CLASS__, 'calendar_columns' ) );
		add_action( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'calendar_column' ), 10, 2 );
		add_filter( 'manage_' . self::DOOR_POST_TYPE . '_posts_columns', array( __CLASS__, 'door_columns' ) );
		add_action( 'manage_' . self::DOOR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'door_column' ), 10, 2 );
		add_shortcode( 'panda_seasons', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'panda_seasons_subscribe', array( __CLASS__, 'subscribe_shortcode' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_filter( 'gettext', array( __CLASS__, 'normalize_german_door_terms' ), 20, 3 );
		add_action( self::CRON_HOOK, array( 'Panda_Seasons_Mailer', 'process' ) );
		Panda_Seasons_Subscriptions::boot();
	}

	public static function normalize_german_door_terms( $translation, $text, $domain ) {
		if ( 'panda-seasons' !== $domain || 0 !== strpos( determine_locale(), 'de_' ) ) return $translation;
		$new_strings = array( 'Availability' => 'Verfügbarkeit', 'Available permanently from the release date' => 'Ab dem Freigabedatum dauerhaft verfügbar', 'Available only on the release day' => 'Nur am Freigabetag verfügbar', 'Clear' => 'Leeren', 'Global color settings' => 'Globale Farbeinstellungen', 'Default door shape' => 'Standard-Türform', 'Default opening mode' => 'Standard-Öffnungsmodus', 'Active door border color' => 'Rahmenfarbe der aktuellen Türe', 'Align now' => 'Jetzt ausrichten' );
		if ( isset( $new_strings[ $text ] ) ) return $new_strings[ $text ];
		if ( false === strpos( $translation, 'Türchen' ) ) return $translation;
		$map = array( 'Türchenbeschriftung' => 'Türenbeschriftung', 'Türchennummer' => 'Türnummer', 'Türcheneinstellungen' => 'Türeinstellungen', 'Türcheninhalt' => 'Türeninhalt', 'Türchenvorschau' => 'Türvorschau', 'Türchenform' => 'Türform', 'Mustertürchen' => 'Mustertüre', 'Kalendertürchen' => 'Kalendertüre', 'Türchen' => 'Türe' );
		return strtr( $translation, $map );
	}

	public static function activate() {
		self::register_post_types();
		Panda_Seasons_Schema::install();
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'panda_seasons_five_minutes', self::CRON_HOOK );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}

	public static function cron_schedules( $schedules ) {
		$schedules['panda_seasons_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'panda-seasons' ),
		);
		return $schedules;
	}

	public static function register_post_types() {
		register_post_type(
			self::CALENDAR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Panda Seasons', 'panda-seasons' ),
					'singular_name' => __( 'Calendar', 'panda-seasons' ),
					'add_new_item'  => __( 'Add calendar', 'panda-seasons' ),
					'edit_item'     => __( 'Edit calendar', 'panda-seasons' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			self::DOOR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Doors', 'panda-seasons' ),
					'singular_name' => __( 'Door', 'panda-seasons' ),
					'add_new_item'  => __( 'Add door', 'panda-seasons' ),
					'edit_item'     => __( 'Edit door', 'panda-seasons' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function contactpanda_available() {
		return defined( 'CONTACTPANDA_VERSION' ) && class_exists( 'ContactPanda_Plugin' );
	}

	private static function contactpanda_newsletter_available() {
		if ( ! self::contactpanda_available() && ! shortcode_exists( 'contactpanda_newsletter_signup' ) && ! shortcode_exists( 'contacthub_newsletter_signup' ) ) return false;
		$settings = (array) get_option( 'contactpanda_registration_settings', array() );
		if ( ! $settings ) $settings = (array) get_option( 'contacthub_registration_settings', array() );
		$settings = wp_parse_args( $settings, array( 'newsletter_enabled' => 1 ) );
		return ! empty( $settings['newsletter_enabled'] );
	}

	private static function contact_signup_shortcode( $calendar_id, $door_id = 0 ) {
		$tag = (string) get_post_meta( $calendar_id, '_ps_contact_tag', true );
		if ( ! $tag ) $tag = sprintf( __( 'Panda Seasons – %s', 'panda-seasons' ), get_the_title( $calendar_id ) );
		$context = apply_filters( 'panda_seasons_contact_signup_context', array(
			'source'       => 'panda_seasons',
			'source_id'    => (string) $calendar_id,
			'source_title' => get_the_title( $calendar_id ),
			'source_url'   => get_permalink( $calendar_id ),
			'tag'          => $tag,
			'door_id'      => $door_id ? (string) $door_id : '',
			'door_number'  => $door_id ? (string) get_post_meta( $door_id, '_ps_door_number', true ) : '',
		), $calendar_id, $door_id );
		$shortcode = shortcode_exists( 'contactpanda_newsletter_signup' ) ? 'contactpanda_newsletter_signup' : ( shortcode_exists( 'contacthub_newsletter_signup' ) ? 'contacthub_newsletter_signup' : '' );
		if ( ! $shortcode ) return '';
		$attributes = '';
		foreach ( $context as $name => $value ) {
			if ( '' !== (string) $value ) $attributes .= ' ' . sanitize_key( $name ) . '="' . esc_attr( $value ) . '"';
		}
		do_action( 'panda_seasons_before_contact_signup', $context, $calendar_id, $door_id );
		return do_shortcode( '[' . $shortcode . $attributes . ']' );
	}

	public static function meta_boxes() {
		remove_meta_box( 'postimagediv', self::CALENDAR_POST_TYPE, 'side' );
		add_meta_box( 'panda-seasons-design', __( 'Visual calendar editor', 'panda-seasons' ), array( __CLASS__, 'calendar_design_box' ), self::CALENDAR_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'panda-seasons-email', __( 'Email delivery and signups', 'panda-seasons' ), array( __CLASS__, 'calendar_email_box' ), self::CALENDAR_POST_TYPE, 'normal', 'default' );
		add_meta_box( 'panda-seasons-door', __( 'Door settings', 'panda-seasons' ), array( __CLASS__, 'door_box' ), self::DOOR_POST_TYPE, 'side', 'high' );
	}

	public static function hide_calendar_screen_options( $show, $screen ) {
		return $screen && self::CALENDAR_POST_TYPE === $screen->post_type ? false : $show;
	}

	public static function admin_menu() {
		add_menu_page(
			__( 'Panda Seasons dashboard', 'panda-seasons' ),
			__( 'Panda Seasons', 'panda-seasons' ),
			'edit_posts',
			'panda-seasons-dashboard',
			array( __CLASS__, 'dashboard_page' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Panda Seasons dashboard', 'panda-seasons' ),
			__( 'Dashboard', 'panda-seasons' ),
			'edit_posts',
			'panda-seasons-dashboard',
			array( __CLASS__, 'dashboard_page' )
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Calendars', 'panda-seasons' ),
			__( 'Calendars', 'panda-seasons' ),
			'edit_posts',
			'edit.php?post_type=' . self::CALENDAR_POST_TYPE
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Add calendar', 'panda-seasons' ),
			__( 'Add calendar', 'panda-seasons' ),
			'edit_posts',
			'post-new.php?post_type=' . self::CALENDAR_POST_TYPE
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Calendar templates', 'panda-seasons' ),
			__( 'Templates', 'panda-seasons' ),
			'edit_posts',
			'panda-seasons-templates',
			array( __CLASS__, 'templates_page' )
		);
	}

	public static function admin_parent_file( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, array( self::CALENDAR_POST_TYPE, self::DOOR_POST_TYPE ), true ) ) return 'panda-seasons-dashboard';
		return $parent_file;
	}

	public static function admin_submenu_file( $submenu_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) return $submenu_file;
		if ( self::CALENDAR_POST_TYPE === $screen->post_type ) return 'edit.php?post_type=' . self::CALENDAR_POST_TYPE;
		if ( self::DOOR_POST_TYPE === $screen->post_type ) return 'edit.php?post_type=' . self::DOOR_POST_TYPE;
		return $submenu_file;
	}

	public static function dashboard_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to view the Panda Seasons dashboard.', 'panda-seasons' ) );
		$calendars = get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 250, 'orderby' => 'modified', 'order' => 'DESC' ) );
		$stats = self::dashboard_statistics();
		$contactpanda_active = self::contactpanda_available();
		$contactpanda_installed = false;
		$contactpanda_plugin = '';
		if ( ! $contactpanda_active && current_user_can( 'activate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( array_keys( get_plugins() ) as $plugin_file ) {
				if ( 'contactpanda/contactpanda.php' === $plugin_file || false !== strpos( $plugin_file, 'contactpanda.php' ) ) {
					$contactpanda_installed = true;
					$contactpanda_plugin = $plugin_file;
					break;
				}
			}
		}
		?>
		<div class="wrap panda-seasons-dashboard">
			<section class="panda-seasons-dashboard__hero">
				<div class="panda-seasons-dashboard__hero-copy">
					<h1><?php esc_html_e( 'Panda Seasons', 'panda-seasons' ); ?></h1>
					<p><?php esc_html_e( 'Create interactive seasonal calendars, manage registrations and keep an eye on the most important results.', 'panda-seasons' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::CALENDAR_POST_TYPE ) ); ?>"><?php esc_html_e( 'Create calendar', 'panda-seasons' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=panda-seasons-templates' ) ); ?>"><?php esc_html_e( 'Open templates', 'panda-seasons' ); ?></a></p>
				</div>
				<img src="<?php echo esc_url( PANDA_SEASONS_URL . 'assets/images/panda-seasons-dashboard.png' ); ?>" alt="<?php esc_attr_e( 'Panda Seasons calendar illustration', 'panda-seasons' ); ?>">
			</section>

			<section class="panda-seasons-dashboard__section">
				<div class="panda-seasons-dashboard__section-heading"><div><h2><?php esc_html_e( 'Your calendars', 'panda-seasons' ); ?></h2><p><?php esc_html_e( 'Door clicks, active email subscriptions and generated leads are counted separately for each calendar.', 'panda-seasons' ); ?></p></div></div>
				<?php if ( ! $calendars ) : ?>
					<div class="panda-seasons-dashboard__empty"><p><?php esc_html_e( 'No calendar has been created yet.', 'panda-seasons' ); ?></p></div>
				<?php else : ?>
					<div class="panda-seasons-dashboard__cards">
						<?php foreach ( $calendars as $calendar ) :
							$calendar_id = absint( $calendar->ID );
							$cover_id = absint( get_post_meta( $calendar_id, '_ps_cover_desktop_id', true ) );
							$cover = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium_large' ) : '';
							$calendar_stats = $stats[ $calendar_id ] ?? array( 'clicks' => 0, 'subscribers' => 0, 'leads' => 0 );
						?>
						<article class="panda-seasons-dashboard-card">
							<a class="panda-seasons-dashboard-card__preview" href="<?php echo esc_url( get_edit_post_link( $calendar_id ) ); ?>">
								<?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Preview image for %s', 'panda-seasons' ), get_the_title( $calendar_id ) ) ); ?>"><?php else : ?><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php endif; ?>
							</a>
							<div class="panda-seasons-dashboard-card__body">
								<h3><a href="<?php echo esc_url( get_edit_post_link( $calendar_id ) ); ?>"><?php echo esc_html( get_the_title( $calendar_id ) ?: sprintf( __( 'Calendar #%d', 'panda-seasons' ), $calendar_id ) ); ?></a></h3>
								<div class="panda-seasons-dashboard-card__stats">
									<div><strong><?php echo esc_html( number_format_i18n( $calendar_stats['clicks'] ) ); ?></strong><span><?php esc_html_e( 'Door clicks', 'panda-seasons' ); ?></span></div>
									<div><strong><?php echo esc_html( number_format_i18n( $calendar_stats['subscribers'] ) ); ?></strong><span><?php esc_html_e( 'Email subscribers', 'panda-seasons' ); ?></span></div>
									<div><strong><?php echo esc_html( number_format_i18n( $calendar_stats['leads'] ) ); ?></strong><span><?php esc_html_e( 'Leads', 'panda-seasons' ); ?></span></div>
								</div>
								<p class="panda-seasons-dashboard-card__actions"><a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $calendar_id ) ); ?>"><?php esc_html_e( 'Edit', 'panda-seasons' ); ?></a><?php if ( 'publish' === get_post_status( $calendar_id ) ) : ?> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_preview&calendar=' . $calendar_id ), 'panda_seasons_preview_' . $calendar_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'panda-seasons' ); ?></a><?php endif; ?></p>
							</div>
						</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<section class="panda-seasons-dashboard__products">
				<div class="panda-seasons-dashboard-product<?php echo $contactpanda_active ? ' is-active' : ''; ?>">
					<h2><?php esc_html_e( 'ContactPanda Basic – free', 'panda-seasons' ); ?></h2>
					<?php if ( $contactpanda_active ) : ?><p><?php esc_html_e( 'ContactPanda Basic is active. Calendar registrations and newsletter functions are available.', 'panda-seasons' ); ?></p><span class="panda-seasons-dashboard-product__status"><?php esc_html_e( 'Active', 'panda-seasons' ); ?></span>
					<?php elseif ( $contactpanda_installed && $contactpanda_plugin ) : ?><p><?php esc_html_e( 'Activate the free ContactPanda Basic plugin to use calendar registrations, double opt-in and email delivery.', 'panda-seasons' ); ?></p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $contactpanda_plugin ) ), 'activate-plugin_' . $contactpanda_plugin ) ); ?>"><?php esc_html_e( 'Activate ContactPanda Basic', 'panda-seasons' ); ?></a>
					<?php else : ?><p><?php esc_html_e( 'Install ContactPanda Basic free of charge to add double opt-in, calendar email delivery, newsletter signups and lead management.', 'panda-seasons' ); ?></p><a class="button button-primary" href="https://mycontactpanda.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Download ContactPanda Basic free', 'panda-seasons' ); ?></a><?php endif; ?>
				</div>
				<div class="panda-seasons-dashboard-product">
					<h2><?php esc_html_e( 'More possibilities with ContactPanda Plus', 'panda-seasons' ); ?></h2>
					<p><?php esc_html_e( 'Expand your contact management with forms, automations and additional communication tools.', 'panda-seasons' ); ?></p>
					<a class="button" href="https://mycontactpanda.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Discover ContactPanda Plus', 'panda-seasons' ); ?></a>
				</div>
			</section>
		</div>
		<?php
	}

	private static function dashboard_statistics() {
		global $wpdb;
		$tables = Panda_Seasons_Schema::tables();
		$stats = array();
		$click_rows = $wpdb->get_results( "SELECT calendar_id,SUM(click_count) AS clicks FROM `{$tables['door_clicks']}` GROUP BY calendar_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
		foreach ( (array) $click_rows as $row ) $stats[ absint( $row['calendar_id'] ) ]['clicks'] = absint( $row['clicks'] );
		$subscription_rows = $wpdb->get_results( "SELECT calendar_id,COUNT(DISTINCT CASE WHEN status='active' THEN email_hash END) AS subscribers,COUNT(DISTINCT email_hash) AS leads FROM `{$tables['subscriptions']}` GROUP BY calendar_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
		foreach ( (array) $subscription_rows as $row ) {
			$id = absint( $row['calendar_id'] );
			$stats[ $id ]['subscribers'] = absint( $row['subscribers'] );
			$stats[ $id ]['leads'] = absint( $row['leads'] );
		}
		foreach ( $stats as &$row ) $row = wp_parse_args( $row, array( 'clicks' => 0, 'subscribers' => 0, 'leads' => 0 ) );
		unset( $row );
		return $stats;
	}

	public static function track_door_click() {
		if ( ! check_ajax_referer( 'panda_seasons_track_door_click', 'nonce', false ) ) wp_send_json_error( array( 'message' => __( 'The click could not be recorded.', 'panda-seasons' ) ), 403 );
		$calendar_id = absint( $_POST['calendar_id'] ?? 0 );
		$door_id = absint( $_POST['door_id'] ?? 0 );
		if ( ! $calendar_id || ! $door_id || self::CALENDAR_POST_TYPE !== get_post_type( $calendar_id ) || self::DOOR_POST_TYPE !== get_post_type( $door_id ) || $calendar_id !== absint( wp_get_post_parent_id( $door_id ) ) ) wp_send_json_error( array( 'message' => __( 'The click could not be recorded.', 'panda-seasons' ) ), 400 );
		if ( 'publish' !== get_post_status( $calendar_id ) || 'publish' !== get_post_status( $door_id ) ) wp_send_json_error( array( 'message' => __( 'The click could not be recorded.', 'panda-seasons' ) ), 400 );
		global $wpdb;
		$table = Panda_Seasons_Schema::tables()['door_clicks'];
		$date = current_time( 'Y-m-d' );
		$now = current_time( 'mysql' );
		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `{$table}` (calendar_id,door_id,click_date,click_count,updated_at) VALUES (%d,%d,%s,1,%s) ON DUPLICATE KEY UPDATE click_count=click_count+1,updated_at=VALUES(updated_at)", $calendar_id, $door_id, $date, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
		if ( false === $result ) wp_send_json_error( array( 'message' => __( 'The click could not be recorded.', 'panda-seasons' ) ), 500 );
		wp_send_json_success();
	}

	public static function templates_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to manage calendar templates.', 'panda-seasons' ) );
		$calendars = get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 250, 'orderby' => 'title', 'order' => 'ASC' ) );
		$library   = self::template_library();
		?>
		<div class="wrap panda-seasons-templates-page">
			<h1><?php esc_html_e( 'Calendar templates', 'panda-seasons' ); ?></h1>
			<?php if ( ! empty( $_GET['panda_seasons_imported'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The calendar content was imported successfully.', 'panda-seasons' ); ?></p></div><?php endif; ?>
			<?php if ( ! empty( $_GET['panda_seasons_error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['panda_seasons_error'] ) ) ); ?></p></div><?php endif; ?>
			<div class="panda-seasons-template-grid">
				<section class="card">
					<h2><?php esc_html_e( 'Export template', 'panda-seasons' ); ?></h2>
					<p><?php esc_html_e( 'Exports settings, doors, positions, content and media references. Installation-specific product, coupon and event assignments are not transferred.', 'panda-seasons' ); ?></p>
					<?php if ( $calendars ) : ?>
						<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="panda_seasons_export_template">
							<?php wp_nonce_field( 'panda_seasons_export_template', '_wpnonce', false ); ?>
							<label><span><?php esc_html_e( 'Calendar', 'panda-seasons' ); ?></span><select name="calendar_id" required><?php foreach ( $calendars as $calendar ) : ?><option value="<?php echo esc_attr( $calendar->ID ); ?>"><?php echo esc_html( $calendar->post_title ?: sprintf( __( 'Calendar #%d', 'panda-seasons' ), $calendar->ID ) ); ?></option><?php endforeach; ?></select></label>
							<label><span><?php esc_html_e( 'Template contents', 'panda-seasons' ); ?></span><select name="with_contents"><option value="1"><?php esc_html_e( 'Include door contents', 'panda-seasons' ); ?></option><option value="0"><?php esc_html_e( 'Layout and appearance only', 'panda-seasons' ); ?></option></select></label>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Download template', 'panda-seasons' ); ?></button></p>
						</form>
					<?php else : ?><p><?php esc_html_e( 'Create a calendar before exporting a template.', 'panda-seasons' ); ?></p><?php endif; ?>
				</section>
				<section class="card">
					<h2><?php esc_html_e( 'Import template', 'panda-seasons' ); ?></h2>
					<p><?php esc_html_e( 'Import into a new draft or add content to an existing calendar. Existing door positions and appearance remain unchanged when a target calendar is selected.', 'panda-seasons' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="panda_seasons_import_template">
						<?php wp_nonce_field( 'panda_seasons_import_template' ); ?>
						<label><span><?php esc_html_e( 'Template file', 'panda-seasons' ); ?></span><input type="file" name="panda_seasons_template" accept="application/json,.json" required></label>
						<label><span><?php esc_html_e( 'Import destination', 'panda-seasons' ); ?></span><select name="panda_seasons_target_calendar"><option value="0"><?php esc_html_e( 'Create a new calendar draft', 'panda-seasons' ); ?></option><?php foreach ( $calendars as $calendar ) : ?><option value="<?php echo esc_attr( $calendar->ID ); ?>"><?php echo esc_html( $calendar->post_title ?: sprintf( __( 'Calendar #%d', 'panda-seasons' ), $calendar->ID ) ); ?></option><?php endforeach; ?></select></label>
						<label><span><?php esc_html_e( 'Existing content', 'panda-seasons' ); ?></span><select name="panda_seasons_import_mode"><option value="empty"><?php esc_html_e( 'Fill empty doors only', 'panda-seasons' ); ?></option><option value="merge"><?php esc_html_e( 'Add imported values without clearing other fields', 'panda-seasons' ); ?></option><option value="overwrite"><?php esc_html_e( 'Replace all existing door content', 'panda-seasons' ); ?></option></select></label>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import content', 'panda-seasons' ); ?></button></p>
					</form>
				</section>
			</div>
				<section class="panda-seasons-template-library">
					<div class="panda-seasons-template-library__header">
						<div><h2><?php esc_html_e( 'Template library', 'panda-seasons' ); ?></h2><p><?php esc_html_e( 'Create a new draft calendar from a centrally maintained layout. Library templates never import door content or personal data.', 'panda-seasons' ); ?></p></div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="panda_seasons_refresh_template_library"><?php wp_nonce_field( 'panda_seasons_refresh_template_library' ); ?><button type="submit" class="button"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh library', 'panda-seasons' ); ?></button></form>
					</div>
					<?php if ( is_wp_error( $library ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( $library->get_error_message() ); ?></p></div>
					<?php elseif ( empty( $library['templates'] ) ) : ?><p><?php esc_html_e( 'No templates are currently available.', 'panda-seasons' ); ?></p>
					<?php else : ?><div class="panda-seasons-template-library__grid">
						<?php foreach ( $library['templates'] as $item ) : ?>
							<article class="panda-seasons-template-card">
								<?php if ( ! empty( $item['thumbnail'] ) ) : ?><a href="<?php echo esc_url( $item['thumbnail'] ); ?>" target="_blank" rel="noopener" class="panda-seasons-template-card__preview"><img src="<?php echo esc_url( $item['thumbnail'] ); ?>" alt=""></a><?php endif; ?>
								<div class="panda-seasons-template-card__body"><h3><?php echo esc_html( $item['title'] ); ?></h3><p><?php echo esc_html( $item['description'] ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="panda_seasons_import_library_template"><input type="hidden" name="template_id" value="<?php echo esc_attr( $item['id'] ); ?>"><?php wp_nonce_field( 'panda_seasons_import_library_template_' . $item['id'] ); ?><?php if ( ! empty( $item['has_images'] ) ) : ?><label class="panda-seasons-template-card__images"><input type="checkbox" name="with_images" value="1" checked> <?php esc_html_e( 'Import images', 'panda-seasons' ); ?></label><?php endif; ?><button type="submit" class="button button-primary"><?php esc_html_e( 'Create calendar', 'panda-seasons' ); ?></button></form></div>
							</article>
						<?php endforeach; ?>
					</div><?php endif; ?>
				</section>
		</div>
		<?php
	}

	public static function export_template() {
		$calendar_id = absint( $_GET['calendar_id'] ?? 0 );
		check_admin_referer( 'panda_seasons_export_template' );
		if ( ! $calendar_id || self::CALENDAR_POST_TYPE !== get_post_type( $calendar_id ) || ! current_user_can( 'edit_post', $calendar_id ) ) wp_die( esc_html__( 'The selected calendar cannot be exported.', 'panda-seasons' ) );
		$calendar = get_post( $calendar_id );
		$with_contents = ! isset( $_GET['with_contents'] ) || '0' !== (string) $_GET['with_contents'];
		$template = Panda_Seasons_Transfer::export( $calendar_id, $with_contents );
		if ( is_wp_error( $template ) ) wp_die( esc_html( $template->get_error_message() ) );
		$filename = sanitize_file_name( ( $calendar->post_title ?: 'panda-seasons' ) . '-template-v2.json' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function sanitize_template_value( $value ) {
		if ( is_array( $value ) ) return array_map( array( __CLASS__, 'sanitize_template_value' ), $value );
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) return $value;
		return wp_kses_post( (string) $value );
	}

	private static function import_template_media( $url, $post_id ) {
		$url = esc_url_raw( (string) $url );
		if ( ! $url ) return 0;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 20 );
		if ( is_wp_error( $tmp ) ) return 0;
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$file = array( 'name' => sanitize_file_name( basename( $path ) ?: 'panda-seasons-media' ), 'tmp_name' => $tmp );
		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) { @unlink( $tmp ); return 0; }
		return absint( $attachment_id );
	}

	private static function import_template_meta( $post_id, $meta, $media ) {
		$is_calendar = self::CALENDAR_POST_TYPE === get_post_type( $post_id );
		$allowed = Panda_Seasons_Contract::prefixed( $is_calendar ? Panda_Seasons_Contract::calendar_meta() : Panda_Seasons_Contract::door_meta() );
		$allowed_media = Panda_Seasons_Contract::prefixed( $is_calendar ? Panda_Seasons_Contract::calendar_media() : Panda_Seasons_Contract::door_media() );
		foreach ( (array) $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! in_array( $key, $allowed, true ) ) continue;
			if ( '_ps_design_layers' === $key && is_array( $value ) ) {
				foreach ( $value as &$layer ) {
					if ( ! is_array( $layer ) ) continue;
					$image_url = esc_url_raw( (string) ( $layer['imageUrl'] ?? '' ) );
					$layer['imageId'] = $image_url ? self::import_template_media( $image_url, $post_id ) : 0;
				}
				unset( $layer );
			}
			update_post_meta( $post_id, $key, self::sanitize_template_value( $value ) );
		}
		foreach ( (array) $media as $key => $url ) {
			$key = sanitize_key( $key );
			if ( ! in_array( $key, $allowed_media, true ) ) continue;
			update_post_meta( $post_id, $key, self::import_template_media( $url, $post_id ) );
		}
	}

	public static function import_template() {
		check_admin_referer( 'panda_seasons_import_template' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to import calendar templates.', 'panda-seasons' ) );
		$redirect = admin_url( 'edit.php?post_type=' . self::CALENDAR_POST_TYPE . '&page=panda-seasons-templates' );
		$file = $_FILES['panda_seasons_template'] ?? array();
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || (int) ( $file['size'] ?? 0 ) > 5 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'panda_seasons_error', __( 'Please select a valid template file up to 5 MB.', 'panda-seasons' ), $redirect ) ); exit;
		}
		$template = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $template ) || 'panda-seasons-template' !== ( $template['format'] ?? '' ) || ! in_array( absint( $template['format_version'] ?? 0 ), array( 1, Panda_Seasons_Contract::TEMPLATE_VERSION ), true ) || empty( $template['calendar'] ) ) {
			wp_safe_redirect( add_query_arg( 'panda_seasons_error', __( 'The selected file is not a supported Panda Seasons template.', 'panda-seasons' ), $redirect ) ); exit;
		}
		$calendar_data = (array) $template['calendar'];
		$calendar_id = absint( $_POST['panda_seasons_target_calendar'] ?? 0 );
		$import_mode = sanitize_key( wp_unslash( $_POST['panda_seasons_import_mode'] ?? 'empty' ) );
		if ( ! in_array( $import_mode, array( 'empty', 'merge', 'overwrite' ), true ) ) $import_mode = 'empty';
		$existing_import = $calendar_id && self::CALENDAR_POST_TYPE === get_post_type( $calendar_id ) && current_user_can( 'edit_post', $calendar_id );
		if ( ! $existing_import ) {
			$calendar_id = wp_insert_post( wp_slash( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => 'draft', 'post_title' => sanitize_text_field( (string) ( $calendar_data['title'] ?? __( 'Imported calendar', 'panda-seasons' ) ) ), 'post_content' => wp_kses_post( (string) ( $calendar_data['content'] ?? '' ) ), 'post_excerpt' => sanitize_textarea_field( (string) ( $calendar_data['excerpt'] ?? '' ) ) ) ), true );
			if ( is_wp_error( $calendar_id ) ) { wp_safe_redirect( add_query_arg( 'panda_seasons_error', $calendar_id->get_error_message(), $redirect ) ); exit; }
			self::import_template_meta( $calendar_id, $calendar_data['meta'] ?? array(), $calendar_data['media'] ?? array() );
		}
		$existing_doors = array();
		if ( $existing_import ) foreach ( self::doors( $calendar_id, 'any' ) as $existing_door ) $existing_doors[ absint( get_post_meta( $existing_door->ID, '_ps_door_number', true ) ) ] = $existing_door;
		foreach ( (array) ( $template['doors'] ?? array() ) as $door_data ) {
			if ( ! is_array( $door_data ) ) continue;
			$door_number = absint( $door_data['meta']['_ps_door_number'] ?? 0 );
			$existing_door = $door_number && isset( $existing_doors[ $door_number ] ) ? $existing_doors[ $door_number ] : null;
			if ( $existing_door && 'empty' === $import_mode && self::door_has_content( $existing_door ) ) continue;
			$post_data = array( 'post_type' => self::DOOR_POST_TYPE, 'post_status' => 'publish', 'post_parent' => $calendar_id );
			if ( $existing_door ) {
				$post_data['ID'] = $existing_door->ID;
				if ( 'overwrite' === $import_mode || ! empty( $door_data['title'] ) ) $post_data['post_title'] = sanitize_text_field( (string) ( $door_data['title'] ?? '' ) );
				if ( 'overwrite' === $import_mode || ! empty( $door_data['content'] ) ) $post_data['post_content'] = wp_kses_post( (string) ( $door_data['content'] ?? '' ) );
				if ( 'overwrite' === $import_mode || ! empty( $door_data['excerpt'] ) ) $post_data['post_excerpt'] = sanitize_textarea_field( (string) ( $door_data['excerpt'] ?? '' ) );
			} else {
				$post_data['post_title'] = sanitize_text_field( (string) ( $door_data['title'] ?? __( 'Imported door', 'panda-seasons' ) ) );
				$post_data['post_content'] = wp_kses_post( (string) ( $door_data['content'] ?? '' ) );
				$post_data['post_excerpt'] = sanitize_textarea_field( (string) ( $door_data['excerpt'] ?? '' ) );
			}
			$door_id = wp_insert_post( wp_slash( $post_data ), true );
			if ( is_wp_error( $door_id ) ) continue;
			$content_meta = array_filter( (array) ( $door_data['meta'] ?? array() ), function( $key ) { return in_array( $key, self::content_meta_keys(), true ); }, ARRAY_FILTER_USE_KEY );
			if ( 'merge' === $import_mode ) $content_meta = array_filter( $content_meta, function( $value ) { return ! empty( $value ); } );
			$content_media = array_filter( (array) ( $door_data['media'] ?? array() ), function( $key ) { return in_array( $key, array( '_ps_media_id', '_ps_download_id' ), true ); }, ARRAY_FILTER_USE_KEY );
			if ( 'merge' === $import_mode ) $content_media = array_filter( $content_media );
			if ( ! $existing_import ) self::import_template_meta( $door_id, $door_data['meta'] ?? array(), $door_data['media'] ?? array() );
			else {
				if ( 'overwrite' === $import_mode ) foreach ( self::content_meta_keys() as $key ) delete_post_meta( $door_id, $key );
				self::import_template_meta( $door_id, $content_meta, $content_media );
			}
		}
		wp_safe_redirect( add_query_arg( 'panda_seasons_imported', 1, get_edit_post_link( $calendar_id, 'url' ) ) );
		exit;
	}

	private static function content_meta_keys() {
		return array( '_ps_content_heading', '_ps_content_type', '_ps_media_id', '_ps_download_id', '_ps_download_label', '_ps_button_label', '_ps_button_url', '_ps_product_id', '_ps_coupon_id', '_ps_event_id', '_ps_quiz_enabled', '_ps_quiz_questions', '_ps_quiz_success', '_ps_quiz_failure', '_ps_cta_mode', '_ps_cta_enabled', '_ps_cta_title', '_ps_cta_text', '_ps_cta_type', '_ps_cta_label', '_ps_cta_url', '_ps_cta_shortcode', '_ps_email_subject', '_ps_email_content' );
	}

	private static function door_has_content( $door ) {
		if ( trim( wp_strip_all_tags( $door->post_content ) ) ) return true;
		foreach ( self::content_meta_keys() as $key ) {
			if ( '_ps_content_type' === $key ) continue;
			if ( ! empty( get_post_meta( $door->ID, $key, true ) ) ) return true;
		}
		return false;
	}

	public static function calendar_design_box( $post ) {
		wp_nonce_field( 'panda_seasons_save_calendar', 'panda_seasons_calendar_nonce' );
		$desktop_id = absint( get_post_meta( $post->ID, '_ps_cover_desktop', true ) );
		$mobile_id  = absint( get_post_meta( $post->ID, '_ps_cover_mobile', true ) );
		$desktop    = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'medium' ) : '';
		$mobile     = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'medium' ) : '';
		$desktop_background = (string) get_post_meta( $post->ID, '_ps_cover_desktop_background', true ) ?: 'rgba(23, 32, 51, 1)';
		$mobile_background  = (string) get_post_meta( $post->ID, '_ps_cover_mobile_background', true ) ?: $desktop_background;
		$ratio        = (float) get_post_meta( $post->ID, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $post->ID, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$doors        = self::doors( $post->ID, 'any' );
		$editor_doors = array();
		$products     = self::product_options();
		$coupons      = self::coupon_options();
		$events       = self::event_options();
		$open_effect  = (string) get_post_meta( $post->ID, '_ps_open_effect', true ) ?: 'door-left';
		$effect_speed = (string) get_post_meta( $post->ID, '_ps_effect_speed', true ) ?: 'normal';
		$grid_size    = absint( get_post_meta( $post->ID, '_ps_grid_size', true ) );
		if ( ! in_array( $grid_size, array( 10, 20, 30 ), true ) ) $grid_size = 10;
		$default_closed_bg = (string) get_post_meta( $post->ID, '_ps_default_closed_background', true ) ?: 'rgba(99, 99, 99, 0.2)';
		$default_open_bg   = (string) get_post_meta( $post->ID, '_ps_default_open_background', true ) ?: 'rgba(51, 79, 232, 1)';
		$default_closed_image_overlay = (string) get_post_meta( $post->ID, '_ps_default_closed_image_overlay', true ) ?: 'rgba(0, 0, 0, 0)';
		$default_open_image_overlay   = (string) get_post_meta( $post->ID, '_ps_default_open_image_overlay', true ) ?: 'rgba(0, 0, 0, 0)';
		$default_closed_text = (string) get_post_meta( $post->ID, '_ps_default_closed_text_color', true ) ?: 'rgba(255, 255, 255, 1)';
		$default_open_text   = (string) get_post_meta( $post->ID, '_ps_default_open_text_color', true ) ?: 'rgba(255, 255, 255, 1)';
		$default_closed_border = (string) get_post_meta( $post->ID, '_ps_default_closed_border_color', true ) ?: 'rgba(255, 255, 255, 0.9)';
		$default_open_border   = (string) get_post_meta( $post->ID, '_ps_default_open_border_color', true ) ?: 'rgba(255, 255, 255, 0.9)';
		$default_closed_border_width = '' !== get_post_meta( $post->ID, '_ps_default_closed_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_closed_border_width', true ) ) : ( '' !== get_post_meta( $post->ID, '_ps_default_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_border_width', true ) ) : 2 );
		$default_open_border_width = '' !== get_post_meta( $post->ID, '_ps_default_open_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_open_border_width', true ) ) : ( '' !== get_post_meta( $post->ID, '_ps_default_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_border_width', true ) ) : 2 );
		$default_closed_label_bg = (string) get_post_meta( $post->ID, '_ps_default_closed_label_background', true ) ?: 'rgba(10, 18, 30, 0.76)';
		$default_open_label_bg = (string) get_post_meta( $post->ID, '_ps_default_open_label_background', true ) ?: 'rgba(10, 18, 30, 0.76)';
		$default_closed_label_border = (string) get_post_meta( $post->ID, '_ps_default_closed_label_border_color', true ) ?: 'rgba(255, 255, 255, 1)';
		$default_open_label_border = (string) get_post_meta( $post->ID, '_ps_default_open_label_border_color', true ) ?: 'rgba(255, 255, 255, 1)';
		$default_closed_label_border_width = '' !== get_post_meta( $post->ID, '_ps_default_closed_label_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_closed_label_border_width', true ) ) : 1;
		$default_open_label_border_width = '' !== get_post_meta( $post->ID, '_ps_default_open_label_border_width', true ) ? absint( get_post_meta( $post->ID, '_ps_default_open_label_border_width', true ) ) : 1;
		$default_closed_label_shape = (string) get_post_meta( $post->ID, '_ps_default_closed_label_shape', true ) ?: 'rounded';
		$default_open_label_shape = (string) get_post_meta( $post->ID, '_ps_default_open_label_shape', true ) ?: 'rounded';
		$default_closed_label_position = self::sanitize_image_position( get_post_meta( $post->ID, '_ps_default_closed_label_position', true ) ?: 'center' );
		$default_open_label_position = self::sanitize_image_position( get_post_meta( $post->ID, '_ps_default_open_label_position', true ) ?: 'center' );
		$default_label_width_mode = (string) get_post_meta( $post->ID, '_ps_default_label_width_mode', true ) ?: 'content';
		$default_label_width = absint( get_post_meta( $post->ID, '_ps_default_label_width', true ) ) ?: 60;
		$default_label_padding = '' !== get_post_meta( $post->ID, '_ps_default_label_padding', true ) ? absint( get_post_meta( $post->ID, '_ps_default_label_padding', true ) ) : 4;
		$default_active_border = (string) get_post_meta( $post->ID, '_ps_default_active_border_color', true ) ?: 'rgba(255, 193, 7, 1)';
		$default_label_font = (string) get_post_meta( $post->ID, '_ps_default_label_font', true ) ?: 'inherit';
		$default_font_size = absint( get_post_meta( $post->ID, '_ps_default_label_font_size', true ) ) ?: 20;
		$default_font_weight = (string) get_post_meta( $post->ID, '_ps_default_label_font_weight', true ) ?: '700';
		$default_border_style = (string) get_post_meta( $post->ID, '_ps_default_border_style', true ) ?: 'dashed';
		$default_shadow_enabled = (bool) get_post_meta( $post->ID, '_ps_default_shadow_enabled', true );
		$default_shadow_blur = min( 80, absint( get_post_meta( $post->ID, '_ps_default_shadow_blur', true ) ) );
		$default_shadow_color = (string) get_post_meta( $post->ID, '_ps_default_shadow_color', true ) ?: 'rgba(0, 0, 0, 0.25)';
		$default_closed_label_visible = '0' !== (string) get_post_meta( $post->ID, '_ps_default_closed_label_visible', true );
		$default_open_label_visible = '0' !== (string) get_post_meta( $post->ID, '_ps_default_open_label_visible', true );
		$theme_colors      = self::theme_colors();
		$default_shape     = (string) get_post_meta( $post->ID, '_ps_default_shape', true ) ?: 'rounded';
		$default_opening_mode = (string) get_post_meta( $post->ID, '_ps_default_opening_mode', true ) ?: 'auto-permanent';
		$design_layers     = get_post_meta( $post->ID, '_ps_design_layers', true );
		if ( ! is_array( $design_layers ) ) $design_layers = array();
		foreach ( $design_layers as &$design_layer ) {
			if ( is_array( $design_layer ) ) {
				if ( in_array( $design_layer['type'] ?? '', array( 'image', 'color' ), true ) ) $design_layer['type'] = 'surface';
				$design_layer['imageUrl'] = wp_get_attachment_image_url( absint( $design_layer['imageId'] ?? 0 ), 'full' ) ?: '';
				$design_layer['imagePosition'] = self::sanitize_image_position( $design_layer['imagePosition'] ?? 'center' );
			}
		}
		unset( $design_layer );
		$popup_background  = (string) get_post_meta( $post->ID, '_ps_popup_background', true ) ?: 'rgba(255, 255, 255, 1)';
		$popup_text_color  = (string) get_post_meta( $post->ID, '_ps_popup_text_color', true ) ?: 'rgba(31, 41, 55, 1)';
		$popup_border_color = (string) get_post_meta( $post->ID, '_ps_popup_border_color', true ) ?: 'rgba(216, 221, 231, 1)';
		$popup_border_width = absint( get_post_meta( $post->ID, '_ps_popup_border_width', true ) );
		$popup_radius      = '' !== get_post_meta( $post->ID, '_ps_popup_radius', true ) ? absint( get_post_meta( $post->ID, '_ps_popup_radius', true ) ) : 16;
		$popup_padding     = '' !== get_post_meta( $post->ID, '_ps_popup_padding', true ) ? absint( get_post_meta( $post->ID, '_ps_popup_padding', true ) ) : 32;
		$popup_width       = absint( get_post_meta( $post->ID, '_ps_popup_width', true ) ) ?: 720;
		$default_cta_enabled = (bool) get_post_meta( $post->ID, '_ps_default_cta_enabled', true );
		$default_cta_title = (string) get_post_meta( $post->ID, '_ps_default_cta_title', true );
		$default_cta_text = (string) get_post_meta( $post->ID, '_ps_default_cta_text', true );
		$default_cta_type = (string) get_post_meta( $post->ID, '_ps_default_cta_type', true ) ?: 'link';
		$default_cta_label = (string) get_post_meta( $post->ID, '_ps_default_cta_label', true );
		$default_cta_url = (string) get_post_meta( $post->ID, '_ps_default_cta_url', true );
		$default_cta_shortcode = (string) get_post_meta( $post->ID, '_ps_default_cta_shortcode', true );

		$show_email_content = self::contactpanda_newsletter_available();
		foreach ( $doors as $index => $door ) {
			$quiz_questions = get_post_meta( $door->ID, '_ps_quiz_questions', true );
			if ( ! is_array( $quiz_questions ) ) $quiz_questions = array();
			$opening_mode = (string) get_post_meta( $door->ID, '_ps_opening_mode', true ) ?: 'auto-permanent';
			$editor_doors[] = array(
				'id'            => $door->ID,
				'title'         => $door->post_title,
				'content'       => $door->post_content,
				'contentType'   => (string) get_post_meta( $door->ID, '_ps_content_type', true ) ?: 'general',
				'contentHeading'=> (string) get_post_meta( $door->ID, '_ps_content_heading', true ),
				'number'        => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 ),
				'unlockDate'    => (string) get_post_meta( $door->ID, '_ps_unlock_date', true ),
				'unlockTime'    => (string) get_post_meta( $door->ID, '_ps_unlock_time', true ),
				'openingMode'   => $opening_mode,
				'emailSubject'  => (string) get_post_meta( $door->ID, '_ps_email_subject', true ),
				'emailContent'  => (string) get_post_meta( $door->ID, '_ps_email_content', true ),
				'mediaId'       => absint( get_post_meta( $door->ID, '_ps_media_id', true ) ),
				'mediaUrl'      => wp_get_attachment_url( absint( get_post_meta( $door->ID, '_ps_media_id', true ) ) ) ?: '',
				'mediaType'     => self::attachment_kind( absint( get_post_meta( $door->ID, '_ps_media_id', true ) ) ),
				'downloadId'    => absint( get_post_meta( $door->ID, '_ps_download_id', true ) ),
				'downloadUrl'   => wp_get_attachment_url( absint( get_post_meta( $door->ID, '_ps_download_id', true ) ) ) ?: '',
				'downloadLabel' => (string) get_post_meta( $door->ID, '_ps_download_label', true ),
				'buttonLabel'   => (string) get_post_meta( $door->ID, '_ps_button_label', true ),
				'buttonUrl'     => (string) get_post_meta( $door->ID, '_ps_button_url', true ),
				'productId'     => absint( get_post_meta( $door->ID, '_ps_product_id', true ) ),
				'couponId'      => absint( get_post_meta( $door->ID, '_ps_coupon_id', true ) ),
				'ctaMode'       => (string) get_post_meta( $door->ID, '_ps_cta_mode', true ) ?: 'custom',
				'ctaEnabled'    => (bool) get_post_meta( $door->ID, '_ps_cta_enabled', true ),
				'ctaTitle'      => (string) get_post_meta( $door->ID, '_ps_cta_title', true ),
				'ctaText'       => (string) get_post_meta( $door->ID, '_ps_cta_text', true ),
				'ctaType'       => (string) get_post_meta( $door->ID, '_ps_cta_type', true ) ?: 'link',
				'ctaLabel'      => (string) get_post_meta( $door->ID, '_ps_cta_label', true ),
				'ctaUrl'        => (string) get_post_meta( $door->ID, '_ps_cta_url', true ),
				'ctaShortcode'  => (string) get_post_meta( $door->ID, '_ps_cta_shortcode', true ),
				'eventId'       => absint( get_post_meta( $door->ID, '_ps_event_id', true ) ),
				'closedLabel'   => (string) get_post_meta( $door->ID, '_ps_closed_label', true ),
				'openLabel'     => (string) get_post_meta( $door->ID, '_ps_open_label', true ),
				'closedBg'      => self::editor_background_color( $door->ID, 'closed', $default_closed_bg ),
				'closedColor'   => (string) get_post_meta( $door->ID, '_ps_closed_color', true ),
				'closedLabelVisible' => '' === get_post_meta( $door->ID, '_ps_closed_label_visible', true ) || (bool) get_post_meta( $door->ID, '_ps_closed_label_visible', true ),
				'closedFont'    => (string) get_post_meta( $door->ID, '_ps_closed_font', true ) ?: 'inherit',
				'closedFontSize'=> absint( get_post_meta( $door->ID, '_ps_closed_font_size', true ) ) ?: 20,
				'closedFontWeight' => (string) get_post_meta( $door->ID, '_ps_closed_font_weight', true ) ?: '700',
				'closedTextAlign' => (string) get_post_meta( $door->ID, '_ps_closed_text_align', true ) ?: 'center',
				'closedBorderColor' => (string) get_post_meta( $door->ID, '_ps_closed_border_color', true ) ?: 'rgba(255, 255, 255, 0.9)',
				'closedBorderWidth' => '' !== get_post_meta( $door->ID, '_ps_closed_border_width', true ) ? absint( get_post_meta( $door->ID, '_ps_closed_border_width', true ) ) : 2,
				'closedBorderStyle' => (string) get_post_meta( $door->ID, '_ps_closed_border_style', true ) ?: 'dashed',
				'closedLabelBg' => (string) get_post_meta( $door->ID, '_ps_closed_label_background', true ) ?: $default_closed_label_bg,
				'closedLabelBorderColor' => (string) get_post_meta( $door->ID, '_ps_closed_label_border_color', true ) ?: $default_closed_label_border,
				'closedLabelBorderWidth' => '' !== get_post_meta( $door->ID, '_ps_closed_label_border_width', true ) ? absint( get_post_meta( $door->ID, '_ps_closed_label_border_width', true ) ) : 1,
				'closedLabelWidthMode' => (string) get_post_meta( $door->ID, '_ps_closed_label_width_mode', true ) ?: 'content',
				'closedLabelShape' => (string) get_post_meta( $door->ID, '_ps_closed_label_shape', true ) ?: 'rounded',
				'closedLabelPosition' => self::sanitize_image_position( get_post_meta( $door->ID, '_ps_closed_label_position', true ) ?: $default_closed_label_position ),
				'closedLabelWidth' => absint( get_post_meta( $door->ID, '_ps_closed_label_width', true ) ) ?: 60,
				'closedLabelPadding' => '' !== get_post_meta( $door->ID, '_ps_closed_label_padding', true ) ? absint( get_post_meta( $door->ID, '_ps_closed_label_padding', true ) ) : 4,
				'openBg'        => self::editor_background_color( $door->ID, 'open', $default_open_bg ),
				'openColor'     => (string) get_post_meta( $door->ID, '_ps_open_color', true ),
				'openLabelVisible' => '' === get_post_meta( $door->ID, '_ps_open_label_visible', true ) || (bool) get_post_meta( $door->ID, '_ps_open_label_visible', true ),
				'openFont'      => (string) get_post_meta( $door->ID, '_ps_open_font', true ) ?: 'inherit',
				'openFontSize'  => absint( get_post_meta( $door->ID, '_ps_open_font_size', true ) ) ?: 20,
				'openFontWeight'=> (string) get_post_meta( $door->ID, '_ps_open_font_weight', true ) ?: '700',
				'openTextAlign' => (string) get_post_meta( $door->ID, '_ps_open_text_align', true ) ?: 'center',
				'openBorderColor' => (string) get_post_meta( $door->ID, '_ps_open_border_color', true ) ?: 'rgba(255, 255, 255, 0.9)',
				'openBorderWidth' => '' !== get_post_meta( $door->ID, '_ps_open_border_width', true ) ? absint( get_post_meta( $door->ID, '_ps_open_border_width', true ) ) : 2,
				'openBorderStyle' => (string) get_post_meta( $door->ID, '_ps_open_border_style', true ) ?: 'dashed',
				'openLabelBg' => (string) get_post_meta( $door->ID, '_ps_open_label_background', true ) ?: $default_open_label_bg,
				'openLabelBorderColor' => (string) get_post_meta( $door->ID, '_ps_open_label_border_color', true ) ?: $default_open_label_border,
				'openLabelBorderWidth' => '' !== get_post_meta( $door->ID, '_ps_open_label_border_width', true ) ? absint( get_post_meta( $door->ID, '_ps_open_label_border_width', true ) ) : 1,
				'openLabelWidthMode' => (string) get_post_meta( $door->ID, '_ps_open_label_width_mode', true ) ?: 'content',
				'openLabelShape' => (string) get_post_meta( $door->ID, '_ps_open_label_shape', true ) ?: 'rounded',
				'openLabelPosition' => self::sanitize_image_position( get_post_meta( $door->ID, '_ps_open_label_position', true ) ?: $default_open_label_position ),
				'openLabelWidth' => absint( get_post_meta( $door->ID, '_ps_open_label_width', true ) ) ?: 60,
				'openLabelPadding' => '' !== get_post_meta( $door->ID, '_ps_open_label_padding', true ) ? absint( get_post_meta( $door->ID, '_ps_open_label_padding', true ) ) : 4,
				'shape'         => (string) get_post_meta( $door->ID, '_ps_shape', true ) ?: 'rounded',
				'imageId'       => absint( get_post_meta( $door->ID, '_ps_door_image_id', true ) ),
				'imageUrl'      => wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_door_image_id', true ) ), 'medium' ) ?: '',
				'imagePosition' => self::sanitize_image_position( get_post_meta( $door->ID, '_ps_door_image_position', true ) ?: 'center' ),
				'closedImageOverlay' => (string) get_post_meta( $door->ID, '_ps_closed_image_overlay', true ) ?: $default_closed_image_overlay,
				'openImageOverlay' => (string) get_post_meta( $door->ID, '_ps_open_image_overlay', true ) ?: $default_open_image_overlay,
				'quizEnabled'   => (bool) get_post_meta( $door->ID, '_ps_quiz_enabled', true ),
				'quizQuestions' => $quiz_questions,
				'quizSuccess'   => (string) get_post_meta( $door->ID, '_ps_quiz_success', true ),
				'quizFailure'   => (string) get_post_meta( $door->ID, '_ps_quiz_failure', true ),
				'openEffect'    => (string) get_post_meta( $door->ID, '_ps_open_effect', true ) ?: 'inherit',
				'shadowEnabled' => (bool) get_post_meta( $door->ID, '_ps_shadow_enabled', true ),
				'shadowBlur'    => min( 80, absint( get_post_meta( $door->ID, '_ps_shadow_blur', true ) ) ),
				'shadowColor'   => (string) get_post_meta( $door->ID, '_ps_shadow_color', true ) ?: 'rgba(0, 0, 0, 0.25)',
				'desktop'       => self::door_position( $door->ID, 'desktop', $index, count( $doors ) ),
				'mobile'        => self::door_position( $door->ID, 'mobile', $index, count( $doors ) ),
			);
		}
		?>
		<div class="panda-seasons-shortcodes-top"><?php self::calendar_shortcode_box( $post ); ?></div>
		<div class="panda-seasons-composer__primary-actions panda-seasons-composer__primary-actions--global"><span class="panda-seasons-composer__history-actions"><button type="button" class="button" data-editor-undo disabled title="<?php esc_attr_e( 'Undo', 'panda-seasons' ); ?>"><span class="dashicons dashicons-undo" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Undo', 'panda-seasons' ); ?></span></button><button type="button" class="button" data-editor-redo disabled title="<?php esc_attr_e( 'Redo', 'panda-seasons' ); ?>"><span class="dashicons dashicons-redo" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Redo', 'panda-seasons' ); ?></span></button></span><span class="panda-seasons-composer__save-actions"><button type="button" class="button" data-open-template-dialog><span class="dashicons dashicons-layout" aria-hidden="true"></span><?php esc_html_e( 'Save as template', 'panda-seasons' ); ?></button><button type="button" class="button button-secondary panda-seasons-interactive-preview" data-open-calendar-preview data-preview-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_preview&calendar=' . $post->ID ), 'panda_seasons_preview_' . $post->ID ) ); ?>"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'Preview', 'panda-seasons' ); ?></button><button type="button" class="button button-primary" data-save-calendar><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Save calendar', 'panda-seasons' ); ?></button></span></div>
		<p class="panda-seasons-editor-intro"><?php esc_html_e( 'Choose the calendar image and place every door directly on it. Click a door to edit its date and content.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-settings-layout">
			<div class="panda-seasons-cover-settings is-wide">
				<div class="panda-seasons-cover-settings__row">
					<?php self::media_field( 'panda_seasons_cover_desktop', __( 'Desktop cover image', 'panda-seasons' ), $desktop_id, $desktop ); ?>
						<div class="panda-seasons-cover-settings__controls"><label><span><?php esc_html_e( 'Canvas format', 'panda-seasons' ); ?></span><select data-aspect-preset="desktop"><option value="custom"><?php esc_html_e( 'Custom', 'panda-seasons' ); ?></option><option value="1.777778">16:9</option><option value="1.5">3:2</option><option value="1.333333">4:3</option><option value="1">1:1</option><option value="0.75">3:4</option><option value="0.5625">9:16</option></select></label><div class="panda-seasons-aspect-dimensions"><label><span><?php esc_html_e( 'Width', 'panda-seasons' ); ?></span><input type="number" min="1" max="9999" step="1" value="1500" data-aspect-width="desktop"></label><label><span><?php esc_html_e( 'Height', 'panda-seasons' ); ?></span><input type="number" min="1" max="9999" step="1" value="1000" data-aspect-height="desktop"></label></div><input type="hidden" name="panda_seasons_aspect_ratio" value="<?php echo esc_attr( $ratio ); ?>" data-editor-ratio="desktop"><label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_cover_desktop_background" value="<?php echo esc_attr( $desktop_background ); ?>" data-cover-background="desktop"></label></div>
				</div>
				<div class="panda-seasons-cover-settings__row">
					<?php self::media_field( 'panda_seasons_cover_mobile', __( 'Mobile cover image', 'panda-seasons' ), $mobile_id, $mobile ); ?>
						<div class="panda-seasons-cover-settings__controls"><label><span><?php esc_html_e( 'Canvas format', 'panda-seasons' ); ?></span><select data-aspect-preset="mobile"><option value="custom"><?php esc_html_e( 'Custom', 'panda-seasons' ); ?></option><option value="1.777778">16:9</option><option value="1.5">3:2</option><option value="1.333333">4:3</option><option value="1">1:1</option><option value="0.75">3:4</option><option value="0.5625">9:16</option></select></label><div class="panda-seasons-aspect-dimensions"><label><span><?php esc_html_e( 'Width', 'panda-seasons' ); ?></span><input type="number" min="1" max="9999" step="1" value="750" data-aspect-width="mobile"></label><label><span><?php esc_html_e( 'Height', 'panda-seasons' ); ?></span><input type="number" min="1" max="9999" step="1" value="1000" data-aspect-height="mobile"></label></div><input type="hidden" name="panda_seasons_mobile_aspect_ratio" value="<?php echo esc_attr( $mobile_ratio ); ?>" data-editor-ratio="mobile"><label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_cover_mobile_background" value="<?php echo esc_attr( $mobile_background ); ?>" data-cover-background="mobile"></label></div>
				</div>
				<button type="submit" name="save" value="<?php esc_attr_e( 'Save images and backgrounds', 'panda-seasons' ); ?>" class="button panda-seasons-refresh-images" data-refresh-calendar-images><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Save images and backgrounds', 'panda-seasons' ); ?></button>
			</div>
			<section class="is-wide panda-seasons-default-colors">
				<div><h3><?php esc_html_e( 'Default settings for new doors', 'panda-seasons' ); ?></h3><p class="description"><?php esc_html_e( 'These settings are used for newly created doors. Existing doors keep their individual settings unless you explicitly apply the settings to all doors.', 'panda-seasons' ); ?></p></div>
				<div class="panda-seasons-default-general-grid">
					<div class="panda-seasons-default-general-row">
						<label><span><?php esc_html_e( 'Default door shape', 'panda-seasons' ); ?></span><select name="panda_seasons_default_shape" data-default-door-shape><option value="square" <?php selected( $default_shape, 'square' ); ?>><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="rounded" <?php selected( $default_shape, 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'panda-seasons' ); ?></option><option value="round" <?php selected( $default_shape, 'round' ); ?>><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Default opening mode', 'panda-seasons' ); ?></span><select name="panda_seasons_default_opening_mode" data-default-opening-mode><option value="manual-day" <?php selected( $default_opening_mode, 'manual-day' ); ?>><?php esc_html_e( 'Only on the release day – open manually', 'panda-seasons' ); ?></option><option value="auto-day" <?php selected( $default_opening_mode, 'auto-day' ); ?>><?php esc_html_e( 'Only on the release day – open automatically', 'panda-seasons' ); ?></option><option value="auto-permanent" <?php selected( $default_opening_mode, 'auto-permanent' ); ?>><?php esc_html_e( 'Open automatically and remain open', 'panda-seasons' ); ?></option></select></label>
					</div>
					<div class="panda-seasons-default-general-row">
						<label><span><?php esc_html_e( 'Door opening effect', 'panda-seasons' ); ?></span><select name="panda_seasons_open_effect"><option value="none" <?php selected( $open_effect, 'none' ); ?>><?php esc_html_e( 'No effect', 'panda-seasons' ); ?></option><option value="door-left" <?php selected( $open_effect, 'door-left' ); ?>><?php esc_html_e( 'Door opens to the left', 'panda-seasons' ); ?></option><option value="door-right" <?php selected( $open_effect, 'door-right' ); ?>><?php esc_html_e( 'Door opens to the right', 'panda-seasons' ); ?></option><option value="page" <?php selected( $open_effect, 'page' ); ?>><?php esc_html_e( 'Turn page', 'panda-seasons' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Animation speed', 'panda-seasons' ); ?></span><select name="panda_seasons_effect_speed"><option value="fast" <?php selected( $effect_speed, 'fast' ); ?>><?php esc_html_e( 'Fast', 'panda-seasons' ); ?></option><option value="normal" <?php selected( $effect_speed, 'normal' ); ?>><?php esc_html_e( 'Normal', 'panda-seasons' ); ?></option><option value="slow" <?php selected( $effect_speed, 'slow' ); ?>><?php esc_html_e( 'Slow', 'panda-seasons' ); ?></option></select></label>
					</div>
					<label class="is-wide panda-seasons-default-active-color"><span><?php esc_html_e( 'Active door border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_active_border_color" value="<?php echo esc_attr( $default_active_border ); ?>" data-default-door-setting="activeBorderColor"></label>
					<div class="is-wide panda-seasons-shadow-settings"><label class="panda-seasons-quiz-toggle"><input type="checkbox" name="panda_seasons_default_shadow_enabled" value="1" data-default-door-setting="shadowEnabled" <?php checked( $default_shadow_enabled ); ?>> <span><?php esc_html_e( 'Enable shadow', 'panda-seasons' ); ?></span></label><label><span><?php esc_html_e( 'Shadow width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="80" name="panda_seasons_default_shadow_blur" value="<?php echo esc_attr( $default_shadow_blur ); ?>" data-default-door-setting="shadowBlur"></label><label><span><?php esc_html_e( 'Shadow color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_shadow_color" value="<?php echo esc_attr( $default_shadow_color ); ?>" data-default-door-setting="shadowColor"></label></div>
				</div>
				<div class="panda-seasons-default-state-tabs" role="tablist"><button type="button" class="button button-primary" role="tab" aria-selected="true" data-default-state-tab="closed"><?php esc_html_e( 'Door closed', 'panda-seasons' ); ?></button><button type="button" class="button" role="tab" aria-selected="false" data-default-state-tab="open"><?php esc_html_e( 'Door opened', 'panda-seasons' ); ?></button></div>
				<div class="panda-seasons-default-state-panel" data-default-state-panel="closed">
					<label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input id="panda-seasons-default-closed" type="text" name="panda_seasons_default_closed_background" value="<?php echo esc_attr( $default_closed_bg ); ?>" data-default-door-color="closed"></label>
					<label><span><?php esc_html_e( 'Image color overlay', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_closed_image_overlay" value="<?php echo esc_attr( $default_closed_image_overlay ); ?>" data-default-door-setting="closedImageOverlay"></label>
					<h4 class="panda-seasons-label-heading"><?php esc_html_e( 'Label', 'panda-seasons' ); ?></h4>
					<label class="panda-seasons-quiz-toggle panda-seasons-label-toggle"><input type="checkbox" name="panda_seasons_default_closed_label_visible" value="1" data-default-door-setting="closedLabelVisible" <?php checked( $default_closed_label_visible ); ?>> <span><?php esc_html_e( 'Show label when closed by default', 'panda-seasons' ); ?></span></label>
					<label><span><?php esc_html_e( 'Font color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_closed_text_color" value="<?php echo esc_attr( $default_closed_text ); ?>" data-default-door-setting="closedColor"></label>
					<div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_closed_border_color" value="<?php echo esc_attr( $default_closed_border ); ?>" data-default-door-setting="closedBorderColor"></label><label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_default_closed_border_width" value="<?php echo esc_attr( $default_closed_border_width ); ?>" data-default-door-setting="closedBorderWidth"></label></div>
					<label><span><?php esc_html_e( 'Label background shape', 'panda-seasons' ); ?></span><select name="panda_seasons_default_closed_label_shape" data-default-door-setting="closedLabelShape"><option value="rectangle" <?php selected( $default_closed_label_shape, 'rectangle' ); ?>><?php esc_html_e( 'Rectangle', 'panda-seasons' ); ?></option><option value="rounded" <?php selected( $default_closed_label_shape, 'rounded' ); ?>><?php esc_html_e( 'Rounded corners', 'panda-seasons' ); ?></option><option value="square" <?php selected( $default_closed_label_shape, 'square' ); ?>><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="round" <?php selected( $default_closed_label_shape, 'round' ); ?>><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Label background', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_closed_label_background" value="<?php echo esc_attr( $default_closed_label_bg ); ?>" data-default-door-setting="closedLabelBg"></label>
					<label><span><?php esc_html_e( 'Label position', 'panda-seasons' ); ?></span><select name="panda_seasons_default_closed_label_position" data-default-door-setting="closedLabelPosition"><?php foreach ( self::image_position_options() as $position_value => $position_label ) : ?><option value="<?php echo esc_attr( $position_value ); ?>" <?php selected( $default_closed_label_position, $position_value ); ?>><?php echo esc_html( $position_label ); ?></option><?php endforeach; ?></select></label>
					<div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Label border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_closed_label_border_color" value="<?php echo esc_attr( $default_closed_label_border ); ?>" data-default-door-setting="closedLabelBorderColor"></label><label><span><?php esc_html_e( 'Label border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_default_closed_label_border_width" value="<?php echo esc_attr( $default_closed_label_border_width ); ?>" data-default-door-setting="closedLabelBorderWidth"></label></div>
					<div class="panda-seasons-label-size-row"><label><span><?php esc_html_e( 'Width', 'panda-seasons' ); ?></span><select name="panda_seasons_default_label_width_mode" data-default-door-setting="labelWidthMode"><option value="content" <?php selected( $default_label_width_mode, 'content' ); ?>><?php esc_html_e( 'Automatic', 'panda-seasons' ); ?></option><option value="px" <?php selected( $default_label_width_mode, 'px' ); ?>><?php esc_html_e( 'Width in pixels', 'panda-seasons' ); ?></option><option value="percent" <?php selected( $default_label_width_mode, 'percent' ); ?>><?php esc_html_e( 'Width in percent', 'panda-seasons' ); ?></option></select></label><label><span data-label-size-caption><?php esc_html_e( 'Size', 'panda-seasons' ); ?></span><input type="number" min="10" max="300" name="panda_seasons_default_label_width" value="<?php echo esc_attr( $default_label_width ); ?>" data-default-door-setting="labelWidth"></label></div>
				</div>
				<div class="panda-seasons-default-state-panel" data-default-state-panel="open" hidden>
					<label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input id="panda-seasons-default-open" type="text" name="panda_seasons_default_open_background" value="<?php echo esc_attr( $default_open_bg ); ?>" data-default-door-color="open"></label>
					<label><span><?php esc_html_e( 'Image color overlay', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_open_image_overlay" value="<?php echo esc_attr( $default_open_image_overlay ); ?>" data-default-door-setting="openImageOverlay"></label>
					<h4 class="panda-seasons-label-heading"><?php esc_html_e( 'Label', 'panda-seasons' ); ?></h4>
					<label class="panda-seasons-quiz-toggle panda-seasons-label-toggle"><input type="checkbox" name="panda_seasons_default_open_label_visible" value="1" data-default-door-setting="openLabelVisible" <?php checked( $default_open_label_visible ); ?>> <span><?php esc_html_e( 'Show label when opened by default', 'panda-seasons' ); ?></span></label>
					<label><span><?php esc_html_e( 'Font color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_open_text_color" value="<?php echo esc_attr( $default_open_text ); ?>" data-default-door-setting="openColor"></label>
					<div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_open_border_color" value="<?php echo esc_attr( $default_open_border ); ?>" data-default-door-setting="openBorderColor"></label><label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_default_open_border_width" value="<?php echo esc_attr( $default_open_border_width ); ?>" data-default-door-setting="openBorderWidth"></label></div>
					<label><span><?php esc_html_e( 'Label background shape', 'panda-seasons' ); ?></span><select name="panda_seasons_default_open_label_shape" data-default-door-setting="openLabelShape"><option value="rectangle" <?php selected( $default_open_label_shape, 'rectangle' ); ?>><?php esc_html_e( 'Rectangle', 'panda-seasons' ); ?></option><option value="rounded" <?php selected( $default_open_label_shape, 'rounded' ); ?>><?php esc_html_e( 'Rounded corners', 'panda-seasons' ); ?></option><option value="square" <?php selected( $default_open_label_shape, 'square' ); ?>><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="round" <?php selected( $default_open_label_shape, 'round' ); ?>><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Label background', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_open_label_background" value="<?php echo esc_attr( $default_open_label_bg ); ?>" data-default-door-setting="openLabelBg"></label>
					<label><span><?php esc_html_e( 'Label position', 'panda-seasons' ); ?></span><select name="panda_seasons_default_open_label_position" data-default-door-setting="openLabelPosition"><?php foreach ( self::image_position_options() as $position_value => $position_label ) : ?><option value="<?php echo esc_attr( $position_value ); ?>" <?php selected( $default_open_label_position, $position_value ); ?>><?php echo esc_html( $position_label ); ?></option><?php endforeach; ?></select></label>
					<div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Label border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_open_label_border_color" value="<?php echo esc_attr( $default_open_label_border ); ?>" data-default-door-setting="openLabelBorderColor"></label><label><span><?php esc_html_e( 'Label border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_default_open_label_border_width" value="<?php echo esc_attr( $default_open_label_border_width ); ?>" data-default-door-setting="openLabelBorderWidth"></label></div>
					<p class="description is-wide"><?php esc_html_e( 'Label width and padding use the shared settings from the closed state.', 'panda-seasons' ); ?></p>
				</div>
				<div class="panda-seasons-default-typography-grid">
					<label><span><?php esc_html_e( 'Default label font', 'panda-seasons' ); ?></span><select data-default-door-setting="labelFont" name="panda_seasons_default_label_font"><option value="inherit" <?php selected( $default_label_font, 'inherit' ); ?>><?php esc_html_e( 'Theme font', 'panda-seasons' ); ?></option><option value="sans-serif" <?php selected( $default_label_font, 'sans-serif' ); ?>><?php esc_html_e( 'Sans serif', 'panda-seasons' ); ?></option><option value="serif" <?php selected( $default_label_font, 'serif' ); ?>><?php esc_html_e( 'Serif', 'panda-seasons' ); ?></option><option value="monospace" <?php selected( $default_label_font, 'monospace' ); ?>><?php esc_html_e( 'Monospace', 'panda-seasons' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Default font size (px)', 'panda-seasons' ); ?></span><input type="number" min="8" max="96" name="panda_seasons_default_label_font_size" value="<?php echo esc_attr( $default_font_size ); ?>" data-default-door-setting="labelFontSize"></label>
					<label><span><?php esc_html_e( 'Default font weight', 'panda-seasons' ); ?></span><select name="panda_seasons_default_label_font_weight" data-default-door-setting="labelFontWeight"><option value="400" <?php selected( $default_font_weight, '400' ); ?>><?php esc_html_e( 'Normal', 'panda-seasons' ); ?></option><option value="600" <?php selected( $default_font_weight, '600' ); ?>><?php esc_html_e( 'Semi-bold', 'panda-seasons' ); ?></option><option value="700" <?php selected( $default_font_weight, '700' ); ?>><?php esc_html_e( 'Bold', 'panda-seasons' ); ?></option><option value="800" <?php selected( $default_font_weight, '800' ); ?>><?php esc_html_e( 'Extra bold', 'panda-seasons' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Default border style', 'panda-seasons' ); ?></span><select name="panda_seasons_default_border_style" data-default-door-setting="borderStyle"><option value="none" <?php selected( $default_border_style, 'none' ); ?>><?php esc_html_e( 'No border', 'panda-seasons' ); ?></option><option value="solid" <?php selected( $default_border_style, 'solid' ); ?>><?php esc_html_e( 'Solid', 'panda-seasons' ); ?></option><option value="dashed" <?php selected( $default_border_style, 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'panda-seasons' ); ?></option><option value="dotted" <?php selected( $default_border_style, 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'panda-seasons' ); ?></option></select></label>
				</div>
				<?php if ( $theme_colors ) : ?><div class="is-wide panda-seasons-theme-colors"><strong><?php esc_html_e( 'Theme colors', 'panda-seasons' ); ?></strong><div><?php foreach ( $theme_colors as $color ) : ?><button type="button" data-theme-door-color="<?php echo esc_attr( $color['color'] ); ?>" title="<?php echo esc_attr( $color['name'] ); ?>" aria-label="<?php echo esc_attr( $color['name'] ); ?>" style="--ps-theme-color:<?php echo esc_attr( $color['color'] ); ?>"></button><?php endforeach; ?></div><small><?php esc_html_e( 'First select the closed or opened color field, then choose a theme color.', 'panda-seasons' ); ?></small></div><?php endif; ?>
				<section class="is-wide panda-seasons-default-cta">
					<h3><?php esc_html_e( 'Default call to action', 'panda-seasons' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Doors using the calendar defaults inherit these settings. Individual door settings remain unchanged.', 'panda-seasons' ); ?></p>
					<div class="panda-seasons-default-cta__grid">
						<label class="is-wide panda-seasons-quiz-toggle"><input type="checkbox" name="panda_seasons_default_cta_enabled" value="1" <?php checked( $default_cta_enabled ); ?>> <span><?php esc_html_e( 'Enable call to action', 'panda-seasons' ); ?></span></label>
						<label class="is-wide"><span><?php esc_html_e( 'CTA heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_cta_title" value="<?php echo esc_attr( $default_cta_title ); ?>"></label>
						<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'CTA text', 'panda-seasons' ); ?></strong><?php wp_editor( $default_cta_text, 'panda_seasons_default_cta_editor', array( 'textarea_name' => 'panda_seasons_default_cta_text', 'textarea_rows' => 4, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'block_formats' => 'Paragraph=p;Heading 3=h3;Heading 4=h4;Heading 5=h5', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div>
						<label><span><?php esc_html_e( 'CTA type', 'panda-seasons' ); ?></span><select name="panda_seasons_default_cta_type" data-default-cta-type><option value="link" <?php selected( $default_cta_type, 'link' ); ?>><?php esc_html_e( 'Link or button', 'panda-seasons' ); ?></option><option value="shortcode" <?php selected( $default_cta_type, 'shortcode' ); ?>><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></option><option value="contactpanda" <?php selected( $default_cta_type, 'contactpanda' ); disabled( ! self::contactpanda_newsletter_available() ); ?>><?php esc_html_e( 'ContactPanda newsletter form', 'panda-seasons' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Button text', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_cta_label" value="<?php echo esc_attr( $default_cta_label ); ?>"></label>
						<label class="is-wide" data-default-cta-field="link"><span><?php esc_html_e( 'Link URL', 'panda-seasons' ); ?></span><input type="url" name="panda_seasons_default_cta_url" value="<?php echo esc_attr( $default_cta_url ); ?>"></label>
						<label class="is-wide" data-default-cta-field="shortcode"><span><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_default_cta_shortcode" value="<?php echo esc_attr( $default_cta_shortcode ); ?>"></label>
					</div>
					<button type="submit" name="save" class="button button-primary panda-seasons-default-cta__save"><?php esc_html_e( 'Save settings', 'panda-seasons' ); ?></button>
				</section>
			</section>
			<section class="is-wide panda-seasons-popup-settings"><h3><?php esc_html_e( 'Door content popup', 'panda-seasons' ); ?></h3><p class="description"><?php esc_html_e( 'Style the content window that appears whenever an available door is clicked.', 'panda-seasons' ); ?></p><div class="panda-seasons-popup-settings__grid"><label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_popup_background" value="<?php echo esc_attr( $popup_background ); ?>"></label><label><span><?php esc_html_e( 'Text color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_popup_text_color" value="<?php echo esc_attr( $popup_text_color ); ?>"></label><label><span><?php esc_html_e( 'Border color', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_popup_border_color" value="<?php echo esc_attr( $popup_border_color ); ?>"></label><label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_popup_border_width" value="<?php echo esc_attr( $popup_border_width ); ?>"></label><label><span><?php esc_html_e( 'Corner radius (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="80" name="panda_seasons_popup_radius" value="<?php echo esc_attr( $popup_radius ); ?>"></label><label><span><?php esc_html_e( 'Inner spacing (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="100" name="panda_seasons_popup_padding" value="<?php echo esc_attr( $popup_padding ); ?>"></label><label><span><?php esc_html_e( 'Maximum width (px)', 'panda-seasons' ); ?></span><input type="number" min="320" max="1600" name="panda_seasons_popup_width" value="<?php echo esc_attr( $popup_width ); ?>"></label></div><button type="submit" name="save" class="button button-primary panda-seasons-popup-settings__save"><?php esc_html_e( 'Save settings', 'panda-seasons' ); ?></button></section>
		</div>
		<div class="panda-seasons-editor-layout">
			<div class="panda-seasons-composer" data-panda-seasons-composer data-view="desktop" data-preview-state="closed" data-default-closed="<?php echo esc_attr( $default_closed_bg ); ?>" data-default-open="<?php echo esc_attr( $default_open_bg ); ?>" data-default-closed-image-overlay="<?php echo esc_attr( $default_closed_image_overlay ); ?>" data-default-open-image-overlay="<?php echo esc_attr( $default_open_image_overlay ); ?>" data-default-closed-color="<?php echo esc_attr( $default_closed_text ); ?>" data-default-open-color="<?php echo esc_attr( $default_open_text ); ?>" data-default-closed-border-color="<?php echo esc_attr( $default_closed_border ); ?>" data-default-open-border-color="<?php echo esc_attr( $default_open_border ); ?>" data-default-closed-border-width="<?php echo esc_attr( $default_closed_border_width ); ?>" data-default-open-border-width="<?php echo esc_attr( $default_open_border_width ); ?>" data-default-closed-label-bg="<?php echo esc_attr( $default_closed_label_bg ); ?>" data-default-open-label-bg="<?php echo esc_attr( $default_open_label_bg ); ?>" data-default-closed-label-border-color="<?php echo esc_attr( $default_closed_label_border ); ?>" data-default-open-label-border-color="<?php echo esc_attr( $default_open_label_border ); ?>" data-default-closed-label-border-width="<?php echo esc_attr( $default_closed_label_border_width ); ?>" data-default-open-label-border-width="<?php echo esc_attr( $default_open_label_border_width ); ?>" data-default-label-width-mode="<?php echo esc_attr( $default_label_width_mode ); ?>" data-default-label-width="<?php echo esc_attr( $default_label_width ); ?>" data-default-label-padding="<?php echo esc_attr( $default_label_padding ); ?>" data-default-label-font="<?php echo esc_attr( $default_label_font ); ?>" data-default-label-font-size="<?php echo esc_attr( $default_font_size ); ?>" data-default-label-font-weight="<?php echo esc_attr( $default_font_weight ); ?>" data-default-border-style="<?php echo esc_attr( $default_border_style ); ?>" data-default-closed-label-visible="<?php echo $default_closed_label_visible ? '1' : '0'; ?>" data-default-open-label-visible="<?php echo $default_open_label_visible ? '1' : '0'; ?>" data-default-shape="<?php echo esc_attr( $default_shape ); ?>" data-default-shadow-enabled="<?php echo $default_shadow_enabled ? '1' : '0'; ?>" data-default-shadow-blur="<?php echo esc_attr( $default_shadow_blur ); ?>" data-default-shadow-color="<?php echo esc_attr( $default_shadow_color ); ?>">
			<div class="panda-seasons-composer__toolbar">
				<div class="panda-seasons-composer__toolbar-row panda-seasons-composer__toolbar-row--top">
				<div class="panda-seasons-composer__states" role="group" aria-label="<?php esc_attr_e( 'Door state preview', 'panda-seasons' ); ?>">
						<button type="button" class="button button-primary" data-preview-state="closed"><?php esc_html_e( 'All closed', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-preview-state="open"><?php esc_html_e( 'All open', 'panda-seasons' ); ?></button>
					<span class="panda-seasons-composer__toolbar-divider" aria-hidden="true"></span>
					<button type="button" class="button button-primary" data-editor-view="desktop"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><?php esc_html_e( 'Desktop', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-editor-view="mobile"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><?php esc_html_e( 'Mobile', 'panda-seasons' ); ?></button>
				</div>
				</div>
				<div class="panda-seasons-composer__toolbar-row panda-seasons-composer__toolbar-row--grid">
					<div class="panda-seasons-composer__grid-row"><label class="panda-seasons-composer__snap"><input type="checkbox" data-snap-grid checked> <?php esc_html_e( 'Align to grid', 'panda-seasons' ); ?></label><label class="panda-seasons-composer__grid-size"><span><?php esc_html_e( 'Grid size', 'panda-seasons' ); ?></span><select name="panda_seasons_grid_size" data-grid-size><option value="10" <?php selected( $grid_size, 10 ); ?>>10 px</option><option value="20" <?php selected( $grid_size, 20 ); ?>>20 px</option><option value="30" <?php selected( $grid_size, 30 ); ?>>30 px</option></select></label><button type="button" class="button" data-align-grid><?php esc_html_e( 'Align now', 'panda-seasons' ); ?></button></div>
				</div>
				<div class="panda-seasons-composer__toolbar-row panda-seasons-composer__toolbar-row--add"><div class="panda-seasons-composer__add-actions"><button type="button" class="button button-secondary" data-add-door><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 2h11v2H6v16h9v2H4V2zm5 3 10 2v12L9 21V5zm7 7.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg><?php esc_html_e( 'Add door', 'panda-seasons' ); ?></button><button type="button" class="button" data-add-layer="surface"><span class="dashicons dashicons-format-image"></span><?php esc_html_e( 'Design area', 'panda-seasons' ); ?></button><button type="button" class="button" data-add-layer="text"><span class="dashicons dashicons-editor-textcolor"></span><?php esc_html_e( 'Text area', 'panda-seasons' ); ?></button></div></div>
			</div>
			<div class="panda-seasons-calendar-save-state" data-calendar-save-state aria-live="polite"></div>
			<p class="description"><?php esc_html_e( 'Click Add door, Design area or Text area, or drag the corresponding button directly onto the calendar. Hold Shift while clicking to select several elements and move them together.', 'panda-seasons' ); ?></p>
			<div class="panda-seasons-composer__stage-wrap">
				<div class="panda-seasons-composer__stage" data-editor-stage style="--ps-editor-ratio:<?php echo esc_attr( $ratio ); ?>">
					<p class="panda-seasons-composer__empty" data-editor-empty><?php esc_html_e( 'Add a door to start designing your calendar.', 'panda-seasons' ); ?></p>
				</div>
			</div>
			<p class="description panda-seasons-composer__save-note"><?php esc_html_e( 'Door changes are stored when you update the calendar.', 'panda-seasons' ); ?></p>
			<textarea name="panda_seasons_door_layout" data-editor-data hidden><?php echo esc_textarea( wp_json_encode( $editor_doors ) ); ?></textarea>
			<textarea name="panda_seasons_design_layers" data-layer-data hidden><?php echo esc_textarea( wp_json_encode( $design_layers ) ); ?></textarea>
			<script type="application/json" data-editor-images><?php echo wp_json_encode( array( 'desktop' => $desktop, 'mobile' => $mobile ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		</div>

		<div class="panda-seasons-door-editor-empty" data-door-editor-empty-panel>
			<span class="dashicons dashicons-edit" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'Select a door', 'panda-seasons' ); ?></h2>
			<p><?php esc_html_e( 'Click a door in the calendar to edit its settings here. Changes are shown immediately in the preview.', 'panda-seasons' ); ?></p>
		</div>
		<aside class="panda-seasons-door-modal" data-door-modal hidden aria-labelledby="panda-seasons-door-modal-title">
			<div class="panda-seasons-door-modal__backdrop" data-close-door-modal></div>
			<div class="panda-seasons-door-modal__dialog">
				<div class="panda-seasons-door-modal__header">
					<h2 id="panda-seasons-door-modal-title"><?php esc_html_e( 'Edit door', 'panda-seasons' ); ?></h2>
					<button type="button" class="button-link panda-seasons-door-modal__close" data-close-door-modal aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="panda-seasons-door-modal__body">
					<div class="panda-seasons-door-modal__fields">
						<div class="is-wide panda-seasons-content-type" data-door-section="content-type"><label><span><?php esc_html_e( 'Content type', 'panda-seasons' ); ?></span><select data-door-field="contentType"><option value="general"><?php esc_html_e( 'General content', 'panda-seasons' ); ?></option><option value="video"><?php esc_html_e( 'Video', 'panda-seasons' ); ?></option><?php if ( $products ) : ?><option value="product"><?php esc_html_e( 'Product', 'panda-seasons' ); ?></option><?php endif; ?><option value="quiz"><?php esc_html_e( 'Quiz', 'panda-seasons' ); ?></option></select><small><?php esc_html_e( 'Only the fields for the selected content type are displayed. A call to action may be added independently.', 'panda-seasons' ); ?></small></label></div>
						<details class="is-wide panda-seasons-door-modal__section" open data-door-section="basics">
							<summary><?php esc_html_e( 'Door basics', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<div class="is-wide panda-seasons-door-modal__date-time"><label><span><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></span><input type="number" min="1" max="366" data-door-field="number"></label><label><span><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></span><input type="date" data-door-field="unlockDate"></label><label><span><?php esc_html_e( 'Unlock time', 'panda-seasons' ); ?></span><input type="time" data-door-field="unlockTime"></label></div>
								<label class="is-wide"><span><?php esc_html_e( 'Opening mode', 'panda-seasons' ); ?></span><select data-door-field="openingMode"><option value="manual-day"><?php esc_html_e( 'Only on the release day – open manually', 'panda-seasons' ); ?></option><option value="auto-day"><?php esc_html_e( 'Only on the release day – open automatically', 'panda-seasons' ); ?></option><option value="auto-permanent"><?php esc_html_e( 'Open automatically and remain open', 'panda-seasons' ); ?></option></select><small><?php esc_html_e( 'Defines whether the door can be opened only on its date or remains available afterwards.', 'panda-seasons' ); ?></small></label>
								<label class="is-wide"><span><?php esc_html_e( 'Title', 'panda-seasons' ); ?></span><input type="text" data-door-field="title"></label>
								<div class="is-wide panda-seasons-door-dimensions"><label><span><?php esc_html_e( 'Width (%)', 'panda-seasons' ); ?></span><input type="number" min="4" max="100" step="0.1" data-door-dimension="width"></label><label><span><?php esc_html_e( 'Height (%)', 'panda-seasons' ); ?></span><input type="number" min="4" max="100" step="0.1" data-door-dimension="height"></label><label class="panda-seasons-door-dimensions__lock"><input type="checkbox" data-door-aspect-lock> <span><?php esc_html_e( 'Keep aspect ratio', 'panda-seasons' ); ?></span></label><button type="button" class="button" data-apply-door-size-all><?php esc_html_e( 'Apply dimensions to all doors', 'panda-seasons' ); ?></button><small><?php esc_html_e( 'Dimensions apply separately to the current desktop or mobile layout.', 'panda-seasons' ); ?></small></div>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section" open data-content-type-panel="general video" data-door-section="content">
							<summary><?php esc_html_e( 'Door content and media', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label class="is-wide"><span><?php esc_html_e( 'Content heading', 'panda-seasons' ); ?></span><input type="text" data-door-field="contentHeading"><small><?php esc_html_e( 'Leave empty to use the release date.', 'panda-seasons' ); ?></small></label>
								<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'Text', 'panda-seasons' ); ?></strong><?php wp_editor( '', 'panda_seasons_door_content_editor', array( 'textarea_name' => 'panda_seasons_door_content_editor', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div>
									<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="media">
									<div class="panda-seasons-door-modal__asset-preview panda-seasons-door-modal__dropzone" data-door-asset-preview data-select-door-media role="button" tabindex="0"><span class="panda-seasons-door-modal__drop-hint"><?php esc_html_e( 'Drag an image, video or audio file here or select it from the media library.', 'panda-seasons' ); ?></span></div>
										<p class="description"><?php esc_html_e( 'Displayed together with the content in the central window after the door is opened.', 'panda-seasons' ); ?></p>
									<input type="hidden" data-door-field="mediaId"><input type="hidden" data-door-field="mediaUrl"><input type="hidden" data-door-field="mediaType">
									<button type="button" class="button-link-delete panda-seasons-door-asset-remove" data-remove-door-media><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button>
								</div>
								<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="download">
									<div class="panda-seasons-door-modal__asset-preview panda-seasons-door-download-field" data-door-asset-preview data-select-door-download role="button" tabindex="0"><span><?php esc_html_e( 'Select a download file', 'panda-seasons' ); ?></span><span class="dashicons dashicons-media-document" aria-hidden="true"></span></div>
									<input type="hidden" data-door-field="downloadId"><input type="hidden" data-door-field="downloadUrl">
									<button type="button" class="button-link-delete panda-seasons-door-asset-remove" data-remove-door-download><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button>
								</div>
								<label class="is-wide"><span><?php esc_html_e( 'Download link text', 'panda-seasons' ); ?></span><input type="text" data-door-field="downloadLabel" placeholder="<?php esc_attr_e( 'Download file', 'panda-seasons' ); ?>"></label>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section" data-content-type-panel="general" data-door-section="button">
							<summary><?php esc_html_e( 'Button and related content', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label><span><?php esc_html_e( 'Button text', 'panda-seasons' ); ?></span><input type="text" data-door-field="buttonLabel"></label>
								<label><span><?php esc_html_e( 'Button link', 'panda-seasons' ); ?></span><input type="url" data-door-field="buttonUrl" placeholder="https://"></label>
								<?php if ( $events ) : ?>
									<label class="is-wide"><span><?php esc_html_e( 'Event', 'panda-seasons' ); ?></span><select data-door-field="eventId"><option value=""><?php esc_html_e( 'No event', 'panda-seasons' ); ?></option><?php foreach ( $events as $event_id => $event_title ) : ?><option value="<?php echo esc_attr( $event_id ); ?>"><?php echo esc_html( $event_title ); ?></option><?php endforeach; ?></select></label>
								<?php endif; ?>
							</div>
						</details>

						<?php if ( $products ) : ?><details class="is-wide panda-seasons-door-modal__section" data-content-type-panel="product" data-door-section="product">
							<summary><?php esc_html_e( 'Product', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<?php if ( $products ) : ?>
									<label class="is-wide"><span><?php esc_html_e( 'WooCommerce product', 'panda-seasons' ); ?></span><select data-door-field="productId"><option value=""><?php esc_html_e( 'No product', 'panda-seasons' ); ?></option><?php foreach ( $products as $product_id => $product_title ) : ?><option value="<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_title ); ?></option><?php endforeach; ?></select></label>
					<label class="is-wide"><span><?php esc_html_e( 'WooCommerce coupon', 'panda-seasons' ); ?></span><select data-door-field="couponId"><option value=""><?php esc_html_e( 'No coupon', 'panda-seasons' ); ?></option><?php foreach ( $coupons as $coupon_id => $coupon_title ) : ?><option value="<?php echo esc_attr( $coupon_id ); ?>"><?php echo esc_html( $coupon_title ); ?></option><?php endforeach; ?></select></label>
								<?php endif; ?>
							</div>
						</details><?php endif; ?>
							<details class="is-wide panda-seasons-door-modal__section" data-door-section="cta">
								<summary><?php esc_html_e( 'Call to action', 'panda-seasons' ); ?></summary>
								<div class="panda-seasons-door-modal__section-grid">
								<label class="is-wide"><span><?php esc_html_e( 'CTA settings', 'panda-seasons' ); ?></span><select data-door-field="ctaMode"><option value="default"><?php esc_html_e( 'Use calendar defaults', 'panda-seasons' ); ?></option><option value="custom"><?php esc_html_e( 'Use individual settings', 'panda-seasons' ); ?></option></select></label>
								<div class="is-wide panda-seasons-cta-custom-fields" data-cta-custom-fields>
									<label class="is-wide panda-seasons-quiz-toggle"><input type="checkbox" data-door-field="ctaEnabled"> <span><?php esc_html_e( 'Enable call to action', 'panda-seasons' ); ?></span></label>
									<label class="is-wide"><span><?php esc_html_e( 'CTA heading', 'panda-seasons' ); ?></span><input type="text" data-door-field="ctaTitle"></label>
									<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'CTA text', 'panda-seasons' ); ?></strong><?php wp_editor( '', 'panda_seasons_door_cta_editor', array( 'textarea_name' => 'panda_seasons_door_cta_editor', 'textarea_rows' => 4, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'block_formats' => 'Paragraph=p;Heading 3=h3;Heading 4=h4;Heading 5=h5', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div>
									<div class="is-wide panda-seasons-cta-primary-row"><label><span><?php esc_html_e( 'CTA type', 'panda-seasons' ); ?></span><select data-door-field="ctaType"><option value="link"><?php esc_html_e( 'Link or button', 'panda-seasons' ); ?></option><option value="shortcode"><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></option><option value="contactpanda" <?php disabled( ! self::contactpanda_newsletter_available() ); ?>><?php esc_html_e( 'ContactPanda newsletter form', 'panda-seasons' ); ?></option></select></label><label><span><?php esc_html_e( 'Button text', 'panda-seasons' ); ?></span><input type="text" data-door-field="ctaLabel"></label></div>
									<label class="is-wide" data-cta-field="link"><span><?php esc_html_e( 'Link URL', 'panda-seasons' ); ?></span><input type="url" data-door-field="ctaUrl"></label>
									<label class="is-wide" data-cta-field="shortcode"><span><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></span><input type="text" data-door-field="ctaShortcode"><small><?php esc_html_e( 'Enter the shortcode of a form plugin, for example Contact Form 7, Gravity Forms or Mailchimp. The form is displayed in this door’s call-to-action area.', 'panda-seasons' ); ?></small></label>
								</div>
								</div>
							</details>

						<?php if ( $show_email_content ) : ?><details class="is-wide panda-seasons-door-modal__section" data-door-section="email">
							<summary><?php esc_html_e( 'Calendar email', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<p class="description is-wide"><?php esc_html_e( 'Visitors must register separately for calendar emails. This registration uses the compact ContactPanda form and does not grant newsletter consent.', 'panda-seasons' ); ?></p>
								<label class="is-wide"><span><?php esc_html_e( 'Email subject override', 'panda-seasons' ); ?></span><input type="text" data-door-field="emailSubject"></label>
								<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'Email content', 'panda-seasons' ); ?></strong><p class="description"><?php esc_html_e( 'Leave empty to use the regular door content.', 'panda-seasons' ); ?></p><?php wp_editor( '', 'panda_seasons_door_email_editor', array( 'textarea_name' => 'panda_seasons_door_email_editor', 'textarea_rows' => 8, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?></div>
							</div>
						</details><?php endif; ?>

						<details class="is-wide panda-seasons-door-modal__section" data-door-section="appearance">
							<summary><?php esc_html_e( 'Appearance before and after opening', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label><span><?php esc_html_e( 'Door shape', 'panda-seasons' ); ?></span><select data-door-field="shape"><option value="square"><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="rounded"><?php esc_html_e( 'Rounded', 'panda-seasons' ); ?></option><option value="round"><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label>
								<label><span><?php esc_html_e( 'Opening effect', 'panda-seasons' ); ?></span><select data-door-field="openEffect"><option value="inherit"><?php esc_html_e( 'Use calendar setting', 'panda-seasons' ); ?></option><option value="none"><?php esc_html_e( 'No effect', 'panda-seasons' ); ?></option><option value="door-left"><?php esc_html_e( 'Door opens to the left', 'panda-seasons' ); ?></option><option value="door-right"><?php esc_html_e( 'Door opens to the right', 'panda-seasons' ); ?></option><option value="page"><?php esc_html_e( 'Turn page', 'panda-seasons' ); ?></option></select></label>
								<div class="is-wide panda-seasons-door-modal__asset panda-seasons-door-background-image" data-door-asset="image"><strong><?php esc_html_e( 'Background image', 'panda-seasons' ); ?></strong><div class="panda-seasons-door-modal__asset-preview" data-door-asset-preview><span><?php esc_html_e( 'No image selected', 'panda-seasons' ); ?></span></div><input type="hidden" data-door-field="imageId"><input type="hidden" data-door-field="imageUrl"><div class="panda-seasons-door-image-actions"><button type="button" class="button" data-select-door-image="image"><?php esc_html_e( 'Select image', 'panda-seasons' ); ?></button> <button type="button" class="button-link-delete" data-remove-door-image="image"><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button></div><label><span><?php esc_html_e( 'Image position', 'panda-seasons' ); ?></span><select data-door-field="imagePosition"><?php foreach ( self::image_position_options() as $position_value => $position_label ) : ?><option value="<?php echo esc_attr( $position_value ); ?>"><?php echo esc_html( $position_label ); ?></option><?php endforeach; ?></select></label></div>
								<div class="is-wide panda-seasons-shadow-settings"><label class="panda-seasons-quiz-toggle"><input type="checkbox" data-door-field="shadowEnabled"> <span><?php esc_html_e( 'Enable shadow', 'panda-seasons' ); ?></span></label><label><span><?php esc_html_e( 'Shadow width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="80" data-door-field="shadowBlur"></label><label><span><?php esc_html_e( 'Shadow color', 'panda-seasons' ); ?></span><input type="text" data-door-field="shadowColor"></label></div>
								<div class="is-wide panda-seasons-door-state-tabs"><button type="button" class="button button-primary" data-door-state-tab="closed"><?php esc_html_e( 'Door closed', 'panda-seasons' ); ?></button><button type="button" class="button" data-door-state-tab="open"><?php esc_html_e( 'Door opened', 'panda-seasons' ); ?></button></div>
				<?php foreach ( array( 'closed' => __( 'Door closed', 'panda-seasons' ), 'open' => __( 'Door opened', 'panda-seasons' ) ) as $state => $state_label ) : ?>
				<div class="is-wide panda-seasons-door-state-panel" data-door-state-panel="<?php echo esc_attr( $state ); ?>"<?php echo 'open' === $state ? ' hidden' : ''; ?>>
					<div class="is-wide panda-seasons-door-frame-row">
						<label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>Bg" placeholder="rgba(31, 41, 55, 1)"></label>
						<label><span><?php esc_html_e( 'Border color', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>BorderColor" value="rgba(255, 255, 255, 0.9)"></label>
						<label><span><?php esc_html_e( 'Border style', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>BorderStyle"><option value="none"><?php esc_html_e( 'No border', 'panda-seasons' ); ?></option><option value="solid"><?php esc_html_e( 'Solid', 'panda-seasons' ); ?></option><option value="dashed"><?php esc_html_e( 'Dashed', 'panda-seasons' ); ?></option><option value="dotted"><?php esc_html_e( 'Dotted', 'panda-seasons' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" data-door-field="<?php echo esc_attr( $state ); ?>BorderWidth"></label>
					</div>
					<h3 class="is-wide panda-seasons-label-heading"><?php esc_html_e( 'Label', 'panda-seasons' ); ?></h3>
					<label class="is-wide panda-seasons-quiz-toggle panda-seasons-label-toggle"><input type="checkbox" data-door-field="<?php echo esc_attr( $state ); ?>LabelVisible"> <span><?php echo esc_html( 'closed' === $state ? __( 'Show label when closed', 'panda-seasons' ) : __( 'Show label when opened', 'panda-seasons' ) ); ?></span></label>
					<label class="is-wide"><span><?php esc_html_e( 'Door label', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>Label" placeholder="<?php esc_attr_e( 'Door number', 'panda-seasons' ); ?>"><small><?php esc_html_e( 'This text is shown on the door. Leave it empty to display the door number automatically. You may use a different label for the closed and opened state.', 'panda-seasons' ); ?></small></label>
					<div class="is-wide panda-seasons-label-typography-row"><label><span><?php esc_html_e( 'Label font', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>Font"><option value="inherit"><?php esc_html_e( 'Theme font', 'panda-seasons' ); ?></option><option value="sans-serif"><?php esc_html_e( 'Sans serif', 'panda-seasons' ); ?></option><option value="serif"><?php esc_html_e( 'Serif', 'panda-seasons' ); ?></option><option value="monospace"><?php esc_html_e( 'Monospace', 'panda-seasons' ); ?></option></select></label><label><span><?php esc_html_e( 'Font size (px)', 'panda-seasons' ); ?></span><input type="number" min="8" max="96" data-door-field="<?php echo esc_attr( $state ); ?>FontSize"></label><label><span><?php esc_html_e( 'Font weight', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>FontWeight"><option value="400"><?php esc_html_e( 'Normal', 'panda-seasons' ); ?></option><option value="600"><?php esc_html_e( 'Semi-bold', 'panda-seasons' ); ?></option><option value="700"><?php esc_html_e( 'Bold', 'panda-seasons' ); ?></option><option value="800"><?php esc_html_e( 'Extra bold', 'panda-seasons' ); ?></option></select></label><label><span><?php esc_html_e( 'Text alignment', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>TextAlign"><option value="left"><?php esc_html_e( 'Left', 'panda-seasons' ); ?></option><option value="center"><?php esc_html_e( 'Centered', 'panda-seasons' ); ?></option><option value="right"><?php esc_html_e( 'Right', 'panda-seasons' ); ?></option></select></label></div>
					<div class="panda-seasons-label-shape-row"><label><span><?php esc_html_e( 'Label background shape', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>LabelShape"><option value="rectangle"><?php esc_html_e( 'Rectangle', 'panda-seasons' ); ?></option><option value="rounded"><?php esc_html_e( 'Rounded corners', 'panda-seasons' ); ?></option><option value="square"><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="round"><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label><label><span><?php esc_html_e( 'Label position', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>LabelPosition"><?php foreach ( self::image_position_options() as $position_value => $position_label ) : ?><option value="<?php echo esc_attr( $position_value ); ?>"><?php echo esc_html( $position_label ); ?></option><?php endforeach; ?></select></label></div>
					<div class="panda-seasons-label-size-row"><label><span><?php esc_html_e( 'Width', 'panda-seasons' ); ?></span><select data-door-field="<?php echo esc_attr( $state ); ?>LabelWidthMode"><option value="content"><?php esc_html_e( 'Automatic', 'panda-seasons' ); ?></option><option value="px"><?php esc_html_e( 'Width in pixels', 'panda-seasons' ); ?></option><option value="percent"><?php esc_html_e( 'Width in percent', 'panda-seasons' ); ?></option></select></label><label><span data-label-size-caption><?php esc_html_e( 'Size', 'panda-seasons' ); ?></span><input type="number" min="10" max="300" data-door-field="<?php echo esc_attr( $state ); ?>LabelWidth"></label></div>
					<div class="is-wide panda-seasons-label-colors-row"><label><span><?php esc_html_e( 'Font color', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>Color" value="rgba(255, 255, 255, 1)"></label><label><span><?php esc_html_e( 'Label background', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>LabelBg" value="rgba(10, 18, 30, 0.76)"></label><label><span><?php esc_html_e( 'Label border color', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>LabelBorderColor" value="rgba(255, 255, 255, 1)"></label><label><span><?php esc_html_e( 'Label border width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="20" data-door-field="<?php echo esc_attr( $state ); ?>LabelBorderWidth"></label></div>
					<label class="panda-seasons-image-overlay is-wide" data-image-overlay-wrap="<?php echo esc_attr( $state ); ?>" hidden><span><?php esc_html_e( 'Image color overlay', 'panda-seasons' ); ?></span><input type="text" data-door-field="<?php echo esc_attr( $state ); ?>ImageOverlay"></label>
				</div>
				<?php endforeach; ?>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section" data-content-type-panel="quiz" data-door-section="quiz">
							<summary><?php esc_html_e( 'Quiz', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label class="is-wide panda-seasons-quiz-toggle"><input type="checkbox" data-door-field="quizEnabled"> <span><?php esc_html_e( 'Enable quiz', 'panda-seasons' ); ?></span></label>
								<div class="is-wide" data-quiz-questions></div><button type="button" class="button is-wide" data-add-quiz-question><?php esc_html_e( 'Add question', 'panda-seasons' ); ?></button>
								<label class="is-wide"><span><?php esc_html_e( 'Message for a correct answer', 'panda-seasons' ); ?></span><input type="text" data-door-field="quizSuccess"></label>
								<label class="is-wide"><span><?php esc_html_e( 'Message for a wrong answer', 'panda-seasons' ); ?></span><input type="text" data-door-field="quizFailure"></label>
								<p class="description is-wide"><?php esc_html_e( 'In standalone mode answers are checked only in the visitor’s browser and no personal data is stored.', 'panda-seasons' ); ?></p>
							</div>
						</details>

						<div class="is-wide panda-seasons-door-modal__preview" data-door-preview hidden></div>
					</div>
				</div>
				<div class="panda-seasons-door-modal__footer">
					<div><button type="button" class="button button-primary" data-save-door><?php esc_html_e( 'Apply changes', 'panda-seasons' ); ?></button><button type="button" class="button" data-close-door-modal><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button></div>
					<button type="button" class="button is-wide panda-seasons-apply-door-appearance" data-apply-door-appearance><?php esc_html_e( 'Apply settings to all doors', 'panda-seasons' ); ?></button>
					<button type="button" class="button-link-delete" data-delete-door><?php esc_html_e( 'Move door to trash', 'panda-seasons' ); ?></button>
					<span class="panda-seasons-door-save-status" data-door-save-status aria-live="polite"></span>
				</div>
			</div>
		</aside>
		<div class="panda-seasons-apply-dialog" data-apply-door-dialog hidden>
			<div class="panda-seasons-apply-dialog__backdrop" data-close-apply-door-dialog></div>
			<div class="panda-seasons-apply-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="panda-seasons-apply-dialog-title">
				<h2 id="panda-seasons-apply-dialog-title"><?php esc_html_e( 'Select settings to apply', 'panda-seasons' ); ?></h2>
				<div class="panda-seasons-apply-dialog__blocks">
					<label><input type="checkbox" value="appearance" checked> <span><?php esc_html_e( 'Appearance', 'panda-seasons' ); ?></span></label>
					<label><input type="checkbox" value="related"> <span><?php esc_html_e( 'Button and related content', 'panda-seasons' ); ?></span></label>
					<label><input type="checkbox" value="cta"> <span><?php esc_html_e( 'Call to action', 'panda-seasons' ); ?></span></label>
				</div>
				<div class="panda-seasons-apply-dialog__actions"><button type="button" class="button button-primary" data-confirm-apply-door-settings><?php esc_html_e( 'Apply', 'panda-seasons' ); ?></button><button type="button" class="button" data-close-apply-door-dialog><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button></div>
			</div>
		</div>
		<aside class="panda-seasons-layer-editor" data-layer-editor hidden><div class="panda-seasons-layer-editor__header"><h2><?php esc_html_e( 'Edit design element', 'panda-seasons' ); ?></h2><button type="button" class="button-link" data-close-layer-editor aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>"><span class="dashicons dashicons-no-alt"></span></button></div><div class="panda-seasons-layer-editor__body"><div data-layer-field-wrap="text"><strong><?php esc_html_e( 'Text', 'panda-seasons' ); ?></strong><?php wp_editor( '', 'panda_seasons_layer_text_editor', array( 'textarea_name' => 'panda_seasons_layer_text_editor', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'block_formats' => 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div><div data-layer-field-wrap="surface"><input type="hidden" data-layer-field="imageId"><input type="hidden" data-layer-field="imageUrl"><div class="panda-seasons-layer-media-dropzone" data-select-layer-image role="button" tabindex="0"><div data-layer-image-preview><span><?php esc_html_e( 'Select image or drop it here', 'panda-seasons' ); ?></span></div></div><div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Image position', 'panda-seasons' ); ?></span><select data-layer-field="imagePosition"><?php foreach ( self::image_position_options() as $position_value => $position_label ) : ?><option value="<?php echo esc_attr( $position_value ); ?>"><?php echo esc_html( $position_label ); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e( 'Image fit', 'panda-seasons' ); ?></span><select data-layer-field="imageFit"><option value="cover"><?php esc_html_e( 'Cover', 'panda-seasons' ); ?></option><option value="contain"><?php esc_html_e( 'Contain', 'panda-seasons' ); ?></option></select></label></div></div><label><span><?php esc_html_e( 'Background color', 'panda-seasons' ); ?></span><input type="text" data-layer-field="background"></label><label data-layer-field-wrap="text"><span><?php esc_html_e( 'Text color', 'panda-seasons' ); ?></span><input type="text" data-layer-field="color"></label><label data-layer-field-wrap="text"><span><?php esc_html_e( 'Font size (px)', 'panda-seasons' ); ?></span><input type="number" min="8" max="160" data-layer-field="fontSize"></label><label data-layer-field-wrap="text"><span><?php esc_html_e( 'Inner spacing (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="100" data-layer-field="padding"></label><label class="panda-seasons-quiz-toggle"><input type="checkbox" data-layer-field="shadowEnabled"> <span><?php esc_html_e( 'Enable shadow', 'panda-seasons' ); ?></span></label><div class="panda-seasons-inline-pair"><label><span><?php esc_html_e( 'Shadow width (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="80" data-layer-field="shadowBlur"></label><label><span><?php esc_html_e( 'Shadow color', 'panda-seasons' ); ?></span><input type="text" data-layer-field="shadowColor"></label></div><label><span><?php esc_html_e( 'Layer order', 'panda-seasons' ); ?></span><input type="number" min="0" max="999" data-layer-field="z"></label><label><span><?php esc_html_e( 'Link URL', 'panda-seasons' ); ?></span><input type="url" data-layer-field="url" placeholder="https://"></label><label><span><?php esc_html_e( 'Corner radius (px)', 'panda-seasons' ); ?></span><input type="number" min="0" max="200" data-layer-field="radius"></label></div><div class="panda-seasons-layer-editor__footer"><div><button type="button" class="button button-primary" data-apply-layer-editor><?php esc_html_e( 'Apply changes', 'panda-seasons' ); ?></button><button type="button" class="button" data-close-layer-editor><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button></div><button type="button" class="button panda-seasons-layer-delete" data-delete-layer><?php esc_html_e( 'Delete element', 'panda-seasons' ); ?></button><span class="panda-seasons-layer-save-status" data-layer-save-status aria-live="polite"></span></div></aside>
		</div>
		<div class="panda-seasons-calendar-preview" data-calendar-preview hidden>
			<div class="panda-seasons-calendar-preview__backdrop" data-close-calendar-preview></div>
			<div class="panda-seasons-calendar-preview__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Preview', 'panda-seasons' ); ?>">
				<header><strong><?php esc_html_e( 'Preview', 'panda-seasons' ); ?></strong><div class="panda-seasons-calendar-preview__date"><label><span><?php esc_html_e( 'Simulated date', 'panda-seasons' ); ?></span><input type="date" data-calendar-preview-date value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label><label><span><?php esc_html_e( 'Time', 'panda-seasons' ); ?></span><input type="time" data-calendar-preview-time value="<?php echo esc_attr( wp_date( 'H:i' ) ); ?>"></label><button type="button" class="button" data-calendar-preview-date-apply><?php esc_html_e( 'Apply', 'panda-seasons' ); ?></button></div><div><button type="button" class="button button-primary" data-calendar-preview-size="desktop"><span class="dashicons dashicons-desktop"></span></button><button type="button" class="button" data-calendar-preview-size="mobile"><span class="dashicons dashicons-smartphone"></span></button><button type="button" class="button-link" data-close-calendar-preview aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>"><span class="dashicons dashicons-no-alt"></span></button></div></header>
				<iframe title="<?php esc_attr_e( 'Preview', 'panda-seasons' ); ?>"></iframe>
			</div>
		</div>
		<div class="panda-seasons-publish-panel" data-panda-seasons-publish-panel></div>
		<div class="panda-seasons-template-dialog" data-template-dialog hidden><div class="panda-seasons-template-dialog__backdrop" data-close-template-dialog></div><div class="panda-seasons-template-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="panda-seasons-template-dialog-title"><h2 id="panda-seasons-template-dialog-title"><?php esc_html_e( 'Save calendar as template', 'panda-seasons' ); ?></h2><p><?php esc_html_e( 'Choose whether editorial door contents should be included.', 'panda-seasons' ); ?></p><div class="panda-seasons-template-dialog__actions"><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_export_template&calendar_id=' . $post->ID . '&with_contents=1' ), 'panda_seasons_export_template' ) ); ?>"><?php esc_html_e( 'Template with contents', 'panda-seasons' ); ?></a><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_export_template&calendar_id=' . $post->ID . '&with_contents=0' ), 'panda_seasons_export_template' ) ); ?>"><?php esc_html_e( 'Template without contents', 'panda-seasons' ); ?></a><button type="button" class="button-link" data-close-template-dialog><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button></div></div></div>
		<?php
	}

	private static function template_library_url() {
		return (string) apply_filters( 'panda_seasons_template_library_url', 'https://raw.githubusercontent.com/kultur3gmbh/panda-seasons/main/template-library/manifest.json' );
	}

	private static function template_library( $refresh = false ) {
		$cache_key = 'panda_seasons_template_library_v1';
		if ( $refresh ) delete_site_transient( $cache_key );
		$cached = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;
		$response = wp_safe_remote_get( self::template_library_url(), array( 'timeout' => 12, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) ) return new WP_Error( 'library_unavailable', __( 'The template library is temporarily unavailable. Please try again later.', 'panda-seasons' ) );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || 1 !== absint( $data['format_version'] ?? 0 ) ) return new WP_Error( 'library_invalid', __( 'The template library returned an invalid response.', 'panda-seasons' ) );
		$base = preg_replace( '~/[^/]*$~', '/', self::template_library_url() );
		$templates = array();
		foreach ( (array) ( $data['templates'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['template'] ) || empty( $item['sha256'] ) ) continue;
			$item['id']          = sanitize_key( $item['id'] );
			$item['title']       = sanitize_text_field( $item['title'] ?? $item['id'] );
			$item['description'] = sanitize_textarea_field( $item['description'] ?? '' );
			foreach ( array( 'template', 'thumbnail' ) as $url_key ) {
				$url = (string) ( $item[ $url_key ] ?? '' );
				if ( $url && ! wp_parse_url( $url, PHP_URL_HOST ) ) $url = $base . ltrim( $url, '/' );
				$item[ $url_key ] = esc_url_raw( $url );
			}
			$item['sha256']    = strtolower( preg_replace( '/[^a-f0-9]/', '', (string) $item['sha256'] ) );
			$item['has_images'] = ! empty( $item['has_images'] );
			$item['min_plugin_version'] = sanitize_text_field( $item['min_plugin_version'] ?? '0.1.0' );
			if ( 64 === strlen( $item['sha256'] ) && self::allowed_library_url( $item['template'] ) && version_compare( PANDA_SEASONS_VERSION, $item['min_plugin_version'], '>=' ) ) $templates[] = $item;
		}
		$result = array( 'templates' => $templates );
		set_site_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	private static function allowed_library_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return in_array( $host, array( 'raw.githubusercontent.com', 'github.com' ), true );
	}

	public static function refresh_template_library() {
		check_admin_referer( 'panda_seasons_refresh_template_library' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to manage calendar templates.', 'panda-seasons' ) );
		self::template_library( true );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CALENDAR_POST_TYPE . '&page=panda-seasons-templates' ) ); exit;
	}

	public static function import_library_template() {
		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		check_admin_referer( 'panda_seasons_import_library_template_' . $template_id );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to import calendar templates.', 'panda-seasons' ) );
		$library = self::template_library();
		$item = null;
		if ( ! is_wp_error( $library ) ) foreach ( $library['templates'] as $candidate ) if ( $template_id === $candidate['id'] ) { $item = $candidate; break; }
		if ( ! $item ) wp_die( esc_html__( 'The selected library template is no longer available.', 'panda-seasons' ) );
		$response = wp_safe_remote_get( $item['template'], array( 'timeout' => 20, 'redirection' => 2, 'limit_response_size' => 5 * MB_IN_BYTES ) );
		$body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
		if ( ! $body || ! hash_equals( $item['sha256'], hash( 'sha256', $body ) ) ) wp_die( esc_html__( 'The downloaded template failed its integrity check.', 'panda-seasons' ) );
		$template = json_decode( $body, true );
		if ( ! is_array( $template ) || 'panda-seasons-template' !== ( $template['format'] ?? '' ) || 1 !== absint( $template['format_version'] ?? 0 ) ) wp_die( esc_html__( 'The selected file is not a supported Panda Seasons template.', 'panda-seasons' ) );
		$with_images = ! empty( $_POST['with_images'] );
		$calendar = (array) ( $template['calendar'] ?? array() );
		$calendar_id = wp_insert_post( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => 'draft', 'post_title' => sanitize_text_field( $calendar['title'] ?? $item['title'] ), 'post_content' => '' ), true );
		if ( is_wp_error( $calendar_id ) ) wp_die( esc_html( $calendar_id->get_error_message() ) );
		$blocked_calendar = array( '_ps_contact_tag', '_ps_email_enabled', '_ps_signup_heading', '_ps_signup_text', '_ps_newsletter_type', '_ps_newsletter_shortcode', '_ps_newsletter_url' );
		$calendar_meta = array_diff_key( (array) ( $calendar['meta'] ?? array() ), array_flip( $blocked_calendar ) );
		if ( ! empty( $calendar_meta['_ps_design_layers'] ) && is_array( $calendar_meta['_ps_design_layers'] ) ) {
			foreach ( $calendar_meta['_ps_design_layers'] as &$layer ) {
				if ( ! is_array( $layer ) ) continue;
				if ( $with_images && ! empty( $layer['imageUrl'] ) ) {
					$attachment_id = self::import_template_media( $layer['imageUrl'], $calendar_id );
					$layer['imageId'] = $attachment_id;
					$layer['imageUrl'] = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
				} else {
					$layer['imageId'] = 0;
					$layer['imageUrl'] = '';
				}
			}
			unset( $layer );
		}
		self::import_template_meta( $calendar_id, $calendar_meta, $with_images ? ( $calendar['media'] ?? array() ) : array() );
		foreach ( (array) ( $template['doors'] ?? array() ) as $door ) {
			$number = absint( $door['meta']['_ps_door_number'] ?? 0 );
			$door_id = wp_insert_post( array( 'post_type' => self::DOOR_POST_TYPE, 'post_status' => 'publish', 'post_parent' => $calendar_id, 'post_title' => sprintf( __( 'Door %d', 'panda-seasons' ), $number ?: 1 ), 'post_content' => '', 'post_excerpt' => '' ), true );
			if ( is_wp_error( $door_id ) ) continue;
			$meta = array_diff_key( (array) ( $door['meta'] ?? array() ), array_flip( self::content_meta_keys() ) );
			self::import_template_meta( $door_id, $meta, $with_images ? array_intersect_key( (array) ( $door['media'] ?? array() ), array( '_ps_door_image_id' => true ) ) : array() );
		}
		wp_safe_redirect( add_query_arg( 'panda_seasons_imported', 1, get_edit_post_link( $calendar_id, 'url' ) ) ); exit;
	}

	private static function media_field( $name, $label, $id, $url ) {
		?>
		<div class="panda-seasons-media-field" data-media-field>
			<strong><?php echo esc_html( $label ); ?></strong>
			<div class="panda-seasons-media-preview" data-select-media role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Select image or drop it here', 'panda-seasons' ); ?>"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php else : ?><span><?php esc_html_e( 'Select image or drop it here', 'panda-seasons' ); ?></span><?php endif; ?></div>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $id ); ?>">
			<button type="button" class="button-link-delete" data-remove-media<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button>
		</div>
		<?php
	}

	private static function image_position_options() {
		return array(
			'top-left'      => __( 'Top left', 'panda-seasons' ),
			'top-center'    => __( 'Top center', 'panda-seasons' ),
			'top-right'     => __( 'Top right', 'panda-seasons' ),
			'center-left'   => __( 'Center left', 'panda-seasons' ),
			'center'        => __( 'Centered', 'panda-seasons' ),
			'center-right'  => __( 'Center right', 'panda-seasons' ),
			'bottom-left'   => __( 'Bottom left', 'panda-seasons' ),
			'bottom-center' => __( 'Bottom center', 'panda-seasons' ),
			'bottom-right'  => __( 'Bottom right', 'panda-seasons' ),
		);
	}

	private static function sanitize_image_position( $position ) {
		$position = sanitize_key( (string) $position );
		return array_key_exists( $position, self::image_position_options() ) ? $position : 'center';
	}

	private static function attachment_kind( $attachment_id ) {
		$mime = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';
		if ( 0 === strpos( $mime, 'image/' ) ) return 'image';
		if ( 0 === strpos( $mime, 'video/' ) ) return 'video';
		if ( 0 === strpos( $mime, 'audio/' ) ) return 'audio';
		return '';
	}

	private static function product_options() {
		if ( ! post_type_exists( 'product' ) ) return array();
		$posts   = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 250, 'orderby' => 'title', 'order' => 'ASC' ) );
		$options = array();
		foreach ( $posts as $item ) $options[ $item->ID ] = $item->post_title;
		return apply_filters( 'panda_seasons_product_options', $options );
	}
	private static function coupon_options() {
		if ( ! post_type_exists( 'shop_coupon' ) ) return array();
		$options = array();
		foreach ( get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'posts_per_page' => 250 ) ) as $item ) $options[ $item->ID ] = $item->post_title;
		return $options;
	}

	private static function event_options() {
		$post_types = (array) apply_filters( 'panda_seasons_event_post_types', array( 'tribe_events', 'mec-events', 'event' ) );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ), 'post_type_exists' ) );
		$options    = array();
		if ( $post_types ) {
			$posts = get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => 250, 'orderby' => 'date', 'order' => 'DESC' ) );
			foreach ( $posts as $item ) $options[ $item->ID ] = $item->post_title;
		}
		return apply_filters( 'panda_seasons_event_options', $options );
	}

	private static function theme_colors() {
		if ( ! self::contactpanda_available() ) return array();
		$contactpanda_settings = (array) get_option( 'contactpanda_general_settings', array() );
		if ( empty( $contactpanda_settings['theme_styles'] ) ) return array();
		$selected_theme = strtolower( sanitize_text_field( (string) ( $contactpanda_settings['theme_stylesheet'] ?? $contactpanda_settings['theme_name'] ?? '' ) ) );
		if ( ! $selected_theme ) return array();
		$colors = array();

		// Use the exact palette ContactPanda already detected and displays in its
		// appearance settings. This keeps both modules in sync instead of trying to
		// interpret a page builder's evolving option structure twice.
		if ( class_exists( 'ContactPanda_Basic_Newsletters' ) && is_callable( array( 'ContactPanda_Basic_Newsletters', 'detected_theme_colors' ) ) ) {
			self::append_theme_palette( $colors, ContactPanda_Basic_Newsletters::detected_theme_colors(), 'ContactPanda' );
		}

		$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
		$palettes = $settings['color']['palette'] ?? array();
		foreach ( array( 'theme', 'default', 'custom' ) as $group ) {
			foreach ( (array) ( $palettes[ $group ] ?? array() ) as $item ) {
				$color = sanitize_hex_color( $item['color'] ?? '' );
				if ( ! $color ) continue;
				$colors[ strtolower( $color ) ] = array( 'color' => $color, 'name' => sanitize_text_field( $item['name'] ?? $color ) );
			}
		}

		if ( false !== strpos( $selected_theme, 'avada' ) || false !== strpos( $selected_theme, 'fusion' ) ) {
			$fusion = (array) get_option( 'fusion_options', array() );
			foreach ( array( 'global_colors_palette', 'fusion_global_colors', 'color_palette' ) as $key ) {
				self::append_theme_palette( $colors, $fusion[ $key ] ?? array(), 'Avada' );
			}
		}

		if ( false !== strpos( $selected_theme, 'elementor' ) || false !== strpos( $selected_theme, 'hello' ) ) {
			$kit_id = absint( get_option( 'elementor_active_kit' ) );
			$kit    = $kit_id ? (array) get_post_meta( $kit_id, '_elementor_page_settings', true ) : array();
			self::append_theme_palette( $colors, $kit['system_colors'] ?? array(), 'Elementor' );
			self::append_theme_palette( $colors, $kit['custom_colors'] ?? array(), 'Elementor' );
		}

		if ( false !== strpos( $selected_theme, 'divi' ) ) {
			$divi = (array) get_option( 'et_divi', array() );
			foreach ( array( 'accent_color', 'header_color', 'font_color', 'primary_nav_font_color', 'secondary_nav_font_color', 'footer_bg' ) as $key ) {
				if ( ! empty( $divi[ $key ] ) ) self::append_theme_palette( $colors, array( array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'color' => $divi[ $key ] ) ), 'Divi' );
			}
		}

		return array_values( apply_filters( 'panda_seasons_theme_colors', $colors ) );
	}

	private static function append_theme_palette( &$colors, $palette, $source ) {
		if ( is_string( $palette ) ) {
			$decoded = json_decode( $palette, true );
			if ( is_array( $decoded ) ) $palette = $decoded;
		}
		foreach ( (array) $palette as $key => $item ) {
			if ( is_array( $item ) ) {
				$color = sanitize_hex_color( $item['color'] ?? $item['value'] ?? '' );
				$name  = sanitize_text_field( $item['name'] ?? $item['title'] ?? $item['_id'] ?? $key );
				if ( ! $color ) {
					self::append_theme_palette( $colors, $item, $source );
					continue;
				}
			} else {
				$color = sanitize_hex_color( $item );
				$name  = sanitize_text_field( is_string( $key ) ? $key : $color );
			}
			if ( $color ) $colors[ strtolower( $color ) ] = array( 'color' => $color, 'name' => trim( $source . ' – ' . $name, ' –' ) );
		}
	}

	private static function door_position( $door_id, $view, $index, $total ) {
		$stored = get_post_meta( $door_id, '_ps_position_' . $view, true );
		if ( is_array( $stored ) && isset( $stored['x'], $stored['y'], $stored['w'], $stored['h'] ) ) {
			return self::sanitize_position( $stored );
		}

		$columns = 'mobile' === $view ? 3 : 6;
		$columns = max( 1, min( $columns, max( 1, $total ) ) );
		$rows    = max( 1, (int) ceil( max( 1, $total ) / $columns ) );
		$gap     = 1.5;
		$width   = ( 100 - ( $columns + 1 ) * $gap ) / $columns;
		$height  = min( 24, ( 100 - ( $rows + 1 ) * $gap ) / $rows );
		$column  = $index % $columns;
		$row     = (int) floor( $index / $columns );

		return self::sanitize_position(
			array(
				'x' => $gap + $column * ( $width + $gap ),
				'y' => $gap + $row * ( $height + $gap ),
				'w' => $width,
				'h' => $height,
			)
		);
	}

	private static function sanitize_position( $position ) {
		if ( ! is_array( $position ) ) $position = array();
		$width  = min( 100, max( 4, (float) ( $position['w'] ?? 16 ) ) );
		$height = min( 100, max( 4, (float) ( $position['h'] ?? 18 ) ) );

		return array(
			'x' => round( min( 100 - $width, max( 0, (float) ( $position['x'] ?? 0 ) ) ), 4 ),
			'y' => round( min( 100 - $height, max( 0, (float) ( $position['y'] ?? 0 ) ) ), 4 ),
			'w' => round( $width, 4 ),
			'h' => round( $height, 4 ),
		);
	}

	public static function calendar_email_box( $post ) {
		global $wpdb;
		$available = self::contactpanda_available();
		$contactpanda_file = 'contactpanda/contactpanda.php';
		$contactpanda_installed = file_exists( WP_PLUGIN_DIR . '/' . $contactpanda_file );
		$contactpanda_action_url = $contactpanda_installed && current_user_can( 'activate_plugins' )
			? wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $contactpanda_file ) ), 'activate-plugin_' . $contactpanda_file )
			: 'https://github.com/kultur3gmbh/contactpanda/releases/latest';
		$enabled   = $available && (bool) get_post_meta( $post->ID, '_ps_email_enabled', true );
		$time      = get_post_meta( $post->ID, '_ps_email_time', true ) ?: '09:00';
		$subject   = get_post_meta( $post->ID, '_ps_email_subject', true ) ?: __( '{calendar}: Door {day} is open', 'panda-seasons' );
		$signup_title = get_post_meta( $post->ID, '_ps_signup_title', true ) ?: __( 'Receive this calendar by email', 'panda-seasons' );
		$signup_text  = (string) get_post_meta( $post->ID, '_ps_signup_text', true );
		$newsletter_mode = (string) get_post_meta( $post->ID, '_ps_newsletter_mode', true ) ?: 'none';
		$contact_tag = (string) get_post_meta( $post->ID, '_ps_contact_tag', true );
		if ( ! $contact_tag ) $contact_tag = sprintf( __( 'Panda Seasons – %s', 'panda-seasons' ), get_the_title( $post ) );
		$signup_consent = (string) get_post_meta( $post->ID, '_ps_signup_consent', true ) ?: sprintf( __( 'I would like to receive the daily emails for “%s”. I can unsubscribe at any time.', 'panda-seasons' ), get_the_title( $post ) );
		$signup_button = (string) get_post_meta( $post->ID, '_ps_signup_button_label', true ) ?: __( 'Register for email calendar', 'panda-seasons' );
		$signup_pending = (string) get_post_meta( $post->ID, '_ps_signup_pending_message', true ) ?: __( 'Please check your inbox and confirm your email address. Also check your spam folder.', 'panda-seasons' );
		$signup_active = (string) get_post_meta( $post->ID, '_ps_signup_active_message', true ) ?: __( 'You are registered for this email calendar.', 'panda-seasons' );
		$signup_error = (string) get_post_meta( $post->ID, '_ps_signup_error_message', true ) ?: __( 'The registration could not be completed. Please try again.', 'panda-seasons' );
		$doi_subject = (string) get_post_meta( $post->ID, '_ps_doi_subject', true ) ?: sprintf( __( 'Confirm your registration for %s', 'panda-seasons' ), get_the_title( $post ) );
		$doi_text = (string) get_post_meta( $post->ID, '_ps_doi_text', true ) ?: __( 'Please confirm that you would like to receive this email calendar.', 'panda-seasons' );
		$doi_button = (string) get_post_meta( $post->ID, '_ps_doi_button_label', true ) ?: __( 'Confirm email calendar', 'panda-seasons' );
		$confirmation_title = (string) get_post_meta( $post->ID, '_ps_confirmation_title', true ) ?: __( 'Registration confirmed', 'panda-seasons' );
		$confirmation_text = (string) get_post_meta( $post->ID, '_ps_confirmation_text', true ) ?: sprintf( __( 'You will now receive the emails for “%s”.', 'panda-seasons' ), get_the_title( $post ) );
		$doi_reminder_count = min( 3, absint( get_post_meta( $post->ID, '_ps_doi_reminder_count', true ) ) );
		$doi_reminder_delays = (array) get_post_meta( $post->ID, '_ps_doi_reminder_delays', true );
		$doi_reminder_subject = (string) get_post_meta( $post->ID, '_ps_doi_reminder_subject', true ) ?: sprintf( __( 'Reminder: confirm your registration for %s', 'panda-seasons' ), get_the_title( $post ) );
		$doi_reminder_text = (string) get_post_meta( $post->ID, '_ps_doi_reminder_text', true ) ?: __( 'Please confirm your registration for the email calendar using the following link.', 'panda-seasons' );
		$subscription_stats = array( 'active' => 0, 'pending' => 0, 'unsubscribed' => 0, 'total' => 0 );
		$subscription_table = Panda_Seasons_Schema::tables()['subscriptions'];
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status,COUNT(*) AS amount FROM `{$subscription_table}` WHERE calendar_id=%d GROUP BY status", $post->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		foreach ( (array) $rows as $row ) {
			$amount = absint( $row['amount'] ?? 0 );
			$subscription_stats['total'] += $amount;
			if ( 'active' === ( $row['status'] ?? '' ) ) $subscription_stats['active'] += $amount;
			elseif ( 'pending' === ( $row['status'] ?? '' ) ) $subscription_stats['pending'] += $amount;
			else $subscription_stats['unsubscribed'] += $amount;
		}
		?>
		<div class="panda-seasons-email-settings">
		<?php if ( ! $available ) : ?>
			<div class="notice notice-info inline panda-seasons-contactpanda-promo">
				<p><strong><?php esc_html_e( 'Add email and newsletter features for free', 'panda-seasons' ); ?></strong></p>
				<p><?php esc_html_e( 'Install the free ContactPanda plugin to send calendar doors by email, collect advance calendar registrations and use the integrated newsletter signup. The standalone web calendar remains fully available without it.', 'panda-seasons' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( $contactpanda_action_url ); ?>"<?php echo $contactpanda_installed ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>><?php echo esc_html( $contactpanda_installed ? __( 'Activate ContactPanda', 'panda-seasons' ) : __( 'Get ContactPanda free', 'panda-seasons' ) ); ?></a><?php if ( ! $contactpanda_installed ) : ?> <a class="button" href="<?php echo esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload plugin ZIP', 'panda-seasons' ); ?></a><?php endif; ?></p>
			</div>
		<?php endif; ?>
		<section><h3><?php esc_html_e( 'Daily calendar email', 'panda-seasons' ); ?></h3><p class="description"><?php esc_html_e( 'Sends the newly opened door to registered calendar subscribers at the selected time.', 'panda-seasons' ); ?></p>
		<p><label><input type="checkbox" name="panda_seasons_email_enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $available ); ?>> <?php esc_html_e( 'Enable daily delivery', 'panda-seasons' ); ?></label></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Daily delivery time', 'panda-seasons' ); ?></span><input type="time" name="panda_seasons_email_time" value="<?php echo esc_attr( $time ); ?>"></label>
			<label><span><?php esc_html_e( 'Subject template', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_email_subject" value="<?php echo esc_attr( $subject ); ?>"><small><?php esc_html_e( 'Available: {calendar}, {day}, {first_name}', 'panda-seasons' ); ?></small></label>
		</div></section>
		<div class="panda-seasons-email-status" role="status">
			<div><strong><?php esc_html_e( 'ContactPanda status', 'panda-seasons' ); ?></strong><span class="<?php echo $available ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( $available ? __( 'Connected', 'panda-seasons' ) : __( 'Not available', 'panda-seasons' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Registrations', 'panda-seasons' ); ?></strong><span><?php echo esc_html( $subscription_stats['total'] ); ?></span></div>
			<div><strong><?php esc_html_e( 'Confirmed', 'panda-seasons' ); ?></strong><span><?php echo esc_html( $subscription_stats['active'] ); ?></span></div>
			<div><strong><?php esc_html_e( 'Awaiting confirmation', 'panda-seasons' ); ?></strong><span><?php echo esc_html( $subscription_stats['pending'] ); ?></span></div>
			<div><strong><?php esc_html_e( 'Unsubscribed or blocked', 'panda-seasons' ); ?></strong><span><?php echo esc_html( $subscription_stats['unsubscribed'] ); ?></span></div>
		</div>
		<div class="panda-seasons-signup-columns"><div class="panda-seasons-signup-column">
		<section><h3><?php esc_html_e( 'Advance email-calendar registration', 'panda-seasons' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Publish this registration immediately, before the first door opens. New contacts receive a double opt-in email and the calendar-specific tag.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Signup heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_signup_title" value="<?php echo esc_attr( $signup_title ); ?>"></label>
			<label><span><?php esc_html_e( 'Signup description', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_text" rows="3"><?php echo esc_textarea( $signup_text ); ?></textarea></label>
			<label class="is-wide"><span><?php esc_html_e( 'Consent text', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_consent" rows="2"><?php echo esc_textarea( $signup_consent ); ?></textarea></label>
			<label><span><?php esc_html_e( 'Signup button text', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_signup_button_label" value="<?php echo esc_attr( $signup_button ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Contact tag for this calendar', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_contact_tag" value="<?php echo esc_attr( $contact_tag ); ?>"><small><?php esc_html_e( 'ContactPanda uses this tag and the calendar origin details for people who sign up through this calendar.', 'panda-seasons' ); ?></small></label>
			<label><span><?php esc_html_e( 'Message after confirmed registration', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_active_message" rows="2"><?php echo esc_textarea( $signup_active ); ?></textarea></label>
			<label><span><?php esc_html_e( 'Message while confirmation is pending', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_pending_message" rows="2"><?php echo esc_textarea( $signup_pending ); ?></textarea></label>
			<label class="is-wide"><span><?php esc_html_e( 'Error message', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_error_message" rows="2"><?php echo esc_textarea( $signup_error ); ?></textarea></label>
		</div>
		<p class="panda-seasons-shortcode"><strong><?php esc_html_e( 'Signup shortcode', 'panda-seasons' ); ?></strong><span class="panda-seasons-copy-row"><input type="text" readonly value="[panda_seasons_subscribe id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]"><button type="button" class="button" data-copy-shortcode><?php esc_html_e( 'Copy', 'panda-seasons' ); ?></button></span></p></section>
		<section><h3><?php esc_html_e( 'Double opt-in email', 'panda-seasons' ); ?></h3><p class="description"><?php esc_html_e( 'These texts are sent to new, not yet confirmed contacts. The confirmation link is inserted automatically.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<label class="is-wide"><span><?php esc_html_e( 'Double opt-in subject', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_doi_subject" value="<?php echo esc_attr( $doi_subject ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Double opt-in text', 'panda-seasons' ); ?></span><textarea name="panda_seasons_doi_text" rows="4"><?php echo esc_textarea( $doi_text ); ?></textarea></label>
			<label><span><?php esc_html_e( 'Confirmation button text', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_doi_button_label" value="<?php echo esc_attr( $doi_button ); ?>"></label>
			<label><span><?php esc_html_e( 'Confirmation page heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_confirmation_title" value="<?php echo esc_attr( $confirmation_title ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Confirmation page text', 'panda-seasons' ); ?></span><textarea name="panda_seasons_confirmation_text" rows="3"><?php echo esc_textarea( $confirmation_text ); ?></textarea></label>
			<label><span><?php esc_html_e( 'Number of reminders', 'panda-seasons' ); ?></span><select name="panda_seasons_doi_reminder_count"><?php for ( $reminder_number = 0; $reminder_number <= 3; $reminder_number++ ) : ?><option value="<?php echo esc_attr( $reminder_number ); ?>" <?php selected( $doi_reminder_count, $reminder_number ); ?>><?php echo esc_html( $reminder_number ); ?></option><?php endfor; ?></select></label>
			<?php for ( $reminder_index = 0; $reminder_index < 3; $reminder_index++ ) : ?><label><span><?php echo esc_html( sprintf( __( 'Reminder %d after days', 'panda-seasons' ), $reminder_index + 1 ) ); ?></span><input type="number" min="1" max="30" name="panda_seasons_doi_reminder_delays[]" value="<?php echo esc_attr( absint( $doi_reminder_delays[ $reminder_index ] ?? ( $reminder_index + 1 ) * 3 ) ); ?>"></label><?php endfor; ?>
			<label class="is-wide"><span><?php esc_html_e( 'Reminder subject', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_doi_reminder_subject" value="<?php echo esc_attr( $doi_reminder_subject ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Reminder email text', 'panda-seasons' ); ?></span><textarea name="panda_seasons_doi_reminder_text" rows="4"><?php echo esc_textarea( $doi_reminder_text ); ?></textarea></label>
		</div></section>
		</div><div class="panda-seasons-signup-column">
		<section><h3><?php esc_html_e( 'General newsletter signup', 'panda-seasons' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Optionally show a link or an existing form. Newsletter consent remains separate from calendar consent.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Signup type', 'panda-seasons' ); ?></span><select name="panda_seasons_newsletter_mode"><option value="none" <?php selected( $newsletter_mode, 'none' ); ?>><?php esc_html_e( 'None', 'panda-seasons' ); ?></option><option value="link" <?php selected( $newsletter_mode, 'link' ); ?>><?php esc_html_e( 'Link or button', 'panda-seasons' ); ?></option><option value="shortcode" <?php selected( $newsletter_mode, 'shortcode' ); ?>><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></option><option value="contactpanda" <?php selected( $newsletter_mode, 'contactpanda' ); ?> <?php disabled( ! self::contactpanda_newsletter_available() ); ?>><?php esc_html_e( 'Simple ContactPanda form', 'panda-seasons' ); ?></option></select></label>
			<label><span><?php esc_html_e( 'Heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_title" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_title', true ) ); ?>"></label>
			<label><span><?php esc_html_e( 'Link text', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_link_label" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_link_label', true ) ); ?>"></label>
			<label><span><?php esc_html_e( 'Link URL', 'panda-seasons' ); ?></span><input type="url" name="panda_seasons_newsletter_link_url" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_link_url', true ) ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_shortcode" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_shortcode', true ) ); ?>" placeholder="[contact-form-7 id=&quot;123&quot;]"></label>
		</div></section>
		</div></div>
		<?php if ( has_action( 'contactpanda_email_preview_controls' ) ) : ?><section class="panda-seasons-email-preview"><h3><?php esc_html_e( 'Email preview', 'panda-seasons' ); ?></h3><?php do_action( 'contactpanda_email_preview_controls', array( 'source' => 'panda-seasons', 'calendar_id' => $post->ID, 'modes' => array( 'desktop', 'mobile' ) ) ); ?></section><?php else : ?><p class="description"><?php esc_html_e( 'Desktop and mobile email previews are provided centrally by ContactPanda Basic as soon as the shared preview module is available.', 'panda-seasons' ); ?></p><?php endif; ?>
		<p class="description"><?php esc_html_e( 'Verified ContactPanda contacts are enrolled immediately. All other addresses receive a double opt-in email. Calendar consent is stored separately from general marketing consent.', 'panda-seasons' ); ?></p>
		<p><button type="submit" name="save" class="button button-primary panda-seasons-email-settings__save"><?php esc_html_e( 'Save email settings', 'panda-seasons' ); ?></button></p>
		</div>
		<?php
	}

	public static function calendar_shortcode_box( $post ) {
		$calendar_shortcode = '[panda_seasons id="' . absint( $post->ID ) . '"]';
		$signup_shortcode = '[panda_seasons_subscribe id="' . absint( $post->ID ) . '"]';
		echo '<p><label><strong>' . esc_html__( 'Calendar shortcode', 'panda-seasons' ) . '</strong><span class="panda-seasons-copy-row"><input type="text" readonly value="' . esc_attr( $calendar_shortcode ) . '"><button type="button" class="button" data-copy-shortcode>' . esc_html__( 'Copy', 'panda-seasons' ) . '</button></span></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Signup shortcode', 'panda-seasons' ) . '</strong><span class="panda-seasons-copy-row"><input type="text" readonly value="' . esc_attr( $signup_shortcode ) . '"><button type="button" class="button" data-copy-shortcode>' . esc_html__( 'Copy', 'panda-seasons' ) . '</button></span></label></p>';
	}

	public static function door_box( $post ) {
		wp_nonce_field( 'panda_seasons_save_door', 'panda_seasons_door_nonce' );
		$calendars = get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$calendar  = (int) $post->post_parent;
		$number    = absint( get_post_meta( $post->ID, '_ps_door_number', true ) ) ?: 1;
		$date      = get_post_meta( $post->ID, '_ps_unlock_date', true );
		$time      = get_post_meta( $post->ID, '_ps_unlock_time', true );
		$subject   = get_post_meta( $post->ID, '_ps_email_subject', true );
		?>
		<p><label><strong><?php esc_html_e( 'Calendar', 'panda-seasons' ); ?></strong><select name="panda_seasons_calendar_id" required><option value=""><?php esc_html_e( 'Select calendar', 'panda-seasons' ); ?></option><?php foreach ( $calendars as $item ) : ?><option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( $calendar, $item->ID ); ?>><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?></select></label></p>
		<p><label><strong><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></strong><input type="number" min="1" max="366" name="panda_seasons_door_number" value="<?php echo esc_attr( $number ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></strong><input type="date" name="panda_seasons_unlock_date" value="<?php echo esc_attr( $date ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Optional unlock time', 'panda-seasons' ); ?></strong><input type="time" name="panda_seasons_unlock_time" value="<?php echo esc_attr( $time ); ?>"></label></p>
		<p><label><strong><?php esc_html_e( 'Email subject override', 'panda-seasons' ); ?></strong><input type="text" name="panda_seasons_door_email_subject" value="<?php echo esc_attr( $subject ); ?>"></label></p>
		<?php
	}

	public static function save_calendar( $post_id ) {
		if ( ! self::can_save( $post_id, 'panda_seasons_calendar_nonce', 'panda_seasons_save_calendar' ) ) return array();
		update_post_meta( $post_id, '_ps_cover_desktop', absint( $_POST['panda_seasons_cover_desktop'] ?? 0 ) );
		update_post_meta( $post_id, '_ps_cover_mobile', absint( $_POST['panda_seasons_cover_mobile'] ?? 0 ) );
		self::update_css_color_meta( $post_id, '_ps_cover_desktop_background', wp_unslash( $_POST['panda_seasons_cover_desktop_background'] ?? 'rgba(23, 32, 51, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_cover_mobile_background', wp_unslash( $_POST['panda_seasons_cover_mobile_background'] ?? 'rgba(23, 32, 51, 1)' ) );
		$ratio = (float) ( $_POST['panda_seasons_aspect_ratio'] ?? 1.5 );
		update_post_meta( $post_id, '_ps_aspect_ratio', min( 3, max( 0.4, $ratio ) ) );
		$mobile_ratio = (float) ( $_POST['panda_seasons_mobile_aspect_ratio'] ?? 0.75 );
		update_post_meta( $post_id, '_ps_mobile_aspect_ratio', min( 2, max( 0.35, $mobile_ratio ) ) );
		$open_effect = sanitize_key( wp_unslash( $_POST['panda_seasons_open_effect'] ?? 'door-left' ) );
		update_post_meta( $post_id, '_ps_open_effect', in_array( $open_effect, array( 'none', 'door-left', 'door-right', 'page' ), true ) ? $open_effect : 'door-left' );
		$effect_speed = sanitize_key( wp_unslash( $_POST['panda_seasons_effect_speed'] ?? 'normal' ) );
		update_post_meta( $post_id, '_ps_effect_speed', in_array( $effect_speed, array( 'fast', 'normal', 'slow' ), true ) ? $effect_speed : 'normal' );
		$grid_size = absint( $_POST['panda_seasons_grid_size'] ?? 10 );
		update_post_meta( $post_id, '_ps_grid_size', in_array( $grid_size, array( 10, 20, 30 ), true ) ? $grid_size : 10 );
		self::update_css_color_meta( $post_id, '_ps_default_closed_background', wp_unslash( $_POST['panda_seasons_default_closed_background'] ?? 'rgba(99, 99, 99, 0.2)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_background', wp_unslash( $_POST['panda_seasons_default_open_background'] ?? 'rgba(51, 79, 232, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_closed_image_overlay', wp_unslash( $_POST['panda_seasons_default_closed_image_overlay'] ?? 'rgba(0, 0, 0, 0)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_image_overlay', wp_unslash( $_POST['panda_seasons_default_open_image_overlay'] ?? 'rgba(0, 0, 0, 0)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_closed_text_color', wp_unslash( $_POST['panda_seasons_default_closed_text_color'] ?? 'rgba(255, 255, 255, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_text_color', wp_unslash( $_POST['panda_seasons_default_open_text_color'] ?? 'rgba(255, 255, 255, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_closed_border_color', wp_unslash( $_POST['panda_seasons_default_closed_border_color'] ?? 'rgba(255, 255, 255, 0.9)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_border_color', wp_unslash( $_POST['panda_seasons_default_open_border_color'] ?? 'rgba(255, 255, 255, 0.9)' ) );
		update_post_meta( $post_id, '_ps_default_closed_border_width', min( 20, absint( $_POST['panda_seasons_default_closed_border_width'] ?? 2 ) ) );
		update_post_meta( $post_id, '_ps_default_open_border_width', min( 20, absint( $_POST['panda_seasons_default_open_border_width'] ?? 2 ) ) );
		self::update_css_color_meta( $post_id, '_ps_default_closed_label_background', wp_unslash( $_POST['panda_seasons_default_closed_label_background'] ?? 'rgba(10, 18, 30, 0.76)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_label_background', wp_unslash( $_POST['panda_seasons_default_open_label_background'] ?? 'rgba(10, 18, 30, 0.76)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_closed_label_border_color', wp_unslash( $_POST['panda_seasons_default_closed_label_border_color'] ?? 'rgba(255, 255, 255, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_default_open_label_border_color', wp_unslash( $_POST['panda_seasons_default_open_label_border_color'] ?? 'rgba(255, 255, 255, 1)' ) );
		update_post_meta( $post_id, '_ps_default_closed_label_border_width', min( 20, absint( $_POST['panda_seasons_default_closed_label_border_width'] ?? 1 ) ) );
		update_post_meta( $post_id, '_ps_default_open_label_border_width', min( 20, absint( $_POST['panda_seasons_default_open_label_border_width'] ?? 1 ) ) );
		foreach ( array( 'closed', 'open' ) as $default_state ) {
			$default_label_shape = sanitize_key( wp_unslash( $_POST[ 'panda_seasons_default_' . $default_state . '_label_shape' ] ?? 'rounded' ) );
			update_post_meta( $post_id, '_ps_default_' . $default_state . '_label_shape', in_array( $default_label_shape, array( 'rectangle', 'rounded', 'square', 'round' ), true ) ? $default_label_shape : 'rounded' );
			update_post_meta( $post_id, '_ps_default_' . $default_state . '_label_position', self::sanitize_image_position( wp_unslash( $_POST[ 'panda_seasons_default_' . $default_state . '_label_position' ] ?? 'center' ) ) );
		}
		$default_label_width_mode = sanitize_key( wp_unslash( $_POST['panda_seasons_default_label_width_mode'] ?? 'content' ) );
		update_post_meta( $post_id, '_ps_default_label_width_mode', in_array( $default_label_width_mode, array( 'content', 'px', 'percent' ), true ) ? $default_label_width_mode : 'content' );
		$default_label_size_max = 'px' === $default_label_width_mode ? 300 : 100;
		update_post_meta( $post_id, '_ps_default_label_width', min( $default_label_size_max, max( 10, absint( $_POST['panda_seasons_default_label_width'] ?? 60 ) ) ) );
		update_post_meta( $post_id, '_ps_default_label_padding', 4 );
		self::update_css_color_meta( $post_id, '_ps_default_active_border_color', wp_unslash( $_POST['panda_seasons_default_active_border_color'] ?? 'rgba(255, 193, 7, 1)' ) );
		$default_label_font = sanitize_key( wp_unslash( $_POST['panda_seasons_default_label_font'] ?? 'inherit' ) );
		update_post_meta( $post_id, '_ps_default_label_font', in_array( $default_label_font, array( 'inherit', 'sans-serif', 'serif', 'monospace' ), true ) ? $default_label_font : 'inherit' );
		update_post_meta( $post_id, '_ps_default_label_font_size', min( 96, max( 8, absint( $_POST['panda_seasons_default_label_font_size'] ?? 20 ) ) ) );
		$default_font_weight = (string) ( $_POST['panda_seasons_default_label_font_weight'] ?? '700' );
		update_post_meta( $post_id, '_ps_default_label_font_weight', in_array( $default_font_weight, array( '400', '600', '700', '800' ), true ) ? $default_font_weight : '700' );
		$default_border_style = sanitize_key( wp_unslash( $_POST['panda_seasons_default_border_style'] ?? 'dashed' ) );
		update_post_meta( $post_id, '_ps_default_border_style', in_array( $default_border_style, array( 'none', 'solid', 'dashed', 'dotted' ), true ) ? $default_border_style : 'dashed' );
		update_post_meta( $post_id, '_ps_default_shadow_enabled', ! empty( $_POST['panda_seasons_default_shadow_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ps_default_shadow_blur', min( 80, absint( $_POST['panda_seasons_default_shadow_blur'] ?? 0 ) ) );
		self::update_css_color_meta( $post_id, '_ps_default_shadow_color', wp_unslash( $_POST['panda_seasons_default_shadow_color'] ?? 'rgba(0, 0, 0, 0.25)' ) );
		update_post_meta( $post_id, '_ps_default_closed_label_visible', ! empty( $_POST['panda_seasons_default_closed_label_visible'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ps_default_open_label_visible', ! empty( $_POST['panda_seasons_default_open_label_visible'] ) ? 1 : 0 );
		$default_shape = sanitize_key( wp_unslash( $_POST['panda_seasons_default_shape'] ?? 'rounded' ) );
		update_post_meta( $post_id, '_ps_default_shape', in_array( $default_shape, array( 'square', 'rounded', 'round' ), true ) ? $default_shape : 'rounded' );
		$default_opening_mode = sanitize_key( wp_unslash( $_POST['panda_seasons_default_opening_mode'] ?? 'auto-permanent' ) );
		update_post_meta( $post_id, '_ps_default_opening_mode', in_array( $default_opening_mode, array( 'manual-day', 'auto-day', 'auto-permanent' ), true ) ? $default_opening_mode : 'auto-permanent' );
		self::update_css_color_meta( $post_id, '_ps_popup_background', wp_unslash( $_POST['panda_seasons_popup_background'] ?? 'rgba(255, 255, 255, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_popup_text_color', wp_unslash( $_POST['panda_seasons_popup_text_color'] ?? 'rgba(31, 41, 55, 1)' ) );
		self::update_css_color_meta( $post_id, '_ps_popup_border_color', wp_unslash( $_POST['panda_seasons_popup_border_color'] ?? 'rgba(216, 221, 231, 1)' ) );
		update_post_meta( $post_id, '_ps_popup_border_width', min( 20, absint( $_POST['panda_seasons_popup_border_width'] ?? 0 ) ) );
		update_post_meta( $post_id, '_ps_popup_radius', min( 80, absint( $_POST['panda_seasons_popup_radius'] ?? 16 ) ) );
		update_post_meta( $post_id, '_ps_popup_padding', min( 100, absint( $_POST['panda_seasons_popup_padding'] ?? 32 ) ) );
		update_post_meta( $post_id, '_ps_popup_width', min( 1600, max( 320, absint( $_POST['panda_seasons_popup_width'] ?? 720 ) ) ) );
		update_post_meta( $post_id, '_ps_default_cta_enabled', ! empty( $_POST['panda_seasons_default_cta_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ps_default_cta_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_default_cta_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_default_cta_text', wp_kses_post( wp_unslash( $_POST['panda_seasons_default_cta_text'] ?? '' ) ) );
		$default_cta_type = sanitize_key( wp_unslash( $_POST['panda_seasons_default_cta_type'] ?? 'link' ) );
		if ( ! in_array( $default_cta_type, array( 'link', 'shortcode', 'contactpanda' ), true ) || ( 'contactpanda' === $default_cta_type && ! self::contactpanda_newsletter_available() ) ) $default_cta_type = 'link';
		update_post_meta( $post_id, '_ps_default_cta_type', $default_cta_type );
		update_post_meta( $post_id, '_ps_default_cta_label', sanitize_text_field( wp_unslash( $_POST['panda_seasons_default_cta_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_default_cta_url', esc_url_raw( wp_unslash( $_POST['panda_seasons_default_cta_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_default_cta_shortcode', sanitize_text_field( wp_unslash( $_POST['panda_seasons_default_cta_shortcode'] ?? '' ) ) );
		if ( isset( $_POST['panda_seasons_design_layers'] ) ) {
			$layers = json_decode( wp_unslash( $_POST['panda_seasons_design_layers'] ), true );
			update_post_meta( $post_id, '_ps_design_layers', self::sanitize_design_layers( $layers ) );
		}
		if ( self::contactpanda_available() ) update_post_meta( $post_id, '_ps_email_enabled', ! empty( $_POST['panda_seasons_email_enabled'] ) ? 1 : 0 );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_time'] ?? '09:00' ) );
		update_post_meta( $post_id, '_ps_email_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '09:00' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_subject'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_signup_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_text', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_text'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_consent', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_consent'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_button_label', sanitize_text_field( wp_unslash( $_POST['panda_seasons_signup_button_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_active_message', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_active_message'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_pending_message', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_pending_message'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_error_message', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_error_message'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_doi_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_doi_subject'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_doi_text', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_doi_text'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_doi_button_label', sanitize_text_field( wp_unslash( $_POST['panda_seasons_doi_button_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_confirmation_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_confirmation_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_confirmation_text', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_confirmation_text'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_doi_reminder_count', min( 3, absint( $_POST['panda_seasons_doi_reminder_count'] ?? 0 ) ) );
		$reminder_delays = array();
		foreach ( array_slice( (array) ( $_POST['panda_seasons_doi_reminder_delays'] ?? array() ), 0, 3 ) as $reminder_delay ) $reminder_delays[] = min( 30, max( 1, absint( $reminder_delay ) ) );
		update_post_meta( $post_id, '_ps_doi_reminder_delays', array_pad( $reminder_delays, 3, 7 ) );
		update_post_meta( $post_id, '_ps_doi_reminder_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_doi_reminder_subject'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_doi_reminder_text', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_doi_reminder_text'] ?? '' ) ) );
		$newsletter_mode = sanitize_key( wp_unslash( $_POST['panda_seasons_newsletter_mode'] ?? 'none' ) );
		if ( ! in_array( $newsletter_mode, array( 'none', 'link', 'shortcode', 'contactpanda' ), true ) || ( 'contactpanda' === $newsletter_mode && ! self::contactpanda_newsletter_available() ) ) $newsletter_mode = 'none';
		update_post_meta( $post_id, '_ps_newsletter_mode', $newsletter_mode );
		update_post_meta( $post_id, '_ps_newsletter_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_link_label', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_link_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_link_url', esc_url_raw( wp_unslash( $_POST['panda_seasons_newsletter_link_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_shortcode', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_shortcode'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_contact_tag', sanitize_text_field( wp_unslash( $_POST['panda_seasons_contact_tag'] ?? '' ) ) );
		return self::save_composer_doors( $post_id );
	}

	/** Save the complete visual editor without relying on the classic WordPress publish box. */
	public static function save_calendar_ajax() {
		check_ajax_referer( 'panda_seasons_save_calendar', 'panda_seasons_calendar_nonce' );
		$calendar_id = absint( $_POST['post_ID'] ?? $_POST['calendar_id'] ?? 0 );
		if ( ! $calendar_id || self::CALENDAR_POST_TYPE !== get_post_type( $calendar_id ) || ! current_user_can( 'edit_post', $calendar_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The calendar could not be saved. Please try again.', 'panda-seasons' ) ), 403 );
		}
		$title = sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) );
		if ( '' !== $title && $title !== get_the_title( $calendar_id ) ) {
			remove_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
			$result = wp_update_post( array( 'ID' => $calendar_id, 'post_title' => $title ), true );
			add_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
			if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		$door_ids = self::save_calendar( $calendar_id );
		clean_post_cache( $calendar_id );
		wp_send_json_success(
			array(
				'message' => __( 'Calendar saved.', 'panda-seasons' ),
				'doorIds' => array_values( array_map( 'absint', (array) $door_ids ) ),
				'savedAt' => current_time( 'mysql' ),
			)
		);
	}

	private static function save_composer_doors( $calendar_id ) {
		$saved_door_ids = array();
		if ( ! isset( $_POST['panda_seasons_door_layout'] ) ) return $saved_door_ids;
		$items = json_decode( wp_unslash( $_POST['panda_seasons_door_layout'] ), true );
		if ( ! is_array( $items ) ) return $saved_door_ids;
		$event_options = self::event_options();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) continue;
			$door_id = absint( $item['id'] ?? 0 );
			if ( $door_id && self::DOOR_POST_TYPE !== get_post_type( $door_id ) ) continue;
			if ( $door_id && absint( wp_get_post_parent_id( $door_id ) ) !== absint( $calendar_id ) ) continue;
			if ( $door_id && ! current_user_can( 'edit_post', $door_id ) ) continue;

			if ( ! empty( $item['deleted'] ) ) {
				if ( $door_id && current_user_can( 'delete_post', $door_id ) ) wp_trash_post( $door_id );
				continue;
			}

			$number    = max( 1, min( 366, absint( $item['number'] ?? 1 ) ) );
			$title     = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$post_data = array(
				'post_type'    => self::DOOR_POST_TYPE,
				'post_parent'  => absint( $calendar_id ),
				'post_title'   => $title ?: sprintf( __( 'Door %d', 'panda-seasons' ), $number ),
				'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
			);
			if ( $door_id ) {
				$post_data['ID'] = $door_id;
				$result          = wp_update_post( wp_slash( $post_data ), true );
			} else {
				$post_data['post_status'] = 'publish';
				$result                   = wp_insert_post( wp_slash( $post_data ), true );
			}
			if ( is_wp_error( $result ) || ! $result ) continue;
			$door_id = absint( $result );

			update_post_meta( $door_id, '_ps_door_number', $number );
			$content_type = sanitize_key( (string) ( $item['contentType'] ?? 'general' ) );
			update_post_meta( $door_id, '_ps_content_type', in_array( $content_type, array( 'general', 'video', 'product', 'quiz' ), true ) ? $content_type : 'general' );
				update_post_meta( $door_id, '_ps_content_heading', sanitize_text_field( (string) ( $item['contentHeading'] ?? '' ) ) );
			$date = sanitize_text_field( (string) ( $item['unlockDate'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
			$time = sanitize_text_field( (string) ( $item['unlockTime'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '' );
			$opening_mode = sanitize_key( (string) ( $item['openingMode'] ?? '' ) );
			if ( ! in_array( $opening_mode, array( 'manual-day', 'auto-day', 'auto-permanent' ), true ) ) $opening_mode = 'auto-permanent';
			update_post_meta( $door_id, '_ps_opening_mode', $opening_mode );
			update_post_meta( $door_id, '_ps_email_subject', sanitize_text_field( (string) ( $item['emailSubject'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_email_content', wp_kses_post( (string) ( $item['emailContent'] ?? '' ) ) );
			self::update_attachment_meta( $door_id, '_ps_media_id', $item['mediaId'] ?? 0 );
			self::update_attachment_meta( $door_id, '_ps_download_id', $item['downloadId'] ?? 0 );
			update_post_meta( $door_id, '_ps_download_label', sanitize_text_field( (string) ( $item['downloadLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_button_label', sanitize_text_field( (string) ( $item['buttonLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_button_url', esc_url_raw( (string) ( $item['buttonUrl'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_product_id', self::valid_related_post( $item['productId'] ?? 0, array( 'product' ) ) );
			update_post_meta( $door_id, '_ps_coupon_id', self::valid_related_post( $item['couponId'] ?? 0, array( 'shop_coupon' ) ) );
			$cta_mode = sanitize_key( (string) ( $item['ctaMode'] ?? 'custom' ) );
			update_post_meta( $door_id, '_ps_cta_mode', in_array( $cta_mode, array( 'default', 'custom' ), true ) ? $cta_mode : 'custom' );
			update_post_meta( $door_id, '_ps_cta_enabled', ! empty( $item['ctaEnabled'] ) ? 1 : 0 );
			update_post_meta( $door_id, '_ps_cta_title', sanitize_text_field( (string) ( $item['ctaTitle'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_cta_text', wp_kses_post( (string) ( $item['ctaText'] ?? '' ) ) );
			$cta_type = sanitize_key( (string) ( $item['ctaType'] ?? 'link' ) );
			update_post_meta( $door_id, '_ps_cta_type', in_array( $cta_type, array( 'link', 'shortcode', 'contactpanda' ), true ) ? $cta_type : 'link' );
			update_post_meta( $door_id, '_ps_cta_label', sanitize_text_field( (string) ( $item['ctaLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_cta_url', esc_url_raw( (string) ( $item['ctaUrl'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_cta_shortcode', sanitize_text_field( (string) ( $item['ctaShortcode'] ?? '' ) ) );
			$event_id = absint( $item['eventId'] ?? 0 );
			update_post_meta( $door_id, '_ps_event_id', $event_id && isset( $event_options[ $event_id ] ) ? $event_id : 0 );
			update_post_meta( $door_id, '_ps_closed_label', sanitize_text_field( (string) ( $item['closedLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_open_label', sanitize_text_field( (string) ( $item['openLabel'] ?? '' ) ) );
			self::update_css_color_meta( $door_id, '_ps_closed_background', $item['closedBg'] ?? '' );
			self::update_css_color_meta( $door_id, '_ps_closed_color', $item['closedColor'] ?? '' );
			self::update_css_color_meta( $door_id, '_ps_open_background', $item['openBg'] ?? '' );
			self::update_css_color_meta( $door_id, '_ps_open_color', $item['openColor'] ?? '' );
			foreach ( array( 'closed', 'open' ) as $state ) {
				update_post_meta( $door_id, '_ps_' . $state . '_label_visible', ! empty( $item[ $state . 'LabelVisible' ] ) ? 1 : 0 );
				$font = sanitize_key( (string) ( $item[ $state . 'Font' ] ?? 'inherit' ) );
				update_post_meta( $door_id, '_ps_' . $state . '_font', in_array( $font, array( 'inherit', 'sans-serif', 'serif', 'monospace' ), true ) ? $font : 'inherit' );
				update_post_meta( $door_id, '_ps_' . $state . '_font_size', min( 96, max( 8, absint( $item[ $state . 'FontSize' ] ?? 20 ) ) ) );
				$weight = (string) ( $item[ $state . 'FontWeight' ] ?? '700' );
				update_post_meta( $door_id, '_ps_' . $state . '_font_weight', in_array( $weight, array( '400', '600', '700', '800' ), true ) ? $weight : '700' );
				$align = sanitize_key( (string) ( $item[ $state . 'TextAlign' ] ?? 'center' ) );
				update_post_meta( $door_id, '_ps_' . $state . '_text_align', in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center' );
				self::update_css_color_meta( $door_id, '_ps_' . $state . '_border_color', $item[ $state . 'BorderColor' ] ?? 'rgba(255, 255, 255, 0.9)' );
				update_post_meta( $door_id, '_ps_' . $state . '_border_width', min( 20, absint( $item[ $state . 'BorderWidth' ] ?? 2 ) ) );
				$border_style = sanitize_key( (string) ( $item[ $state . 'BorderStyle' ] ?? 'dashed' ) );
				update_post_meta( $door_id, '_ps_' . $state . '_border_style', in_array( $border_style, array( 'none', 'solid', 'dashed', 'dotted' ), true ) ? $border_style : 'dashed' );
				self::update_css_color_meta( $door_id, '_ps_' . $state . '_label_background', $item[ $state . 'LabelBg' ] ?? 'rgba(10, 18, 30, 0.76)' );
				self::update_css_color_meta( $door_id, '_ps_' . $state . '_label_border_color', $item[ $state . 'LabelBorderColor' ] ?? 'rgba(255, 255, 255, 1)' );
				update_post_meta( $door_id, '_ps_' . $state . '_label_border_width', min( 20, absint( $item[ $state . 'LabelBorderWidth' ] ?? 1 ) ) );
					$label_shape = sanitize_key( (string) ( $item[ $state . 'LabelShape' ] ?? 'rounded' ) );
					update_post_meta( $door_id, '_ps_' . $state . '_label_shape', in_array( $label_shape, array( 'rectangle', 'rounded', 'square', 'round' ), true ) ? $label_shape : 'rounded' );
					update_post_meta( $door_id, '_ps_' . $state . '_label_position', self::sanitize_image_position( $item[ $state . 'LabelPosition' ] ?? 'center' ) );
					$label_width_mode = sanitize_key( (string) ( $item[ $state . 'LabelWidthMode' ] ?? 'content' ) );
					update_post_meta( $door_id, '_ps_' . $state . '_label_width_mode', in_array( $label_width_mode, array( 'content', 'px', 'percent' ), true ) ? $label_width_mode : 'content' );
					$label_size_max = 'px' === $label_width_mode ? 300 : 100;
				update_post_meta( $door_id, '_ps_' . $state . '_label_width', min( $label_size_max, max( 10, absint( $item[ $state . 'LabelWidth' ] ?? 60 ) ) ) );
					update_post_meta( $door_id, '_ps_' . $state . '_label_padding', 4 );
			}
			$shape = sanitize_key( (string) ( $item['shape'] ?? 'rounded' ) );
			update_post_meta( $door_id, '_ps_shape', in_array( $shape, array( 'square', 'rounded', 'round' ), true ) ? $shape : 'rounded' );
			$open_effect = sanitize_key( (string) ( $item['openEffect'] ?? 'inherit' ) );
			update_post_meta( $door_id, '_ps_open_effect', in_array( $open_effect, array( 'inherit', 'none', 'door-left', 'door-right', 'page' ), true ) ? $open_effect : 'inherit' );
			update_post_meta( $door_id, '_ps_shadow_enabled', ! empty( $item['shadowEnabled'] ) ? 1 : 0 );
			update_post_meta( $door_id, '_ps_shadow_blur', min( 80, absint( $item['shadowBlur'] ?? 0 ) ) );
			self::update_css_color_meta( $door_id, '_ps_shadow_color', $item['shadowColor'] ?? 'rgba(0, 0, 0, 0.25)' );
				self::update_attachment_meta( $door_id, '_ps_door_image_id', $item['imageId'] ?? 0 );
				update_post_meta( $door_id, '_ps_door_image_position', self::sanitize_image_position( $item['imagePosition'] ?? 'center' ) );
			self::update_css_color_meta( $door_id, '_ps_closed_image_overlay', $item['closedImageOverlay'] ?? 'rgba(0, 0, 0, 0)' );
			self::update_css_color_meta( $door_id, '_ps_open_image_overlay', $item['openImageOverlay'] ?? 'rgba(0, 0, 0, 0)' );
			update_post_meta( $door_id, '_ps_quiz_enabled', ! empty( $item['quizEnabled'] ) ? 1 : 0 );
			update_post_meta( $door_id, '_ps_quiz_success', sanitize_text_field( (string) ( $item['quizSuccess'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_quiz_failure', sanitize_text_field( (string) ( $item['quizFailure'] ?? '' ) ) );
			$questions = array();
			foreach ( array_slice( (array) ( $item['quizQuestions'] ?? array() ), 0, 20 ) as $question ) {
				if ( ! is_array( $question ) ) continue;
				$question_answers = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $question['answers'] ?? array() ) ), 'strlen' ) ), 0, 20 );
				$question_text = sanitize_text_field( (string) ( $question['question'] ?? '' ) );
				if ( ! $question_text || count( $question_answers ) < 2 ) continue;
				$questions[] = array( 'question' => $question_text, 'answers' => $question_answers, 'correct' => min( count( $question_answers ), max( 1, absint( $question['correct'] ?? 1 ) ) ) );
			}
			update_post_meta( $door_id, '_ps_quiz_questions', $questions );
			update_post_meta( $door_id, '_ps_position_desktop', self::sanitize_position( $item['desktop'] ?? array() ) );
			update_post_meta( $door_id, '_ps_position_mobile', self::sanitize_position( $item['mobile'] ?? array() ) );
			$saved_door_ids[] = $door_id;
		}
		return $saved_door_ids;
	}

	public static function save_editor_door_ajax() {
		check_ajax_referer( 'panda_seasons_save_editor_door', 'nonce' );
		$calendar_id = absint( $_POST['calendar_id'] ?? 0 );
		if ( ! $calendar_id || self::CALENDAR_POST_TYPE !== get_post_type( $calendar_id ) || ! current_user_can( 'edit_post', $calendar_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to save this calendar.', 'panda-seasons' ) ), 403 );
		}
		$door = json_decode( wp_unslash( $_POST['door'] ?? '' ), true );
		if ( ! is_array( $door ) ) wp_send_json_error( array( 'message' => __( 'The door data is invalid.', 'panda-seasons' ) ), 400 );
		$door_id = absint( $door['id'] ?? 0 );
		if ( $door_id && ( self::DOOR_POST_TYPE !== get_post_type( $door_id ) || absint( wp_get_post_parent_id( $door_id ) ) !== $calendar_id || ! current_user_can( 'edit_post', $door_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to save this door.', 'panda-seasons' ) ), 403 );
		}
		$_POST['panda_seasons_door_layout'] = wp_slash( wp_json_encode( array( $door ) ) );
		$saved_ids = self::save_composer_doors( $calendar_id );
		$saved_id  = absint( $saved_ids[0] ?? 0 );
		if ( ! $saved_id ) wp_send_json_error( array( 'message' => __( 'The door could not be saved.', 'panda-seasons' ) ), 500 );
		$media_id = absint( get_post_meta( $saved_id, '_ps_media_id', true ) );
		wp_send_json_success(
			array(
				'doorId'    => $saved_id,
				'mediaId'   => $media_id,
				'mediaUrl'  => $media_id ? ( wp_get_attachment_url( $media_id ) ?: '' ) : '',
				'mediaType' => self::attachment_kind( $media_id ),
				'message'   => __( 'Door saved.', 'panda-seasons' ),
			)
		);
	}

	private static function update_attachment_meta( $door_id, $meta_key, $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		update_post_meta( $door_id, $meta_key, $attachment_id && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0 );
	}

	private static function sanitize_design_layers( $layers ) {
		$clean = array();
		foreach ( array_slice( is_array( $layers ) ? $layers : array(), 0, 100 ) as $index => $layer ) {
			if ( ! is_array( $layer ) ) continue;
			$type = sanitize_key( (string) ( $layer['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'surface', 'image', 'text', 'color' ), true ) ) continue;
			if ( in_array( $type, array( 'image', 'color' ), true ) ) $type = 'surface';
			$image_id = absint( $layer['imageId'] ?? 0 );
			if ( $image_id && 'attachment' !== get_post_type( $image_id ) ) $image_id = 0;
			$background = trim( (string) ( $layer['background'] ?? 'rgba(255, 255, 255, 0)' ) );
			$color = trim( (string) ( $layer['color'] ?? 'rgba(31, 41, 55, 1)' ) );
			$shadow_color = trim( (string) ( $layer['shadowColor'] ?? 'rgba(0, 0, 0, 0.25)' ) );
			if ( ! sanitize_hex_color( $background ) && ! preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0(?:\.\d+)?|1(?:\.0+)?))?\s*\)$/i', $background ) ) $background = 'rgba(255, 255, 255, 0)';
			if ( ! sanitize_hex_color( $color ) && ! preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0(?:\.\d+)?|1(?:\.0+)?))?\s*\)$/i', $color ) ) $color = 'rgba(31, 41, 55, 1)';
			if ( ! sanitize_hex_color( $shadow_color ) && ! preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0(?:\.\d+)?|1(?:\.0+)?))?\s*\)$/i', $shadow_color ) ) $shadow_color = 'rgba(0, 0, 0, 0.25)';
			$clean[] = array(
				'id'         => sanitize_key( (string) ( $layer['id'] ?? 'layer-' . ( $index + 1 ) ) ),
				'type'       => $type,
				'text'       => wp_kses_post( (string) ( $layer['text'] ?? '' ) ),
				'imageId'    => $image_id,
				'imagePosition' => self::sanitize_image_position( $layer['imagePosition'] ?? 'center' ),
				'imageFit'    => in_array( (string) ( $layer['imageFit'] ?? 'cover' ), array( 'cover', 'contain' ), true ) ? (string) $layer['imageFit'] : 'cover',
				'background' => $background,
				'color'      => $color,
				'fontSize'   => min( 160, max( 8, absint( $layer['fontSize'] ?? 24 ) ) ),
				'padding'    => min( 100, max( 0, absint( $layer['padding'] ?? 8 ) ) ),
				'z'          => min( 999, max( 0, absint( $layer['z'] ?? 1 ) ) ),
				'url'        => esc_url_raw( (string) ( $layer['url'] ?? '' ) ),
				'radius'     => min( 200, absint( $layer['radius'] ?? 0 ) ),
				'shadowEnabled' => ! empty( $layer['shadowEnabled'] ),
				'shadowBlur' => min( 80, absint( $layer['shadowBlur'] ?? 0 ) ),
				'shadowColor' => $shadow_color,
				'desktop'    => self::sanitize_position( $layer['desktop'] ?? array() ),
				'mobile'     => self::sanitize_position( $layer['mobile'] ?? array() ),
			);
		}
		return $clean;
	}

	private static function valid_related_post( $post_id, $allowed_types ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) return 0;
		$allowed_types = array_filter( array_map( 'sanitize_key', (array) $allowed_types ) );
		return in_array( get_post_type( $post_id ), $allowed_types, true ) ? $post_id : 0;
	}

	private static function update_color_meta( $door_id, $meta_key, $color ) {
		$color = sanitize_hex_color( (string) $color );
		update_post_meta( $door_id, $meta_key, $color ?: '' );
	}

	private static function update_css_color_meta( $door_id, $meta_key, $color ) {
		$color = trim( (string) $color );
		if ( sanitize_hex_color( $color ) ) {
			update_post_meta( $door_id, $meta_key, $color );
			return;
		}
		if ( preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0(?:\.\d+)?|1(?:\.0+)?))?\s*\)$/i', $color ) ) {
			update_post_meta( $door_id, $meta_key, $color );
			return;
		}
		update_post_meta( $door_id, $meta_key, '' );
	}

	private static function editor_background_color( $door_id, $state, $fallback ) {
		return (string) get_post_meta( $door_id, '_ps_' . $state . '_background', true ) ?: $fallback;
	}

	public static function save_door( $post_id ) {
		if ( ! self::can_save( $post_id, 'panda_seasons_door_nonce', 'panda_seasons_save_door' ) ) return;
		$calendar = absint( $_POST['panda_seasons_calendar_id'] ?? 0 );
		if ( $calendar && (int) wp_get_post_parent_id( $post_id ) !== $calendar ) {
			remove_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_parent' => $calendar ) );
			add_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
		}
		update_post_meta( $post_id, '_ps_door_number', max( 1, absint( $_POST['panda_seasons_door_number'] ?? 1 ) ) );
		$date = sanitize_text_field( wp_unslash( $_POST['panda_seasons_unlock_date'] ?? '' ) );
		update_post_meta( $post_id, '_ps_unlock_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_unlock_time'] ?? '' ) );
		update_post_meta( $post_id, '_ps_unlock_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_door_email_subject'] ?? '' ) ) );
		if ( $calendar && absint( get_post_field( 'post_parent', $post_id ) ) !== $calendar ) {
			remove_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_parent' => $calendar ) );
			add_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
		}
	}

	private static function can_save( $post_id, $field, $action ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return false;
		if ( empty( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) return false;
		return current_user_can( 'edit_post', $post_id );
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		$is_dashboard = 'toplevel_page_panda-seasons-dashboard' === $hook;
		$is_editor = $screen && in_array( $screen->post_type, array( self::CALENDAR_POST_TYPE, self::DOOR_POST_TYPE ), true );
		if ( ! $is_dashboard && ! $is_editor ) return;
		if ( $is_editor ) wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'panda-seasons-admin', PANDA_SEASONS_URL . 'assets/editor.css', $is_editor ? array( 'wp-color-picker' ) : array(), PANDA_SEASONS_VERSION );
		if ( ! $is_editor ) return;
		wp_enqueue_media();
		$admin_dependencies = array( 'jquery', 'wp-color-picker' );
		$shared_ui_file     = WP_PLUGIN_DIR . '/contactpanda/assets/contactpanda-admin-ui.js';
		if ( file_exists( $shared_ui_file ) ) {
			wp_enqueue_script( 'contactpanda-admin-ui', plugins_url( 'contactpanda/assets/contactpanda-admin-ui.js' ), array( 'wp-api-fetch' ), (string) filemtime( $shared_ui_file ), true );
			wp_localize_script(
				'contactpanda-admin-ui',
				'CONTACTPANDA_ADMIN_UI',
				array(
					'themeColors' => array_values( array_filter( array_column( self::theme_colors(), 'color' ) ) ),
					'i18n'        => array(
						'chooseColor'  => __( 'Select color', 'panda-seasons' ),
						'colorValue'   => __( 'Color value', 'panda-seasons' ),
						'themeColors'  => __( 'Theme and template colors', 'panda-seasons' ),
						'invalidColor' => __( 'Enter a valid HEX, RGB or RGBA color.', 'panda-seasons' ),
						'dropImage'    => __( 'Click or drop an image here', 'panda-seasons' ),
						'chooseImage'  => __( 'Select image', 'panda-seasons' ),
						'useImage'     => __( 'Use image', 'panda-seasons' ),
						'removeImage'  => __( 'Remove image', 'panda-seasons' ),
						'uploading'    => __( 'Uploading image…', 'panda-seasons' ),
						'uploadFailed' => __( 'The image could not be uploaded.', 'panda-seasons' ),
					),
				)
			);
			$admin_dependencies[] = 'contactpanda-admin-ui';
		}
		wp_enqueue_script( 'panda-seasons-admin', PANDA_SEASONS_URL . 'assets/admin.js', $admin_dependencies, PANDA_SEASONS_VERSION, true );
		wp_localize_script(
			'panda-seasons-admin',
			'pandaSeasonsAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'calendarId'     => get_the_ID(),
				'saveDoorNonce'  => wp_create_nonce( 'panda_seasons_save_editor_door' ),
				'uploadUrl'      => admin_url( 'async-upload.php' ),
				'uploadNonce'    => wp_create_nonce( 'media-form' ),
				'deleteConfirm'  => __( 'Move this door to the trash? You can restore it from the door list.', 'panda-seasons' ),
				'untitledDoor'   => __( 'Door %d', 'panda-seasons' ),
				'untitledPreview' => __( 'Door preview', 'panda-seasons' ),
				'selectMedia'    => __( 'Select image, video or audio', 'panda-seasons' ),
				'selectImage'    => __( 'Select image', 'panda-seasons' ),
				'noImageSelected' => __( 'No image selected', 'panda-seasons' ),
					'imageDropHint'    => __( 'Select image or drop it here', 'panda-seasons' ),
				'selectDownload' => __( 'Select download file', 'panda-seasons' ),
				'mediaDropHint'   => __( 'Drag an image, video or audio file here or select it from the media library.', 'panda-seasons' ),
				'questionLabel'   => __( 'Question', 'panda-seasons' ),
				'answersLabel'    => __( 'Answers (one per line)', 'panda-seasons' ),
				'correctLabel'    => __( 'Correct answer number', 'panda-seasons' ),
				'remove'          => __( 'Remove', 'panda-seasons' ),
				'alpha'           => __( 'Transparency', 'panda-seasons' ),
				'openColorPicker' => __( 'Open color picker', 'panda-seasons' ),
				'globalColors'    => __( 'Global theme colors', 'panda-seasons' ),
				'globalColorsTooltip' => __( 'Global color settings', 'panda-seasons' ),
				'clear'           => __( 'Clear', 'panda-seasons' ),
				'duplicateDoor'   => __( 'Duplicate door', 'panda-seasons' ),
				'deleteDoor'      => __( 'Delete door', 'panda-seasons' ),
				'duplicateElement' => __( 'Duplicate element', 'panda-seasons' ),
				'deleteElement'    => __( 'Delete element', 'panda-seasons' ),
				'deleteElementConfirm' => __( 'Delete this element?', 'panda-seasons' ),
				'editDoorTitle'   => __( 'Edit door %1$s – %2$s', 'panda-seasons' ),
				'savingDoor'      => __( 'Saving door…', 'panda-seasons' ),
				'doorSaved'       => __( 'Door saved.', 'panda-seasons' ),
				'doorSaveFailed'  => __( 'The door could not be saved. Please try again.', 'panda-seasons' ),
				'elementApplied'  => __( 'Changes applied. Save the calendar to store them permanently.', 'panda-seasons' ),
				'dimensionsApplied' => __( 'Dimensions applied to all doors in this layout.', 'panda-seasons' ),
				'unsavedChanges'  => __( 'Unsaved changes', 'panda-seasons' ),
				'savingCalendar'  => __( 'Saving calendar…', 'panda-seasons' ),
				'calendarSaved'   => __( 'Calendar saved.', 'panda-seasons' ),
				'calendarSaveFailed' => __( 'The calendar could not be saved. Please try again.', 'panda-seasons' ),
				'saveBeforePreview' => __( 'There are unsaved changes. Save them before opening the preview?', 'panda-seasons' ),
				'saveBeforeTemplate' => __( 'Save the calendar before creating a template.', 'panda-seasons' ),
				'calendarStatusTitle' => __( 'Calendar status', 'panda-seasons' ),
				'calendarTitle'   => __( 'Calendar title', 'panda-seasons' ),
				'editCalendar'    => __( 'Edit calendar', 'panda-seasons' ),
				'generalSettings' => __( 'General defaults', 'panda-seasons' ),
				'emailSettings'   => __( 'Registration and email delivery', 'panda-seasons' ),
				'applyDefaults'   => __( 'Apply global settings to existing doors', 'panda-seasons' ),
				'defaultsTitle'   => __( 'Apply global settings', 'panda-seasons' ),
				'defaultsHelp'    => __( 'Choose each setting that may replace existing individual values.', 'panda-seasons' ),
				'selectAll'       => __( 'Select all', 'panda-seasons' ),
				'selectNone'      => __( 'Select none', 'panda-seasons' ),
				'overwrite'       => __( 'Overwrite', 'panda-seasons' ),
				'setting'         => __( 'Setting', 'panda-seasons' ),
				'oldValue'        => __( 'Old value', 'panda-seasons' ),
				'newValue'        => __( 'New value', 'panda-seasons' ),
				'differentValues' => __( 'Different individual values', 'panda-seasons' ),
				'cancel'          => __( 'Cancel', 'panda-seasons' ),
				'applySelected'   => __( 'Apply selected values', 'panda-seasons' ),
				'yes'             => __( 'Yes', 'panda-seasons' ),
				'no'              => __( 'No', 'panda-seasons' ),
				'diameterPx'      => __( 'Diameter (px)', 'panda-seasons' ),
				'diameterPercent' => __( 'Diameter (%)', 'panda-seasons' ),
				'widthPercent'    => __( 'Width (%)', 'panda-seasons' ),
					'widthPx'         => __( 'Width (px)', 'panda-seasons' ),
					'applyAppearanceConfirm' => __( 'Only appearance and style settings will be applied to all doors. Contents, media, links, products, quiz questions, call-to-action settings and dates remain unchanged.', 'panda-seasons' ),
			)
		);
	}

}
