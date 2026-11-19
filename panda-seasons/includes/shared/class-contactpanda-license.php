<?php

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-panda-license-lifecycle.php';

/**
 * Provider-neutral ContactPanda license client.
 *
 * Licenses may grant one or more durable module entitlements. Signed server policy controls paid grace and archive operation rights.
 * Explicitly blocked licenses are excluded from the effective entitlements.
 */
final class ContactPanda_License {
	private static $booted = false;
	const OPTION           = 'panda_license_state';
	const INSTALLATION     = 'contactpanda_installation_id';
	const PRODUCT          = 'contactpanda-suite';
	const CHECK_HOOK       = 'contactpanda_license_daily_check';
	const RESPONSE_VERSION   = 1;
	const OFFLINE_GRACE_DAYS = 7;

	public static function boot() {
		if ( self::$booted ) return;
		self::$booted = true;
		add_action( 'init', array( __CLASS__, 'load_translations' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_domain_proof' ) );
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 20 );
		add_action( 'contactpanda_render_general_settings_section_license', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_activate_license', array( __CLASS__, 'activate' ) );
		add_action( 'admin_post_contactpanda_disconnect_license', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_contactpanda_check_licenses', array( __CLASS__, 'manual_check' ) );
		add_action( self::CHECK_HOOK, array( __CLASS__, 'scheduled_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_offline_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_lifecycle_notices' ) );
		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK );
		}
	}

	/** Supplement shared-client strings without requiring another family plugin. */
	public static function load_translations() {
		$locale = determine_locale();
		foreach ( array( dirname( __DIR__ ) . '/languages/', dirname( __DIR__, 2 ) . '/languages/' ) as $directory ) {
			$file = $directory . 'contactpanda-license-client-' . $locale . '.mo';
			if ( is_readable( $file ) ) { load_textdomain( 'contactpanda', $file ); break; }
		}
	}

	public static function settings_section( $sections ) {
		$sections['license'] = __( 'Licenses', 'contactpanda' );
		return $sections;
	}

	private static function defaults() {
		return array(
			'version'         => 2,
			'licenses'        => array(),
			'legacy_grants'   => array(),
			'last_checked_at' => '',
			'last_successful_check_at' => '',
			'last_failed_check_at'     => '',
			'check_failure_since'      => '',
		);
	}

	/**
	 * Read version-two state and transparently retain grants made by 0.6.0-rc.
	 */
	public static function state() {
		$raw = (array) get_option( self::OPTION, array() );
		if ( isset( $raw['licenses'] ) ) {
			$state = wp_parse_args( $raw, self::defaults() );
		} else {
			$grants = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) ( $raw['granted_entitlements'] ?? array() ) ) ) ) );
			if ( ! empty( $raw['newsletter_granted'] ) && ! in_array( 'advanced_newsletter', $grants, true ) ) {
				$grants[] = 'advanced_newsletter';
			}
			if ( ! empty( $raw['master_granted'] ) && ! in_array( 'all', $grants, true ) ) {
				$grants[] = 'all';
			}
			$state = self::defaults();
			$state['legacy_grants'] = $grants;
			if ( ! empty( $raw['license_key'] ) ) {
				$id = 'legacy-' . substr( hash( 'sha256', (string) $raw['license_key'] ), 0, 16 );
				$state['licenses'][ $id ] = array(
					'activation_id'    => '',
					'activation_token' => '',
					'license_label'    => sanitize_text_field( $raw['license_label'] ?? self::masked_key( $raw['license_key'] ) ),
					'license_type'     => sanitize_key( $raw['license_type'] ?? 'customer' ),
					'entitlements'     => $grants,
					'limits'           => array(),
					'updates_until'    => self::date_value( $raw['updates_until'] ?? '' ),
					'support_until'    => self::date_value( $raw['support_until'] ?? '' ),
					'activated_at'     => sanitize_text_field( $raw['activated_at'] ?? '' ),
					'last_checked_at'  => sanitize_text_field( $raw['last_checked_at'] ?? '' ),
					'status'           => sanitize_key( $raw['last_status'] ?? 'disconnected' ),
				);
			}
		}

		$state['licenses'] = is_array( $state['licenses'] ) ? $state['licenses'] : array();
		foreach ( $state['licenses'] as $id => $license ) {
			if ( ! is_array( $license ) ) {
				unset( $state['licenses'][ $id ] );
				continue;
			}
			$state['licenses'][ $id ] = self::normalize_license( $license );
		}
		$state['legacy_grants'] = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $state['legacy_grants'] ) ) ) );
		foreach ( array( 'last_checked_at', 'last_successful_check_at', 'last_failed_check_at', 'check_failure_since' ) as $date_key ) {
			$state[ $date_key ] = sanitize_text_field( $state[ $date_key ] ?? '' );
		}
		if ( ! $state['last_successful_check_at'] && $state['last_checked_at'] ) {
			$state['last_successful_check_at'] = $state['last_checked_at'];
		}
		return $state;
	}

	private static function normalize_license( $license ) {
		$license = wp_parse_args(
			(array) $license,
			array(
				'activation_id'    => '',
				'activation_token' => '',
				'license_label'    => '',
				'license_key_last4' => '',
				'transport' => array(),
				'license_type'     => 'customer',
				'features_until' => '',
				'lifecycle' => array(),
				'entitlements'     => array(),
				'limits'           => array(),
				'updates_until'    => '',
				'support_until'    => '',
				'activated_at'     => '',
				'last_checked_at'  => '',
				'status'           => 'valid',
			)
		);
		if ( ! empty( $license['activation_token_encrypted'] ) ) $license['activation_token'] = self::decrypt_token( $license['activation_token_encrypted'] );
		$license['license_key_last4'] = self::key_suffix( $license );
		$license['activation_id']    = sanitize_text_field( $license['activation_id'] );
		$license['activation_token'] = sanitize_text_field( $license['activation_token'] );
		$license['license_label']    = sanitize_text_field( $license['license_label'] );
		$license['features_until'] = sanitize_text_field( $license['features_until'] );
		$license['license_type']     = sanitize_key( $license['license_type'] );
		$license['entitlements']     = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $license['entitlements'] ) ) ) );
		$license['limits']           = is_array( $license['limits'] ) ? self::sanitize_limits( $license['limits'] ) : array();
		$license['updates_until']    = self::date_value( $license['updates_until'] );
		$license['support_until']    = self::date_value( $license['support_until'] );
		$license['status']           = sanitize_key( $license['status'] );
		return $license;
	}

	/** Preserve provider-defined per-product limits without accepting objects or markup. */
	private static function sanitize_limits( $limits ) {
		$clean = array();
		foreach ( (array) $limits as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) continue;
			$clean[ $key ] = is_array( $value ) ? self::sanitize_limits( $value ) : sanitize_text_field( (string) $value );
		}
		return $clean;
	}

	private static function normalize_entitlement( $item ) {
		$item = '*' === trim( (string) $item ) ? 'all' : sanitize_key( $item );
		$aliases = array(
			'newsletter'           => 'advanced_newsletter',
			'contactpanda-newsletter' => 'advanced_newsletter',
			'forms'                => 'forms_editor',
			'flows'                => 'automations',
			'integrations'         => 'advanced_integrations',
			'migration'            => 'form_migration',
			'contactpanda-plus'     => 'plus',
			'contactpanda-business' => 'business',
			'contactpanda-suite'    => 'all',
			'contactpanda-woocommerce' => 'woocommerce',
			'contactpanda-license-server' => 'license-server',
		);
		return $aliases[ $item ] ?? $item;
	}

	/** Canonical paid features contained in the complete Plus package. */
	public static function plus_features() {
		return array( 'advanced_newsletter', 'forms_editor', 'automations', 'advanced_integrations', 'form_migration' );
	}

	public static function installation_id() {
		$id = (string) get_option( self::INSTALLATION, '' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) ) {
			$id = wp_generate_uuid4();
			update_option( self::INSTALLATION, $id, false );
		}
		return $id;
	}

	/**
	 * Read access remains available in archive mode. Mutations use allows().
	 */
	public static function entitled( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		$grants = empty( $state['licenses'] ) ? $state['legacy_grants'] : array();
		foreach ( $state['licenses'] as $license ) {
			if ( ! self::features_available( $license ) ) {
				continue;
			}
			$grants = array_merge( $grants, $license['entitlements'] );
		}
		$module  = self::normalize_entitlement( $module );
		$grants  = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), $grants ) ) ) );
		$granted = in_array( 'all', $grants, true ) || in_array( $module, $grants, true );
		if ( ! $granted && in_array( $module, self::plus_features(), true ) ) {
			$granted = in_array( 'plus', $grants, true );
		}
		return (bool) apply_filters( 'contactpanda_license_entitled', $granted, $module, $state );
	}

	/** Trial expiry is exact and remains enforceable without network access. */
	public static function features_available( $license ) {
		if ( in_array( $license['status'] ?? '', array( 'blocked', 'disconnected', 'deactivated', 'inactive', 'archived' ), true ) ) return false;
		if ( 'trial' !== ( $license['license_type'] ?? '' ) ) return true;
		$deadline = strtotime( (string) ( $license['features_until'] ?? '' ) . ' UTC' );
		return $deadline && $deadline > time() && ! in_array( $license['status'] ?? '', array( 'disconnected', 'deactivated', 'expired' ), true );
	}

	/** Per-product operation rights: an active independent license wins only for its own grants. */
	public static function allows( $module, $operation = 'read', array $context = array() ) {
		$module = self::normalize_entitlement( $module );
		$state = self::state();
		foreach ( $state['licenses'] as $license ) {
			$grants = array_map( array( __CLASS__, 'normalize_entitlement' ), $license['entitlements'] );
			$business_features = array( 'support_desk', 'helpdesk', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management', 'software_licensing' );
			$match = ( in_array( $module, $business_features, true ) && in_array( 'business', $grants, true ) ) || in_array( 'all', $grants, true ) || in_array( $module, $grants, true ) || ( in_array( $module, self::plus_features(), true ) && in_array( 'plus', $grants, true ) );
			if ( $match && self::features_available( $license ) && Panda_License_Lifecycle_V1::allows( $license, $operation, null, $context ) ) return true;
		}
		return empty( $state['licenses'] ) && self::entitled( $module );
	}
	public static function operation_error() { return Panda_License_Lifecycle_V1::error( 'contactpanda' ); }
	public static function render_lifecycle_notices() {
		if ( ! current_user_can( self::capability() ) ) return;
		if ( 'panda-licenses' === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) return;
		foreach ( self::state()['licenses'] as $license ) Panda_License_Lifecycle_V1::notice( $license, 'contactpanda' );
	}

	/** The reduced newsletter is part of free ContactPanda. */
	public static function newsletter_entitled() {
		return (bool) apply_filters( 'contactpanda_newsletter_entitled', true, self::state() );
	}

	public static function advanced_newsletter_entitled() {
		return self::entitled( 'advanced_newsletter' );
	}

	/** Products shown on the shared license page. Extensions append their entry. */
	public static function products() {
		$products = array(
			'contactpanda-basic' => array(
				'label'       => __( 'ContactPanda Basic', 'contactpanda' ),
				'entitlement' => '',
				'version'     => defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '',
				'free'        => true,
				'modules'     => array(),
			),
		);
		if ( ! defined( 'CONTACTPANDA_VERSION' ) ) unset( $products['contactpanda-basic'] );
		$products = (array) apply_filters( 'contactpanda_license_products', $products );
		$clean = array();
		foreach ( $products as $id => $product ) {
			$id = sanitize_key( $id );
			if ( '' === $id || ! is_array( $product ) || empty( $product['label'] ) ) continue;
			$clean[ $id ] = array(
				'label'       => sanitize_text_field( $product['label'] ),
				'entitlement' => sanitize_key( $product['entitlement'] ?? $id ),
				'version'     => sanitize_text_field( $product['version'] ?? '' ),
				'free'        => ! empty( $product['free'] ),
				'modules'     => array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) ( $product['modules'] ?? array() ) ) ) ) ),
			);
		}
		return $clean;
	}

	private static function product_license_summary( $product, $state ) {
		$matches = array();
		foreach ( $state['licenses'] as $license ) {
			if ( empty( $product['free'] ) && self::update_entitlement_matches( $license['entitlements'], $product['entitlement'] ) ) {
				$matches[] = $license;
			}
		}
		$updates = '';
		$support = '';
		$site_used = '';
		$site_limit = '';
		$status = ! empty( $product['free'] ) ? 'free' : 'unlicensed';
		foreach ( $matches as $license ) {
			if ( 'blocked' === $license['status'] ) {
				$status = 'blocked';
				continue;
			}
			if ( 'disconnected' !== $license['status'] ) $status = 'expired' === $license['status'] ? 'expired' : 'valid';
			if ( $license['updates_until'] && ( ! $updates || $license['updates_until'] > $updates ) ) $updates = $license['updates_until'];
			if ( $license['support_until'] && ( ! $support || $license['support_until'] > $support ) ) $support = $license['support_until'];
			$limits = $license['limits'];
			$product_limits = isset( $limits['products'][ $product['entitlement'] ] ) && is_array( $limits['products'][ $product['entitlement'] ] ) ? $limits['products'][ $product['entitlement'] ] : $limits;
			foreach ( array( 'sites_used', 'site_usage', 'activations_used', 'active_sites' ) as $key ) if ( isset( $product_limits[ $key ] ) && '' !== (string) $product_limits[ $key ] ) { $site_used = (string) $product_limits[ $key ]; break; }
			foreach ( array( 'site_limit', 'max_sites', 'activations', 'activation_limit' ) as $key ) if ( isset( $product_limits[ $key ] ) && '' !== (string) $product_limits[ $key ] ) { $site_limit = (string) $product_limits[ $key ]; break; }
		}
		return compact( 'status', 'updates', 'support', 'site_used', 'site_limit' );
	}

	/**
	 * Check whether durable module grants authorize updates for a product family.
	 *
	 * Licenses can grant a complete package or only selected modules. Updating the
	 * package is required in both cases, otherwise individually licensed modules
	 * could never receive maintenance releases.
	 */
	public static function update_entitlement_matches( $entitlements, $required ) {
		$required     = self::normalize_entitlement( $required );
		$entitlements = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $entitlements ) ) ) );
		if ( '' === $required || in_array( 'all', $entitlements, true ) ) {
			return true;
		}

		$families = array(
			'plus'     => array_merge( array( 'plus', 'customer_registration', 'customer_account', 'messaging', 'email_delivery', 'sms', 'whatsapp' ), self::plus_features() ),
			'business' => array( 'business', 'support_desk', 'helpdesk', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management', 'software_licensing' ),
		);
		$candidates = $families[ $required ] ?? array( $required );
		return (bool) array_intersect( $candidates, $entitlements );
	}

	public static function updates_allowed_for( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		foreach ( $state['licenses'] as $license ) {
			if ( in_array( $license['status'], array( 'blocked', 'disconnected', 'website_blocked' ), true ) || ! self::update_entitlement_matches( $license['entitlements'], $module ) ) {
				continue;
			}
			if ( 'expired' !== $license['status'] && ( empty( $license['updates_until'] ) || strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) >= time() ) ) {
				return true;
			}
		}
		return false;
	}

	private static function capability() {
		return 'manage_options';
	}

	private static function endpoint( $action = 'activate' ) {
		$base = defined( 'CONTACTPANDA_LICENSE_ENDPOINT' ) ? CONTACTPANDA_LICENSE_ENDPOINT : 'https://pandacosmo.com/wp-json/contactpanda-license/v1/activate';
		$base = preg_replace( '#/(?:activate|check|deactivate|updates/check)/?$#', '', (string) $base );
		$base = trailingslashit( $base ) . implode( '/', array_map( 'sanitize_key', explode( '/', trim( (string) $action, '/' ) ) ) );
		return esc_url_raw( apply_filters( 'contactpanda_license_endpoint', $base, $action ) );
	}

	/** Expose only the service URL for read-only diagnostics; never license secrets. */
	public static function diagnostic_endpoint() {
		return self::endpoint( 'updates/check' );
	}

	/**
	 * Request signed update metadata for a licensed product.
	 *
	 * The activation token is transmitted only in the HTTPS request body. It is
	 * never placed in a package URL or persisted by the updater cache.
	 */
	public static function product_update( $product, $entitlement, $installed_version, $channel = 'stable' ) {
		$product          = sanitize_key( $product );
		$entitlement      = sanitize_key( $entitlement );
		$channel          = 'beta' === sanitize_key( $channel ) ? 'beta' : 'stable';
		$installed_version = sanitize_text_field( $installed_version );
		$state            = self::state();
		$last_error       = new WP_Error( 'contactpanda_update_not_licensed' );

		foreach ( $state['licenses'] as $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || in_array( $license['status'], array( 'blocked', 'disconnected', 'expired', 'website_blocked' ), true ) ) {
				continue;
			}
			if ( ! self::update_entitlement_matches( $license['entitlements'], $entitlement ) ) {
				continue;
			}
			if ( $license['updates_until'] && strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) < time() ) {
				continue;
			}
			$data = self::request(
				'updates/check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
					'update_product'   => $product,
					'installed_version'=> $installed_version,
					'channel'          => $channel,
				),
				$license
			);
			if ( is_wp_error( $data ) ) {
				$last_error = $data;
				continue;
			}
			return $data;
		}
		return $last_error;
	}

	public static function status_message( $code ) {
		$messages = array(
			'activated'    => __( 'The license has been activated. Product permissions and dates are shown below.', 'contactpanda' ),
			'disconnected' => __( 'The website was disconnected from this license. Other active licenses are retained.', 'contactpanda' ),
			'checked'      => __( 'The license information was updated.', 'contactpanda' ),
			'invalid'      => __( 'The license could not be activated. Please check the license key and try again.', 'contactpanda' ),
			'network'      => __( 'The license service could not be reached. No existing feature or license information was changed.', 'contactpanda' ),
			'signature'    => __( 'The license response could not be verified. No existing license information was changed.', 'contactpanda' ),
			'wrong_product' => __( 'The license service rejected the product assignment. Update the license server or contact support.', 'contactpanda' ),
			'site_limit_reached' => __( 'The website limit is reached. Disconnect an existing website in the customer account first.', 'contactpanda' ),
			'website_blocked' => __( 'This website is blocked for this key. Unblock it in the customer account before activating it again.', 'contactpanda' ),
			'license_not_found' => __( 'The license key was not found. Check the complete key.', 'contactpanda' ),
			'blocked' => __( 'This license is blocked or its trial has ended. Check the customer account.', 'contactpanda' ),
			'invalid_site_identity' => __( 'The website address or installation identity could not be verified.', 'contactpanda' ),
			'activation_storage_error' => __( 'The license server could not save the website activation. No existing license was replaced.', 'contactpanda' ),
		);
		if ( 0 === strpos( $code, 'domain_proof_' ) ) return __( 'The website could not prove its address. Use its public HTTPS address and allow the Panda license verification endpoint through the firewall. Existing licenses are unchanged.', 'contactpanda' );
		return $messages[ $code ] ?? '';
	}

	public static function render_settings() {
		if ( ! class_exists( 'Panda_License_Center', false ) ) require_once __DIR__ . '/class-panda-license-center.php';
		Panda_License_Center::render();
	}

	private static function type_label( $type ) {
		$labels = array(
			'master'   => __( 'MasterKey', 'contactpanda' ),
			'customer' => __( 'Customer license', 'contactpanda' ),
			'partner'  => __( 'Partner license', 'contactpanda' ),
		);
		return $labels[ $type ] ?? __( 'Module license', 'contactpanda' );
	}

	private static function status_label( $status ) {
		$labels = array(
			'valid'        => __( 'Active', 'contactpanda' ),
			'expired'      => __( 'Updates expired — modules remain usable', 'contactpanda' ),
			'blocked'      => __( 'Blocked', 'contactpanda' ),
			'website_blocked' => __( 'Website suspended; local data retained', 'contactpanda' ),
			'disconnected' => __( 'Disconnected', 'contactpanda' ),
		);
		return $labels[ $status ] ?? __( 'Unknown', 'contactpanda' );
	}

	private static function telemetry() {
		return array(
			'api_version'       => self::RESPONSE_VERSION,
			'product'           => self::PRODUCT,
			'installation_id'   => self::installation_id(),
			'site_url'          => home_url( '/' ),
			'network_url'       => network_home_url( '/' ),
			'site_name'         => get_bloginfo( 'name' ),
			'locale'            => get_locale(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'basic_version'     => defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '',
			'plus_version'      => defined( 'CONTACTPANDA_PLUS_VERSION' ) ? CONTACTPANDA_PLUS_VERSION : '',
			'business_version'  => defined( 'CONTACTPANDA_BUSINESS_VERSION' ) ? CONTACTPANDA_BUSINESS_VERSION : '',
		);
	}

	private static function request( $action, $payload, array $license = array() ) {
		$transport = (array) ( $license['transport'] ?? array() );
		$body = array_merge( self::telemetry(), array_intersect_key( $transport, array_flip( array( 'installation_id' ) ) ), $payload, array( 'trial_protocol' => 1 ) );
		$url = empty( $transport['endpoint'] ) ? self::endpoint( $action ) : rtrim( preg_replace( '#/(?:activate|check|deactivate|updates/check)/?$#', '', $transport['endpoint'] ), '/' ) . '/' . $action;
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) return new WP_Error( 'contactpanda_license_invalid_endpoint' );

		/*
		 * A WordPress installation that also hosts Panda License must not make an
		 * HTTPS request back to itself while its own updater has enabled maintenance
		 * mode. The license server can provide the same signed response in-process.
		 * Other installations leave this filter untouched and keep using HTTPS.
		 */
		$local = apply_filters( 'contactpanda_license_local_response', null, $action, $body );
		if ( is_wp_error( $local ) ) {
			return $local;
		}
		if ( is_array( $local ) ) {
			return self::maybe_prove_domain( self::verified_data( $local ), $action, $payload, $license );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode( $body ),
			)
		);
		$response = apply_filters( 'contactpanda_license_response', $response, $action, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 500 || ! is_array( $data ) ) {
			return new WP_Error( 'contactpanda_license_invalid' );
		}
		return self::maybe_prove_domain( self::verified_data( $data ), $action, $payload, $license );
	}


	/** Publish only a signed server-issued random challenge; no key or account data. */
	public static function register_domain_proof() {
		register_rest_route( 'panda-license-client/v1', '/site-proof', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'domain_proof_response' ) ) );
	}
	public static function domain_proof_response( WP_REST_Request $request ) {
		$proof = (array) get_option( 'panda_license_domain_proof', array() );
		$nonce = (string) $request->get_param( 'challenge' );
		$valid = preg_match( '/^[a-f0-9]{64}$/D', $nonce ) && (int) ( $proof['expires_at'] ?? 0 ) >= time() && is_string( $proof['nonce'] ?? null ) && hash_equals( $proof['nonce'], $nonce );
		$response = new WP_REST_Response( $valid ? array( 'challenge' => $nonce ) : array( 'status' => 'not_found' ), $valid ? 200 : 404 );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}
	private static function maybe_prove_domain( $data, $action, $payload, array $license ) {
		if ( is_wp_error( $data ) || 'activate' !== $action || isset( $payload['domain_challenge'] ) || 'domain_proof_required' !== ( $data['status'] ?? '' ) ) return $data;
		$proof = (array) ( $data['domain_proof'] ?? array() );
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', (string) ( $proof['nonce'] ?? '' ) ) || (int) ( $proof['expires_at'] ?? 0 ) < time() || (int) $proof['expires_at'] > time() + 600 ) return new WP_Error( 'contactpanda_license_domain_proof' );
		update_option( 'panda_license_domain_proof', $proof, false );
		try { return self::request( $action, array_merge( $payload, array( 'domain_challenge' => $proof['nonce'] ) ), $license ); }
		finally { delete_option( 'panda_license_domain_proof' ); }
	}

	/**
	 * Verify Ed25519 payloads when the deployment public key is configured.
	 * TLS remains mandatory. CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE can enforce
	 * signatures after pandacosmo.com has been provisioned.
	 */
	private static function verified_data( $data ) {
		$public_key = self::trusted_public_key();
		$required = true;
		if ( empty( $data['signature'] ) ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}
		if ( '' === $public_key ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}

		$signature = base64_decode( (string) $data['signature'], true );
		$key       = base64_decode( $public_key, true );
		$algorithm = sanitize_key( $data['signature_algorithm'] ?? 'ed25519' );
		$payload   = $data;
		unset( $payload['signature'], $payload['signature_algorithm'], $payload['signature_key_id'] );
		$canonical = self::canonical_json( $payload );
		$valid     = false;
		if ( 'ed25519' === $algorithm && function_exists( 'sodium_crypto_sign_verify_detached' ) && false !== $signature && false !== $key ) {
			$valid = 64 === strlen( $signature ) && 32 === strlen( $key ) && sodium_crypto_sign_verify_detached( $signature, $canonical, $key );
		} elseif ( 'rsa-sha256' === $algorithm && function_exists( 'openssl_verify' ) && defined( 'OPENSSL_ALGO_SHA256' ) && false !== $signature && false !== $key ) {
			$valid = 1 === openssl_verify( $canonical, $signature, $key, OPENSSL_ALGO_SHA256 );
		}
		return $valid ? $payload : new WP_Error( 'contactpanda_license_signature' );
	}

	/** Trust only configured keys or metadata fetched directly from the configured HTTPS operator. */
	private static function trusted_public_key() {
		foreach ( array( 'CONTACTPANDA_LICENSE_PUBLIC_KEY', 'PANDATRANSLATE_MULTILINGUAL_LICENSE_PUBLIC_KEY', 'PANDAMERCURY_LICENSE_PUBLIC_KEY', 'PANDAWOO_PRO_LICENSE_PUBLIC_KEY' ) as $constant ) if ( defined( $constant ) && constant( $constant ) ) return trim( (string) constant( $constant ) );
		$url = self::endpoint( 'verification-key' );
		$cache = 'panda_license_public_key_' . substr( hash( 'sha256', $url ), 0, 24 );
		$stored = get_option( $cache, '' ); if ( $stored ) return (string) $stored;
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) return '';
		if ( class_exists( 'CPLS_Crypto', false ) && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) $info = CPLS_Crypto::public_info();
		else {
			$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 16384 ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return '';
			$info = json_decode( wp_remote_retrieve_body( $response ), true );
		}
		if ( ! is_array( $info ) || ! in_array( $info['algorithm'] ?? '', array( 'ed25519', 'rsa-sha256' ), true ) || empty( $info['public_key'] ) ) return '';
		$key = base64_decode( $info['public_key'], true );
		if ( false === $key || ( 'ed25519' === $info['algorithm'] && 32 !== strlen( $key ) ) || strlen( $key ) > 8192 ) return '';
		update_option( $cache, $info['public_key'], false );
		return $info['public_key'];
	}

	private static function canonical_json( $value ) {
		$value = self::normalize_signature_value( $value );
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function normalize_signature_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$keys    = array_keys( $value );
		$is_list = empty( $value ) || $keys === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize_signature_value( $item );
		}
		return $value;
	}

	public static function activate() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_activate_license' );
		$key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		if ( '' === $key || strlen( $key ) > 190 ) {
			self::redirect( 'invalid' );
		}
		$data = self::request( 'activate', array( 'license_key' => $key, 'activation_scope' => 'key' ) );
		if ( is_wp_error( $data ) ) {
			self::redirect( 'contactpanda_license_signature' === $data->get_error_code() ? 'signature' : 'network' );
		}
		if ( empty( $data['valid'] ) || empty( $data['activation_id'] ) || empty( $data['activation_token'] ) ) {
			$reason = sanitize_key( $data['status'] ?? 'invalid' );
			self::redirect( self::status_message( $reason ) ? $reason : 'invalid' );
		}
		$state = self::state();
		$id = sanitize_text_field( $data['license_id'] ?? $data['activation_id'] );
		$data['license_key_last4'] = strlen( $key ) >= 4 ? substr( preg_replace( '/[^A-Za-z0-9]/', '', $key ), -4 ) : '';
		$license = self::license_from_response( $data, $state['licenses'][ $id ] ?? array() );
		if ( empty( $license['entitlements'] ) ) {
			self::redirect( 'invalid' );
		}
		$state['licenses'][ $id ] = $license;
		$state['last_checked_at']          = current_time( 'mysql', true );
		$state['last_successful_check_at'] = $state['last_checked_at'];
		$state['last_failed_check_at']     = '';
		$state['check_failure_since']      = '';
		$saved = self::save_state( $state );
		if ( true !== $saved ) self::redirect( 'network' );
		self::clear_update_caches();
		self::redirect( 'activated', $id );
	}

	private static function license_from_response( $data, $existing = array() ) {
		$existing     = self::normalize_license( $existing );
		$entitlements = is_array( $data['entitlements'] ?? null ) ? array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), $data['entitlements'] ) ) ) ) : $existing['entitlements'];
		$status       = sanitize_key( $data['status'] ?? 'valid' );
		$status       = 'active' === $status ? 'valid' : ( 'deactivated' === $status ? 'disconnected' : $status );
		return self::normalize_license(
			array(
				'activation_id'    => sanitize_text_field( $data['activation_id'] ?? $existing['activation_id'] ),
				'activation_token' => sanitize_text_field( $data['activation_token'] ?? $existing['activation_token'] ),
				'license_label'    => sanitize_text_field( $data['license_label'] ?? $existing['license_label'] ),
				'license_key_last4' => self::key_suffix( $data ) ?: self::key_suffix( $existing ),
				'transport' => $existing['transport'] ?? array(),
				'license_type'     => sanitize_key( $data['license_type'] ?? $existing['license_type'] ),
				'features_until' => sanitize_text_field( $data['features_until'] ?? $existing['features_until'] ),
				'lifecycle' => Panda_License_Lifecycle_V1::normalize( $data['lifecycle'] ?? $existing['lifecycle'] ),
				'entitlements'     => $entitlements,
				'limits'           => is_array( $data['limits'] ?? null ) ? $data['limits'] : $existing['limits'],
				'updates_until'    => self::date_value( $data['updates_until'] ?? $existing['updates_until'] ),
				'support_until'    => self::date_value( $data['support_until'] ?? $existing['support_until'] ),
				'activated_at'     => $existing['activated_at'] ?: current_time( 'mysql', true ),
				'last_checked_at'  => current_time( 'mysql', true ),
				'status'           => $status,
			)
		);
	}

	public static function manual_check() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_check_licenses' );
		$result = self::check_all();
		self::redirect( is_wp_error( $result ) ? 'network' : 'checked' );
	}

	public static function scheduled_check() {
		self::check_all();
	}

	private static function check_all() {
		$state       = self::state();
		$had_success = false;
		$attempted   = false;
		foreach ( $state['licenses'] as $id => $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || 'disconnected' === $license['status'] ) {
				continue;
			}
			$attempted = true;
			$data = self::request(
				'check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				),
				$license
			);
			if ( is_wp_error( $data ) || ! isset( $data['valid'] ) ) {
				continue;
			}
			$had_success = true;
			if ( empty( $data['valid'] ) ) {
				// A rotated or revoked key invalidates every former activation token.
				if ( 'blocked' !== ( $data['status'] ?? '' ) ) $state['licenses'][ $id ]['activation_token'] = '';
				$state['licenses'][ $id ]['status'] = 'blocked' === ( $data['status'] ?? '' ) ? 'blocked' : 'disconnected';
				$state['licenses'][ $id ]['last_checked_at'] = current_time( 'mysql', true );
			} elseif ( ! empty( $data['valid'] ) || 'blocked' === ( $data['status'] ?? '' ) ) {
				$state['licenses'][ $id ] = self::license_from_response( $data, $license );
			}
		}
		if ( $had_success || ! $attempted ) {
			$state['last_checked_at']            = current_time( 'mysql', true );
			$state['last_successful_check_at']   = $state['last_checked_at'];
			$state['last_failed_check_at']       = '';
			$state['check_failure_since']        = '';
			if ( true !== self::save_state( $state ) ) return new WP_Error( 'panda_license_storage' );
			self::clear_update_caches();
			return true;
		}
		$state['last_failed_check_at'] = current_time( 'mysql', true );
		if ( ! $state['check_failure_since'] ) {
			$state['check_failure_since'] = $state['last_failed_check_at'];
		}
		self::save_state( $state );
		return new WP_Error( 'contactpanda_license_network' );
	}

	private static function offline_status( $state = null ) {
		$state = is_array( $state ) ? $state : self::state();
		$since = sanitize_text_field( $state['check_failure_since'] ?? '' );
		$time  = $since ? strtotime( $since . ' UTC' ) : false;
		$age   = $time ? max( 0, time() - $time ) : 0;
		return array(
			'failed'  => (bool) $time,
			'overdue' => (bool) $time && $age >= self::OFFLINE_GRACE_DAYS * DAY_IN_SECONDS,
			'days'    => $time ? (int) floor( $age / DAY_IN_SECONDS ) : 0,
		);
	}

	public static function render_offline_notice() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$status = self::offline_status();
		if ( ! $status['overdue'] ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The ContactPanda license service has not been reachable for more than seven days.', 'contactpanda' ) . '</strong> ' . esc_html__( 'The cached license status remains in effect. Automatic checks will continue.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_check_licenses">';
		wp_nonce_field( 'contactpanda_check_licenses' );
		echo '<p><button class="button">' . esc_html__( 'Check licenses now', 'contactpanda' ) . '</button></p></form></div>';
	}

	public static function disconnect() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['license_id'] ?? '' ) );
		check_admin_referer( 'contactpanda_disconnect_license_' . $id );
		$state = self::state();
		if ( empty( $state['licenses'][ $id ] ) ) {
			self::redirect( 'invalid' );
		}
		$license = $state['licenses'][ $id ];
		if ( empty( $_POST['confirm_disconnect'] ) ) wp_die( esc_html__( 'Confirm disconnection of the listed products', 'contactpanda' ), '', array( 'response' => 400 ) );
		if ( $license['activation_id'] && $license['activation_token'] ) {
			$result = self::request(
				'deactivate',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				),
				$license
			);
		}
		if ( isset( $result ) && ( is_wp_error( $result ) || 'deactivated' !== ( $result['status'] ?? '' ) || false !== ( $result['valid'] ?? null ) ) ) self::redirect( 'network' );
		$state['licenses'][ $id ]['activation_token'] = '';
		$state['licenses'][ $id ]['status']           = 'disconnected';
		$saved = self::save_state( $state );
		if ( true !== $saved ) self::redirect( 'network' );
		self::clear_update_caches();
		self::redirect( 'disconnected', $id );
	}

	/** Clear public and protected update results after every license change. */
	private static function clear_update_caches() {
		delete_site_transient( 'contactpanda_github_release' );
		delete_site_transient( 'update_plugins' );
		do_action( 'contactpanda_license_state_refreshed' );
	}

	private static function date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = substr( $value, 0, 10 );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : '';
	}

	public static function key_suffix( array $license ) {
		foreach ( array( 'license_key_last4', 'key_last4' ) as $field ) if ( preg_match( '/^[A-Za-z0-9]{4}$/D', (string) ( $license[ $field ] ?? '' ) ) ) return strtoupper( $license[ $field ] );
		$hint = (string) ( $license['key_hint'] ?? '' );
		return preg_match( '/(?:[•*…]|\.\.\.)[^A-Za-z0-9]*([A-Za-z0-9]{4,5})$/uD', $hint, $match ) ? strtoupper( substr( $match[1], -4 ) ) : '';
	}
	private static function encrypt_token( $token ) {
		if ( ! $token ) return '';
		if ( ! function_exists( 'openssl_encrypt' ) ) return new WP_Error( 'panda_license_crypto' );
		$iv = random_bytes( 12 ); $tag = '';
		$value = openssl_encrypt( $token, 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ) . '|panda-licenses', true ), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $value ? new WP_Error( 'panda_license_crypto' ) : 'v1:' . base64_encode( $iv . $tag . $value );
	}
	private static function decrypt_token( $value ) {
		if ( ! function_exists( 'openssl_decrypt' ) || 0 !== strpos( $value, 'v1:' ) ) return '';
		$raw = base64_decode( substr( $value, 3 ), true );
		if ( false === $raw || strlen( $raw ) <= 28 ) return '';
		$value = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ) . '|panda-licenses', true ), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $value ? '' : $value;
	}
	public static function save_state( array $state ) {
		foreach ( $state['licenses'] as &$license ) {
			$cipher = self::encrypt_token( $license['activation_token'] ?? '' );
			if ( is_wp_error( $cipher ) ) return $cipher;
			$license['activation_token_encrypted'] = $cipher;
			unset( $license['activation_token'] );
		}
		unset( $license );
		$result = update_option( self::OPTION, $state, false );
		return $result || get_option( self::OPTION ) === $state;
	}

	/** A matching record, including a disconnected one, prevents old option data from bypassing revocation. */
	public static function state_for_product( $product ) {
		$best = null; $best_score = -1;
		foreach ( self::state()['licenses'] as $license ) {
			if ( ! self::update_entitlement_matches( $license['entitlements'], $product ) ) continue;
			$score = self::features_available( $license ) ? ( Panda_License_Lifecycle_V1::allows( $license, 'write' ) ? 3 : ( Panda_License_Lifecycle_V1::allows( $license, 'read' ) ? 2 : 0 ) ) : 0;
			if ( $score > $best_score ) { $best = $license; $best_score = $score; }
		}
		return $best;
	}

	private static function masked_key( $key ) {
		return strlen( $key ) >= 4 ? '••••-' . substr( $key, -4 ) : '••••';
	}

	private static function redirect( $notice, $license_id = '' ) {
		$args = array( 'page' => 'panda-licenses', 'license_notice' => sanitize_key( $notice ) );
		if ( $license_id ) $args['license_result'] = sanitize_text_field( $license_id );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) . '#contactpanda-license-feedback' );
		exit;
	}
}
