<?php

defined( 'ABSPATH' ) || exit;

/** Public GitHub update channel for Panda Seasons Free. */
final class Panda_Seasons_Free_Updater {
	private const API   = 'https://api.github.com/repos/kultur3gmbh/panda-seasons';
	private const CACHE = 'panda_seasons_free_github_release';
	private $plugin;
	private $version;

	public function __construct( $plugin, $version ) {
		$this->plugin  = $plugin;
		$this->version = $version;
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'updates' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'plugin_action_links_' . $plugin, array( $this, 'links' ) );
		add_action( 'admin_post_panda_seasons_free_check_updates', array( $this, 'check' ) );
		add_action( 'admin_init', array( $this, 'maybe_clear_forced_check_cache' ), 1 );
		add_action( 'upgrader_process_complete', array( $this, 'clear' ), 10, 2 );
	}

	private function request() {
		$response = wp_safe_remote_get(
			self::API . '/releases?per_page=50',
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return new WP_Error( 'panda_seasons_free_update', __( 'The update service could not be reached.', 'panda-seasons-free' ) );
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) ) return $cached;
		}
		$releases = $this->request();
		if ( is_wp_error( $releases ) ) return $releases;
		$best = null;
		foreach ( (array) $releases as $release ) {
			$tag = (string) ( $release['tag_name'] ?? '' );
			if ( ! preg_match( '/^free-v(\d+\.\d+\.\d+)$/D', $tag, $match ) || ! empty( $release['draft'] ) ) continue;
			if ( ! $best || version_compare( $match[1], $best['version'], '>' ) ) {
				$asset = null;
				foreach ( (array) ( $release['assets'] ?? array() ) as $candidate ) {
					if ( 'panda-seasons-free-' . $match[1] . '.zip' === ( $candidate['name'] ?? '' ) && 'uploaded' === ( $candidate['state'] ?? '' ) ) $asset = $candidate;
				}
				if ( $asset && ! empty( $asset['browser_download_url'] ) ) {
					$best = array( 'version' => $match[1], 'package' => esc_url_raw( $asset['browser_download_url'] ), 'published' => (string) ( $release['published_at'] ?? '' ) );
				}
			}
		}
		if ( ! $best ) return new WP_Error( 'panda_seasons_free_update', __( 'No valid published update was found.', 'panda-seasons-free' ) );
		if ( version_compare( $best['version'], $this->version, '>' ) ) set_site_transient( self::CACHE, $best, 30 * MINUTE_IN_SECONDS );
		return $best;
	}

	public function updates( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		unset( $transient->response[ $this->plugin ], $transient->no_update[ $this->plugin ] );
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $transient;
		$item = (object) array(
			'id' => 'https://github.com/kultur3gmbh/panda-seasons', 'slug' => 'panda-seasons-free', 'plugin' => $this->plugin,
			'new_version' => $release['version'], 'url' => 'https://github.com/kultur3gmbh/panda-seasons/releases', 'package' => $release['package'],
			'requires' => '6.4', 'requires_php' => '7.4',
		);
		$key = version_compare( $release['version'], $transient->checked[ $this->plugin ] ?? $this->version, '>' ) ? 'response' : 'no_update';
		if ( ! isset( $transient->{$key} ) || ! is_array( $transient->{$key} ) ) $transient->{$key} = array();
		$transient->{$key}[ $this->plugin ] = $item;
		return $transient;
	}

	public function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'panda-seasons-free' !== ( $args->slug ?? '' ) ) return $result;
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $release;
		return (object) array( 'name' => 'Panda Seasons Free', 'slug' => 'panda-seasons-free', 'version' => $release['version'], 'author' => 'Kultur3 GmbH', 'requires' => '6.4', 'requires_php' => '7.4', 'download_link' => $release['package'] );
	}

	public function links( $links ) {
		if ( current_user_can( 'update_plugins' ) ) $links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_free_check_updates' ), 'panda_seasons_free_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'panda-seasons-free' ) . '</a>';
		return $links;
	}

	public function check() {
		if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html__( 'You are not allowed to check for updates.', 'panda-seasons-free' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'panda_seasons_free_check_updates' );
		$this->clear_update_cache();
		wp_update_plugins();
		wp_safe_redirect( self_admin_url( 'plugins.php' ) );
		exit;
	}

	public function maybe_clear_forced_check_cache() {
		if ( current_user_can( 'update_plugins' ) && ! empty( $_GET['force-check'] ) ) $this->clear_update_cache(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core's read-only update refresh action.
	}

	private function clear_update_cache() {
		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
	}

	public function clear( $upgrader, $extra ) {
		if ( in_array( $this->plugin, (array) ( $extra['plugins'] ?? array( $extra['plugin'] ?? '' ) ), true ) ) $this->clear_update_cache();
	}
}
