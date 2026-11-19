<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Panda_License_Center', false ) ) {
/** One provider and one UI per installation; bundled unchanged by standalone products. */
final class Panda_License_Center {
	private static $products = array();
	private static $booted = false;
	public static function register( $key, array $product ) {
		self::$products[ $key ] = $product;
		if ( self::$booted ) return;
		self::$booted = true;
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 18 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'contactpanda_license_products', array( __CLASS__, 'products' ), 90 );
	}
	public static function boot() {
		if ( ! class_exists( 'ContactPanda_License' ) ) require_once __DIR__ . '/class-contactpanda-license.php';
		ContactPanda_License::boot();
	}
	public static function products( $products ) {
		foreach ( self::$products as $key => $product ) $products[ $key ] = array( 'label' => $product['label'], 'entitlement' => $product['entitlement'], 'version' => $product['version'], 'free' => false, 'modules' => array() );
		return $products;
	}
	public static function menu() { add_options_page( __( 'Panda licenses', 'contactpanda' ), __( 'Panda licenses', 'contactpanda' ), 'manage_options', 'panda-licenses', array( __CLASS__, 'page' ) ); }
	public static function url() { return admin_url( 'options-general.php?page=panda-licenses' ); }
	public static function assets() {
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( ! preg_match( '/^(?:panda|contactpanda|cpls)/', $page ) ) return;
		wp_enqueue_style( 'panda-license-center', plugin_dir_url( __FILE__ ) . 'panda-license-center.css', array( 'dashicons' ), '0.2.1' );
		wp_enqueue_script( 'panda-license-center', plugin_dir_url( __FILE__ ) . 'panda-license-center.js', array(), '0.2.1', true );
	}

	public static function link() {
		echo '<p><a class="button" href="' . esc_url( self::url() ) . '"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> ' . esc_html__( 'Manage license', 'contactpanda' ) . '</a></p>';
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission denied.', 'contactpanda' ), '', array( 'response' => 403 ) );
		echo '<div class="wrap panda-license-center"><h1>' . esc_html__( 'Panda licenses', 'contactpanda' ) . '</h1>';
		self::render(); echo '</div>';
	}
	public static function restrictions( $entitlement ) {
		$labels = array(
			'plus' => __( 'Existing forms, newsletters and automations continue. New forms, templates, automations, premium imports and extensions require an active license.', 'contactpanda' ),
			'business' => __( 'Existing customer portals, support processes and agreements continue. New projects and service configurations require an active license.', 'contactpanda' ),
			'pandawoo-pro' => __( 'Existing offers, subscriptions, renewal billing and payment recovery continue. New subscription models and commercial setups require an active license.', 'contactpanda' ),
			'seasons' => __( 'Existing calendars, published doors and scheduled deliveries continue. New calendars and extensions require an active license unless permanent use was purchased.', 'contactpanda' ),
			'pandamercury' => __( 'The existing assistant and support handoff continue. New assistant configurations and knowledge-source extensions require an active license. Provider services remain separately payable.', 'contactpanda' ),
			'pandatranslate-multilingual' => __( 'Existing languages, published translations and configured translation processes continue. New languages and multilingual setups require an active license.', 'contactpanda' ),
			'license-server' => __( 'Existing customer licenses, validation, protected downloads and billing continue. New license models and new grants require an active license.', 'contactpanda' ),
		);
		return $labels[ $entitlement ] ?? __( 'Existing setups and automatic processes continue. New paid setups and extensions require an active license.', 'contactpanda' );
	}
	private static function license_status( array $license ) {
		if ( 'trial' === ( $license['license_type'] ?? '' ) && ! ContactPanda_License::features_available( $license ) ) return __( 'Evaluation expired', 'contactpanda' );
		if ( 'website_blocked' === $license['status'] ) return __( 'Website suspended; local data retained', 'contactpanda' );
		if ( 'disconnected' === $license['status'] ) return __( 'Disconnected', 'contactpanda' );
		$labels = array( 'active' => __( 'Active', 'contactpanda' ), 'legacy' => __( 'Active', 'contactpanda' ), 'grace' => __( 'License grace period', 'contactpanda' ), 'archive' => __( 'Existing setups only', 'contactpanda' ), 'trial' => __( 'Evaluation license', 'contactpanda' ), 'blocked' => __( 'Blocked', 'contactpanda' ) );
		return $labels[ Panda_License_Lifecycle_V1::mode( $license ) ];
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$state = ContactPanda_License::state(); $products = ContactPanda_License::products();
		$archive = 'archive' === sanitize_key( wp_unslash( $_GET['license_view'] ?? '' ) );
		echo '<section class="panda-license-center"><p>' . esc_html__( 'Enter a product key or an All-in key once. Covered installed products share the same activation.', 'contactpanda' ) . '</p>';
		$notice = sanitize_key( wp_unslash( $_GET['license_notice'] ?? '' ) );
		$result = sanitize_text_field( wp_unslash( $_GET['license_result'] ?? '' ) );
		$success = in_array( $notice, array( 'activated', 'disconnected', 'checked' ), true );
		if ( $notice && ContactPanda_License::status_message( $notice ) ) echo '<div id="contactpanda-license-feedback" tabindex="-1" class="panda-license-feedback notice notice-' . ( $success ? 'success' : 'error' ) . '" role="' . ( $success ? 'status' : 'alert' ) . '"><p><strong>' . esc_html( ContactPanda_License::status_message( $notice ) ) . '</strong></p></div>';
		ob_start();
		echo '<div class="panda-license-products">';
		foreach ( $products as $product ) {
			$license = empty( $product['free'] ) ? ContactPanda_License::state_for_product( $product['entitlement'] ) : null;
			$active = $license && ContactPanda_License::allows( $product['entitlement'], 'read' );
			$mode = $license ? Panda_License_Lifecycle_V1::mode( $license ) : 'blocked';
			$bundle = $license && ( 'master' === ( $license['license_type'] ?? '' ) || in_array( 'all', $license['entitlements'], true ) );
			echo '<article class="panda-license-card"><h2>' . esc_html( $product['label'] ) . '</h2><p>' . esc_html( $product['version'] ) . '</p><p class="panda-license-status">';
			$status = ! empty( $product['free'] ) ? __( 'Free product active', 'contactpanda' ) : ( $active ? ( 'archive' === $mode ? __( 'Existing setups only', 'contactpanda' ) : ( 'grace' === $mode ? __( 'License grace period', 'contactpanda' ) : __( 'Product license active', 'contactpanda' ) ) ) : __( 'No active license', 'contactpanda' ) );
			if ( $license && 'website_blocked' === $license['status'] ) $status = __( 'Website suspended; local data retained', 'contactpanda' );
			if ( $active && $bundle ) $status .= ' · ' . __( 'Via All-in / shared license', 'contactpanda' );
			echo esc_html( $status ) . '</p>';
			if ( empty( $product['free'] ) ) echo '<details class="panda-license-restrictions"><summary>' . esc_html__( 'License scope and restrictions', 'contactpanda' ) . '</summary><p>' . esc_html( self::restrictions( $product['entitlement'] ) ) . '</p></details>';
			if ( $license ) {
				if ( 'trial' === ( $license['license_type'] ?? '' ) ) echo '<p><strong>' . esc_html__( 'Evaluation ends', 'contactpanda' ) . ':</strong> ' . esc_html( Panda_License_Lifecycle_V1::date_label( $license['features_until'] ?? '' ) ) . '</p>';
				$suffix = ContactPanda_License::key_suffix( $license );
				echo '<p><strong>' . esc_html__( 'License key', 'contactpanda' ) . ':</strong> ' . esc_html( $suffix ? '••••-' . $suffix : __( 'Masked; suffix not available', 'contactpanda' ) ) . '</p>';
				Panda_License_Lifecycle_V1::notice( $license, 'contactpanda' );
				foreach ( array( 'updates_until' => __( 'Updates until', 'contactpanda' ), 'support_until' => __( 'Support until', 'contactpanda' ) ) as $field => $label ) echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( Panda_License_Lifecycle_V1::date_label( $license[ $field ] ) ) . '</p>';
			}
			echo '</article>';
		}
		echo '</div>'; $product_cards = ob_get_clean();
		echo '<h2>' . esc_html__( 'Stored licenses', 'contactpanda' ) . '</h2><nav class="panda-license-tabs" aria-label="' . esc_attr__( 'Stored licenses', 'contactpanda' ) . '"><a class="button' . ( $archive ? '' : ' button-primary' ) . '" href="' . esc_url( self::url() ) . '"' . ( $archive ? '' : ' aria-current="page"' ) . '><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> ' . esc_html__( 'Active licenses', 'contactpanda' ) . '</a><a class="button' . ( $archive ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'license_view', 'archive', self::url() ) ) . '"' . ( $archive ? ' aria-current="page"' : '' ) . '><span class="dashicons dashicons-archive" aria-hidden="true"></span> ' . esc_html__( 'License archive', 'contactpanda' ) . '</a></nav>';
		$reopened = false;
		foreach ( $state['licenses'] as $id => $license ) {
			$is_archive = in_array( $license['status'], array( 'disconnected', 'deactivated', 'archived' ), true );
			$just_disconnected = ! $archive && 'disconnected' === $notice && (string) $id === $result && 'disconnected' === $license['status'];
			if ( $archive !== $is_archive && ! $just_disconnected ) continue;
			$suffix = ContactPanda_License::key_suffix( $license );
			$field = 'panda-license-key-' . substr( hash( 'sha256', (string) $id ), 0, 16 );
			echo '<article class="panda-license-card panda-license-key-card"><header><h3>' . esc_html( $license['license_label'] ?: __( 'Product license', 'contactpanda' ) ) . '</h3><span class="panda-license-status">' . esc_html( self::license_status( $license ) ) . '</span></header>';
			$covered = array(); foreach ( $products as $product ) if ( empty( $product['free'] ) && ContactPanda_License::update_entitlement_matches( $license['entitlements'], $product['entitlement'] ) ) $covered[] = $product['label'];
			if ( 'master' === ( $license['license_type'] ?? '' ) || in_array( 'all', $license['entitlements'], true ) ) echo '<p><strong>' . esc_html__( 'Via All-in / shared license', 'contactpanda' ) . '</strong></p>';
			echo '<p>' . esc_html( implode( ', ', $covered ) ) . '</p>';
			if ( $just_disconnected ) {
				echo '<p class="panda-license-inline-feedback" role="status">' . esc_html__( 'License disconnected. A new key can now be entered.', 'contactpanda' ) . '</p>';
				self::activation_form( $field ); $reopened = true;
			} else {
				echo '<label class="panda-license-key-label" for="' . esc_attr( $field ) . '">' . esc_html__( 'License key', 'contactpanda' ) . '</label><input class="panda-license-key" id="' . esc_attr( $field ) . '" type="text" value="' . esc_attr( $suffix ? '••••-' . $suffix : '••••' ) . '" readonly aria-readonly="true" autocomplete="off">';
				if ( ! $is_archive && $license['activation_token'] && ContactPanda_License::features_available( $license ) ) echo '<p class="panda-license-inline-feedback"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'License activated. The listed products use this key.', 'contactpanda' ) . '</p>';
			}
			if ( ! $is_archive && $license['activation_token'] ) {
				echo '<details class="panda-license-disconnect"><summary class="button"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . esc_html__( 'Disconnect website', 'contactpanda' ) . '</summary><p>' . esc_html__( 'This disconnects this key for all products listed above. Other independent keys remain unchanged.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_disconnect_license"><input type="hidden" name="license_id" value="' . esc_attr( $id ) . '">';
				wp_nonce_field( 'contactpanda_disconnect_license_' . $id );
				echo '<label class="panda-license-confirm"><input type="checkbox" name="confirm_disconnect" value="1" required> ' . esc_html__( 'Confirm disconnection of the listed products', 'contactpanda' ) . '</label><p><button class="button" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . esc_html__( 'Disconnect website', 'contactpanda' ) . '</button></p></form></details>';
			}
			echo '</article>';
		}
		if ( ! $reopened ) { echo '<h2>' . esc_html__( 'Activate or renew license', 'contactpanda' ) . '</h2>'; self::activation_form( 'panda-license-new-key' ); }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_check_licenses">';
		wp_nonce_field( 'contactpanda_check_licenses' );
		echo '<p><button class="button" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span> ' . esc_html__( 'Check licenses now', 'contactpanda' ) . '</button></p></form><p>' . esc_html__( 'Updates and support end with the paid entitlement. Existing setups, automatic processes and billing continue after the grace period; new paid setups require renewal.', 'contactpanda' ) . '</p><h2>' . esc_html__( 'Installed products', 'contactpanda' ) . '</h2>' . $product_cards . '</section>';
	}
	private static function activation_form( $field ) {
		echo '<form class="panda-license-actions" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_activate_license">';
		wp_nonce_field( 'contactpanda_activate_license' );
		echo '<label for="' . esc_attr( $field ) . '">' . esc_html__( 'License key', 'contactpanda' ) . '<input id="' . esc_attr( $field ) . '" type="password" name="license_key" autocomplete="off" required></label><button class="button button-primary" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> ' . esc_html__( 'Activate or renew license', 'contactpanda' ) . '</button></form>';

	}
}
}
