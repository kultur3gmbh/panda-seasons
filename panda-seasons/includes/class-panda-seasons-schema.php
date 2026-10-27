<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Schema {
	const VERSION = '1.2.0';
	const OPTION  = 'panda_seasons_db_version';

	public static function tables() {
		global $wpdb;
		return array(
			'subscriptions' => $wpdb->prefix . 'ps_subscriptions',
			'mail_log'      => $wpdb->prefix . 'ps_mail_log',
			'events'        => $wpdb->prefix . 'ps_consent_events',
			'door_clicks'   => $wpdb->prefix . 'ps_door_clicks',
		);
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables  = self::tables();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$tables['subscriptions']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				calendar_id bigint(20) unsigned NOT NULL,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(190) NOT NULL,
				email_hash char(64) NOT NULL,
				first_name varchar(190) NOT NULL DEFAULT '',
				last_name varchar(190) NOT NULL DEFAULT '',
				status varchar(40) NOT NULL DEFAULT 'pending',
				verification_hash char(64) NOT NULL DEFAULT '',
				source varchar(80) NOT NULL DEFAULT 'calendar_form',
				subscribed_at datetime NOT NULL,
				confirmed_at datetime DEFAULT NULL,
				unsubscribed_at datetime DEFAULT NULL,
				unsubscribe_scope varchar(40) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY calendar_email (calendar_id,email_hash),
				KEY contact_id (contact_id),
				KEY email_status (email_hash,status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['mail_log']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				subscription_id bigint(20) unsigned NOT NULL,
				calendar_id bigint(20) unsigned NOT NULL,
				door_id bigint(20) unsigned NOT NULL,
				email_hash char(64) NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'queued',
				attempts tinyint unsigned NOT NULL DEFAULT 0,
				error_message text NULL,
				sent_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY subscription_door (subscription_id,door_id),
				KEY calendar_status (calendar_id,status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
				calendar_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email_hash char(64) NOT NULL,
				event_type varchar(60) NOT NULL,
				scope varchar(40) NOT NULL DEFAULT '',
				source varchar(80) NOT NULL DEFAULT '',
				details text NULL,
				ip_hash char(64) NOT NULL DEFAULT '',
				occurred_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY email_event (email_hash,event_type),
				KEY calendar_id (calendar_id)
			) $charset;"
		);

		// Store aggregate daily click counts only. No visitor identifiers or raw IP
		// addresses are required for the dashboard statistic.
		dbDelta(
			"CREATE TABLE {$tables['door_clicks']} (
				calendar_id bigint(20) unsigned NOT NULL,
				door_id bigint(20) unsigned NOT NULL,
				click_date date NOT NULL,
				click_count bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (calendar_id,door_id,click_date),
				KEY calendar_date (calendar_id,click_date)
			) $charset;"
		);

		update_option( self::OPTION, self::VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( self::VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}
}
