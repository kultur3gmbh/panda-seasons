<?php

defined( 'ABSPATH' ) || exit;

/** Server-checked quizzes; a unique door/email record owns each reward. */
final class Panda_Seasons_Quiz {
	const FIELDS = array( 'quizReward' => 'reward', 'quizCouponMode' => 'coupon_mode', 'quizCouponId' => 'coupon_id', 'quizRewardType' => 'type', 'quizRewardAmount' => 'amount', 'quizRewardDays' => 'days' );

	public static function boot() {
		add_action( 'wp_ajax_panda_seasons_quiz', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_panda_seasons_quiz', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_panda_seasons_quiz_export', array( __CLASS__, 'export' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'privacy_erasers' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon' ), 20, 3 );
		add_action( 'woocommerce_checkout_create_order_coupon_item', array( __CLASS__, 'attribute_coupon' ), 20, 4 );
	}

	public static function table() { global $wpdb; return $wpdb->prefix . 'ps_quiz_rewards'; }

	public static function privacy_exporters( $exporters ) { $exporters['panda-seasons-quiz'] = array( 'exporter_friendly_name' => 'Panda Seasons quiz', 'callback' => array( __CLASS__, 'privacy_export' ) ); return $exporters; }
	public static function privacy_erasers( $erasers ) { $erasers['panda-seasons-quiz'] = array( 'eraser_friendly_name' => 'Panda Seasons quiz', 'callback' => array( __CLASS__, 'privacy_erase' ) ); return $erasers; }
	public static function privacy_export( $email, $page = 1 ) {
		global $wpdb; $table = self::table(); $offset = ( max( 1, absint( $page ) ) - 1 ) * 100;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE email=%s ORDER BY id LIMIT 100 OFFSET %d", strtolower( sanitize_email( $email ) ), $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$data = array(); foreach ( $rows as $row ) $data[] = array( 'group_id' => 'panda-seasons-quiz', 'group_label' => 'Panda Seasons quiz', 'item_id' => 'ps-quiz-' . $row->id, 'data' => array( array( 'name' => 'Email', 'value' => $row->email ), array( 'name' => 'Door', 'value' => (string) $row->door_id ), array( 'name' => 'Reward', 'value' => $row->kind ), array( 'name' => 'Created (UTC)', 'value' => $row->created_at ) ) );
		return array( 'data' => $data, 'done' => count( $rows ) < 100 );
	}
	public static function privacy_erase( $email, $page = 1 ) {
		global $wpdb; $table = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,coupon_id FROM `{$table}` WHERE email=%s ORDER BY id LIMIT 100", strtolower( sanitize_email( $email ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$removed = false; $retained = false;
		foreach ( $rows as $row ) {
			if ( $row->coupon_id && class_exists( 'WC_Coupon' ) ) {
				$coupon = new WC_Coupon( $row->coupon_id );
				// Invalidate the coupon before removing its personal restrictions. Orders are untouched.
				$coupon->update_meta_data( '_ps_quiz_revoked', 1 );
				$coupon->set_email_restrictions( array() ); $coupon->delete_meta_data( '_ps_quiz_email_hash' ); $coupon->save();
				$trashed = wp_trash_post( $row->coupon_id );
				if ( ! $trashed ) { $retained = true; continue; }
			}
			$removed = (bool) $wpdb->delete( $table, array( 'id' => $row->id ) ) || $removed;
		}
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $retained ? array( 'Some reward records could not be erased safely.' ) : array(), 'done' => count( $rows ) < 100 || $retained );
	}

	public static function editor_data( $id ) {
		$result = array();
		$defaults = array( 'quizReward' => 'message', 'quizCouponMode' => 'new', 'quizCouponId' => 0, 'quizRewardType' => 'percent', 'quizRewardAmount' => 10, 'quizRewardDays' => 7 );
		foreach ( self::FIELDS as $name => $key ) { $value = get_post_meta( $id, '_ps_quiz_reward_' . $key, true ); $result[ $name ] = '' === $value ? $defaults[ $name ] : $value; }
		return $result;
	}

	public static function editor_fields( $coupons ) {
		?>
		<label class="is-wide"><span><?php esc_html_e( 'After a successful quiz', 'panda-seasons' ); ?></span><select data-door-field="quizReward"><option value="message"><?php esc_html_e( 'Success message only', 'panda-seasons' ); ?></option><option value="coupon"><?php esc_html_e( 'Unlock a coupon', 'panda-seasons' ); ?></option><option value="draw"><?php esc_html_e( 'Enter a prize draw', 'panda-seasons' ); ?></option></select></label>
		<label data-quiz-coupon><span><?php esc_html_e( 'Coupon source', 'panda-seasons' ); ?></span><select data-door-field="quizCouponMode"><option value="new"><?php esc_html_e( 'New personal coupon', 'panda-seasons' ); ?></option><option value="existing"><?php esc_html_e( 'Use existing coupon as template', 'panda-seasons' ); ?></option></select></label>
		<label data-quiz-coupon-template><span><?php esc_html_e( 'WooCommerce coupon', 'panda-seasons' ); ?></span><select data-door-field="quizCouponId"><option value="0"><?php esc_html_e( 'Select coupon', 'panda-seasons' ); ?></option><?php foreach ( $coupons as $id => $name ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
		<label data-quiz-coupon-new><span><?php esc_html_e( 'Discount type', 'panda-seasons' ); ?></span><select data-door-field="quizRewardType"><option value="percent"><?php esc_html_e( 'Percent', 'panda-seasons' ); ?></option><option value="fixed_cart"><?php esc_html_e( 'Fixed cart amount', 'panda-seasons' ); ?></option></select></label>
		<label data-quiz-coupon-new><span><?php esc_html_e( 'Discount value', 'panda-seasons' ); ?></span><input type="number" step="0.01" min="0.01" data-door-field="quizRewardAmount"></label>
		<label data-quiz-coupon><span><?php esc_html_e( 'Valid for (days)', 'panda-seasons' ); ?></span><input type="number" min="1" max="365" data-door-field="quizRewardDays"></label>
		<p class="description is-wide"><?php esc_html_e( 'Answers are checked on the server. Rewards require an email address and are issued once per door and address. A template coupon is copied, never changed. Draw entries can be exported in Calendar results; a draw entry is not a guaranteed prize.', 'panda-seasons' ); ?></p>
		<?php
	}

	public static function save( $id, $item ) {
		if ( ! isset( $item['quizReward'] ) ) return;
		$reward = in_array( $item['quizReward'], array( 'message', 'coupon', 'draw' ), true ) ? $item['quizReward'] : 'message';
		if ( 'coupon' === $reward && ! current_user_can( 'manage_woocommerce' ) ) return;
		update_post_meta( $id, '_ps_quiz_reward_reward', $reward );
		update_post_meta( $id, '_ps_quiz_reward_coupon_mode', 'existing' === ( $item['quizCouponMode'] ?? '' ) ? 'existing' : 'new' );
		update_post_meta( $id, '_ps_quiz_reward_coupon_id', absint( $item['quizCouponId'] ?? 0 ) );
		update_post_meta( $id, '_ps_quiz_reward_type', 'fixed_cart' === ( $item['quizRewardType'] ?? '' ) ? 'fixed_cart' : 'percent' );
		update_post_meta( $id, '_ps_quiz_reward_amount', sanitize_text_field( (string) ( $item['quizRewardAmount'] ?? '10' ) ) );
		update_post_meta( $id, '_ps_quiz_reward_days', min( 365, max( 1, absint( $item['quizRewardDays'] ?? 7 ) ) ) );
	}

	public static function markup( $door, $preview = false ) {
		$questions = get_post_meta( $door->ID, '_ps_quiz_questions', true );
		if ( ! is_array( $questions ) || ! $questions ) return '';
		$config = self::editor_data( $door->ID );
		$html = '<form class="panda-seasons__quiz" data-season-quiz data-door-id="' . absint( $door->ID ) . '" data-quiz-preview="' . ( $preview ? '1' : '0' ) . '">';
		foreach ( $questions as $index => $question ) {
			$answers = array_values( array_filter( (array) ( $question['answers'] ?? array() ), 'strlen' ) );
			if ( empty( $question['question'] ) || count( $answers ) < 2 ) continue;
			$html .= '<fieldset data-quiz-question><legend>' . esc_html( $question['question'] ) . '</legend>';
			foreach ( $answers as $a => $answer ) $html .= '<label><input type="radio" name="answers[' . absint( $index ) . ']" value="' . ( $a + 1 ) . '" required> ' . esc_html( $answer ) . '</label>';
			$html .= '</fieldset>';
		}
		if ( 'message' !== $config['quizReward'] ) $html .= '<label>' . esc_html__( 'Email address for your reward or draw entry', 'panda-seasons' ) . '<input type="email" name="email" required autocomplete="email"></label><p>' . esc_html__( 'Used only for this reward or draw, not a newsletter subscription.', 'panda-seasons' ) . '</p>';
		$html .= '<button type="submit">' . esc_html__( 'Check answers', 'panda-seasons' ) . '</button><p data-quiz-result role="status"></p></form>';
		return $html;
	}

	public static function ajax() {
		check_ajax_referer( 'panda_seasons_quiz', 'nonce' );
		$id = absint( $_POST['door'] ?? 0 );
		$ip_hash = hash_hmac( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), wp_salt( 'nonce' ) );
		$rate_key = 'ps_quiz_rate_' . $id . '_' . substr( $ip_hash, 0, 24 );
		$count = absint( get_transient( $rate_key ) );
		if ( $count >= 30 ) wp_send_json_error( array( 'message' => __( 'Please wait before trying again.', 'panda-seasons' ) ), 429 );
		set_transient( $rate_key, $count + 1, 15 * MINUTE_IN_SECONDS );
		$result = self::complete( $id, (array) ( $_POST['answers'] ?? array() ), sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), ! empty( $_POST['preview'] ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		self::record_participant( $id );
		wp_send_json_success( $result );
	}

	/** Count a submitted quiz once per browser session, including incorrect answers. */
	public static function record_participant( $id ) {
		$session = (string) ( $_COOKIE['ps_quiz_session'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $session ) ) {
			$session = wp_generate_uuid4();
			setcookie( 'ps_quiz_session', $session, array( 'expires' => 0, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$_COOKIE['ps_quiz_session'] = $session;
		}
		global $wpdb; $table = Panda_Seasons_Schema::tables()['quiz_participants'];
		$hash = hash_hmac( 'sha256', $id . '|' . $session, wp_salt( 'nonce' ) );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (calendar_id,door_id,session_hash,created_at) VALUES (%d,%d,%s,%s)", wp_get_post_parent_id( $id ), $id, $hash, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	public static function complete( $id, $answers, $email, $preview = false ) {
		$calendar = absint( wp_get_post_parent_id( $id ) );
		if ( $preview || 'panda_door' !== get_post_type( $id ) || 'panda_calendar' !== get_post_type( $calendar ) || 'publish' !== get_post_status( $id ) || 'publish' !== get_post_status( $calendar ) || 'quiz' !== get_post_meta( $id, '_ps_content_type', true ) || ! get_post_meta( $id, '_ps_quiz_enabled', true ) || ! Panda_Seasons_Plugin::door_is_available( $id ) ) return new WP_Error( 'quiz_unavailable', __( 'This quiz is not currently available. Preview cannot issue rewards.', 'panda-seasons' ) );
		$questions = get_post_meta( $id, '_ps_quiz_questions', true );
		if ( ! is_array( $questions ) || ! $questions ) return new WP_Error( 'quiz_empty', __( 'This quiz has no questions.', 'panda-seasons' ) );
		foreach ( $questions as $index => $question ) {
			$options = array_values( array_filter( (array) ( $question['answers'] ?? array() ), 'strlen' ) );
			$correct = absint( $question['correct'] ?? 0 );
			if ( empty( $question['question'] ) || $correct < 1 || $correct > count( $options ) || ! isset( $answers[ $index ] ) || is_array( $answers[ $index ] ) || (string) $answers[ $index ] !== (string) $correct ) return array( 'correct' => false, 'message' => get_post_meta( $id, '_ps_quiz_failure', true ) ?: __( 'That is not correct yet.', 'panda-seasons' ) );
		}
		$success = get_post_meta( $id, '_ps_quiz_success', true ) ?: __( 'All answers correct!', 'panda-seasons' );
		$config = self::editor_data( $id );
		if ( 'message' === $config['quizReward'] ) return array( 'correct' => true, 'message' => $success );
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) return new WP_Error( 'quiz_email', __( 'Enter a valid email address for the reward.', 'panda-seasons' ) );
		$hash = hash_hmac( 'sha256', $email, wp_salt( 'auth' ) );
		$lock = 'ps_quiz_lock_' . $id . '_' . substr( $hash, 0, 24 );
		if ( ! add_option( $lock, time(), '', false ) ) return new WP_Error( 'quiz_busy', __( 'Your reward is being prepared. Please try again shortly.', 'panda-seasons' ) );
		global $wpdb; $table = self::table();
		try {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE door_id=%d AND email_hash=%s", $id, $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
			if ( $existing ) return self::reward_result( $existing->kind, (int) $existing->coupon_id, $success );
			$coupon_id = 0;
			if ( 'coupon' === $config['quizReward'] ) {
				$coupon_id = self::create_coupon( $id, $email, $hash, $config );
				if ( is_wp_error( $coupon_id ) ) return $coupon_id;
			}
			$inserted = $wpdb->insert( $table, array( 'calendar_id' => $calendar, 'door_id' => $id, 'email_hash' => $hash, 'email' => $email, 'kind' => $config['quizReward'], 'coupon_id' => $coupon_id, 'created_at' => current_time( 'mysql', true ) ) );
			if ( ! $inserted ) return new WP_Error( 'quiz_store', __( 'Your reward could not be recorded. Please try again.', 'panda-seasons' ) );
			return self::reward_result( $config['quizReward'], $coupon_id, $success );
		} finally { delete_option( $lock ); }
	}

	private static function create_coupon( $id, $email, $hash, $config ) {
		if ( ! class_exists( 'WC_Coupon' ) ) return new WP_Error( 'quiz_woo', __( 'WooCommerce is required for this reward.', 'panda-seasons' ) );
		$code = 'psq-' . $id . '-' . substr( $hash, 0, 20 );
		$existing_id = wc_get_coupon_id_by_code( $code );
		if ( $existing_id ) {
			$existing = new WC_Coupon( $existing_id );
			return absint( $existing->get_meta( '_ps_quiz_door_id' ) ) === $id && hash_equals( (string) $existing->get_meta( '_ps_quiz_email_hash' ), $hash ) ? $existing_id : new WP_Error( 'quiz_conflict', __( 'The reward assignment could not be verified.', 'panda-seasons' ) );
		}
		try {
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$expiry = time() + max( 1, (int) $config['quizRewardDays'] ) * DAY_IN_SECONDS;
			if ( 'existing' === $config['quizCouponMode'] ) {
				$source = new WC_Coupon( absint( $config['quizCouponId'] ) );
				if ( ! $source->get_id() || 'publish' !== get_post_status( $source->get_id() ) || $source->get_meta( '_ps_door_id' ) || $source->get_meta( '_ps_quiz_door_id' ) || ! $source->is_type( array( 'percent', 'fixed_cart', 'fixed_product' ) ) ) throw new RuntimeException( __( 'The reward coupon template is unavailable.', 'panda-seasons' ) );
				if ( $source->get_date_expires() ) $expiry = min( $expiry, $source->get_date_expires()->getTimestamp() );
				if ( $expiry <= time() ) throw new RuntimeException( __( 'The reward coupon template has expired.', 'panda-seasons' ) );
				$restrictions = $source->get_email_restrictions();
				if ( $restrictions && ! in_array( $email, array_map( 'strtolower', $restrictions ), true ) ) throw new RuntimeException( __( 'This coupon template is restricted to other email addresses.', 'panda-seasons' ) );
				foreach ( array( 'discount_type', 'amount', 'product_ids', 'excluded_product_ids', 'product_categories', 'excluded_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'individual_use', 'free_shipping', 'limit_usage_to_x_items' ) as $property ) $coupon->{ 'set_' . $property }( $source->{ 'get_' . $property }() );
			} else {
				$amount = (float) wc_format_decimal( $config['quizRewardAmount'] );
				if ( $amount <= 0 || ( 'percent' === $config['quizRewardType'] && $amount > 100 ) ) throw new RuntimeException( __( 'The quiz reward discount is invalid.', 'panda-seasons' ) );
				$coupon->set_discount_type( $config['quizRewardType'] ); $coupon->set_amount( $amount );
			}
			$coupon->set_usage_limit( 1 ); $coupon->set_usage_limit_per_user( 1 ); $coupon->set_email_restrictions( array( $email ) ); $coupon->set_date_expires( $expiry );
			$coupon->update_meta_data( '_ps_quiz_door_id', $id ); $coupon->update_meta_data( '_ps_quiz_email_hash', $hash );
			return $coupon->save();
		} catch ( Throwable $error ) { return new WP_Error( 'quiz_coupon', $error->getMessage() ); }
	}

	private static function reward_result( $kind, $id, $message ) {
		if ( 'draw' === $kind ) return array( 'correct' => true, 'message' => $message . ' ' . __( 'Your prize draw entry has been recorded. This is not a guaranteed prize.', 'panda-seasons' ) );
		$coupon = class_exists( 'WC_Coupon' ) ? new WC_Coupon( $id ) : null;
		if ( ! $coupon || ! $coupon->get_id() ) return new WP_Error( 'quiz_missing', __( 'The previously issued reward is unavailable. No additional coupon was issued.', 'panda-seasons' ) );
		return array( 'correct' => true, 'message' => $message . ' ' . __( 'Your personal one-use coupon:', 'panda-seasons' ), 'code' => $coupon->get_code(), 'expires' => $coupon->get_date_expires() ? wp_date( 'd.m.Y H:i', $coupon->get_date_expires()->getTimestamp(), wp_timezone() ) : '' );
	}

	public static function validate_coupon( $valid, $coupon, $discounts = null ) {
		$id = absint( $coupon->get_meta( '_ps_quiz_door_id' ) );
		return ! $id ? $valid : ( $valid && ! $coupon->get_meta( '_ps_quiz_revoked' ) && 'publish' === get_post_status( $coupon->get_id() ) && 'publish' === get_post_status( $id ) && 'publish' === get_post_status( wp_get_post_parent_id( $id ) ) );
	}

	public static function attribute_coupon( $item, $code, $coupon, $order ) {
		$id = absint( $coupon->get_meta( '_ps_quiz_door_id' ) );
		if ( ! $id ) return;
		$item->add_meta_data( '_ps_door_id', $id, true ); $item->add_meta_data( '_ps_offer_calendar_id', absint( wp_get_post_parent_id( $id ) ), true );
	}

	public static function export() {
		$id = absint( $_GET['calendar'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $id ) || 'panda_calendar' !== get_post_type( $id ) ) wp_die( esc_html__( 'You do not have permission.', 'panda-seasons' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'panda_seasons_quiz_export_' . $id );
		global $wpdb; $table = self::table();
		nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="calendar-draw-' . $id . '.csv"' );
		$out = fopen( 'php://output', 'w' ); fputcsv( $out, array( 'Door', 'Email', 'Entered (UTC)' ), ',', '"', '' );
		$last = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,door_id,email,created_at FROM `{$table}` WHERE calendar_id=%d AND kind='draw' AND id>%d ORDER BY id LIMIT 500", $id, $last ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
			foreach ( $rows as $row ) { $email = preg_match( '/^[=+@\-]/', $row->email ) ? "'" . $row->email : $row->email; fputcsv( $out, array( absint( get_post_meta( $row->door_id, '_ps_door_number', true ) ), $email, $row->created_at ), ',', '"', '' ); $last = (int) $row->id; }
		} while ( count( $rows ) === 500 );
		fclose( $out ); exit;
	}
}
