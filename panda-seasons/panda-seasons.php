<?php
/**
 * Plugin Name: Panda Seasons
 * Description: Interactive seasonal calendars with responsive cover images and optional ContactPanda email delivery.
 * Version: 0.8.11
 * Update URI: https://github.com/kultur3gmbh/panda-seasons
 * Author: Kultur3 GmbH
 * Text Domain: panda-seasons
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDA_SEASONS_VERSION', '0.8.11' );
define( 'PANDA_SEASONS_FILE', __FILE__ );
define( 'PANDA_SEASONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PANDA_SEASONS_URL', plugin_dir_url( __FILE__ ) );

require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-schema.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-contract.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-migrator.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-transfer.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-subscriptions.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-mailer.php';
require_once PANDA_SEASONS_PATH . 'includes/trait-panda-seasons-runtime.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-plugin.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-integrations.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-mcp.php';
require_once PANDA_SEASONS_PATH . 'includes/class-panda-seasons-updater.php';

// Register the Abilities API hooks immediately. Some MCP adapters initialize
// their registries before the later plugins_loaded callbacks run.
Panda_Seasons_MCP::boot();

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
		new Panda_Seasons_Updater( plugin_basename( PANDA_SEASONS_FILE ), PANDA_SEASONS_VERSION );
		Panda_Seasons_Integrations::boot();
	},
	20
);

register_activation_hook( __FILE__, array( 'Panda_Seasons_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Panda_Seasons_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Panda_Seasons_Plugin', 'boot' ), 40 );
