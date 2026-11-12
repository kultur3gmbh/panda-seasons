<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Review {
	public static function boot() {
		add_action( 'wp_ajax_panda_seasons_review', array( __CLASS__, 'ajax' ) );
	}

	public static function ajax() {
		check_ajax_referer( 'panda_seasons_review', 'nonce' );
		$id = absint( $_POST['calendar'] ?? 0 );
		if ( 'panda_calendar' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_send_json_error( array( 'message' => __( 'You cannot review this calendar.', 'panda-seasons' ) ), 403 );
		if ( 'results' === ( $_POST['mode'] ?? '' ) ) wp_send_json_success( self::results( $id ) );
		$items = json_decode( wp_unslash( $_POST['doors'] ?? '[]' ), true );
		if ( ! is_array( $items ) || count( $items ) > 366 ) wp_send_json_error( array( 'message' => __( 'The calendar data is invalid.', 'panda-seasons' ) ), 400 );
		$mail = ! empty( $_POST['email_enabled'] );
		wp_send_json_success( array( 'issues' => self::issues( $items, $mail, $id ) ) );
	}

	/** Inspect the supplied editor snapshot without saving or running shortcodes. */
	public static function issues( $items, $mail, $calendar_id ) {
		$issues = array();
		foreach ( $items as $index => $door ) {
			if ( ! is_array( $door ) || ! empty( $door['deleted'] ) ) continue;
			$messages = array();
			$type = $door['contentType'] ?? 'general';
			$text = trim( wp_strip_all_tags( (string) ( $door['content'] ?? '' ) ) );
			$has_content = '' !== $text;
			$product = null;
			if ( 'product' === $type ) {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $door['productId'] ?? 0 ) ) : false;
				$has_content = $product && $product->is_visible() && $product->is_purchasable() && $product->is_in_stock();
				if ( ! $has_content ) $messages[] = __( 'The linked product is not available.', 'panda-seasons' );
			} elseif ( 'quiz' === $type ) {
				$questions = array_filter( (array) ( $door['quizQuestions'] ?? array() ), static function ( $question ) {
					$answers = array_values( array_filter( (array) ( $question['answers'] ?? array() ), 'strlen' ) );
					return ! empty( $question['question'] ) && count( $answers ) >= 2 && (int) ( $question['correct'] ?? 0 ) >= 1 && (int) $question['correct'] <= count( $answers );
				} );
				$has_content = ! empty( $door['quizEnabled'] ) && ! empty( $questions );
			} else {
				$media = absint( $door['mediaId'] ?? 0 );
				$download = absint( $door['downloadId'] ?? 0 );
				$has_media = $media && wp_get_attachment_url( $media );
				$has_content = 'video' === $type ? $has_media : ( $has_content || $has_media || ( $download && wp_get_attachment_url( $download ) ) || ( ! empty( $door['buttonLabel'] ) && ! empty( $door['buttonUrl'] ) ) );
			}
			if ( ! $has_content && 'product' !== $type ) $messages[] = __( 'This door has no usable content.', 'panda-seasons' );
			$date = (string) ( $door['unlockDate'] ?? '' );
			if ( ! Panda_Seasons_Offers::window( $date, $date ) ) $messages[] = __( 'Set a valid opening date.', 'panda-seasons' );
			if ( $mail ) {
				$email = trim( wp_strip_all_tags( (string) ( $door['emailContent'] ?? '' ) ) );
				$mail_media = ! empty( $door['mediaId'] ) && wp_get_attachment_url( absint( $door['mediaId'] ) );
				// Same fallback as the mailer, including additional media/product content.
				if ( '' === $email && '' === $text && ! ( 'product' === $type && $product ) && ! ( in_array( $type, array( 'general', 'video' ), true ) && ( $has_content || $mail_media ) ) ) $messages[] = __( 'The daily email has no content, including its door-content fallback.', 'panda-seasons' );
			}
			$mode = $door['discountMode'] ?? ( empty( $door['couponId'] ) ? 'none' : 'existing' );
			if ( 'product' === $type && 'new' === $mode ) {
				$amount = (float) ( $door['discountAmount'] ?? 0 );
				$start = (string) ( $door['discountStart'] ?? '' ) ?: $date;
				$end = (string) ( $door['discountEnd'] ?? '' ) ?: $date;
				if ( ! Panda_Seasons_Offers::window( $start, $end, $door['discountStartTime'] ?? '00:00', $door['discountEndTime'] ?? '23:59' ) || $amount <= 0 || ( 'percent' === ( $door['discountType'] ?? 'percent' ) && $amount > 100 ) ) $messages[] = __( 'Check the discount value and validity dates.', 'panda-seasons' );
			} elseif ( 'product' === $type && 'existing' === $mode ) {
				$coupon = class_exists( 'WC_Coupon' ) ? new WC_Coupon( absint( $door['couponId'] ?? 0 ) ) : null;
				if ( ! $coupon || ! $coupon->get_id() || 'publish' !== get_post_status( $coupon->get_id() ) ) $messages[] = __( 'Select an available WooCommerce coupon.', 'panda-seasons' );
				elseif ( ! Panda_Seasons_Offers::product_coupon( $coupon, absint( $door['productId'] ?? 0 ) ) || $coupon->get_meta( '_ps_door_id' ) ) $messages[] = __( 'Choose an existing product coupon restricted to this product only. Generated calendar coupons belong to their original door.', 'panda-seasons' );
			}
			$id = absint( $door['id'] ?? 0 );
			if ( $id && absint( wp_get_post_parent_id( $id ) ) === $calendar_id ) {
				$error = get_post_meta( $id, '_ps_discount_error', true );
				if ( $error ) $messages[] = (string) $error;
			}
			foreach ( $messages as $message ) $issues[] = array( 'index' => $index, 'number' => absint( $door['number'] ?? $index + 1 ), 'message' => $message );
		}
		return $issues;
	}

	public static function results( $calendar_id ) {
		global $wpdb;
		$table = Panda_Seasons_Schema::tables()['door_clicks'];
		$clicks = $wpdb->get_results( $wpdb->prepare( "SELECT door_id,SUM(click_count) AS total FROM `{$table}` WHERE calendar_id=%d GROUP BY door_id", $calendar_id ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$participant_table = Panda_Seasons_Schema::tables()['quiz_participants'];
		$participants = $wpdb->get_results( $wpdb->prepare( "SELECT door_id,COUNT(*) AS total FROM `{$participant_table}` WHERE calendar_id=%d GROUP BY door_id", $calendar_id ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$redemptions = array();
		$has_more = false;
		if ( function_exists( 'wc_get_orders' ) && current_user_can( 'manage_woocommerce' ) ) {
			// Coupon-line attribution is immutable and works with both HPOS and posts.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT i.order_id FROM {$wpdb->prefix}woocommerce_order_items i INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta m ON m.order_item_id=i.order_item_id WHERE i.order_item_type='coupon' AND m.meta_key='_ps_offer_calendar_id' AND m.meta_value=%s ORDER BY i.order_id DESC LIMIT 1001", (string) $calendar_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal tables.
			$has_more = count( $ids ) > 1000;
			foreach ( array_chunk( array_slice( $ids, 0, 1000 ), 100 ) as $chunk ) {
				$orders = wc_get_orders( array( 'include' => array_map( 'absint', $chunk ), 'status' => wc_get_is_paid_statuses(), 'limit' => 100 ) );
				foreach ( $orders as $order ) {
					if ( (float) $order->get_total() > 0 && (float) $order->get_total_refunded() >= (float) $order->get_total() ) continue;
					$seen = array();
					foreach ( $order->get_items( 'coupon' ) as $item ) {
						$id = absint( $item->get_meta( '_ps_door_id' ) );
						if ( $id && $calendar_id === absint( $item->get_meta( '_ps_offer_calendar_id' ) ) && (float) $item->get_discount() > 0 ) $seen[ $id ] = true;
					}
					foreach ( $seen as $id => $unused ) $redemptions[ $id ] = ( $redemptions[ $id ] ?? 0 ) + 1;
				}
			}
		}
		$rows = array();
		foreach ( Panda_Seasons_Plugin::doors( $calendar_id ) as $door ) {
			$types = array( 'general' => __( 'General content', 'panda-seasons' ), 'video' => __( 'Video', 'panda-seasons' ), 'product' => __( 'Product', 'panda-seasons' ), 'quiz' => __( 'Quiz', 'panda-seasons' ) );
			$type = get_post_meta( $door->ID, '_ps_content_type', true ) ?: 'general';
			$rows[] = array( 'number' => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ), 'title' => $door->post_title, 'type' => $types[ $type ] ?? $type, 'heading' => 'product' === $type ? Panda_Seasons_Offers::heading( $door->ID ) : ( get_post_meta( $door->ID, '_ps_content_heading', true ) ?: $door->post_title ), 'openings' => absint( $clicks[ $door->ID ]->total ?? 0 ), 'participants' => 'quiz' === $type ? absint( $participants[ $door->ID ]->total ?? 0 ) : null, 'redemptions' => current_user_can( 'manage_woocommerce' ) && function_exists( 'wc_get_orders' ) ? ( $redemptions[ $door->ID ] ?? 0 ) : null );
		}
		return array( 'rows' => $rows, 'limited' => $has_more, 'drawExport' => current_user_can( 'manage_options' ) ? wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_quiz_export&calendar=' . $calendar_id ), 'panda_seasons_quiz_export_' . $calendar_id ) : '' );
	}
}
