<?php

defined( 'ABSPATH' ) || exit;

/** Product settings; licenses use the same state and implementation as the family. */
final class Panda_Seasons_Settings {
	public static function load_license() {
		// The host may provide an older shared class. Route its canonical service
		// to PandaCosmo as well, without replacing the shared license state.
		add_filter( 'contactpanda_license_endpoint', array( __CLASS__, 'license_endpoint' ), 20, 2 );
		if ( ! class_exists( 'ContactPanda_License' ) ) {
			require_once PANDA_SEASONS_PATH . 'includes/shared/class-contactpanda-license.php';
			ContactPanda_License::boot();
		}
		if ( ! class_exists( 'ContactPanda_Licensed_Updater' ) ) require_once PANDA_SEASONS_PATH . 'includes/shared/class-contactpanda-licensed-updater.php';
	}

	public static function allows( $operation, $id = 0 ) {
		self::load_license();
		$post = $id ? get_post( $id ) : null;
		$existing = $post && ( Panda_Seasons_Plugin::DOOR_POST_TYPE === $post->post_type || Panda_Seasons_Plugin::CALENDAR_POST_TYPE === $post->post_type );
		$context = $existing ? array( 'existing' => true, 'created_at' => $post->post_date_gmt . ' UTC' ) : array();
		return ContactPanda_License::allows( 'seasons', $existing && 'write' === $operation ? 'edit_existing' : $operation, $context );
	}

