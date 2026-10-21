<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Plugin {
	const CALENDAR_POST_TYPE = 'panda_calendar';
	const DOOR_POST_TYPE     = 'panda_door';
	const CRON_HOOK          = 'panda_seasons_process_email_calendars';

	public static function boot() {
		load_plugin_textdomain( 'panda-seasons', false, dirname( plugin_basename( PANDA_SEASONS_FILE ) ) . '/languages' );
		Panda_Seasons_Schema::maybe_upgrade();
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
		add_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_filter( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_columns', array( __CLASS__, 'calendar_columns' ) );
		add_action( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'calendar_column' ), 10, 2 );
		add_filter( 'manage_' . self::DOOR_POST_TYPE . '_posts_columns', array( __CLASS__, 'door_columns' ) );
		add_action( 'manage_' . self::DOOR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'door_column' ), 10, 2 );
		add_shortcode( 'panda_seasons', array( __CLASS__, 'shortcode' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( 'Panda_Seasons_Mailer', 'process' ) );
		Panda_Seasons_Subscriptions::boot();
	}

	public static function activate() {
		self::register_post_types();
		Panda_Seasons_Schema::install();
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'panda_seasons_five_minutes', self::CRON_HOOK );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}

	public static function cron_schedules( $schedules ) {
		$schedules['panda_seasons_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'panda-seasons' ),
		);
		return $schedules;
	}

	public static function register_post_types() {
		register_post_type(
			self::CALENDAR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Panda Seasons', 'panda-seasons' ),
					'singular_name' => __( 'Calendar', 'panda-seasons' ),
					'add_new_item'  => __( 'Add calendar', 'panda-seasons' ),
					'edit_item'     => __( 'Edit calendar', 'panda-seasons' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			self::DOOR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Doors', 'panda-seasons' ),
					'singular_name' => __( 'Door', 'panda-seasons' ),
					'add_new_item'  => __( 'Add door', 'panda-seasons' ),
					'edit_item'     => __( 'Edit door', 'panda-seasons' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . self::CALENDAR_POST_TYPE,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function contactpanda_available() {
		return defined( 'CONTACTPANDA_VERSION' ) && class_exists( 'ContactPanda_Plugin' );
	}

	public static function meta_boxes() {
		add_meta_box( 'panda-seasons-design', __( 'Calendar design', 'panda-seasons' ), array( __CLASS__, 'calendar_design_box' ), self::CALENDAR_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'panda-seasons-email', __( 'Email calendar', 'panda-seasons' ), array( __CLASS__, 'calendar_email_box' ), self::CALENDAR_POST_TYPE, 'normal' );
		add_meta_box( 'panda-seasons-shortcode', __( 'Embed calendar', 'panda-seasons' ), array( __CLASS__, 'calendar_shortcode_box' ), self::CALENDAR_POST_TYPE, 'side' );
		add_meta_box( 'panda-seasons-door', __( 'Door settings', 'panda-seasons' ), array( __CLASS__, 'door_box' ), self::DOOR_POST_TYPE, 'side', 'high' );
	}

	public static function calendar_design_box( $post ) {
		wp_nonce_field( 'panda_seasons_save_calendar', 'panda_seasons_calendar_nonce' );
		$desktop_id = absint( get_post_meta( $post->ID, '_ps_cover_desktop', true ) );
		$mobile_id  = absint( get_post_meta( $post->ID, '_ps_cover_mobile', true ) );
		$desktop    = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'medium' ) : '';
		$mobile     = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'medium' ) : '';
		$cols       = max( 1, absint( get_post_meta( $post->ID, '_ps_desktop_cols', true ) ) ?: 6 );
		$mobilecols = max( 1, absint( get_post_meta( $post->ID, '_ps_mobile_cols', true ) ) ?: 3 );
		$ratio      = (float) get_post_meta( $post->ID, '_ps_aspect_ratio', true ) ?: 1.5;
		?>
		<p><?php esc_html_e( 'Build the doors first, then place one continuous image over the complete calendar. Separate images can be used for desktop and mobile.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<?php self::media_field( 'panda_seasons_cover_desktop', __( 'Desktop cover image', 'panda-seasons' ), $desktop_id, $desktop ); ?>
			<?php self::media_field( 'panda_seasons_cover_mobile', __( 'Mobile cover image', 'panda-seasons' ), $mobile_id, $mobile ); ?>
			<label><span><?php esc_html_e( 'Desktop columns', 'panda-seasons' ); ?></span><input type="number" min="1" max="12" name="panda_seasons_desktop_cols" value="<?php echo esc_attr( $cols ); ?>"></label>
			<label><span><?php esc_html_e( 'Mobile columns', 'panda-seasons' ); ?></span><input type="number" min="1" max="6" name="panda_seasons_mobile_cols" value="<?php echo esc_attr( $mobilecols ); ?>"></label>
			<label><span><?php esc_html_e( 'Calendar width-to-height ratio', 'panda-seasons' ); ?></span><input type="number" min="0.4" max="3" step="0.05" name="panda_seasons_aspect_ratio" value="<?php echo esc_attr( $ratio ); ?>"></label>
		</div>
		<?php
	}

	private static function media_field( $name, $label, $id, $url ) {
		?>
		<div class="panda-seasons-media-field" data-media-field>
			<strong><?php echo esc_html( $label ); ?></strong>
			<div class="panda-seasons-media-preview"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></div>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $id ); ?>">
			<button type="button" class="button" data-select-media><?php esc_html_e( 'Select image', 'panda-seasons' ); ?></button>
			<button type="button" class="button-link-delete" data-remove-media<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button>
		</div>
		<?php
	}

	public static function calendar_email_box( $post ) {
		$available = self::contactpanda_available();
		$enabled   = $available && (bool) get_post_meta( $post->ID, '_ps_email_enabled', true );
		$time      = get_post_meta( $post->ID, '_ps_email_time', true ) ?: '09:00';
		$subject   = get_post_meta( $post->ID, '_ps_email_subject', true ) ?: __( '{calendar}: Door {day} is open', 'panda-seasons' );
		?>
		<?php if ( ! $available ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The free ContactPanda plugin must be installed and active to use email calendars. The web calendar remains fully available.', 'panda-seasons' ); ?></p></div>
		<?php endif; ?>
		<p><label><input type="checkbox" name="panda_seasons_email_enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $available ); ?>> <?php esc_html_e( 'Send this calendar by email', 'panda-seasons' ); ?></label></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Daily delivery time', 'panda-seasons' ); ?></span><input type="time" name="panda_seasons_email_time" value="<?php echo esc_attr( $time ); ?>"></label>
			<label><span><?php esc_html_e( 'Subject template', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_email_subject" value="<?php echo esc_attr( $subject ); ?>"><small><?php esc_html_e( 'Available: {calendar}, {day}, {first_name}', 'panda-seasons' ); ?></small></label>
		</div>
		<p><?php esc_html_e( 'Verified ContactPanda contacts are enrolled immediately. All other addresses receive a double opt-in email. Calendar consent is stored separately from general marketing consent.', 'panda-seasons' ); ?></p>
		<?php
	}

	public static function calendar_shortcode_box( $post ) {
		echo '<code>[panda_seasons id="' . esc_html( $post->ID ) . '"]</code>';
	}

	public static function door_box( $post ) {
		wp_nonce_field( 'panda_seasons_save_door', 'panda_seasons_door_nonce' );
		$calendars = get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$calendar  = $post->post_parent ?: absint( get_post_meta( $post->ID, '_ps_calendar_id', true ) );
		$number    = absint( get_post_meta( $post->ID, '_ps_door_number', true ) ) ?: 1;
		$date      = get_post_meta( $post->ID, '_ps_unlock_date', true );
		$subject   = get_post_meta( $post->ID, '_ps_email_subject', true );
		?>
		<p><label><strong><?php esc_html_e( 'Calendar', 'panda-seasons' ); ?></strong><select name="panda_seasons_calendar_id" required><option value=""><?php esc_html_e( 'Select calendar', 'panda-seasons' ); ?></option><?php foreach ( $calendars as $item ) : ?><option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( $calendar, $item->ID ); ?>><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?></select></label></p>
		<p><label><strong><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></strong><input type="number" min="1" max="366" name="panda_seasons_door_number" value="<?php echo esc_attr( $number ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></strong><input type="date" name="panda_seasons_unlock_date" value="<?php echo esc_attr( $date ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Email subject override', 'panda-seasons' ); ?></strong><input type="text" name="panda_seasons_door_email_subject" value="<?php echo esc_attr( $subject ); ?>"></label></p>
		<?php
	}

	public static function save_calendar( $post_id ) {
		if ( ! self::can_save( $post_id, 'panda_seasons_calendar_nonce', 'panda_seasons_save_calendar' ) ) return;
		update_post_meta( $post_id, '_ps_cover_desktop', absint( $_POST['panda_seasons_cover_desktop'] ?? 0 ) );
		update_post_meta( $post_id, '_ps_cover_mobile', absint( $_POST['panda_seasons_cover_mobile'] ?? 0 ) );
		update_post_meta( $post_id, '_ps_desktop_cols', min( 12, max( 1, absint( $_POST['panda_seasons_desktop_cols'] ?? 6 ) ) ) );
		update_post_meta( $post_id, '_ps_mobile_cols', min( 6, max( 1, absint( $_POST['panda_seasons_mobile_cols'] ?? 3 ) ) ) );
		$ratio = (float) ( $_POST['panda_seasons_aspect_ratio'] ?? 1.5 );
		update_post_meta( $post_id, '_ps_aspect_ratio', min( 3, max( 0.4, $ratio ) ) );
		update_post_meta( $post_id, '_ps_email_enabled', self::contactpanda_available() && ! empty( $_POST['panda_seasons_email_enabled'] ) ? 1 : 0 );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_time'] ?? '09:00' ) );
		update_post_meta( $post_id, '_ps_email_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '09:00' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_subject'] ?? '' ) ) );
	}

	public static function save_door( $post_id ) {
		if ( ! self::can_save( $post_id, 'panda_seasons_door_nonce', 'panda_seasons_save_door' ) ) return;
		$calendar = absint( $_POST['panda_seasons_calendar_id'] ?? 0 );
		update_post_meta( $post_id, '_ps_calendar_id', $calendar );
		update_post_meta( $post_id, '_ps_door_number', max( 1, absint( $_POST['panda_seasons_door_number'] ?? 1 ) ) );
		$date = sanitize_text_field( wp_unslash( $_POST['panda_seasons_unlock_date'] ?? '' ) );
		update_post_meta( $post_id, '_ps_unlock_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_door_email_subject'] ?? '' ) ) );
		if ( $calendar && absint( get_post_field( 'post_parent', $post_id ) ) !== $calendar ) {
			remove_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_parent' => $calendar ) );
			add_action( 'save_post_' . self::DOOR_POST_TYPE, array( __CLASS__, 'save_door' ) );
		}
	}

	private static function can_save( $post_id, $field, $action ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return false;
		if ( empty( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) return false;
		return current_user_can( 'edit_post', $post_id );
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( self::CALENDAR_POST_TYPE, self::DOOR_POST_TYPE ), true ) ) return;
		wp_enqueue_media();
		wp_enqueue_style( 'panda-seasons-admin', PANDA_SEASONS_URL . 'assets/admin.css', array(), PANDA_SEASONS_VERSION );
		wp_enqueue_script( 'panda-seasons-admin', PANDA_SEASONS_URL . 'assets/admin.js', array(), PANDA_SEASONS_VERSION, true );
	}

	public static function register_frontend_assets() {
		wp_register_style( 'panda-seasons', PANDA_SEASONS_URL . 'assets/frontend.css', array(), PANDA_SEASONS_VERSION );
		wp_register_script( 'panda-seasons', PANDA_SEASONS_URL . 'assets/frontend.js', array(), PANDA_SEASONS_VERSION, true );
	}

	public static function calendar_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Calendar', 'panda-seasons' ), 'doors' => __( 'Doors', 'panda-seasons' ), 'email' => __( 'Email calendar', 'panda-seasons' ), 'shortcode' => __( 'Shortcode', 'panda-seasons' ), 'date' => $columns['date'] );
	}

	public static function calendar_column( $column, $post_id ) {
		if ( 'doors' === $column ) echo esc_html( count( self::doors( $post_id, 'any' ) ) );
		if ( 'email' === $column ) echo esc_html( get_post_meta( $post_id, '_ps_email_enabled', true ) ? __( 'Enabled', 'panda-seasons' ) : __( 'Disabled', 'panda-seasons' ) );
		if ( 'shortcode' === $column ) echo '<code>[panda_seasons id="' . esc_html( $post_id ) . '"]</code>';
	}

	public static function door_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Door', 'panda-seasons' ), 'number' => __( 'Number', 'panda-seasons' ), 'calendar' => __( 'Calendar', 'panda-seasons' ), 'unlock' => __( 'Unlock date', 'panda-seasons' ), 'date' => $columns['date'] );
	}

	public static function door_column( $column, $post_id ) {
		if ( 'number' === $column ) echo esc_html( get_post_meta( $post_id, '_ps_door_number', true ) );
		if ( 'calendar' === $column ) echo esc_html( get_the_title( wp_get_post_parent_id( $post_id ) ) ?: '—' );
		if ( 'unlock' === $column ) echo esc_html( get_post_meta( $post_id, '_ps_unlock_date', true ) ?: '—' );
	}

	public static function doors( $calendar_id, $status = 'publish' ) {
		$statuses = 'any' === $status ? array( 'publish', 'draft', 'private', 'pending' ) : $status;
		return get_posts(
			array(
				'post_type'      => self::DOOR_POST_TYPE,
				'post_parent'    => absint( $calendar_id ),
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'meta_key'       => '_ps_door_number',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'panda_seasons' );
		$id   = absint( $atts['id'] );
		$post = get_post( $id );
		if ( ! $post || self::CALENDAR_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) return '';
		$doors = self::doors( $id );
		if ( ! $doors ) return '<p>' . esc_html__( 'This calendar does not have any doors yet.', 'panda-seasons' ) . '</p>';

		wp_enqueue_style( 'panda-seasons' );
		wp_enqueue_script( 'panda-seasons' );
		$desktop_cols = max( 1, absint( get_post_meta( $id, '_ps_desktop_cols', true ) ) ?: 6 );
		$mobile_cols  = max( 1, absint( get_post_meta( $id, '_ps_mobile_cols', true ) ) ?: 3 );
		$desktop_rows = (int) ceil( count( $doors ) / $desktop_cols );
		$mobile_rows  = (int) ceil( count( $doors ) / $mobile_cols );
		$ratio        = (float) get_post_meta( $id, '_ps_aspect_ratio', true ) ?: 1.5;
		$desktop      = wp_get_attachment_image_url( absint( get_post_meta( $id, '_ps_cover_desktop', true ) ), 'full' );
		$mobile       = wp_get_attachment_image_url( absint( get_post_meta( $id, '_ps_cover_mobile', true ) ), 'full' ) ?: $desktop;
		$style        = '--ps-cols:' . $desktop_cols . ';--ps-mobile-cols:' . $mobile_cols . ';--ps-rows:' . $desktop_rows . ';--ps-mobile-rows:' . $mobile_rows . ';--ps-ratio:' . $ratio . ';';
		if ( $desktop ) $style .= '--ps-cover-desktop:url("' . esc_url_raw( $desktop ) . '");';
		if ( $mobile ) $style .= '--ps-cover-mobile:url("' . esc_url_raw( $mobile ) . '");';

		ob_start();
		?>
		<section class="panda-seasons" id="panda-seasons-<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $style ); ?>" data-calendar="<?php echo esc_attr( $id ); ?>">
			<header class="panda-seasons__header"><h2><?php echo esc_html( get_the_title( $id ) ); ?></h2><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></header>
			<div class="panda-seasons__grid" role="list">
				<?php foreach ( $doors as $index => $door ) :
					$unlock     = get_post_meta( $door->ID, '_ps_unlock_date', true );
					$available  = ! $unlock || $unlock <= wp_date( 'Y-m-d' );
					$number     = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 );
					$dcol       = $index % $desktop_cols;
					$drow       = (int) floor( $index / $desktop_cols );
					$mcol       = $index % $mobile_cols;
					$mrow       = (int) floor( $index / $mobile_cols );
					$dx         = $desktop_cols > 1 ? 100 * $dcol / ( $desktop_cols - 1 ) : 50;
					$dy         = $desktop_rows > 1 ? 100 * $drow / ( $desktop_rows - 1 ) : 50;
					$mx         = $mobile_cols > 1 ? 100 * $mcol / ( $mobile_cols - 1 ) : 50;
					$my         = $mobile_rows > 1 ? 100 * $mrow / ( $mobile_rows - 1 ) : 50;
					$item_style = '--ps-x:' . round( $dx, 4 ) . '%;--ps-y:' . round( $dy, 4 ) . '%;--ps-mobile-x:' . round( $mx, 4 ) . '%;--ps-mobile-y:' . round( $my, 4 ) . '%;';
					?>
					<article class="panda-seasons__door<?php echo $available ? ' is-available' : ' is-locked'; ?>" role="listitem" style="<?php echo esc_attr( $item_style ); ?>" id="panda-season-door-<?php echo esc_attr( $door->ID ); ?>">
						<button type="button" class="panda-seasons__door-face" aria-expanded="false" aria-controls="panda-season-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>>
							<span class="panda-seasons__door-number"><?php echo esc_html( $number ); ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $available ? sprintf( __( 'Open door %d', 'panda-seasons' ), $number ) : sprintf( __( 'Door %1$d opens on %2$s', 'panda-seasons' ), $number, $unlock ) ); ?></span>
						</button>
						<div class="panda-seasons__door-content" id="panda-season-content-<?php echo esc_attr( $door->ID ); ?>" hidden><?php echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard filtered post content. ?></div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( get_post_meta( $id, '_ps_email_enabled', true ) ) echo Panda_Seasons_Subscriptions::form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return ob_get_clean();
	}
}
