<?php
/**
 * Plugin Name: Panda Seasons
 * Description: Interactive seasonal calendars with responsive cover images and optional ContactPanda email delivery.
 * Version: 0.8.27
 * Update URI: https://github.com/kultur3gmbh/panda-seasons
 * Author: Kultur3 GmbH
 * Text Domain: panda-seasons
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDA_SEASONS_VERSION', '0.8.27' );
define( 'PANDA_SEASONS_EDITION', 'full' );
define( 'PANDA_SEASONS_FILE', __FILE__ );
define( 'PANDA_SEASONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PANDA_SEASONS_URL', plugin_dir_url( __FILE__ ) );

/** Keep the always-loaded bootstrap small; feature implementations are lazy. */
function panda_seasons_autoload( $class ) {
	$map = array(
		'Panda_Seasons_Schema'        => 'class-panda-seasons-schema.php',
		'Panda_Seasons_Contract'      => 'class-panda-seasons-contract.php',
		'Panda_Seasons_Migrator'      => 'class-panda-seasons-migrator.php',
		'Panda_Seasons_Transfer'      => 'class-panda-seasons-transfer.php',
		'Panda_Seasons_Subscriptions' => 'class-panda-seasons-subscriptions.php',
		'Panda_Seasons_Mailer'        => 'class-panda-seasons-mailer.php',
		'Panda_Seasons_Plugin'        => 'class-panda-seasons-plugin.php',
		'Panda_Seasons_Integrations'  => 'class-panda-seasons-integrations.php',
		'Panda_Seasons_MCP'           => 'class-panda-seasons-mcp.php',
		'Panda_Seasons_Updater'       => 'class-panda-seasons-updater.php',
	);
	if ( 'Panda_Seasons_Runtime' === $class ) require_once PANDA_SEASONS_PATH . 'includes/trait-panda-seasons-runtime.php';
	if ( isset( $map[ $class ] ) ) require_once PANDA_SEASONS_PATH . 'includes/' . $map[ $class ];
}
spl_autoload_register( 'panda_seasons_autoload' );

// Register MCP callback references without parsing the MCP implementation.
add_action( 'wp_abilities_api_categories_init', array( 'Panda_Seasons_MCP', 'register_category' ) );
add_action( 'wp_abilities_api_init', array( 'Panda_Seasons_MCP', 'register_abilities' ) );

/** Integrate Seasons into ContactPanda's shared license center when Basic is active. */
function panda_seasons_contactpanda_license_modules( $modules ) {
	$modules['seasons'] = __( 'Panda Seasons', 'panda-seasons' );
	return $modules;
}
add_filter( 'contactpanda_license_modules', 'panda_seasons_contactpanda_license_modules' );

function panda_seasons_contactpanda_license_product( $products ) {
	$products['panda-seasons'] = array(
		'label'       => __( 'Panda Seasons', 'panda-seasons' ),
		'entitlement' => 'seasons',
		'version'     => PANDA_SEASONS_VERSION,
		'modules'     => array( 'seasons' ),
	);
	return $products;
}
add_filter( 'contactpanda_license_products', 'panda_seasons_contactpanda_license_product' );

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'ContactPanda_Licensed_Updater' ) ) {
			new ContactPanda_Licensed_Updater(
				array(
					'product'     => 'panda-seasons',
					'entitlement' => 'seasons',
					'plugin'      => plugin_basename( PANDA_SEASONS_FILE ),
					'slug'        => 'panda-seasons',
					'name'        => 'Panda Seasons',
					'version'     => PANDA_SEASONS_VERSION,
					'channel'     => defined( 'CONTACTPANDA_UPDATE_CHANNEL' ) ? CONTACTPANDA_UPDATE_CHANNEL : 'stable',
				)
			);
		} else {
			new Panda_Seasons_Updater( plugin_basename( PANDA_SEASONS_FILE ), PANDA_SEASONS_VERSION );
		}
		Panda_Seasons_Integrations::boot();
	},
	22
);

/**
 * The complete product replaces Panda Seasons Free instead of running beside
 * it. Both editions intentionally share the same calendar data contract.
 */
function panda_seasons_activate_full_edition() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	deactivate_plugins( 'panda-seasons-free/panda-seasons-free.php', true );
	Panda_Seasons_Plugin::activate();
}

register_activation_hook( __FILE__, 'panda_seasons_activate_full_edition' );
register_deactivation_hook( __FILE__, array( 'Panda_Seasons_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Panda_Seasons_Plugin', 'boot' ), 40 );