	public static function license_endpoint( $url, $action ) {
		$parts = wp_parse_url( $url );
		$path = '/wp-json/contactpanda-license/v1/' . $action;
		if ( is_array( $parts ) && 'mycontactpanda.com' === strtolower( $parts['host'] ?? '' ) && $path === ( $parts['path'] ?? '' ) && in_array( $action, array( 'activate', 'check', 'deactivate', 'updates/check' ), true ) ) return 'https://pandacosmo.com' . $path;
		return $url;
	}

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_panda_seasons_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_submenu_page( 'panda-seasons-dashboard', __( 'Settings', 'panda-seasons' ), __( 'Settings', 'panda-seasons' ), 'manage_options', 'panda-seasons-settings', array( __CLASS__, 'render' ) );
	}

	public static function assets( $hook ) {
		if ( 'panda-seasons-settings' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) return;
		wp_enqueue_style( 'panda-seasons-admin', PANDA_SEASONS_URL . 'assets/editor.css', array(), PANDA_SEASONS_VERSION );
		wp_enqueue_style( 'panda-seasons-theme-settings', PANDA_SEASONS_URL . 'assets/contactpanda-theme-settings.css', array( 'panda-seasons-admin' ), PANDA_SEASONS_VERSION );
		wp_enqueue_script( 'panda-seasons-action-buttons', PANDA_SEASONS_URL . 'assets/panda-action-buttons.js', array( 'wp-api-fetch' ), PANDA_SEASONS_VERSION, true );
	}

	public static function theme_styles() {
		return (bool) get_option( 'panda_seasons_theme_styles', false );
	}

	/** Same theme-color sources as ContactPanda, with block-theme palette support. */
	public static function theme_colors() {
		if ( class_exists( 'ContactPanda_Basic_Newsletters' ) ) $colors = ContactPanda_Basic_Newsletters::detected_theme_colors();
		else $colors = array();
		$walk = static function ( $value ) use ( &$colors, &$walk ) {
			if ( is_array( $value ) ) { foreach ( $value as $item ) $walk( $item ); }
			elseif ( is_string( $value ) && preg_match_all( '/#[0-9a-fA-F]{6}\b/', $value, $matches ) ) foreach ( $matches[0] as $color ) $colors[] = strtolower( $color );
		};
		$walk( get_theme_mods() ); $walk( get_option( 'fusion_options', array() ) );
		if ( function_exists( 'wp_get_global_settings' ) ) $walk( wp_get_global_settings( array( 'color', 'palette' ) ) );
		return array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_hex_color', $colors ) ) ) ), 0, 16 );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'panda-seasons' ) );
		$theme = wp_get_theme();
		$active = self::theme_styles();
		$name = $theme->get( 'Name' ) ?: $theme->get_stylesheet();
		$colors = self::theme_colors();
		$fusion = (array) get_option( 'fusion_options', array() );
		$button_bg = sanitize_hex_color( $fusion['button_gradient_top_color'] ?? '' ) ?: ( $colors[0] ?? '#34495a' );
		$button_color = sanitize_hex_color( $fusion['button_accent_color'] ?? '' ) ?: '#ffffff';
		?>
		<div class="wrap panda-seasons-settings" style="--button_gradient_top_color:<?php echo esc_attr( $button_bg ); ?>;--button_accent_color:<?php echo esc_attr( $button_color ); ?>"><h1><?php esc_html_e( 'Panda Seasons Full settings', 'panda-seasons' ); ?></h1>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings', 'panda-seasons' ); ?>"><a class="nav-tab nav-tab-active" href="#appearance"><?php esc_html_e( 'Settings', 'panda-seasons' ); ?></a><a class="nav-tab" href="#license"><?php esc_html_e( 'License', 'panda-seasons' ); ?></a></nav>

		<section id="appearance" class="contactpanda-card contactpanda-settings-card contactpanda-theme-settings"><h2><?php esc_html_e( 'Appearance', 'panda-seasons' ); ?></h2>
		<div class="contactpanda-active-theme"><span><strong><?php esc_html_e( 'Active theme', 'panda-seasons' ); ?></strong><small><?php echo esc_html( $name ); ?></small></span><span class="contactpanda-status-badge is-positive"><?php echo esc_html( $active ? sprintf( __( '%s theme style', 'panda-seasons' ), $name ) : __( 'Panda Seasons Full style', 'panda-seasons' ) ); ?></span></div>
		<p class="description"><?php esc_html_e( 'Panda Seasons Full can inherit the theme typography, colors, form fields, buttons, borders, and corner radii. Its functional layout remains intact.', 'panda-seasons' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="panda_seasons_settings"><?php wp_nonce_field( 'panda_seasons_settings' ); ?>
		<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Use theme styles', 'panda-seasons' ); ?></legend><div class="contactpanda-style-switch">
		<label class="contactpanda-style-option"><input type="radio" name="theme_styles" value="0" <?php checked( ! $active ); ?>><span><strong><?php esc_html_e( 'Panda Seasons Full style', 'panda-seasons' ); ?></strong><small><?php esc_html_e( 'Use the neutral Panda Seasons Full design for calendars.', 'panda-seasons' ); ?></small><span class="contactpanda-theme-button-sample"><small><?php esc_html_e( 'Button preview', 'panda-seasons' ); ?></small><span class="contactpanda-theme-button-preview is-contactpanda"><?php esc_html_e( 'Example button', 'panda-seasons' ); ?></span></span></span></label>
		<label class="contactpanda-style-option"><input type="radio" name="theme_styles" value="1" <?php checked( $active ); ?>><span><strong><?php echo esc_html( sprintf( __( '%s theme style', 'panda-seasons' ), $name ) ); ?></strong><small><?php esc_html_e( 'Inherit compatible typography, colors, fields, buttons, borders, and spacing from the active theme.', 'panda-seasons' ); ?></small><span class="contactpanda-style-theme-colors" aria-label="<?php esc_attr_e( 'Detected theme colors', 'panda-seasons' ); ?>"><?php foreach ( $colors as $color ) : ?><i title="<?php echo esc_attr( $color ); ?>" style="background:<?php echo esc_attr( $color ); ?>"></i><?php endforeach; ?></span><span class="contactpanda-theme-button-sample"><small><?php esc_html_e( 'Button preview', 'panda-seasons' ); ?></small><span class="contactpanda-theme-button-preview is-theme"><?php esc_html_e( 'Example button', 'panda-seasons' ); ?></span></span></span></label>
		</div></fieldset><?php submit_button( __( 'Save appearance', 'panda-seasons' ) ); ?></form></section>
		<section id="license" class="contactpanda-card panda-seasons-settings-card">
		<?php if ( isset( $_GET['license_notice'] ) ) : ?><div id="panda-seasons-license-feedback" role="status" tabindex="-1" class="notice notice-info"><p><?php
			$notices = array( 'activated' => __( 'License activated.', 'panda-seasons' ), 'checked' => __( 'License status refreshed.', 'panda-seasons' ), 'invalid' => __( 'The license could not be activated. Please check the key.', 'panda-seasons' ), 'network' => __( 'The license service could not be reached. Please try again.', 'panda-seasons' ), 'signature' => __( 'The license response could not be verified.', 'panda-seasons' ), 'wrong_product' => __( 'The license service rejected the product assignment.', 'panda-seasons' ), 'site_limit_reached' => __( 'The website limit is reached. Disconnect an existing website in the customer account first.', 'panda-seasons' ), 'license_not_found' => __( 'The license key was not found.', 'panda-seasons' ), 'blocked' => __( 'The license is blocked or the trial has ended.', 'panda-seasons' ), 'invalid_site_identity' => __( 'The website identity could not be verified.', 'panda-seasons' ) );
			$code = sanitize_key( wp_unslash( $_GET['license_notice'] ) );
			$message = is_callable( array( 'ContactPanda_License', 'status_message' ) ) ? ContactPanda_License::status_message( $code ) : '';
			echo esc_html( $message ?: ( $notices[ $code ] ?? __( 'Please check the license status below.', 'panda-seasons' ) ) );
		?></p></div><?php endif; ?>
		<h2><?php esc_html_e( 'License', 'panda-seasons' ); ?></h2>
		<?php Panda_License_Center::link(); ?>
		</section></div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'panda-seasons' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'panda_seasons_settings' );
		update_option( 'panda_seasons_theme_styles', '1' === ( $_POST['theme_styles'] ?? '' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=panda-seasons-settings' ) );
		exit;
	}

	private static function return_here() {
		add_filter( 'wp_redirect', static function ( $url ) {
			if ( false !== strpos( $url, 'page=contactpanda-settings' ) ) {
				$query = array(); parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
				return add_query_arg( array( 'page' => 'panda-seasons-settings', 'license_notice' => sanitize_key( $query['license_notice'] ?? '' ) ), admin_url( 'admin.php' ) ) . '#panda-seasons-license-feedback';
			}
			return $url;
		} );
	}

	public static function activate() { self::return_here(); ContactPanda_License::activate(); }
	public static function check() { self::return_here(); ContactPanda_License::manual_check(); }
}
