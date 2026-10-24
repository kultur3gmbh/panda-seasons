<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Subscriptions {
	public static function boot() {
		add_action( 'admin_post_panda_seasons_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'admin_post_nopriv_panda_seasons_subscribe', array( __CLASS__, 'subscribe' ) );
		add_action( 'admin_post_panda_seasons_confirm', array( __CLASS__, 'confirm' ) );
		add_action( 'admin_post_nopriv_panda_seasons_confirm', array( __CLASS__, 'confirm' ) );
		add_action( 'admin_post_panda_seasons_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_nopriv_panda_seasons_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
	}

	public static function form( $calendar_id ) {
		if ( ! Panda_Seasons_Plugin::contactpanda_available() ) return '';
		$message = sanitize_key( wp_unslash( $_GET['panda_seasons_message'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status.
		$messages = array(
			'active'  => __( 'You are registered for this email calendar.', 'panda-seasons' ),
			'pending' => __( 'Please check your inbox and confirm your email address. Also check your spam folder.', 'panda-seasons' ),
			'error'   => __( 'The registration could not be completed. Please try again.', 'panda-seasons' ),
		);
		ob_start();
		?>
		<div class="panda-seasons__subscription">
			<h3><?php echo esc_html( get_post_meta( $calendar_id, '_ps_signup_title', true ) ?: __( 'Receive this calendar by email', 'panda-seasons' ) ); ?></h3>
			<?php if ( get_post_meta( $calendar_id, '_ps_signup_text', true ) ) : ?><p><?php echo esc_html( get_post_meta( $calendar_id, '_ps_signup_text', true ) ); ?></p><?php endif; ?>
			<?php if ( isset( $messages[ $message ] ) ) : ?><p class="panda-seasons__notice" role="status"><?php echo esc_html( $messages[ $message ] ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="panda_seasons_subscribe">
				<input type="hidden" name="calendar_id" value="<?php echo esc_attr( $calendar_id ); ?>">
				<?php wp_nonce_field( 'panda_seasons_subscribe_' . $calendar_id ); ?>
				<label><span><?php esc_html_e( 'First name', 'panda-seasons' ); ?></span><input type="text" name="first_name" autocomplete="given-name" required></label>
				<label><span><?php esc_html_e( 'Last name', 'panda-seasons' ); ?></span><input type="text" name="last_name" autocomplete="family-name" required></label>
				<label><span><?php esc_html_e( 'Email address', 'panda-seasons' ); ?></span><input type="email" name="email" autocomplete="email" required></label>
				<label class="panda-seasons__consent"><input type="checkbox" name="calendar_consent" value="1" required> <span><?php echo esc_html( sprintf( __( 'I would like to receive the daily emails for “%s”. I can unsubscribe at any time.', 'panda-seasons' ), get_the_title( $calendar_id ) ) ); ?></span></label>
				<button type="submit"><?php esc_html_e( 'Register for email calendar', 'panda-seasons' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function subscribe() {
		$calendar_id = absint( $_POST['calendar_id'] ?? 0 );
		if ( ! $calendar_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'panda_seasons_subscribe_' . $calendar_id ) ) self::redirect( $calendar_id, 'error' );
		if ( ! Panda_Seasons_Plugin::contactpanda_available() || ! get_post_meta( $calendar_id, '_ps_email_enabled', true ) || empty( $_POST['calendar_consent'] ) ) self::redirect( $calendar_id, 'error' );
		$email      = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		if ( ! is_email( $email ) || ! $first_name || ! $last_name ) self::redirect( $calendar_id, 'error' );

		$contact    = ContactPanda_Plugin::extension_contact_by_email( $email );
		$contact_id = $contact ? absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) ) : 0;
		$status     = $contact ? ContactPanda_Plugin::extension_optin_status( $contact ) : '';
		if ( in_array( $status, array( 6, 7, 8, 9, 'hard_bounce', 'bounced', 'spam', 'complained', 'blocked' ), true ) ) self::redirect( $calendar_id, 'error' );
		if ( ! $contact && method_exists( 'ContactPanda_Plugin', 'extension_create_contact' ) ) {
			$created = ContactPanda_Plugin::extension_create_contact( array( 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name ) );
			if ( ! is_wp_error( $created ) ) $contact_id = absint( $created );
		}

		$verified = ( $contact && ContactPanda_Plugin::extension_is_marketable( $contact ) ) || self::email_was_confirmed( $email );
		if ( is_user_logged_in() && strtolower( wp_get_current_user()->user_email ) === $email ) $verified = true;
		$token = wp_generate_password( 48, false, false );
		$row   = self::upsert( $calendar_id, $contact_id, $email, $first_name, $last_name, $verified ? 'active' : 'pending', $token );
		if ( ! $row ) self::redirect( $calendar_id, 'error' );
		do_action( 'panda_seasons_contact_registered', $contact_id, array(
			'source'       => 'panda_seasons',
			'source_id'    => (string) $calendar_id,
			'source_title' => get_the_title( $calendar_id ),
			'source_url'   => wp_get_referer() ?: get_permalink( $calendar_id ),
			'tag'          => (string) get_post_meta( $calendar_id, '_ps_contact_tag', true ) ?: sprintf( __( 'Panda Seasons – %s', 'panda-seasons' ), get_the_title( $calendar_id ) ),
			'form'         => 'calendar_email_signup',
			'subscribed_at'=> current_time( 'mysql' ),
		), $row );
		self::log_event( $row, $verified ? 'subscribed' : 'confirmation_requested', 'calendar', 'calendar_form' );
		if ( $verified ) self::redirect( $calendar_id, 'active' );

		$url = add_query_arg( array( 'action' => 'panda_seasons_confirm', 'subscription' => $row['id'], 'token' => $token ), admin_url( 'admin-post.php' ) );
		$subject = sprintf( __( 'Confirm your registration for %s', 'panda-seasons' ), get_the_title( $calendar_id ) );
		$body = '<p>' . esc_html( sprintf( __( 'Hello %s,', 'panda-seasons' ), $first_name ) ) . '</p><p>' . esc_html__( 'Please confirm that you would like to receive this email calendar.', 'panda-seasons' ) . '</p><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Confirm email calendar', 'panda-seasons' ) . '</a></p>';
		$sent = self::send( $email, $subject, $body );
		if ( ! $sent ) self::redirect( $calendar_id, 'error' );
		self::redirect( $calendar_id, 'pending' );
	}

	public static function confirm() {
		$row = self::verified_row( absint( $_GET['subscription'] ?? 0 ), sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ) );
		if ( ! $row ) wp_die( esc_html__( 'This confirmation link is invalid.', 'panda-seasons' ), '', array( 'response' => 403 ) );
		global $wpdb; $table = Panda_Seasons_Schema::tables()['subscriptions']; $now = current_time( 'mysql' );
		$wpdb->update( $table, array( 'status' => 'active', 'confirmed_at' => $now, 'verification_hash' => '', 'unsubscribed_at' => null, 'unsubscribe_scope' => '', 'updated_at' => $now ), array( 'id' => $row['id'] ) );
		$row['status'] = 'active'; self::log_event( $row, 'confirmed', 'calendar', 'double_opt_in' );
		self::result_page( __( 'Registration confirmed', 'panda-seasons' ), sprintf( __( 'You will now receive the emails for “%s”.', 'panda-seasons' ), get_the_title( $row['calendar_id'] ) ) );
	}

	public static function unsubscribe_url( $subscription_id ) {
		global $wpdb; $table = Panda_Seasons_Schema::tables()['subscriptions']; $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		if ( ! $row ) return '';
		return add_query_arg( array( 'action' => 'panda_seasons_unsubscribe', 'subscription' => $row['id'], 'token' => self::action_token( $row ) ), admin_url( 'admin-post.php' ) );
	}

	public static function unsubscribe() {
		$id    = absint( $_REQUEST['subscription'] ?? 0 );
		$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ?? '' ) );
		global $wpdb; $tables = Panda_Seasons_Schema::tables(); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tables['subscriptions']}` WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		if ( ! $row || ! hash_equals( self::action_token( $row ), $token ) ) wp_die( esc_html__( 'This unsubscribe link is invalid.', 'panda-seasons' ), '', array( 'response' => 403 ) );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			self::unsubscribe_page( $row, $token );
		}
		$scope = sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
		if ( ! in_array( $scope, array( 'calendar', 'all_calendars', 'all_mailings' ), true ) ) wp_die( esc_html__( 'Please select what you want to unsubscribe from.', 'panda-seasons' ), '', array( 'response' => 400 ) );
		$now = current_time( 'mysql' );
		if ( 'calendar' === $scope ) {
			$wpdb->update( $tables['subscriptions'], array( 'status' => 'calendar_unsubscribed', 'unsubscribed_at' => $now, 'unsubscribe_scope' => $scope, 'updated_at' => $now ), array( 'id' => $row['id'] ) );
		} else {
			$status = 'all_mailings' === $scope ? 'global_unsubscribed' : 'all_calendars_unsubscribed';
			$wpdb->query( $wpdb->prepare( "UPDATE `{$tables['subscriptions']}` SET status=%s,unsubscribed_at=%s,unsubscribe_scope=%s,updated_at=%s WHERE email_hash=%s", $status, $now, $scope, $now, $row['email_hash'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			if ( 'all_mailings' === $scope && $row['contact_id'] && method_exists( 'ContactPanda_Plugin', 'extension_unsubscribe_contact' ) ) {
				ContactPanda_Plugin::extension_unsubscribe_contact( $row['contact_id'], 'panda_seasons', sprintf( 'All mailings unsubscribed through Panda Seasons at %s.', $now ), $row['email'] );
			}
		}
		self::log_event( $row, 'unsubscribed', $scope, 'email_link' );
		$labels = array( 'calendar' => __( 'You have been finally unsubscribed from this calendar.', 'panda-seasons' ), 'all_calendars' => __( 'You have been finally unsubscribed from all email calendars.', 'panda-seasons' ), 'all_mailings' => __( 'You have been finally unsubscribed from all email mailings.', 'panda-seasons' ) );
		self::result_page( __( 'Finally unsubscribed', 'panda-seasons' ), $labels[ $scope ] );
	}

	private static function unsubscribe_page( $row, $token ) {
		$title = __( 'What would you like to unsubscribe from?', 'panda-seasons' );
		$action = admin_url( 'admin-post.php' );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title><style>body{font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f6f8;color:#253342;margin:0}.ps-box{max-width:680px;margin:8vh auto;background:#fff;border-radius:16px;padding:38px;box-shadow:0 12px 40px rgba(20,35,50,.12)}label{display:block;padding:14px;border:1px solid #dfe5eb;border-radius:9px;margin:12px 0}button{border:0;border-radius:8px;background:#334fe8;color:#fff;padding:13px 20px;font-weight:700}</style></head><body><main class="ps-box"><h1>' . esc_html( $title ) . '</h1><form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="panda_seasons_unsubscribe"><input type="hidden" name="subscription" value="' . esc_attr( $row['id'] ) . '"><input type="hidden" name="token" value="' . esc_attr( $token ) . '"><label><input type="radio" name="scope" value="calendar" required> ' . esc_html( sprintf( __( 'Only “%s”', 'panda-seasons' ), get_the_title( $row['calendar_id'] ) ) ) . '</label><label><input type="radio" name="scope" value="all_calendars"> ' . esc_html__( 'All email calendars, but keep other mailings', 'panda-seasons' ) . '</label><label><input type="radio" name="scope" value="all_mailings"> ' . esc_html__( 'All email mailings', 'panda-seasons' ) . '</label><button type="submit">' . esc_html__( 'Finally unsubscribe', 'panda-seasons' ) . '</button></form></main></body></html>';
		exit;
	}

	private static function upsert( $calendar_id, $contact_id, $email, $first_name, $last_name, $status, $token ) {
		global $wpdb; $table = Panda_Seasons_Schema::tables()['subscriptions']; $hash = hash( 'sha256', $email ); $now = current_time( 'mysql' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE calendar_id=%d AND email_hash=%s", $calendar_id, $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		$data = array( 'calendar_id' => $calendar_id, 'contact_id' => $contact_id, 'email' => $email, 'email_hash' => $hash, 'first_name' => $first_name, 'last_name' => $last_name, 'status' => $status, 'verification_hash' => 'pending' === $status ? hash( 'sha256', $token ) : '', 'subscribed_at' => $now, 'confirmed_at' => 'active' === $status ? $now : null, 'unsubscribed_at' => null, 'unsubscribe_scope' => '', 'updated_at' => $now );
		if ( $existing ) { $wpdb->update( $table, $data, array( 'id' => $existing['id'] ) ); $id = $existing['id']; }
		else { $data['source'] = 'calendar_form'; $data['created_at'] = $now; $wpdb->insert( $table, $data ); $id = $wpdb->insert_id; }
		return $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A ) : false; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	private static function verified_row( $id, $token ) {
		global $wpdb; $table = Panda_Seasons_Schema::tables()['subscriptions']; $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
		return $row && $row['verification_hash'] && hash_equals( $row['verification_hash'], hash( 'sha256', $token ) ) ? $row : false;
	}

	private static function email_was_confirmed( $email ) {
		global $wpdb; $table = Panda_Seasons_Schema::tables()['subscriptions'];
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE email_hash=%s AND confirmed_at IS NOT NULL LIMIT 1", hash( 'sha256', strtolower( $email ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	private static function action_token( $row ) {
		return hash_hmac( 'sha256', $row['id'] . '|' . $row['calendar_id'] . '|' . $row['email_hash'], wp_salt( 'secure_auth' ) );
	}

	private static function send( $email, $subject, $html ) {
		$payload = array( 'to' => $email, 'subject' => $subject, 'html' => $html, 'headers' => array( 'Content-Type: text/html; charset=UTF-8' ) );
		$result = apply_filters( 'panda_seasons_send_email', null, $payload );
		return null === $result ? wp_mail( $payload['to'], $payload['subject'], $payload['html'], $payload['headers'] ) : (bool) $result;
	}

	public static function send_mail( $email, $subject, $html ) { return self::send( $email, $subject, $html ); }

	private static function log_event( $row, $event, $scope, $source ) {
		global $wpdb; $table = Panda_Seasons_Schema::tables()['events'];
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$wpdb->insert( $table, array( 'subscription_id' => absint( $row['id'] ), 'calendar_id' => absint( $row['calendar_id'] ), 'email_hash' => $row['email_hash'], 'event_type' => sanitize_key( $event ), 'scope' => sanitize_key( $scope ), 'source' => sanitize_key( $source ), 'ip_hash' => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ) : '', 'occurred_at' => current_time( 'mysql' ) ) );
	}

	private static function redirect( $calendar_id, $message ) {
		$url = wp_get_referer() ?: home_url( '/' );
		$url = add_query_arg( 'panda_seasons_message', sanitize_key( $message ), $url );
		wp_safe_redirect( $url . '#panda-seasons-' . absint( $calendar_id ) ); exit;
	}

	private static function result_page( $title, $message ) {
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title><style>body{font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f6f8;color:#253342;margin:0}.ps-box{max-width:620px;margin:10vh auto;background:#fff;border-radius:16px;padding:38px;box-shadow:0 12px 40px rgba(20,35,50,.12)}</style></head><body><main class="ps-box"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></main></body></html>';
		exit;
	}
}
