<?php

defined( 'ABSPATH' ) || exit;

/**
 * Portable, subscriber-free calendar backups.
 */
final class Panda_Seasons_Transfer {
	public static function export( $calendar_id, $with_contents = true ) {
		$calendar = get_post( $calendar_id );
		if ( ! $calendar || Panda_Seasons_Plugin::CALENDAR_POST_TYPE !== $calendar->post_type ) return new WP_Error( 'panda_seasons_calendar_not_found', __( 'The selected calendar cannot be exported.', 'panda-seasons' ) );

		$calendar_data = self::post_data( $calendar, Panda_Seasons_Contract::calendar_meta(), Panda_Seasons_Contract::calendar_media() );
		if ( ! $with_contents ) $calendar_data = self::without_calendar_contents( $calendar_data );
		$doors         = array();
		foreach ( Panda_Seasons_Plugin::doors( $calendar_id, 'any' ) as $door ) {
			$door_data = self::post_data( $door, Panda_Seasons_Contract::door_meta(), Panda_Seasons_Contract::door_media() );
			if ( ! $with_contents ) $door_data = self::without_door_contents( $door_data );
			$doors[] = $door_data;
		}

		return array(
			'format'          => 'panda-seasons-template',
			'format_version'  => Panda_Seasons_Contract::TEMPLATE_VERSION,
			'data_version'    => Panda_Seasons_Contract::DATA_VERSION,
			'plugin_version'  => PANDA_SEASONS_VERSION,
			'contents'        => $with_contents ? 'included' : 'excluded',
			'exported_at'     => gmdate( 'c' ),
			'calendar'        => $calendar_data,
			'doors'           => $doors,
			'not_transferred' => array( 'subscriber_data', 'mail_log', 'consent_events', 'product_assignments', 'coupon_assignments', 'event_assignments' ),
		);
	}

	/** Keep layout and appearance while removing editorial and commercial content. */
	private static function without_door_contents( $door ) {
		$content_keys = array(
			'_ps_unlock_date', '_ps_unlock_time', '_ps_closed_label', '_ps_open_label',
			'_ps_content_heading', '_ps_content_type', '_ps_media_id', '_ps_download_id',
			'_ps_download_label', '_ps_button_label', '_ps_button_url', '_ps_product_id',
			'_ps_coupon_id', '_ps_event_id', '_ps_quiz_enabled', '_ps_quiz_questions',
			'_ps_quiz_success', '_ps_quiz_failure', '_ps_cta_mode', '_ps_cta_enabled',
			'_ps_cta_title', '_ps_cta_text', '_ps_cta_type', '_ps_cta_label', '_ps_cta_url',
			'_ps_cta_shortcode', '_ps_email_subject', '_ps_email_content',
		);
		$door['title']   = '';
		$door['content'] = '';
		$door['excerpt'] = '';
		foreach ( $content_keys as $key ) unset( $door['meta'][ $key ], $door['media'][ $key ] );
		return $door;
	}

	private static function without_calendar_contents( $calendar ) {
		$prefixes = array( '_ps_default_cta_', '_ps_email_', '_ps_newsletter_', '_ps_signup_', '_ps_doi_', '_ps_confirmation_' );
		foreach ( array_keys( $calendar['meta'] ) as $key ) {
			if ( '_ps_contact_tag' === $key ) unset( $calendar['meta'][ $key ] );
			foreach ( $prefixes as $prefix ) if ( 0 === strpos( $key, $prefix ) ) unset( $calendar['meta'][ $key ] );
		}
		$calendar['content'] = '';
		$calendar['excerpt'] = '';
		return $calendar;
	}

	private static function post_data( $post, $allowed_meta, $media_keys ) {
		$meta  = array();
		$media = array();
		$relations = Panda_Seasons_Contract::local_relations();

		foreach ( $allowed_meta as $key ) {
			if ( in_array( $key, $relations, true ) ) continue;
			$value = get_post_meta( $post->ID, '_ps_' . $key, true );
			if ( in_array( $key, $media_keys, true ) ) {
				$attachment_id = absint( $value );
				$media[ '_ps_' . $key ] = $attachment_id ? ( wp_get_attachment_url( $attachment_id ) ?: '' ) : '';
				continue;
			}
			if ( 'design_layers' === $key && is_array( $value ) ) {
				foreach ( $value as &$layer ) {
					if ( ! is_array( $layer ) ) continue;
					$attachment_id = absint( $layer['imageId'] ?? 0 );
					$layer['imageUrl'] = $attachment_id ? ( wp_get_attachment_url( $attachment_id ) ?: '' ) : '';
					$layer['imageId'] = 0;
				}
				unset( $layer );
			}
			if ( '' !== $value && null !== $value ) $meta[ '_ps_' . $key ] = $value;
		}

		return array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
			'meta'    => $meta,
			'media'   => $media,
		);
	}
}
