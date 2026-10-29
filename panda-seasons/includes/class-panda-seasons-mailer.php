<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Mailer {
	public static function process() {
		if ( ! Panda_Seasons_Plugin::contactpanda_available() ) return;
		$calendars = get_posts( array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_ps_email_enabled', 'meta_value' => '1' ) );
		foreach ( $calendars as $calendar ) self::process_calendar( $calendar );
	}

	private static function process_calendar( $calendar ) {
		$time = get_post_meta( $calendar->ID, '_ps_email_time', true ) ?: '09:00';
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $now->format( 'H:i' ) < $time ) return;
		$doors = Panda_Seasons_Plugin::doors( $calendar->ID );
		foreach ( $doors as $door ) {
			if ( get_post_meta( $door->ID, '_ps_unlock_date', true ) !== $now->format( 'Y-m-d' ) ) continue;
			if ( ! Panda_Seasons_Plugin::door_is_available( $door->ID ) ) continue;
			self::deliver_door( $calendar, $door );
		}
	}

	private static function deliver_door( $calendar, $door ) {
		global $wpdb; $tables = Panda_Seasons_Schema::tables();
		$subscriptions = $wpdb->get_results( $wpdb->prepare( "SELECT s.*,l.id AS log_id,l.status AS log_status,l.attempts AS log_attempts FROM `{$tables['subscriptions']}` s LEFT JOIN `{$tables['mail_log']}` l ON l.subscription_id=s.id AND l.door_id=%d WHERE s.calendar_id=%d AND s.status='active' AND (l.id IS NULL OR (l.status='failed' AND l.attempts<3)) ORDER BY s.id ASC LIMIT 500", $door->ID, $calendar->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		foreach ( $subscriptions as $subscription ) {
			$log_id = absint( $subscription['log_id'] ?? 0 );
			if ( ! $log_id ) {
				$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['mail_log']}` (subscription_id,calendar_id,door_id,email_hash,status,attempts,created_at) VALUES (%d,%d,%d,%s,'queued',0,%s)", $subscription['id'], $calendar->ID, $door->ID, $subscription['email_hash'], current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				if ( ! $inserted ) continue;
				$log_id = absint( $wpdb->insert_id );
			}
			$attempts = absint( $subscription['log_attempts'] ?? 0 ) + 1;
			$wpdb->update( $tables['mail_log'], array( 'status' => 'sending', 'attempts' => $attempts, 'error_message' => '' ), array( 'id' => $log_id ) );
			$subject = get_post_meta( $door->ID, '_ps_email_subject', true ) ?: get_post_meta( $calendar->ID, '_ps_email_subject', true );
			if ( '' === trim( (string) $subject ) ) $subject = __( '{calendar}: Your door for day {day} is waiting', 'panda-seasons' );
			$number = absint( get_post_meta( $door->ID, '_ps_door_number', true ) );
			$subject = strtr( $subject, array( '{calendar}' => $calendar->post_title, '{day}' => (string) $number, '{first_name}' => $subscription['first_name'] ) );
			$calendar_url = apply_filters( 'panda_seasons_calendar_url', home_url( '/' ), $calendar->ID );
			$unsubscribe = Panda_Seasons_Subscriptions::unsubscribe_url( $subscription['id'] );
			$email_content = (string) get_post_meta( $door->ID, '_ps_email_content', true );
			if ( '' === trim( $email_content ) ) $email_content = $door->post_content;
			$intro = sprintf( __( 'Hello %1$s, a new door in “%2$s” is ready for you. We hope you enjoy discovering today’s surprise.', 'panda-seasons' ), $subscription['first_name'], $calendar->post_title );
			$html = '<p>' . esc_html( $intro ) . '</p><h1>' . esc_html( $door->post_title ?: sprintf( __( 'Door %d', 'panda-seasons' ), $number ) ) . '</h1>' . apply_filters( 'the_content', $email_content );
			$html .= Panda_Seasons_Plugin::door_content_markup( $door, 'email' );
			$html .= '<p><a class="contactpanda-email-button" href="' . esc_url( $calendar_url . '#panda-season-door-' . $door->ID ) . '">' . esc_html__( 'Open calendar', 'panda-seasons' ) . '</a></p>';
			$html .= '<hr><p><a href="' . esc_url( $unsubscribe ) . '">' . esc_html__( 'Manage or unsubscribe from calendar emails', 'panda-seasons' ) . '</a></p>';
			$payload = apply_filters( 'panda_seasons_door_email', array( 'email' => $subscription['email'], 'subject' => $subject, 'html' => $html ), $calendar, $door, $subscription );
			$sent = Panda_Seasons_Subscriptions::send_mail( $payload['email'], $payload['subject'], $payload['html'] );
			$wpdb->update( $tables['mail_log'], array( 'status' => $sent ? 'sent' : 'failed', 'sent_at' => $sent ? current_time( 'mysql' ) : null, 'error_message' => $sent ? '' : __( 'WordPress could not send this email.', 'panda-seasons' ) ), array( 'id' => $log_id ) );
			do_action( 'panda_seasons_email_result', $sent, $subscription, $calendar, $door );
		}
	}
}
