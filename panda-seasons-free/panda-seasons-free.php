<?php
/**
 * Plugin Name: Panda Seasons Free
 * Plugin URI: https://mycontactpanda.com/panda-seasons/
 * Description: Create simple responsive seasonal calendars with colored doors and text content.
 * Version: 0.1.5
 * Update URI: https://github.com/kultur3gmbh/panda-seasons/releases
 * Author: Kultur3 GmbH
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: panda-seasons-free
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDA_SEASONS_FREE_VERSION', '0.1.5' );
define( 'PANDA_SEASONS_FREE_FILE', __FILE__ );
define( 'PANDA_SEASONS_FREE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PANDA_SEASONS_FREE_URL', plugin_dir_url( __FILE__ ) );

/** Load the editor and calendar implementation only when WordPress needs it. */
function panda_seasons_free_autoload( $class ) {
	if ( 'Panda_Seasons_Free_Plugin' === $class ) {
		require_once PANDA_SEASONS_FREE_PATH . 'includes/class-panda-seasons-free-plugin.php';
	}
	if ( 'Panda_Seasons_Free_Updater' === $class ) {
		require_once PANDA_SEASONS_FREE_PATH . 'includes/class-panda-seasons-free-updater.php';
	}
}
spl_autoload_register( 'panda_seasons_free_autoload' );

function panda_seasons_free_activate() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( 'panda-seasons/panda-seasons.php' ) ) {
		wp_die(
			esc_html__( 'Please deactivate Panda Seasons before activating Panda Seasons Free. Both editions use the same calendar data and must not run at the same time.', 'panda-seasons-free' ),
			esc_html__( 'Panda Seasons is already active', 'panda-seasons-free' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'panda_seasons_free_activate' );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'panda-seasons-free', false, dirname( plugin_basename( PANDA_SEASONS_FREE_FILE ) ) . '/languages' );
		if ( defined( 'PANDA_SEASONS_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-info"><p>' . esc_html__( 'Panda Seasons Free is inactive while the complete Panda Seasons edition is active. Your calendars use the same saved data.', 'panda-seasons-free' ) . '</p></div>';
					}
				}
			);
			return;
		}
		if ( is_admin() ) new Panda_Seasons_Free_Updater( plugin_basename( PANDA_SEASONS_FREE_FILE ), PANDA_SEASONS_FREE_VERSION );
		Panda_Seasons_Free_Plugin::boot();
	},
	40
);

