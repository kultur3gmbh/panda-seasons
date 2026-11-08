<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Free_Plugin {
	const CALENDAR_POST_TYPE = 'panda_season';
	const DOOR_POST_TYPE     = 'panda_door';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_shortcode( 'panda_seasons', array( __CLASS__, 'shortcode' ) );
		if ( ! is_admin() ) return;
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'screen_options_show_screen', array( __CLASS__, 'hide_calendar_screen_options' ), 10, 2 );
		add_filter( 'parent_file', array( __CLASS__, 'admin_parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'admin_submenu_file' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'mark_free_calendar' ), 10, 3 );
		add_action( 'admin_post_panda_seasons_free_preview', array( __CLASS__, 'preview_page' ) );
		add_filter( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_columns', array( __CLASS__, 'calendar_columns' ) );
		add_action( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'calendar_column' ), 10, 2 );
	}

	public static function mark_free_calendar( $post_id, $post, $update ) {
		if ( ! $update && $post instanceof WP_Post && self::CALENDAR_POST_TYPE === $post->post_type ) {
			update_post_meta( $post_id, '_ps_origin_edition', 'free' );
		}
	}

	public static function register_post_types() {
		register_post_type(
			self::CALENDAR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Panda Seasons', 'panda-seasons-free' ),
					'singular_name' => __( 'Calendar', 'panda-seasons-free' ),
					'add_new_item'  => __( 'Add calendar', 'panda-seasons-free' ),
					'edit_item'     => __( 'Edit calendar', 'panda-seasons-free' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
		register_post_type(
			self::DOOR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Doors', 'panda-seasons-free' ),
					'singular_name' => __( 'Door', 'panda-seasons-free' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'supports'     => array( 'title', 'editor' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function admin_menu() {
		add_menu_page(
			__( 'Panda Seasons', 'panda-seasons-free' ),
			__( 'Panda Seasons', 'panda-seasons-free' ),
			'edit_posts',
			'panda-seasons-dashboard',
			array( __CLASS__, 'dashboard_page' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Dashboard', 'panda-seasons-free' ),
			__( 'Dashboard', 'panda-seasons-free' ),
			'edit_posts',
			'panda-seasons-dashboard',
			array( __CLASS__, 'dashboard_page' )
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Calendars', 'panda-seasons-free' ),
			__( 'Calendars', 'panda-seasons-free' ),
			'edit_posts',
			'edit.php?post_type=' . self::CALENDAR_POST_TYPE
		);
		add_submenu_page(
			'panda-seasons-dashboard',
			__( 'Add calendar', 'panda-seasons-free' ),
			__( 'Add calendar', 'panda-seasons-free' ),
			'edit_posts',
			'post-new.php?post_type=' . self::CALENDAR_POST_TYPE
		);
	}

	public static function admin_parent_file( $parent_file ) {
		$screen = get_current_screen();
		return $screen && self::CALENDAR_POST_TYPE === $screen->post_type ? 'panda-seasons-dashboard' : $parent_file;
	}

	public static function admin_submenu_file( $submenu_file ) {
		$screen = get_current_screen();
		return $screen && self::CALENDAR_POST_TYPE === $screen->post_type ? 'edit.php?post_type=' . self::CALENDAR_POST_TYPE : $submenu_file;
	}

	public static function hide_calendar_screen_options( $show, $screen ) {
		return $screen && self::CALENDAR_POST_TYPE === $screen->post_type ? false : $show;
	}

	public static function dashboard_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to view the Panda Seasons dashboard.', 'panda-seasons-free' ) );
		$calendars = get_posts(
			array(
				'post_type'      => self::CALENDAR_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 250,
				'no_found_rows'  => true,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$pro_url   = 'https://mycontactpanda.com/panda-seasons/';
		$templates = self::pro_template_previews();
		?>
		<div class="wrap panda-seasons-free-dashboard">
			<section class="panda-seasons-free-dashboard__hero">
				<div><h1><?php esc_html_e( 'Panda Seasons', 'panda-seasons-free' ); ?></h1><p><?php esc_html_e( 'Create simple seasonal calendars with responsive doors and scheduled text content.', 'panda-seasons-free' ); ?></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::CALENDAR_POST_TYPE ) ); ?>"><?php esc_html_e( 'Create calendar', 'panda-seasons-free' ); ?></a></div>
				<div class="panda-seasons-free-dashboard__hero-calendar" aria-hidden="true"><span>24</span><span>7</span><span>18</span><span>3</span><span>12</span><span>21</span></div>
			</section>
			<section class="panda-seasons-free-dashboard__section">
				<h2><?php esc_html_e( 'Your calendars', 'panda-seasons-free' ); ?></h2>
				<?php if ( ! $calendars ) : ?><div class="panda-seasons-free-dashboard__empty"><p><?php esc_html_e( 'No calendar has been created yet.', 'panda-seasons-free' ); ?></p></div><?php else : ?>
				<div class="panda-seasons-free-dashboard__cards">
					<?php foreach ( $calendars as $calendar ) :
						$cover_id = absint( get_post_meta( $calendar->ID, '_ps_cover_desktop', true ) );
						$cover = get_the_post_thumbnail_url( $calendar->ID, 'medium_large' );
						if ( ! $cover ) $cover = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium_large' ) : '';
						$door_count = count( self::doors( $calendar->ID ) );
					?>
					<article class="panda-seasons-free-dashboard-card">
						<a class="panda-seasons-free-dashboard-card__preview" href="<?php echo esc_url( get_edit_post_link( $calendar->ID ) ); ?>"><?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt=""><?php else : ?><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php endif; ?><span class="panda-seasons-free-dashboard-card__doors" aria-hidden="true"><i>4</i><i>11</i><i>19</i><i>23</i></span></a>
						<div class="panda-seasons-free-dashboard-card__body">
							<h3><a href="<?php echo esc_url( get_edit_post_link( $calendar->ID ) ); ?>"><?php
								$title = get_the_title( $calendar->ID );
								if ( ! $title ) {
									/* translators: %d: calendar post ID. */
									$title = sprintf( __( 'Calendar #%d', 'panda-seasons-free' ), $calendar->ID );
								}
								echo esc_html( $title );
							?></a></h3>
							<p><?php
								/* translators: %d: number of calendar doors. */
								echo esc_html( sprintf( _n( '%d door', '%d doors', $door_count, 'panda-seasons-free' ), $door_count ) );
							?></p>
							<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $calendar->ID ) ); ?>"><?php esc_html_e( 'Edit', 'panda-seasons-free' ); ?></a>
						</div>
					</article>
					<?php endforeach; ?>
				</div><?php endif; ?>
			</section>
			<section class="panda-seasons-free-dashboard__section panda-seasons-free-dashboard__pro">
				<div class="panda-seasons-free-dashboard__pro-copy">
					<p class="panda-seasons-free-dashboard__eyebrow">Panda Seasons Pro</p>
					<h2><?php esc_html_e( 'Need more options for your campaign?', 'panda-seasons-free' ); ?></h2>
					<ul class="panda-seasons-free-dashboard__hero-benefits"><li><?php esc_html_e( 'Individual images and content for every door', 'panda-seasons-free' ); ?></li><li><?php esc_html_e( 'Products, daily discounts and direct shop links', 'panda-seasons-free' ); ?></li><li><?php esc_html_e( 'Newsletter forms, double opt-in and daily calendar emails', 'panda-seasons-free' ); ?></li></ul>
					<div class="panda-seasons-free-dashboard__actions"><a class="button button-primary" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Discover Panda Seasons Pro', 'panda-seasons-free' ); ?></a></div>
				</div>
				<div class="panda-seasons-free-dashboard__pro-visual" aria-hidden="true"><span class="is-large">24</span><span>7</span><span>18</span><span>3</span><span class="is-wide">12</span></div>
			</section>
			<?php if ( $templates ) : ?>
			<section class="panda-seasons-free-dashboard__section panda-seasons-free-dashboard__templates-showcase">
				<div class="panda-seasons-free-dashboard__section-heading">
					<div><p class="panda-seasons-free-dashboard__eyebrow">Panda Seasons Pro</p><h2><?php esc_html_e( 'Examples from the Pro template library', 'panda-seasons-free' ); ?></h2></div>
				</div>
				<div class="panda-seasons-free-dashboard__template-gallery">
					<?php foreach ( $templates as $template ) : ?>
					<article class="panda-seasons-free-dashboard-template">
						<a class="panda-seasons-free-dashboard-template__preview" href="<?php echo esc_url( $template['thumbnail'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $template['thumbnail'] ); ?>" alt="<?php echo esc_attr( $template['title'] ); ?>"></a>
						<div><strong><?php echo esc_html( $template['title'] ); ?></strong><small><?php echo esc_html( $template['description'] ); ?></small><a class="button" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Discover Panda Seasons Pro', 'panda-seasons-free' ); ?></a></div>
					</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function pro_template_previews() {
		$cache_key = 'panda_seasons_free_template_previews_v1';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;
		$manifest_url = 'https://raw.githubusercontent.com/kultur3gmbh/panda-seasons/main/template-library/manifest.json';
		$response     = wp_safe_remote_get( $manifest_url, array( 'timeout' => 8, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || 1 !== absint( $data['format_version'] ?? 0 ) || empty( $data['templates'] ) || ! is_array( $data['templates'] ) ) return array();
		$base      = preg_replace( '~/[^/]*$~', '/', $manifest_url );
		$templates = array();
		foreach ( $data['templates'] as $item ) {
			$id          = sanitize_key( $item['id'] ?? '' );
			$title       = sanitize_text_field( $item['title'] ?? '' );
			$thumbnail   = ltrim( (string) ( $item['thumbnail'] ?? '' ), '/' );
			$description = sanitize_text_field( $item['description'] ?? '' );
			if ( ! $id || ! $title || ! $thumbnail || preg_match( '~(?:^|/)\.\.(?:/|$)~', $thumbnail ) ) continue;
			$templates[] = array( 'id' => $id, 'title' => $title, 'description' => $description, 'thumbnail' => esc_url_raw( $base . $thumbnail ) );
		}
		set_transient( $cache_key, $templates, 6 * HOUR_IN_SECONDS );
		return $templates;
	}

	public static function meta_boxes() {
		remove_meta_box( 'postimagediv', self::CALENDAR_POST_TYPE, 'side' );
		add_meta_box(
			'panda-seasons-free-editor',
			__( 'Visual calendar editor', 'panda-seasons-free' ),
			array( __CLASS__, 'editor_box' ),
			self::CALENDAR_POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		$is_dashboard = 'toplevel_page_panda-seasons-dashboard' === $hook;
		if ( ! $is_dashboard && ( ! $screen || self::CALENDAR_POST_TYPE !== $screen->post_type ) ) return;
		if ( $is_dashboard ) {
			wp_enqueue_style( 'panda-seasons-free-ui', PANDA_SEASONS_FREE_URL . 'assets/free-ui.css', array(), PANDA_SEASONS_FREE_VERSION );
			wp_enqueue_style( 'panda-seasons-free-template-gallery', PANDA_SEASONS_FREE_URL . 'assets/template-gallery.css', array( 'panda-seasons-free-ui' ), PANDA_SEASONS_FREE_VERSION );
			wp_enqueue_style( 'contactpanda-admin-ui-contract', PANDA_SEASONS_FREE_URL . 'assets/contactpanda-admin-ui.css', array( 'panda-seasons-free-ui' ), PANDA_SEASONS_FREE_VERSION );
			$shared_ui = WP_PLUGIN_DIR . '/contactpanda/assets/contactpanda-admin-ui.js';
			wp_enqueue_script(
				'contactpanda-admin-ui',
				file_exists( $shared_ui ) ? plugins_url( 'contactpanda/assets/contactpanda-admin-ui.js' ) : PANDA_SEASONS_FREE_URL . 'assets/contactpanda-admin-ui.js',
				array( 'wp-api-fetch' ),
				file_exists( $shared_ui ) ? (string) filemtime( $shared_ui ) : PANDA_SEASONS_FREE_VERSION,
				true
			);
			return;
		}
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'panda-seasons-free-admin', PANDA_SEASONS_FREE_URL . 'assets/admin.css', array( 'wp-color-picker' ), PANDA_SEASONS_FREE_VERSION );
		wp_enqueue_style( 'panda-seasons-free-ui', PANDA_SEASONS_FREE_URL . 'assets/free-ui.css', array( 'panda-seasons-free-admin' ), PANDA_SEASONS_FREE_VERSION );
		wp_enqueue_style( 'contactpanda-admin-ui-contract', PANDA_SEASONS_FREE_URL . 'assets/contactpanda-admin-ui.css', array( 'panda-seasons-free-ui' ), PANDA_SEASONS_FREE_VERSION );
		$shared_editor_ui  = WP_PLUGIN_DIR . '/contactpanda/assets/panda-editor-ui.js';
		$shared_editor_css = WP_PLUGIN_DIR . '/contactpanda/assets/panda-editor-ui.css';
		wp_enqueue_style( 'panda-editor-ui', file_exists( $shared_editor_css ) ? plugins_url( 'contactpanda/assets/panda-editor-ui.css' ) : PANDA_SEASONS_FREE_URL . 'assets/panda-editor-ui.css', array( 'contactpanda-admin-ui-contract' ), file_exists( $shared_editor_css ) ? (string) filemtime( $shared_editor_css ) : PANDA_SEASONS_FREE_VERSION );
		wp_enqueue_script( 'panda-editor-ui', file_exists( $shared_editor_ui ) ? plugins_url( 'contactpanda/assets/panda-editor-ui.js' ) : PANDA_SEASONS_FREE_URL . 'assets/panda-editor-ui.js', array(), file_exists( $shared_editor_ui ) ? (string) filemtime( $shared_editor_ui ) : PANDA_SEASONS_FREE_VERSION, true );
		$theme_colors = array_values( array_filter( array_column( self::theme_colors(), 'color' ) ) );
		$dependencies = array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-resizable', 'wp-editor', 'wp-color-picker', 'panda-editor-ui' );
		$shared_ui    = WP_PLUGIN_DIR . '/contactpanda/assets/contactpanda-admin-ui.js';
		if ( file_exists( $shared_ui ) ) {
			wp_enqueue_script( 'contactpanda-admin-ui', plugins_url( 'contactpanda/assets/contactpanda-admin-ui.js' ), array( 'wp-api-fetch' ), (string) filemtime( $shared_ui ), true );
			wp_localize_script(
				'contactpanda-admin-ui',
				'CONTACTPANDA_ADMIN_UI',
				array(
					'themeColors' => $theme_colors,
					'i18n'        => array(
						'chooseColor'  => __( 'Select color', 'panda-seasons-free' ),
						'colorValue'   => __( 'Color value', 'panda-seasons-free' ),
						'themeColors'  => __( 'Theme colors', 'panda-seasons-free' ),
						'invalidColor' => __( 'Enter a valid HEX, RGB or RGBA color.', 'panda-seasons-free' ),
						'dropImage'    => __( 'Select an image or drop it here', 'panda-seasons-free' ),
						'chooseImage'  => __( 'Select image', 'panda-seasons-free' ),
						'useImage'     => __( 'Use this image', 'panda-seasons-free' ),
						'removeImage'  => __( 'Remove image', 'panda-seasons-free' ),
						'changeImage'  => __( 'Change image', 'panda-seasons-free' ),
						'editImage'    => __( 'Edit image', 'panda-seasons-free' ),
						'selectedImage'=> __( 'Selected image', 'panda-seasons-free' ),
						'imageFormats' => __( 'JPG, PNG, WebP or SVG', 'panda-seasons-free' ),
						'externalImage'=> __( 'Link external image', 'panda-seasons-free' ),
						'externalImagePrompt' => __( 'Enter the external image URL:', 'panda-seasons-free' ),
						'uploading'    => __( 'Uploading image…', 'panda-seasons-free' ),
						'uploadFailed' => __( 'The image could not be uploaded.', 'panda-seasons-free' ),
					),
				)
			);
		} else {
			wp_enqueue_script( 'contactpanda-admin-ui', PANDA_SEASONS_FREE_URL . 'assets/contactpanda-admin-ui.js', array( 'wp-api-fetch' ), PANDA_SEASONS_FREE_VERSION, true );
			wp_localize_script(
				'contactpanda-admin-ui',
				'CONTACTPANDA_ADMIN_UI',
				array(
					'themeColors' => $theme_colors,
					'i18n'        => array(
						'chooseColor'  => __( 'Select color', 'panda-seasons-free' ),
						'colorValue'   => __( 'Color value', 'panda-seasons-free' ),
						'themeColors'  => __( 'Theme colors', 'panda-seasons-free' ),
						'invalidColor' => __( 'Enter a valid HEX, RGB or RGBA color.', 'panda-seasons-free' ),
						'dropImage'    => __( 'Select an image or drop it here', 'panda-seasons-free' ),
						'chooseImage'  => __( 'Select image', 'panda-seasons-free' ),
						'useImage'     => __( 'Use this image', 'panda-seasons-free' ),
						'removeImage'  => __( 'Remove image', 'panda-seasons-free' ),
						'changeImage'  => __( 'Change image', 'panda-seasons-free' ),
						'editImage'    => __( 'Edit image', 'panda-seasons-free' ),
						'selectedImage'=> __( 'Selected image', 'panda-seasons-free' ),
						'imageFormats' => __( 'JPG, PNG, WebP or SVG', 'panda-seasons-free' ),
						'externalImage'=> __( 'Link external image', 'panda-seasons-free' ),
						'externalImagePrompt' => __( 'Enter the external image URL:', 'panda-seasons-free' ),
						'uploading'    => __( 'Uploading image…', 'panda-seasons-free' ),
						'uploadFailed' => __( 'The image could not be uploaded.', 'panda-seasons-free' ),
					),
				)
			);
		}
		$dependencies[] = 'contactpanda-admin-ui';
		wp_enqueue_script( 'panda-seasons-free-admin', PANDA_SEASONS_FREE_URL . 'assets/admin.js', $dependencies, PANDA_SEASONS_FREE_VERSION, true );
		wp_localize_script(
			'panda-seasons-free-admin',
			'pandaSeasonsFreeAdmin',
			array(
				'addDoor'       => __( 'Add door', 'panda-seasons-free' ),
				'saved'         => __( 'Calendar saved.', 'panda-seasons-free' ),
				'confirmDelete' => __( 'Delete this door?', 'panda-seasons-free' ),
				'selectImage'   => __( 'Select calendar background', 'panda-seasons-free' ),
				'useImage'      => __( 'Use this image', 'panda-seasons-free' ),
				'imageDropHint' => __( 'Select an image or drop it here', 'panda-seasons-free' ),
				'uploadUrl'     => admin_url( 'async-upload.php' ),
				'uploadNonce'   => wp_create_nonce( 'media-form' ),
				'openColorPicker' => __( 'Open color picker', 'panda-seasons-free' ),
				'alpha'         => __( 'Transparency', 'panda-seasons-free' ),
				'themeColors'   => __( 'Theme colors', 'panda-seasons-free' ),
				'themeColorValues' => $theme_colors,
				'confirmTemplate' => __( 'Apply this template? Existing text content is retained.', 'panda-seasons-free' ),
				'desktop'        => __( 'Desktop', 'panda-seasons-free' ),
				'mobile'         => __( 'Mobile', 'panda-seasons-free' ),
			)
		);
	}

	/** Return the palette of the active WordPress theme and supported builders. */
	private static function theme_colors() {
		$colors = array();
		if ( class_exists( 'ContactPanda_Basic_Newsletters' ) && is_callable( array( 'ContactPanda_Basic_Newsletters', 'detected_theme_colors' ) ) ) {
			self::append_theme_palette( $colors, ContactPanda_Basic_Newsletters::detected_theme_colors(), 'ContactPanda' );
		}
		$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
		$palettes = $settings['color']['palette'] ?? array();
		foreach ( array( 'theme', 'default', 'custom' ) as $group ) self::append_theme_palette( $colors, $palettes[ $group ] ?? array(), 'WordPress' );
		self::append_theme_palette( $colors, get_theme_support( 'editor-color-palette' ), 'Theme' );
		$background = sanitize_hex_color( '#' . ltrim( (string) get_theme_mod( 'background_color', '' ), '#' ) );
		$text       = sanitize_hex_color( '#' . ltrim( (string) get_theme_mod( 'header_textcolor', '' ), '#' ) );
		if ( $background ) $colors[ strtolower( $background ) ] = array( 'color' => $background, 'name' => __( 'Theme background', 'panda-seasons-free' ) );
		if ( $text ) $colors[ strtolower( $text ) ] = array( 'color' => $text, 'name' => __( 'Theme text', 'panda-seasons-free' ) );

		$fusion = (array) get_option( 'fusion_options', array() );
		foreach ( array( 'global_colors_palette', 'fusion_global_colors', 'color_palette' ) as $key ) self::append_theme_palette( $colors, $fusion[ $key ] ?? array(), 'Avada' );
		$kit_id = absint( get_option( 'elementor_active_kit' ) );
		$kit    = $kit_id ? (array) get_post_meta( $kit_id, '_elementor_page_settings', true ) : array();
		self::append_theme_palette( $colors, $kit['system_colors'] ?? array(), 'Elementor' );
		self::append_theme_palette( $colors, $kit['custom_colors'] ?? array(), 'Elementor' );
		$divi = (array) get_option( 'et_divi', array() );
		foreach ( array( 'accent_color', 'header_color', 'font_color', 'primary_nav_font_color', 'secondary_nav_font_color', 'footer_bg' ) as $key ) {
			if ( ! empty( $divi[ $key ] ) ) self::append_theme_palette( $colors, array( array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'color' => $divi[ $key ] ) ), 'Divi' );
		}
		if ( ! $colors ) self::append_theme_palette( $colors, array( '#000000', '#ffffff', '#2271b1', '#3858e9' ), 'WordPress' );
		return array_values( apply_filters( 'panda_seasons_free_theme_colors', $colors ) );
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
				if ( ! $color ) { self::append_theme_palette( $colors, $item, $source ); continue; }
			} else {
				$color = sanitize_hex_color( $item );
				$name  = sanitize_text_field( is_string( $key ) ? $key : $color );
			}
			if ( $color ) $colors[ strtolower( $color ) ] = array( 'color' => $color, 'name' => trim( $source . ' – ' . $name, ' –' ) );
		}
	}

	private static function doors( $calendar_id ) {
		return get_posts( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Door number is the persisted calendar order and is bounded to the supported 366 doors.
			array(
				'post_type'      => self::DOOR_POST_TYPE,
				'post_parent'    => absint( $calendar_id ),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 366,
				'no_found_rows'  => true,
				'meta_key'       => '_ps_door_number',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
	}

	private static function position( $door_id, $layout, $index, $count ) {
		$value = get_post_meta( $door_id, '_ps_position_' . $layout, true );
		if ( is_array( $value ) && isset( $value['x'], $value['y'], $value['w'], $value['h'] ) ) return self::sanitize_position( $value );
		$columns = 'mobile' === $layout ? 3 : 6;
		$rows = max( 1, (int) ceil( max( 1, $count ) / $columns ) );
		return array(
			'x' => round( ( $index % $columns ) * ( 100 / $columns ), 4 ),
			'y' => round( floor( $index / $columns ) * ( 100 / $rows ), 4 ),
			'w' => round( 100 / $columns, 4 ),
			'h' => round( 100 / $rows, 4 ),
		);
	}

	private static function editor_doors( $calendar_id ) {
		$doors = self::doors( $calendar_id );
		$data  = array();
		foreach ( $doors as $index => $door ) {
			$data[] = array(
				'id'          => (int) $door->ID,
				'number'      => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: $index + 1,
				'unlockDate'  => (string) get_post_meta( $door->ID, '_ps_unlock_date', true ),
				'unlockTime'  => (string) get_post_meta( $door->ID, '_ps_unlock_time', true ),
				'heading'     => (string) get_post_meta( $door->ID, '_ps_content_heading', true ),
				'content'     => (string) $door->post_content,
				'closedBg'    => (string) get_post_meta( $door->ID, '_ps_closed_background', true ),
				'closedColor' => (string) get_post_meta( $door->ID, '_ps_closed_color', true ),
				'closedBorderColor' => (string) get_post_meta( $door->ID, '_ps_closed_border_color', true ),
				'closedBorderWidth' => (string) get_post_meta( $door->ID, '_ps_closed_border_width', true ),
				'closedBorderStyle' => (string) get_post_meta( $door->ID, '_ps_closed_border_style', true ),
				'openBg'      => (string) get_post_meta( $door->ID, '_ps_open_background', true ),
				'openColor'   => (string) get_post_meta( $door->ID, '_ps_open_color', true ),
				'openBorderColor' => (string) get_post_meta( $door->ID, '_ps_open_border_color', true ),
				'openBorderWidth' => (string) get_post_meta( $door->ID, '_ps_open_border_width', true ),
				'openBorderStyle' => (string) get_post_meta( $door->ID, '_ps_open_border_style', true ),
				'shape'       => (string) get_post_meta( $door->ID, '_ps_shape', true ) ?: 'rounded',
				'desktop'     => self::position( $door->ID, 'desktop', $index, count( $doors ) ),
				'mobile'      => self::position( $door->ID, 'mobile', $index, count( $doors ) ),
			);
		}
		return $data;
	}

	public static function editor_box( $post ) {
		wp_nonce_field( 'panda_seasons_free_save_' . $post->ID, 'panda_seasons_free_nonce' );
		$cover_id = absint( get_post_meta( $post->ID, '_ps_cover_desktop', true ) );
		$cover    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
		$ratio    = (float) get_post_meta( $post->ID, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $post->ID, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$background = self::color( get_post_meta( $post->ID, '_ps_cover_desktop_background', true ), '#f4f4f4' );
		$closed_bg = self::color( get_post_meta( $post->ID, '_ps_default_closed_background', true ), 'rgba(44,62,80,.86)' );
		$closed_color = self::color( get_post_meta( $post->ID, '_ps_default_closed_text_color', true ), '#ffffff' );
		$open_bg = self::color( get_post_meta( $post->ID, '_ps_default_open_background', true ), 'rgba(255,255,255,.80)' );
		$open_color = self::color( get_post_meta( $post->ID, '_ps_default_open_text_color', true ), '#1f2937' );
		$closed_border_color = self::color( get_post_meta( $post->ID, '_ps_default_closed_border_color', true ), '#ffffff' );
		$open_border_color = self::color( get_post_meta( $post->ID, '_ps_default_open_border_color', true ), '#ffffff' );
		$closed_border_width = min( 20, absint( get_post_meta( $post->ID, '_ps_default_closed_border_width', true ) ) );
		$open_border_width = min( 20, absint( get_post_meta( $post->ID, '_ps_default_open_border_width', true ) ) );
		$default_border_style = self::border_style( get_post_meta( $post->ID, '_ps_default_border_style', true ) );
		$default_shape = self::shape( get_post_meta( $post->ID, '_ps_default_shape', true ) );
		$doors = self::editor_doors( $post->ID );
		$preview = wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_free_preview&calendar=' . $post->ID ), 'panda_seasons_free_preview_' . $post->ID );
		?>
		<div class="panda-seasons-free-editor panda-editor" data-panda-editor data-seasons-free-editor data-cover-url="<?php echo esc_url( $cover ); ?>">
			<div class="panda-seasons-free-publish-slot" data-free-publish-slot></div>
			<div class="panda-seasons-free-settings-card">
				<div class="contactpanda-image-picker<?php echo $cover ? ' has-image' : ''; ?>" data-cover-picker>
					<button type="button" class="contactpanda-image-picker-drop" data-select-cover>
						<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Select an image or drop it here', 'panda-seasons-free' ); ?></strong>
						<span class="contactpanda-image-picker-preview" data-cover-preview><?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt=""><?php endif; ?></span>
						<small class="contactpanda-image-picker-status" aria-live="polite"></small>
					</button>
					<button type="button" class="contactpanda-image-picker-remove" data-remove-cover<?php echo $cover ? '' : ' hidden'; ?> aria-label="<?php esc_attr_e( 'Remove background image', 'panda-seasons-free' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
					<input type="hidden" name="panda_seasons_free_cover" value="<?php echo esc_attr( $cover_id ); ?>" data-cover-id>
				</div>
				<div class="panda-seasons-free-settings-grid">
					<label><span><?php esc_html_e( 'Desktop aspect ratio', 'panda-seasons-free' ); ?></span><input type="number" min="0.2" max="5" step="0.01" name="panda_seasons_free_ratio" value="<?php echo esc_attr( $ratio ); ?>" data-ratio="desktop"></label>
					<label><span><?php esc_html_e( 'Mobile aspect ratio', 'panda-seasons-free' ); ?></span><input type="number" min="0.2" max="5" step="0.01" name="panda_seasons_free_mobile_ratio" value="<?php echo esc_attr( $mobile_ratio ); ?>" data-ratio="mobile"></label>
					<label><span><?php esc_html_e( 'Background color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_background" value="<?php echo esc_attr( $background ); ?>" data-calendar-color></label>
				</div>
			</div>
			<div class="panda-seasons-free-defaults">
				<h3><?php esc_html_e( 'Default door colors', 'panda-seasons-free' ); ?></h3>
				<div class="panda-seasons-free-settings-grid">
					<label><span><?php esc_html_e( 'Default door shape', 'panda-seasons-free' ); ?></span><select name="panda_seasons_free_default_shape" data-default="shape"><option value="square"<?php selected( $default_shape, 'square' ); ?>><?php esc_html_e( 'Square', 'panda-seasons-free' ); ?></option><option value="rounded"<?php selected( $default_shape, 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'panda-seasons-free' ); ?></option><option value="round"<?php selected( $default_shape, 'round' ); ?>><?php esc_html_e( 'Round', 'panda-seasons-free' ); ?></option></select></label>
				</div>
				<div class="panda-seasons-free-settings-grid is-four">
					<label><span><?php esc_html_e( 'Closed background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_closed_bg" value="<?php echo esc_attr( $closed_bg ); ?>" data-default="closedBg"></label>
					<label><span><?php esc_html_e( 'Closed text', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_closed_color" value="<?php echo esc_attr( $closed_color ); ?>" data-default="closedColor"></label>
					<label><span><?php esc_html_e( 'Closed border color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_closed_border_color" value="<?php echo esc_attr( $closed_border_color ); ?>" data-default="closedBorderColor"></label>
					<label><span><?php esc_html_e( 'Closed border width (px)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_free_closed_border_width" value="<?php echo esc_attr( $closed_border_width ); ?>" data-default="closedBorderWidth"></label>
					<label><span><?php esc_html_e( 'Opened background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_open_bg" value="<?php echo esc_attr( $open_bg ); ?>" data-default="openBg"></label>
					<label><span><?php esc_html_e( 'Opened text', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_open_color" value="<?php echo esc_attr( $open_color ); ?>" data-default="openColor"></label>
					<label><span><?php esc_html_e( 'Opened border color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_open_border_color" value="<?php echo esc_attr( $open_border_color ); ?>" data-default="openBorderColor"></label>
					<label><span><?php esc_html_e( 'Opened border width (px)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="20" name="panda_seasons_free_open_border_width" value="<?php echo esc_attr( $open_border_width ); ?>" data-default="openBorderWidth"></label>
					<label><span><?php esc_html_e( 'Border style', 'panda-seasons-free' ); ?></span><select name="panda_seasons_free_border_style" data-default="borderStyle"><option value="none"<?php selected( $default_border_style, 'none' ); ?>><?php esc_html_e( 'No border', 'panda-seasons-free' ); ?></option><option value="solid"<?php selected( $default_border_style, 'solid' ); ?>><?php esc_html_e( 'Solid', 'panda-seasons-free' ); ?></option><option value="dashed"<?php selected( $default_border_style, 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'panda-seasons-free' ); ?></option><option value="dotted"<?php selected( $default_border_style, 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'panda-seasons-free' ); ?></option></select></label>
				</div>
			</div>
			<div class="panda-seasons-free-templates">
				<h3><?php esc_html_e( 'Starter templates', 'panda-seasons-free' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Apply a ready-made layout and color scheme. Existing door content is retained.', 'panda-seasons-free' ); ?></p>
				<div class="panda-seasons-free-template-grid">
					<button type="button" class="panda-seasons-free-template" data-template="classic" draggable="true"><span class="panda-seasons-free-template__preview is-classic" aria-hidden="true"></span><strong><?php esc_html_e( 'Classic grid', 'panda-seasons-free' ); ?></strong></button>
					<button type="button" class="panda-seasons-free-template" data-template="modern" draggable="true"><span class="panda-seasons-free-template__preview is-modern" aria-hidden="true"></span><strong><?php esc_html_e( 'Modern circles', 'panda-seasons-free' ); ?></strong></button>
				</div>
			</div>
			<div class="panda-seasons-free-toolbar">
				<div><button type="button" class="button" data-layout="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-layout="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-state="closed" aria-pressed="true"><?php esc_html_e( 'All closed', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-state="open" aria-pressed="false"><?php esc_html_e( 'All opened', 'panda-seasons-free' ); ?></button></div>
				<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'panda-seasons-free' ); ?></a>
			</div>
			<p class="description"><?php esc_html_e( 'Drag doors to position them. Use the handle in the lower-right corner to resize them. Desktop and mobile positions are saved separately.', 'panda-seasons-free' ); ?></p>
			<div class="panda-seasons-free-workspace">
				<div class="panda-seasons-free-canvas-wrap" data-panda-editor-canvas><div class="panda-seasons-free-canvas" data-canvas></div></div>
				<aside class="panda-seasons-free-sidebar" data-panda-editor-sidebar>
					<div class="panda-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Calendar editor tools', 'panda-seasons-free' ); ?>">
						<button type="button" class="is-active" role="tab" aria-selected="true" data-panda-editor-tab="elements"><?php esc_html_e( 'Add elements', 'panda-seasons-free' ); ?></button>
						<button type="button" role="tab" aria-selected="false" data-panda-editor-tab="edit"><?php esc_html_e( 'Edit element', 'panda-seasons-free' ); ?></button>
						<button type="button" role="tab" aria-selected="false" data-panda-editor-tab="settings"><?php esc_html_e( 'Basic settings', 'panda-seasons-free' ); ?></button>
					</div>
					<section class="panda-seasons-free-elements-panel" data-panda-editor-panel="elements">
						<p class="description"><?php esc_html_e( 'Add a door and then position it directly on the calendar.', 'panda-seasons-free' ); ?></p>
						<button type="button" class="button" data-panda-editor-element data-add-door draggable="true"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add door', 'panda-seasons-free' ); ?></button>
					</section>
					<section class="panda-seasons-free-edit-panel" data-panda-editor-panel="edit" hidden>
						<div class="panda-seasons-free-editor-empty" data-inspector-empty>
							<span class="dashicons dashicons-edit" aria-hidden="true"></span>
							<h3><?php esc_html_e( 'Select a door', 'panda-seasons-free' ); ?></h3>
							<p><?php esc_html_e( 'Click a door in the calendar to edit its settings here.', 'panda-seasons-free' ); ?></p>
						</div>
				<aside class="panda-seasons-free-inspector" data-inspector hidden>
					<h3><?php esc_html_e( 'Edit door', 'panda-seasons-free' ); ?> <span data-door-title></span></h3>
					<section class="panda-seasons-free-position">
						<h4><?php esc_html_e( 'Position and size', 'panda-seasons-free' ); ?> – <span data-position-layout><?php esc_html_e( 'Desktop', 'panda-seasons-free' ); ?></span></h4>
						<p class="description"><?php esc_html_e( 'Move the door directly on the calendar or enter exact percentage values. Drag the blue handle in the lower-right corner to resize it.', 'panda-seasons-free' ); ?></p>
						<div class="panda-seasons-free-position-grid">
							<label><span><?php esc_html_e( 'Left (%)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="98" step="0.01" data-position-field="x"></label>
							<label><span><?php esc_html_e( 'Top (%)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="98" step="0.01" data-position-field="y"></label>
							<label><span><?php esc_html_e( 'Width (%)', 'panda-seasons-free' ); ?></span><input type="number" min="2" max="100" step="0.01" data-position-field="w"></label>
							<label><span><?php esc_html_e( 'Height (%)', 'panda-seasons-free' ); ?></span><input type="number" min="2" max="100" step="0.01" data-position-field="h"></label>
						</div>
					</section>
					<div class="panda-seasons-free-settings-grid">
						<label><span><?php esc_html_e( 'Door number', 'panda-seasons-free' ); ?></span><input type="number" min="1" max="999" data-door-field="number"></label>
						<label><span><?php esc_html_e( 'Door shape', 'panda-seasons-free' ); ?></span><select data-door-field="shape"><option value="square"><?php esc_html_e( 'Square', 'panda-seasons-free' ); ?></option><option value="rounded"><?php esc_html_e( 'Rounded', 'panda-seasons-free' ); ?></option><option value="round"><?php esc_html_e( 'Round', 'panda-seasons-free' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Unlock date', 'panda-seasons-free' ); ?></span><input type="date" data-door-field="unlockDate"></label>
						<label><span><?php esc_html_e( 'Unlock time', 'panda-seasons-free' ); ?></span><input type="time" data-door-field="unlockTime"></label>
					</div>
					<div class="panda-seasons-free-state-colors">
						<h4><?php esc_html_e( 'Closed door', 'panda-seasons-free' ); ?></h4><label><span><?php esc_html_e( 'Background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="closedBg"></label><label><span><?php esc_html_e( 'Text color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="closedColor"></label><label><span><?php esc_html_e( 'Border color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="closedBorderColor"></label><label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="20" data-door-field="closedBorderWidth"></label><label><span><?php esc_html_e( 'Border style', 'panda-seasons-free' ); ?></span><select data-door-field="closedBorderStyle"><option value="none"><?php esc_html_e( 'No border', 'panda-seasons-free' ); ?></option><option value="solid"><?php esc_html_e( 'Solid', 'panda-seasons-free' ); ?></option><option value="dashed"><?php esc_html_e( 'Dashed', 'panda-seasons-free' ); ?></option><option value="dotted"><?php esc_html_e( 'Dotted', 'panda-seasons-free' ); ?></option></select></label>
						<h4><?php esc_html_e( 'Opened door', 'panda-seasons-free' ); ?></h4><label><span><?php esc_html_e( 'Background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="openBg"></label><label><span><?php esc_html_e( 'Text color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="openColor"></label><label><span><?php esc_html_e( 'Border color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="openBorderColor"></label><label><span><?php esc_html_e( 'Border width (px)', 'panda-seasons-free' ); ?></span><input type="number" min="0" max="20" data-door-field="openBorderWidth"></label><label><span><?php esc_html_e( 'Border style', 'panda-seasons-free' ); ?></span><select data-door-field="openBorderStyle"><option value="none"><?php esc_html_e( 'No border', 'panda-seasons-free' ); ?></option><option value="solid"><?php esc_html_e( 'Solid', 'panda-seasons-free' ); ?></option><option value="dashed"><?php esc_html_e( 'Dashed', 'panda-seasons-free' ); ?></option><option value="dotted"><?php esc_html_e( 'Dotted', 'panda-seasons-free' ); ?></option></select></label>
					</div>
					<label class="panda-seasons-free-content-heading"><span><?php esc_html_e( 'Content heading', 'panda-seasons-free' ); ?></span><input type="text" data-door-field="heading"></label>
					<div class="panda-seasons-free-content-editor"><strong><?php esc_html_e( 'Text content', 'panda-seasons-free' ); ?></strong><?php wp_editor( '', 'panda_seasons_free_door_content', array( 'textarea_name' => 'panda_seasons_free_door_content_visual', 'textarea_rows' => 10, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div>
					<div class="panda-seasons-free-inspector-actions"><button type="button" class="button button-primary" data-apply-door><?php esc_html_e( 'Apply door', 'panda-seasons-free' ); ?></button><button type="button" class="button button-link-delete" data-delete-door><?php esc_html_e( 'Move door to trash', 'panda-seasons-free' ); ?></button></div>
				</aside>
					</section>
					<section class="panda-seasons-free-settings-panel" data-panda-editor-panel="settings" hidden></section>
				</aside>
			</div>
			<input type="hidden" name="panda_seasons_free_doors" data-doors-input>
			<input type="hidden" name="panda_seasons_free_deleted" data-deleted-input>
			<script type="application/json" data-doors-json><?php echo wp_json_encode( $doors ); ?></script>
		</div>
		<?php
	}

	public static function save_calendar( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		if ( ! isset( $_POST['panda_seasons_free_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_nonce'] ) ), 'panda_seasons_free_save_' . $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$cover = absint( $_POST['panda_seasons_free_cover'] ?? 0 );
		if ( $cover && 'attachment' !== get_post_type( $cover ) ) $cover = 0;
		$free_origin = 'free' === (string) get_post_meta( $post_id, '_ps_origin_edition', true );
		update_post_meta( $post_id, '_ps_cover_desktop', $cover );
		if ( $free_origin ) update_post_meta( $post_id, '_ps_cover_mobile', $cover );
		update_post_meta( $post_id, '_ps_aspect_ratio', self::ratio( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_ratio'] ?? '1.5' ) ), 1.5 ) );
		update_post_meta( $post_id, '_ps_mobile_aspect_ratio', self::ratio( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_mobile_ratio'] ?? '.75' ) ), .75 ) );
		$background = self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_background'] ?? '' ) ), '#f4f4f4' );
		update_post_meta( $post_id, '_ps_cover_desktop_background', $background );
		if ( $free_origin ) update_post_meta( $post_id, '_ps_cover_mobile_background', $background );
		$defaults = array(
			'_ps_default_closed_background' => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_closed_bg'] ?? '' ) ), 'rgba(44,62,80,.86)' ),
			'_ps_default_closed_text_color' => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_closed_color'] ?? '' ) ), '#ffffff' ),
			'_ps_default_open_background'   => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_open_bg'] ?? '' ) ), 'rgba(255,255,255,.80)' ),
			'_ps_default_open_text_color'   => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_open_color'] ?? '' ) ), '#1f2937' ),
			'_ps_default_closed_border_color' => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_closed_border_color'] ?? '' ) ), '#ffffff' ),
			'_ps_default_open_border_color' => self::color( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_open_border_color'] ?? '' ) ), '#ffffff' ),
			'_ps_default_closed_border_width' => min( 20, absint( $_POST['panda_seasons_free_closed_border_width'] ?? 0 ) ),
			'_ps_default_open_border_width' => min( 20, absint( $_POST['panda_seasons_free_open_border_width'] ?? 0 ) ),
		);
		$default_shape = self::shape( sanitize_key( wp_unslash( $_POST['panda_seasons_free_default_shape'] ?? 'rounded' ) ) );
		update_post_meta( $post_id, '_ps_default_shape', $default_shape );
		$default_border_style = self::border_style( sanitize_key( wp_unslash( $_POST['panda_seasons_free_border_style'] ?? 'none' ) ) );
		update_post_meta( $post_id, '_ps_default_border_style', $default_border_style );
		foreach ( $defaults as $key => $value ) update_post_meta( $post_id, $key, $value );

		// The JSON contains formatted door content. Every field is validated below before persistence.
		$items = json_decode( wp_unslash( $_POST['panda_seasons_free_doors'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $items ) ) $items = array();
		$valid_ids = wp_list_pluck( self::doors( $post_id ), 'ID' );
		foreach ( array_slice( $items, 0, 366 ) as $item ) {
			if ( ! is_array( $item ) ) continue;
			$door_id = absint( $item['id'] ?? 0 );
			$is_new  = ! $door_id || ! in_array( $door_id, $valid_ids, true );
			if ( $is_new ) {
				$door_id = wp_insert_post(
					array(
						'post_type'   => self::DOOR_POST_TYPE,
						'post_status' => 'publish',
						'post_parent' => $post_id,
						/* translators: %d: calendar door number. */
						'post_title'  => sprintf( __( 'Door %d', 'panda-seasons-free' ), absint( $item['number'] ?? 1 ) ),
					)
				);
			}
			if ( ! $door_id || is_wp_error( $door_id ) ) continue;
			$number = min( 999, max( 1, absint( $item['number'] ?? 1 ) ) );
			$post_update = array(
				'ID'           => $door_id,
				'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
			);
			if ( $is_new ) {
				/* translators: %d: calendar door number. */
				$post_update['post_title'] = sprintf( __( 'Door %d', 'panda-seasons-free' ), $number );
			}
			wp_update_post( wp_slash( $post_update ) );
			update_post_meta( $door_id, '_ps_door_number', $number );
			update_post_meta( $door_id, '_ps_unlock_date', self::date( $item['unlockDate'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_time', self::time( $item['unlockTime'] ?? '' ) );
			update_post_meta( $door_id, '_ps_content_heading', sanitize_text_field( (string) ( $item['heading'] ?? '' ) ) );
			if ( $is_new ) {
				update_post_meta( $door_id, '_ps_content_type', 'general' );
				update_post_meta( $door_id, '_ps_opening_mode', 'auto-permanent' );
			}
			if ( $is_new ) {
				update_post_meta( $door_id, '_ps_closed_label', (string) $number );
				update_post_meta( $door_id, '_ps_open_label', (string) $number );
			}
			update_post_meta( $door_id, '_ps_closed_background', self::color( $item['closedBg'] ?? '', $defaults['_ps_default_closed_background'] ) );
			update_post_meta( $door_id, '_ps_closed_color', self::color( $item['closedColor'] ?? '', $defaults['_ps_default_closed_text_color'] ) );
			update_post_meta( $door_id, '_ps_closed_border_color', self::color( $item['closedBorderColor'] ?? '', $defaults['_ps_default_closed_border_color'] ) );
			update_post_meta( $door_id, '_ps_closed_border_width', min( 20, absint( $item['closedBorderWidth'] ?? $defaults['_ps_default_closed_border_width'] ) ) );
			update_post_meta( $door_id, '_ps_closed_border_style', self::border_style( $item['closedBorderStyle'] ?? $default_border_style ) );
			update_post_meta( $door_id, '_ps_open_background', self::color( $item['openBg'] ?? '', $defaults['_ps_default_open_background'] ) );
			update_post_meta( $door_id, '_ps_open_color', self::color( $item['openColor'] ?? '', $defaults['_ps_default_open_text_color'] ) );
			update_post_meta( $door_id, '_ps_open_border_color', self::color( $item['openBorderColor'] ?? '', $defaults['_ps_default_open_border_color'] ) );
			update_post_meta( $door_id, '_ps_open_border_width', min( 20, absint( $item['openBorderWidth'] ?? $defaults['_ps_default_open_border_width'] ) ) );
			update_post_meta( $door_id, '_ps_open_border_style', self::border_style( $item['openBorderStyle'] ?? $default_border_style ) );
			update_post_meta( $door_id, '_ps_shape', self::shape( $item['shape'] ?? $default_shape ) );
			update_post_meta( $door_id, '_ps_position_desktop', self::sanitize_position( $item['desktop'] ?? array() ) );
			update_post_meta( $door_id, '_ps_position_mobile', self::sanitize_position( $item['mobile'] ?? array() ) );
			// Set neutral advanced styling only for doors created in Free. Existing
			// Pro-only styling remains untouched while the free edition is active.
			if ( $is_new ) {
				foreach ( array( 'closed', 'open' ) as $state ) {
					update_post_meta( $door_id, '_ps_' . $state . '_label_background', 'rgba(0,0,0,0)' );
					update_post_meta( $door_id, '_ps_' . $state . '_label_border_width', 0 );
					update_post_meta( $door_id, '_ps_' . $state . '_label_visible', 1 );
				}
				update_post_meta( $door_id, '_ps_shadow_enabled', 0 );
			}
		}
		$deleted = json_decode( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_deleted'] ?? '[]' ) ), true );
		foreach ( is_array( $deleted ) ? $deleted : array() as $door_id ) {
			$door_id = absint( $door_id );
			if ( $door_id && in_array( $door_id, $valid_ids, true ) ) wp_trash_post( $door_id );
		}
		self::generate_featured_preview( $post_id );
	}

	private static function ratio( $value, $fallback ) {
		$value = (float) $value;
		return $value >= .2 && $value <= 5 ? $value : $fallback;
	}

	private static function preview_rgba( $value, $fallback = array( 255, 255, 255, 1 ) ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $value, $match ) ) {
			$hex = 3 === strlen( $match[1] ) ? $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2] : $match[1];
			return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), 1 );
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([01]?(?:\.\d+)?))?\s*\)$/i', $value, $match ) ) return array( min( 255, (int) $match[1] ), min( 255, (int) $match[2] ), min( 255, (int) $match[3] ), isset( $match[4] ) && '' !== $match[4] ? max( 0, min( 1, (float) $match[4] ) ) : 1 );
		return $fallback;
	}

	private static function preview_color( $image, $value, $fallback ) {
		$rgba = self::preview_rgba( $value, $fallback );
		return imagecolorallocatealpha( $image, $rgba[0], $rgba[1], $rgba[2], 127 - (int) round( $rgba[3] * 127 ) );
	}

	private static function generate_featured_preview( $calendar_id ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! current_user_can( 'edit_post', $calendar_id ) ) return 0;
		$ratio = (float) get_post_meta( $calendar_id, '_ps_aspect_ratio', true );
		if ( $ratio <= 0 ) $ratio = 1.5;
		$width = 1200; $height = max( 400, min( 1600, (int) round( $width / $ratio ) ) );
		$canvas = imagecreatetruecolor( $width, $height ); imagesavealpha( $canvas, true ); imagealphablending( $canvas, true );
		imagefilledrectangle( $canvas, 0, 0, $width, $height, self::preview_color( $canvas, get_post_meta( $calendar_id, '_ps_cover_desktop_background', true ), array( 244, 244, 244, 1 ) ) );
		$cover_id = absint( get_post_meta( $calendar_id, '_ps_cover_desktop', true ) );
		if ( $cover_id ) {
			$path = get_attached_file( $cover_id );
			if ( $path && is_readable( $path ) ) {
				$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local media file.
				$source = $bytes ? @imagecreatefromstring( $bytes ) : false;
				if ( $source ) { imagecopyresampled( $canvas, $source, 0, 0, 0, 0, $width, $height, imagesx( $source ), imagesy( $source ) ); imagedestroy( $source ); }
			}
		}
		$doors = self::doors( $calendar_id );
		foreach ( $doors as $index => $door ) {
			$position = self::sanitize_position( get_post_meta( $door->ID, '_ps_position_desktop', true ) );
			$x = (int) round( $width * $position['x'] / 100 ); $y = (int) round( $height * $position['y'] / 100 ); $w = (int) round( $width * $position['w'] / 100 ); $h = (int) round( $height * $position['h'] / 100 );
			imagefilledrectangle( $canvas, $x, $y, $x + $w, $y + $h, self::preview_color( $canvas, get_post_meta( $door->ID, '_ps_closed_background', true ), array( 44, 62, 80, .86 ) ) );
			$number = (string) ( absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: $index + 1 );
			imagestring( $canvas, 5, $x + max( 4, (int) ( $w / 2 ) - strlen( $number ) * 4 ), $y + max( 4, (int) ( $h / 2 ) - 7 ), $number, self::preview_color( $canvas, get_post_meta( $door->ID, '_ps_closed_color', true ), array( 255, 255, 255, 1 ) ) );
		}
		$uploads = wp_upload_dir(); if ( ! empty( $uploads['error'] ) ) { imagedestroy( $canvas ); return 0; }
		$directory = trailingslashit( $uploads['basedir'] ) . 'panda-seasons-previews'; wp_mkdir_p( $directory ); $path = trailingslashit( $directory ) . 'calendar-' . absint( $calendar_id ) . '.png';
		if ( ! imagepng( $canvas, $path, 8 ) ) { imagedestroy( $canvas ); return 0; } imagedestroy( $canvas );
		$attachment_id = absint( get_post_meta( $calendar_id, '_ps_preview_attachment_id', true ) );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$attachment_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => sprintf( __( '%s preview', 'panda-seasons-free' ), get_the_title( $calendar_id ) ), 'post_status' => 'inherit' ), $path, $calendar_id );
			if ( is_wp_error( $attachment_id ) ) return 0;
			update_post_meta( $calendar_id, '_ps_preview_attachment_id', $attachment_id );
		} else update_attached_file( $attachment_id, $path );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) ); set_post_thumbnail( $calendar_id, $attachment_id );
		return $attachment_id;
	}

	private static function color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) || preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)$/i', $value ) || 'transparent' === strtolower( $value ) ) return $value;
		return $fallback;
	}

	private static function shape( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'square', 'rounded', 'round' ), true ) ? $value : 'rounded';
	}

	private static function border_style( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'none', 'solid', 'dashed', 'dotted' ), true ) ? $value : 'none';
	}

	private static function date( $value ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function time( $value ) {
		$value = (string) $value;
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	private static function sanitize_position( $value ) {
		$value = is_array( $value ) ? $value : array();
		$x = min( 100, max( 0, (float) ( $value['x'] ?? 0 ) ) );
		$y = min( 100, max( 0, (float) ( $value['y'] ?? 0 ) ) );
		$w = min( 100, max( 2, (float) ( $value['w'] ?? 15 ) ) );
		$h = min( 100, max( 2, (float) ( $value['h'] ?? 15 ) ) );
		if ( $x + $w > 100 ) $x = 100 - $w;
		if ( $y + $h > 100 ) $y = 100 - $h;
		return array( 'x' => round( $x, 4 ), 'y' => round( $y, 4 ), 'w' => round( $w, 4 ), 'h' => round( $h, 4 ) );
	}

	public static function register_frontend_assets() {
		wp_register_style( 'panda-seasons-free-additions', PANDA_SEASONS_FREE_URL . 'assets/calendar-additions.css', array(), PANDA_SEASONS_FREE_VERSION );
		wp_register_style( 'panda-seasons-free', PANDA_SEASONS_FREE_URL . 'assets/calendar.css', array( 'panda-seasons-free-additions' ), PANDA_SEASONS_FREE_VERSION );
		wp_register_script( 'panda-seasons-free', PANDA_SEASONS_FREE_URL . 'assets/frontend.js', array(), PANDA_SEASONS_FREE_VERSION, true );
	}

	private static function unlock_at( $door_id ) {
		$date = self::date( get_post_meta( $door_id, '_ps_unlock_date', true ) );
		if ( ! $date ) return null;
		$time = self::time( get_post_meta( $door_id, '_ps_unlock_time', true ) ) ?: '00:00';
		$value = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return $value ?: null;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'preview' => 0, 'show_title' => 'yes' ), $atts, 'panda_seasons' );
		$id = absint( $atts['id'] );
		$post = get_post( $id );
		$preview = ! empty( $atts['preview'] ) && current_user_can( 'edit_post', $id );
		if ( ! $post || self::CALENDAR_POST_TYPE !== $post->post_type || ( 'publish' !== $post->post_status && ! $preview ) ) return '';
		$doors = self::doors( $id );
		if ( ! $doors ) return '<p>' . esc_html__( 'This calendar does not have any doors yet.', 'panda-seasons-free' ) . '</p>';
		wp_enqueue_style( 'panda-seasons-free' );
		wp_enqueue_script( 'panda-seasons-free' );
		$cover_id = absint( get_post_meta( $id, '_ps_cover_desktop', true ) );
		$cover = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
		$ratio = (float) get_post_meta( $id, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $id, '_ps_mobile_aspect_ratio', true ) ?: .75;
		$background = self::color( get_post_meta( $id, '_ps_cover_desktop_background', true ), '#f4f4f4' );
		$default_closed_bg = self::color( get_post_meta( $id, '_ps_default_closed_background', true ), 'rgba(44,62,80,.86)' );
		$default_closed_color = self::color( get_post_meta( $id, '_ps_default_closed_text_color', true ), '#ffffff' );
		$default_open_bg = self::color( get_post_meta( $id, '_ps_default_open_background', true ), 'rgba(255,255,255,.80)' );
		$default_open_color = self::color( get_post_meta( $id, '_ps_default_open_text_color', true ), '#1f2937' );
		$default_closed_border_color = self::color( get_post_meta( $id, '_ps_default_closed_border_color', true ), '#ffffff' );
		$default_open_border_color = self::color( get_post_meta( $id, '_ps_default_open_border_color', true ), '#ffffff' );
		$default_closed_border_width = min( 20, absint( get_post_meta( $id, '_ps_default_closed_border_width', true ) ) );
		$default_open_border_width = min( 20, absint( get_post_meta( $id, '_ps_default_open_border_width', true ) ) );
		$default_border_style = self::border_style( get_post_meta( $id, '_ps_default_border_style', true ) );
		$default_shape = self::shape( get_post_meta( $id, '_ps_default_shape', true ) );
		$show_title = ! in_array( strtolower( (string) $atts['show_title'] ), array( '0', 'false', 'no', 'off' ), true );
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		ob_start();
		?>
		<section class="panda-seasons-free" style="--psf-ratio:<?php echo esc_attr( $ratio ); ?>;--psf-mobile-ratio:<?php echo esc_attr( $mobile_ratio ); ?>;--psf-background:<?php echo esc_attr( $background ); ?>;--psf-cover:<?php echo $cover ? 'url(&quot;' . esc_url( $cover ) . '&quot;)' : 'none'; ?>">
			<?php if ( $show_title ) : ?><h2 class="panda-seasons-free__title"><?php echo esc_html( get_the_title( $id ) ); ?></h2><?php endif; ?>
			<div class="panda-seasons-free__canvas" role="list">
				<?php foreach ( $doors as $index => $door ) :
					$unlock = self::unlock_at( $door->ID );
					$available = ! $unlock || $now >= $unlock;
					$number = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: $index + 1;
					$desktop = self::position( $door->ID, 'desktop', $index, count( $doors ) );
					$mobile = self::position( $door->ID, 'mobile', $index, count( $doors ) );
					$closed_bg = self::color( get_post_meta( $door->ID, '_ps_closed_background', true ), $default_closed_bg );
					$closed_color = self::color( get_post_meta( $door->ID, '_ps_closed_color', true ), $default_closed_color );
					$open_bg = self::color( get_post_meta( $door->ID, '_ps_open_background', true ), $default_open_bg );
					$open_color = self::color( get_post_meta( $door->ID, '_ps_open_color', true ), $default_open_color );
					$closed_border_color = self::color( get_post_meta( $door->ID, '_ps_closed_border_color', true ), $default_closed_border_color );
					$open_border_color = self::color( get_post_meta( $door->ID, '_ps_open_border_color', true ), $default_open_border_color );
					$closed_border_width = '' !== get_post_meta( $door->ID, '_ps_closed_border_width', true ) ? min( 20, absint( get_post_meta( $door->ID, '_ps_closed_border_width', true ) ) ) : $default_closed_border_width;
					$open_border_width = '' !== get_post_meta( $door->ID, '_ps_open_border_width', true ) ? min( 20, absint( get_post_meta( $door->ID, '_ps_open_border_width', true ) ) ) : $default_open_border_width;
					$closed_border_style = self::border_style( get_post_meta( $door->ID, '_ps_closed_border_style', true ) ?: $default_border_style );
					$open_border_style = self::border_style( get_post_meta( $door->ID, '_ps_open_border_style', true ) ?: $default_border_style );
					$shape = self::shape( get_post_meta( $door->ID, '_ps_shape', true ) ?: $default_shape );
					$style = '--psf-x:' . $desktop['x'] . '%;--psf-y:' . $desktop['y'] . '%;--psf-w:' . $desktop['w'] . '%;--psf-h:' . $desktop['h'] . '%;--psf-mx:' . $mobile['x'] . '%;--psf-my:' . $mobile['y'] . '%;--psf-mw:' . $mobile['w'] . '%;--psf-mh:' . $mobile['h'] . '%;--psf-closed-bg:' . $closed_bg . ';--psf-closed-color:' . $closed_color . ';--psf-open-bg:' . $open_bg . ';--psf-open-color:' . $open_color . ';--psf-closed-border-color:' . $closed_border_color . ';--psf-open-border-color:' . $open_border_color . ';--psf-closed-border-width:' . $closed_border_width . 'px;--psf-open-border-width:' . $open_border_width . 'px;--psf-closed-border-style:' . $closed_border_style . ';--psf-open-border-style:' . $open_border_style . ';';
					$heading = (string) get_post_meta( $door->ID, '_ps_content_heading', true );
					if ( '' === $heading ) {
						/* translators: %d: calendar door number. */
						$heading = sprintf( __( 'Door %d', 'panda-seasons-free' ), $number );
					}
					?>
					<article class="panda-seasons-free__door panda-seasons-free__door--<?php echo esc_attr( $shape ); ?> <?php echo $available ? 'is-available' : 'is-locked'; ?>" style="<?php echo esc_attr( $style ); ?>" role="listitem">
						<button type="button" aria-haspopup="dialog" aria-controls="panda-seasons-free-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>><span><?php echo esc_html( $number ); ?></span><span class="screen-reader-text"><?php
							if ( $available ) {
								/* translators: %d: calendar door number. */
								$door_state = sprintf( __( 'Open door %d', 'panda-seasons-free' ), $number );
							} else {
								/* translators: %d: calendar door number. */
								$door_state = sprintf( __( 'Door %d is not available yet', 'panda-seasons-free' ), $number );
							}
							echo esc_html( $door_state );
						?></span></button>
						<template id="panda-seasons-free-content-<?php echo esc_attr( $door->ID ); ?>"><h3><?php echo esc_html( $heading ); ?></h3><?php if ( $unlock ) : ?><p class="panda-seasons-free__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $unlock->getTimestamp(), wp_timezone() ) ); ?></p><?php endif; ?><div><?php
							// Core's content filter deliberately returns safe rendered post content.
							echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.Security.EscapeOutput.OutputNotEscaped
						?></div></template>
					</article>
				<?php endforeach; ?>
			</div>
			<div class="panda-seasons-free__modal" data-psf-modal hidden><div class="panda-seasons-free__backdrop" data-psf-close></div><div class="panda-seasons-free__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Calendar door', 'panda-seasons-free' ); ?>"><button type="button" class="panda-seasons-free__close" data-psf-close aria-label="<?php esc_attr_e( 'Close', 'panda-seasons-free' ); ?>">&times;</button><div data-psf-content></div></div></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function preview_page() {
		$id = absint( $_GET['calendar'] ?? 0 );
		check_admin_referer( 'panda_seasons_free_preview_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || self::CALENDAR_POST_TYPE !== get_post_type( $id ) ) wp_die( esc_html__( 'You are not allowed to preview this calendar.', 'panda-seasons-free' ) );
		nocache_headers();
		show_admin_bar( false );
		self::register_frontend_assets();
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head><body class="panda-seasons-free-preview"><?php echo self::shortcode( array( 'id' => $id, 'preview' => 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	public static function register_block() {
		wp_register_script( 'panda-seasons-free-block', PANDA_SEASONS_FREE_URL . 'assets/block.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), PANDA_SEASONS_FREE_VERSION, true );
		$choices = array();
		if ( is_admin() ) {
			foreach ( get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 200, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC' ) ) as $calendar ) $choices[] = array( 'label' => $calendar->post_title, 'value' => (int) $calendar->ID );
		}
		wp_localize_script( 'panda-seasons-free-block', 'pandaSeasonsFreeBlock', array( 'calendars' => $choices, 'selectCalendar' => __( 'Select calendar', 'panda-seasons-free' ), 'showTitle' => __( 'Show title', 'panda-seasons-free' ) ) );
		register_block_type(
			'panda-seasons/calendar',
			array(
				'api_version'     => 2,
				'editor_script'   => 'panda-seasons-free-block',
				'attributes'      => array( 'id' => array( 'type' => 'integer', 'default' => 0 ), 'showTitle' => array( 'type' => 'boolean', 'default' => true ) ),
				'render_callback' => static function ( $attributes ) { return self::shortcode( array( 'id' => absint( $attributes['id'] ?? 0 ), 'show_title' => ! empty( $attributes['showTitle'] ) ? 'yes' : 'no' ) ); },
			)
		);
	}

	public static function calendar_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Calendar', 'panda-seasons-free' ), 'doors' => __( 'Doors', 'panda-seasons-free' ), 'shortcode' => __( 'Shortcode', 'panda-seasons-free' ), 'date' => $columns['date'] );
	}

	public static function calendar_column( $column, $post_id ) {
		if ( 'doors' === $column ) echo esc_html( count( self::doors( $post_id ) ) );
		if ( 'shortcode' === $column ) echo '<code>[panda_seasons id="' . esc_html( $post_id ) . '"]</code>';
	}
}

