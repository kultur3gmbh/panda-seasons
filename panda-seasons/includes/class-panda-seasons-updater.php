<?php

defined( 'ABSPATH' ) || exit;

/** Standalone GitHub release updater with manifest and archive integrity checks. */
final class Panda_Seasons_Updater {
	private const API = 'https://api.github.com/repos/kultur3gmbh/panda-seasons';
	private const CACHE = 'panda_seasons_github_release';
	private $plugin;
	private $version;
	private $install_release;

	public function __construct( $plugin, $version ) {
		$this->plugin = $plugin;
		$this->version = $version;
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'updates' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'source' ), 10, 4 );
		add_filter( 'plugin_action_links_' . $plugin, array( $this, 'links' ) );
		add_action( 'admin_post_panda_seasons_check_updates', array( $this, 'check' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear' ), 10, 2 );
	}

	private function enabled() {
		return ! defined( 'PANDA_SEASONS_GITHUB_UPDATES' ) || false !== PANDA_SEASONS_GITHUB_UPDATES;
	}

	private function error( $message ) {
		return new WP_Error( 'panda_seasons_update', $message );
	}

	private function asset_url( $id ) {
		return self::API . '/releases/assets/' . absint( $id );
	}

	private function request( $url, $binary = false, $filename = '' ) {
		$headers = array(
			'Accept'               => $binary ? 'application/octet-stream' : 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( 0 !== strpos( $url, self::API . '/releases/' ) ) {
			return $this->error( __( 'The update source is invalid.', 'panda-seasons' ) );
		}
		for ( $redirect = 0; $redirect < 4; $redirect++ ) {
			$args = array( 'timeout' => $filename ? 120 : 15, 'redirection' => 0, 'sslverify' => true, 'headers' => $headers );
			if ( $filename ) {
				$args['stream'] = true;
				$args['filename'] = $filename;
			}
			$response = wp_safe_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $this->error( __( 'The update service could not be reached.', 'panda-seasons' ) );
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$next = wp_remote_retrieve_header( $response, 'location' );
				$parts = is_string( $next ) ? wp_parse_url( $next ) : false;
				$hosts = array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com', 'github-releases.githubusercontent.com' );
				if ( ! $binary || ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', $hosts, true ) ) {
					return $this->error( __( 'The update download is invalid.', 'panda-seasons' ) );
				}
				$url = $next;
				continue;
			}
			return 200 === $code ? $response : $this->error( __( 'No valid published update was found.', 'panda-seasons' ) );
		}
		return $this->error( __( 'The update download could not be followed safely.', 'panda-seasons' ) );
	}

	public function release( $force = false ) {
		if ( ! $this->enabled() ) {
			return $this->error( __( 'Updates are disabled for Panda Seasons.', 'panda-seasons' ) );
		}
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) ) return $cached;
		}
		$response = $this->request( self::API . '/releases/latest' );
		if ( is_wp_error( $response ) ) return $response;
		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = is_array( $release ) ? preg_replace( '/^v/', '', (string) ( $release['tag_name'] ?? '' ) ) : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/D', $version ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
			return $this->error( __( 'The published update metadata is invalid.', 'panda-seasons' ) );
		}
		$assets = array();
		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			if ( 'uploaded' === ( $asset['state'] ?? '' ) && ! empty( $asset['id'] ) && ! empty( $asset['name'] ) ) $assets[ $asset['name'] ] = $asset;
		}
		$zip = $assets[ 'panda-seasons-' . $version . '.zip' ] ?? null;
		$manifest = $assets['panda-seasons-update.json'] ?? null;
		if ( ! $zip || ! $manifest || empty( $zip['size'] ) || $zip['size'] > 100 * 1024 * 1024 ) {
			return $this->error( __( 'The update package is incomplete.', 'panda-seasons' ) );
		}
		$response = $this->request( $this->asset_url( $manifest['id'] ), true );
		if ( is_wp_error( $response ) ) return $response;
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || 'panda-seasons' !== ( $data['slug'] ?? '' ) || $version !== ( $data['version'] ?? '' ) || ! preg_match( '/^[a-f0-9]{64}$/D', (string) ( $data['sha256'] ?? '' ) ) ) {
			return $this->error( __( 'The update manifest is invalid.', 'panda-seasons' ) );
		}
		$result = array(
			'version'      => $version,
			'requires'     => (string) ( $data['requires'] ?? '6.4' ),
			'requires_php' => (string) ( $data['requires_php'] ?? '7.4' ),
			tested'       => (string) ( $data['tested'] ?? '' ),
			'sha256'      => $data['sha256'],
			'asset_id'    => absint( $zip['id'] ),
			'size'        => absint( $zip['size'] ),
		);
		set_site_transient( self::CACHE, $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	private function compatible( $release ) {
		global $wp_version;
		return is_array( $release ) && version_compare( $wp_version, $release['requires'], '>=' ) && version_compare( PHP_VERSION, $release['requires_php'], '>=' );
	}

	public function updates( $transient ) {
		if ( ! is_object( $transient ) || ! $this->enabled() ) return $transient;
		unset( $transient->response[ $this->plugin ], $transient->no_update[ $this->plugin ] );
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $transient;
		$item = (object) array(
			'id' => 'https://github.com/kultur3gmbh/panda-seasons', 'slug' => 'panda-seasons', 'plugin' => $this->plugin,
			'new_version' => $release['version'], 'url' => 'https://github.com/kultur3gmbh/panda-seasons/releases',
			'package' => $this->compatible( $release ) ? $this->asset_url( $release['asset_id'] ) : '',
			'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'],
		);
		$key = version_compare( $release['version'], $transient->checked[ $this->plugin ] ?? $this->version, '>' ) ? 'response' : 'no_update';
		if ( ! isset( $transient->{$key} ) || ! is_array( $transient->{$key} ) ) $transient->{$key} = array();
		$transient->{$key}[ $this->plugin ] = $item;
		return $transient;
	}

	public function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'panda-seasons' !== ( $args->slug ?? '' ) ) return $result;
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $release;
		return (object) array( 'name' => 'Panda Seasons', 'slug' => 'panda-seasons', 'version' => $release['version'], 'author' => 'Kultur3 GmbH', 'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'], 'download_link' => $this->asset_url( $release['asset_id'] ) );
	}

	public function download( $reply, $package, $upgrader, $extra ) {
		$own = is_string( $package ) && 0 === strpos( $package, self::API . '/releases/assets/' );
		if ( ! $own ) return $reply;
		if ( ! current_user_can( 'update_plugins' ) && ! wp_doing_cron() ) return $this->error( __( 'You are not allowed to install updates.', 'panda-seasons' ) );
		$release = $this->release( true );
		if ( is_wp_error( $release ) || ! $this->compatible( $release ) || $package !== $this->asset_url( $release['asset_id'] ) ) return $this->error( __( 'The update package could not be verified.', 'panda-seasons' ) );
		$file = wp_tempnam( 'panda-seasons.zip' );
		if ( ! $file ) return $this->error( __( 'The update package could not be stored temporarily.', 'panda-seasons' ) );
		$response = $this->request( $package, true, $file );
		if ( is_wp_error( $response ) || ! is_file( $file ) || filesize( $file ) !== $release['size'] || ! hash_equals( $release['sha256'], hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return $this->error( __( 'The update package failed its integrity check.', 'panda-seasons' ) );
		}
		$this->install_release = $release;
		return $file;
	}

	public function source( $source, $remote, $upgrader, $extra ) {
		if ( ! $this->install_release || is_wp_error( $source ) ) return $source;
		global $wp_filesystem;
		if ( 'panda-seasons' !== basename( untrailingslashit( $source ) ) ) return $this->error( __( 'The update archive has an invalid folder.', 'panda-seasons' ) );
		$entries = $wp_filesystem->dirlist( $remote );
		if ( ! is_array( $entries ) || array_keys( $entries ) !== array( 'panda-seasons' ) ) return $this->error( __( 'The update archive has an invalid structure.', 'panda-seasons' ) );
		$main = $wp_filesystem->get_contents( trailingslashit( $source ) . 'panda-seasons.php' );
		if ( ! is_string( $main ) || ! preg_match( '/^[ \t\/*#@]*Version:\s*([^\r\n]*)/mi', substr( $main, 0, 8192 ), $match ) || trim( $match[1] ) !== $this->install_release['version'] ) return $this->error( __( 'The update archive version is invalid.', 'panda-seasons' ) );
		return $source;
	}

	public function links( $links ) {
		if ( $this->enabled() && current_user_can( 'update_plugins' ) ) $links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_check_updates' ), 'panda_seasons_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'panda-seasons' ) . '</a>';
		return $links;
	}

	public function check() {
		if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html__( 'You are not allowed to check for updates.', 'panda-seasons' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'panda_seasons_check_updates' );
		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( self_admin_url( 'plugins.php' ) );
		exit;
	}

	public function clear( $upgrader, $extra ) {
		if ( in_array( $this->plugin, (array) ( $extra['plugins'] ?? array( $extra['plugin'] ?? '' ) ), true ) ) delete_site_transient( self::CACHE );
	}
}
