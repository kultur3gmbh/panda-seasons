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
		add_meta_box( 'panda-seasons-design', __( 'Visual calendar editor', 'panda-seasons' ), array( __CLASS__, 'calendar_design_box' ), self::CALENDAR_POST_TYPE, 'normal', 'high' );
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
		$ratio        = (float) get_post_meta( $post->ID, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $post->ID, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$doors        = self::doors( $post->ID, 'any' );
		$editor_doors = array();

		foreach ( $doors as $index => $door ) {
			$editor_doors[] = array(
				'id'            => $door->ID,
				'title'         => $door->post_title,
				'content'       => $door->post_content,
				'number'        => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 ),
				'unlockDate'    => (string) get_post_meta( $door->ID, '_ps_unlock_date', true ),
				'emailSubject'  => (string) get_post_meta( $door->ID, '_ps_email_subject', true ),
				'desktop'       => self::door_position( $door->ID, 'desktop', $index, count( $doors ) ),
				'mobile'        => self::door_position( $door->ID, 'mobile', $index, count( $doors ) ),
			);
		}
		?>
		<p><?php esc_html_e( 'Choose the calendar image and place every door directly on it. Click a door to edit its date and content.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<?php self::media_field( 'panda_seasons_cover_desktop', __( 'Desktop cover image', 'panda-seasons' ), $desktop_id, $desktop ); ?>
			<?php self::media_field( 'panda_seasons_cover_mobile', __( 'Mobile cover image', 'panda-seasons' ), $mobile_id, $mobile ); ?>
			<label><span><?php esc_html_e( 'Desktop width-to-height ratio', 'panda-seasons' ); ?></span><input type="number" min="0.4" max="3" step="0.05" name="panda_seasons_aspect_ratio" value="<?php echo esc_attr( $ratio ); ?>" data-editor-ratio="desktop"></label>
			<label><span><?php esc_html_e( 'Mobile width-to-height ratio', 'panda-seasons' ); ?></span><input type="number" min="0.35" max="2" step="0.05" name="panda_seasons_mobile_aspect_ratio" value="<?php echo esc_attr( $mobile_ratio ); ?>" data-editor-ratio="mobile"></label>
		</div>

		<div class="panda-seasons-composer" data-panda-seasons-composer data-view="desktop">
			<div class="panda-seasons-composer__toolbar">
				<div class="panda-seasons-composer__views" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'panda-seasons' ); ?>">
					<button type="button" class="button button-primary" data-editor-view="desktop"><?php esc_html_e( 'Desktop', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-editor-view="mobile"><?php esc_html_e( 'Mobile', 'panda-seasons' ); ?></button>
				</div>
				<button type="button" class="button button-secondary" data-add-door><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add door', 'panda-seasons' ); ?></button>
				<label class="panda-seasons-composer__snap"><input type="checkbox" data-snap-grid checked> <?php esc_html_e( 'Align to grid', 'panda-seasons' ); ?></label>
			</div>
			<p class="description"><?php esc_html_e( 'Drag doors to move them. Use the handle at the bottom right to resize them. Desktop and mobile positions are saved separately.', 'panda-seasons' ); ?></p>
			<div class="panda-seasons-composer__stage-wrap">
				<div class="panda-seasons-composer__stage" data-editor-stage style="--ps-editor-ratio:<?php echo esc_attr( $ratio ); ?>">
					<p class="panda-seasons-composer__empty" data-editor-empty><?php esc_html_e( 'Add a door to start designing your calendar.', 'panda-seasons' ); ?></p>
				</div>
			</div>
			<p class="description panda-seasons-composer__save-note"><?php esc_html_e( 'Door changes are stored when you update the calendar.', 'panda-seasons' ); ?></p>
			<textarea name="panda_seasons_door_layout" data-editor-data hidden><?php echo esc_textarea( wp_json_encode( $editor_doors ) ); ?></textarea>
			<script type="application/json" data-editor-images><?php echo wp_json_encode( array( 'desktop' => $desktop, 'mobile' => $mobile ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		</div>

		<div class="panda-seasons-door-modal" data-door-modal hidden>
			<div class="panda-seasons-door-modal__backdrop" data-close-door-modal></div>
			<div class="panda-seasons-door-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="panda-seasons-door-modal-title">
				<div class="panda-seasons-door-modal__header">
					<h2 id="panda-seasons-door-modal-title"><?php esc_html_e( 'Edit door', 'panda-seasons' ); ?></h2>
					<button type="button" class="button-link panda-seasons-door-modal__close" data-close-door-modal aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="panda-seasons-door-modal__body">
					<div class="panda-seasons-door-modal__fields">
						<label><span><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></span><input type="number" min="1" max="366" data-door-field="number"></label>
						<label><span><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></span><input type="date" data-door-field="unlockDate"></label>
						<label class="is-wide"><span><?php esc_html_e( 'Title', 'panda-seasons' ); ?></span><input type="text" data-door-field="title"></label>
						<label class="is-wide"><span><?php esc_html_e( 'Email subject override', 'panda-seasons' ); ?></span><input type="text" data-door-field="emailSubject"></label>
						<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'Content', 'panda-seasons' ); ?></strong><?php wp_editor( '', 'panda_seasons_door_content_editor', array( 'textarea_name' => 'panda_seasons_door_content_editor', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?></div>
					</div>
				</div>
				<div class="panda-seasons-door-modal__footer">
					<button type="button" class="button-link-delete" data-delete-door><?php esc_html_e( 'Move door to trash', 'panda-seasons' ); ?></button>
					<div>
						<button type="button" class="button" data-close-door-modal><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button>
						<button type="button" class="button button-primary" data-save-door><?php esc_html_e( 'Apply door', 'panda-seasons' ); ?></button>
					</div>
				</div>
			</div>
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

	private static function door_position( $door_id, $view, $index, $total ) {
		$stored = get_post_meta( $door_id, '_ps_position_' . $view, true );
		if ( is_array( $stored ) && isset( $stored['x'], $stored['y'], $stored['w'], $stored['h'] ) ) {
			return self::sanitize_position( $stored );
		}

		$columns = 'mobile' === $view ? 3 : 6;
		$columns = max( 1, min( $columns, max( 1, $total ) ) );
		$rows    = max( 1, (int) ceil( max( 1, $total ) / $columns ) );
		$gap     = 1.5;
		$width   = ( 100 - ( $columns + 1 ) * $gap ) / $columns;
		$height  = min( 24, ( 100 - ( $rows + 1 ) * $gap ) / $rows );
		$column  = $index % $columns;
		$row     = (int) floor( $index / $columns );

		return self::sanitize_position(
			array(
				'x' => $gap + $column * ( $width + $gap ),
				'y' => $gap + $row * ( $height + $gap ),
				'w' => $width,
				'h' => $height,
			)
		);
	}

	private static function sanitize_position( $position ) {
		if ( ! is_array( $position ) ) $position = array();
		$width  = min( 100, max( 4, (float) ( $position['w'] ?? 16 ) ) );
		$height = min( 100, max( 4, (float) ( $position['h'] ?? 18 ) ) );

		return array(
			'x' => round( min( 100 - $width, max( 0, (float) ( $position['x'] ?? 0 ) ) ), 4 ),
			'y' => round( min( 100 - $height, max( 0, (float) ( $position['y'] ?? 0 ) ) ), 4 ),
			'w' => round( $width, 4 ),
			'h' => round( $height, 4 ),
		);
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
		$ratio = (float) ( $_POST['panda_seasons_aspect_ratio'] ?? 1.5 );
		update_post_meta( $post_id, '_ps_aspect_ratio', min( 3, max( 0.4, $ratio ) ) );
		$mobile_ratio = (float) ( $_POST['panda_seasons_mobile_aspect_ratio'] ?? 0.75 );
		update_post_meta( $post_id, '_ps_mobile_aspect_ratio', min( 2, max( 0.35, $mobile_ratio ) ) );
		update_post_meta( $post_id, '_ps_email_enabled', self::contactpanda_available() && ! empty( $_POST['panda_seasons_email_enabled'] ) ? 1 : 0 );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_time'] ?? '09:00' ) );
		update_post_meta( $post_id, '_ps_email_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '09:00' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_subject'] ?? '' ) ) );
		self::save_composer_doors( $post_id );
	}

	private static function save_composer_doors( $calendar_id ) {
		if ( ! isset( $_POST['panda_seasons_door_layout'] ) ) return;
		$items = json_decode( wp_unslash( $_POST['panda_seasons_door_layout'] ), true );
		if ( ! is_array( $items ) ) return;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) continue;
			$door_id = absint( $item['id'] ?? 0 );
			if ( $door_id && self::DOOR_POST_TYPE !== get_post_type( $door_id ) ) continue;
			if ( $door_id && absint( wp_get_post_parent_id( $door_id ) ) !== absint( $calendar_id ) ) continue;
			if ( $door_id && ! current_user_can( 'edit_post', $door_id ) ) continue;

			if ( ! empty( $item['deleted'] ) ) {
				if ( $door_id && current_user_can( 'delete_post', $door_id ) ) wp_trash_post( $door_id );
				continue;
			}

			$number    = max( 1, min( 366, absint( $item['number'] ?? 1 ) ) );
			$title     = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$post_data = array(
				'post_type'    => self::DOOR_POST_TYPE,
				'post_parent'  => absint( $calendar_id ),
				'post_title'   => $title ?: sprintf( __( 'Door %d', 'panda-seasons' ), $number ),
				'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
			);
			if ( $door_id ) {
				$post_data['ID'] = $door_id;
				$result          = wp_update_post( wp_slash( $post_data ), true );
			} else {
				$post_data['post_status'] = 'publish';
				$result                   = wp_insert_post( wp_slash( $post_data ), true );
			}
			if ( is_wp_error( $result ) || ! $result ) continue;
			$door_id = absint( $result );

			update_post_meta( $door_id, '_ps_calendar_id', absint( $calendar_id ) );
			update_post_meta( $door_id, '_ps_door_number', $number );
			$date = sanitize_text_field( (string) ( $item['unlockDate'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
			update_post_meta( $door_id, '_ps_email_subject', sanitize_text_field( (string) ( $item['emailSubject'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_position_desktop', self::sanitize_position( $item['desktop'] ?? array() ) );
			update_post_meta( $door_id, '_ps_position_mobile', self::sanitize_position( $item['mobile'] ?? array() ) );
		}
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
		wp_localize_script(
			'panda-seasons-admin',
			'pandaSeasonsAdmin',
			array(
				'deleteConfirm' => __( 'Move this door to the trash? You can restore it from the door list.', 'panda-seasons' ),
				'untitledDoor'  => __( 'Door %d', 'panda-seasons' ),
			)
		);
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
		$ratio        = (float) get_post_meta( $id, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $id, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$desktop      = wp_get_attachment_image_url( absint( get_post_meta( $id, '_ps_cover_desktop', true ) ), 'full' );
		$mobile       = wp_get_attachment_image_url( absint( get_post_meta( $id, '_ps_cover_mobile', true ) ), 'full' ) ?: $desktop;
		$style        = '--ps-ratio:' . $ratio . ';--ps-mobile-ratio:' . $mobile_ratio . ';';
		if ( $desktop ) $style .= '--ps-cover-desktop:url("' . esc_url_raw( $desktop ) . '");';
		if ( $mobile ) $style .= '--ps-cover-mobile:url("' . esc_url_raw( $mobile ) . '");';

		ob_start();
		?>
		<section class="panda-seasons" id="panda-seasons-<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $style ); ?>" data-calendar="<?php echo esc_attr( $id ); ?>">
			<header class="panda-seasons__header"><h2><?php echo esc_html( get_the_title( $id ) ); ?></h2><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></header>
			<div class="panda-seasons__canvas" role="list">
				<?php foreach ( $doors as $index => $door ) :
					$unlock     = get_post_meta( $door->ID, '_ps_unlock_date', true );
					$available  = ! $unlock || $unlock <= wp_date( 'Y-m-d' );
					$number     = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 );
					$desktop_position = self::door_position( $door->ID, 'desktop', $index, count( $doors ) );
					$mobile_position  = self::door_position( $door->ID, 'mobile', $index, count( $doors ) );
					$item_style = '--ps-x:' . $desktop_position['x'] . '%;--ps-y:' . $desktop_position['y'] . '%;--ps-w:' . $desktop_position['w'] . '%;--ps-h:' . $desktop_position['h'] . '%;--ps-mobile-x:' . $mobile_position['x'] . '%;--ps-mobile-y:' . $mobile_position['y'] . '%;--ps-mobile-w:' . $mobile_position['w'] . '%;--ps-mobile-h:' . $mobile_position['h'] . '%;';
					?>
					<article class="panda-seasons__door<?php echo $available ? ' is-available' : ' is-locked'; ?>" role="listitem" style="<?php echo esc_attr( $item_style ); ?>" id="panda-season-door-<?php echo esc_attr( $door->ID ); ?>">
						<button type="button" class="panda-seasons__door-face" aria-haspopup="dialog" aria-controls="panda-season-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>>
							<span class="panda-seasons__door-number"><?php echo esc_html( $number ); ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $available ? sprintf( __( 'Open door %d', 'panda-seasons' ), $number ) : sprintf( __( 'Door %1$d opens on %2$s', 'panda-seasons' ), $number, $unlock ) ); ?></span>
						</button>
						<template class="panda-seasons__door-content" id="panda-season-content-<?php echo esc_attr( $door->ID ); ?>"><h3><?php echo esc_html( $door->post_title ); ?></h3><?php echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard filtered post content. ?></template>
					</article>
				<?php endforeach; ?>
			</div>
			<div class="panda-seasons__modal" data-season-modal hidden>
				<div class="panda-seasons__modal-backdrop" data-close-season-modal></div>
				<div class="panda-seasons__modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Calendar door', 'panda-seasons' ); ?>">
					<button type="button" class="panda-seasons__modal-close" data-close-season-modal aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>">&times;</button>
					<div class="panda-seasons__modal-content" data-season-modal-content></div>
				</div>
			</div>
			<?php if ( get_post_meta( $id, '_ps_email_enabled', true ) ) echo Panda_Seasons_Subscriptions::form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return ob_get_clean();
	}
}
