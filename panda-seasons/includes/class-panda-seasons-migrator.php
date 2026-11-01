<?php

defined( 'ABSPATH' ) || exit;

/**
 * One-time consolidation of the editor data created during the prototype phase.
 */
final class Panda_Seasons_Migrator {
	const OPTION = 'panda_seasons_data_version';

	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) >= Panda_Seasons_Contract::DATA_VERSION ) return;

		self::migrate_doors();
		$page = 1;
		do {
			$calendar_ids = get_posts( array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 50, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			foreach ( $calendar_ids as $calendar_id ) update_post_meta( $calendar_id, '_ps_data_version', Panda_Seasons_Contract::DATA_VERSION );
			$page++;
		} while ( count( $calendar_ids ) === 50 );

		update_option( self::OPTION, Panda_Seasons_Contract::DATA_VERSION, false );
	}

	private static function migrate_doors() {
		$page = 1;
		do {
			$doors = get_posts( array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 100, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			foreach ( $doors as $door ) {
				$calendar_id = (int) $door->post_parent;
				$legacy_calendar_id = absint( get_post_meta( $door->ID, '_ps_calendar_id', true ) );
				if ( ! $calendar_id ) $calendar_id = $legacy_calendar_id;
				if ( ! $calendar_id ) continue;
				if ( (int) $door->post_parent !== $calendar_id ) wp_update_post( array( 'ID' => $door->ID, 'post_parent' => $calendar_id ) );

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
			$page++;
			if ( function_exists( 'wp_cache_flush_runtime' ) ) wp_cache_flush_runtime();
		} while ( count( $doors ) === 100 );
	}
}
