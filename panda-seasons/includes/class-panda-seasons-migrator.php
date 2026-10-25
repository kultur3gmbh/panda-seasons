<?php

defined( 'ABSPATH' ) || exit;

/**
 * One-time consolidation of the editor data created during the prototype phase.
 */
final class Panda_Seasons_Migrator {
	const OPTION = 'panda_seasons_data_version';

	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) >= Panda_Seasons_Contract::DATA_VERSION ) return;

		$calendar_ids = get_posts(
			array(
				'post_type'      => Panda_Seasons_Plugin::CALENDAR_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $calendar_ids as $calendar_id ) {
			update_post_meta( $calendar_id, '_ps_data_version', Panda_Seasons_Contract::DATA_VERSION );
			self::migrate_doors( (int) $calendar_id );
		}

		update_option( self::OPTION, Panda_Seasons_Contract::DATA_VERSION, false );
	}

	private static function migrate_doors( $calendar_id ) {
		$doors = get_posts(
			array(
				'post_type'      => Panda_Seasons_Plugin::DOOR_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $doors as $door ) {
			$legacy_calendar_id = absint( get_post_meta( $door->ID, '_ps_calendar_id', true ) );
			if ( (int) $door->post_parent !== $calendar_id && $legacy_calendar_id !== $calendar_id ) continue;
			if ( (int) $door->post_parent !== $calendar_id ) {
				wp_update_post( array( 'ID' => $door->ID, 'post_parent' => $calendar_id ) );
			}

			$opening_mode = (string) get_post_meta( $door->ID, '_ps_opening_mode', true );
			if ( ! in_array( $opening_mode, array( 'manual-day', 'auto-day', 'auto-permanent' ), true ) ) {
				$behavior = (string) get_post_meta( $door->ID, '_ps_unlock_behavior', true );
				$window   = (string) get_post_meta( $door->ID, '_ps_availability_window', true );
				$opening_mode = 'release-day' === $window
					? ( 'auto-open' === $behavior ? 'auto-day' : 'manual-day' )
					: 'auto-permanent';
				update_post_meta( $door->ID, '_ps_opening_mode', $opening_mode );
			}

			$questions = get_post_meta( $door->ID, '_ps_quiz_questions', true );
			if ( ! is_array( $questions ) || ! $questions ) {
				$question = trim( (string) get_post_meta( $door->ID, '_ps_quiz_question', true ) );
				$answers  = array_values( array_filter( (array) get_post_meta( $door->ID, '_ps_quiz_answers', true ), 'strlen' ) );
				$correct  = absint( get_post_meta( $door->ID, '_ps_quiz_correct', true ) );
				if ( $question && count( $answers ) >= 2 ) {
					update_post_meta(
						$door->ID,
						'_ps_quiz_questions',
						array( array( 'question' => $question, 'answers' => $answers, 'correct' => max( 1, $correct ) ) )
					);
				}
			}

			foreach ( Panda_Seasons_Contract::legacy_door_meta() as $legacy_key ) {
				delete_post_meta( $door->ID, '_ps_' . $legacy_key );
			}
			update_post_meta( $door->ID, '_ps_data_version', Panda_Seasons_Contract::DATA_VERSION );
		}
	}
}
