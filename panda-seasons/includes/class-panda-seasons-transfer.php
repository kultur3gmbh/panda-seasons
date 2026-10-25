<?php

defined( 'ABSPATH' ) || exit;

/**
 * Portable, subscriber-free calendar backups.
 */
final class Panda_Seasons_Transfer {
	public static function export( $calendar_id ) {
		$calendar = get_post( $calendar_id );
		if ( ! $calendar || Panda_Seasons_Plugin::CALENDAR_POST_TYPE !== $calendar->post_type ) return new WP_Error( 'panda_seasons_calendar_not_found', __( 'The selected calendar cannot be exported.', 'panda-seasons' ) );

		$calendar_data = self::post_data( $calendar, Panda_Seasons_Contract::calendar_meta(), Panda_Seasons_Contract::calendar_media() );
		$doors         = array();
		foreach ( Panda_Seasons_Plugin::doors( $calendar_id, 'any' ) as $door ) {
			$doors[] = self::post_data( $door, Panda_Seasons_Contract::door_meta(), Panda_Seasons_Contract::door_media() );
		}

		return array(
			'format'          => 'panda-seasons-template',
			'format_version'  => Panda_Seasons_Contract::TEMPLATE_VERSION,
			'data_version'    => Panda_Seasons_Contract::DATA_VERSION,
			'plugin_version'  => PANDA_SEASONS_VERSION,
			'exported_at'     => gmdate( 'c' ),
			'calendar'        => $calendar_data,
			'doors'           => $doors,
			'not_transferred' => array( 'subscriber_data', 'mail_log', 'consent_events', 'product_assignments', 'coupon_assignments', 'event_assignments' ),
		);
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
