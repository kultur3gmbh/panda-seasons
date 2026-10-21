<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Content and consent records are intentionally retained. Administrators can
// remove them explicitly before uninstalling; silent data loss is avoided.
delete_option( 'panda_seasons_db_version' );
