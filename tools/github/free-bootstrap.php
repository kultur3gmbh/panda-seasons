<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'Panda_Public_Updater' ) ) require_once __DIR__ . '/class-panda-public-updater.php';
new Panda_Public_Updater( plugin_basename( PANDA_SEASONS_FREE_FILE ), PANDA_SEASONS_FREE_VERSION, array(
	'repository' => 'kultur3gmbh/panda-seasons', 'slug' => 'panda-seasons-free', 'tag_prefix' => 'free-v',
	'name' => 'Panda Seasons Free', 'asset_prefix' => 'panda-seasons-free', 'manifest' => 'panda-seasons-free-update.json',
) );
