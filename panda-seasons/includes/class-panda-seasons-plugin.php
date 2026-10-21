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
		add_shortcode( 'panda_seasons_subscribe', array( __CLASS__, 'subscribe_shortcode' ) );
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
		$products     = self::product_options();
		$events       = self::event_options();

		foreach ( $doors as $index => $door ) {
			$editor_doors[] = array(
				'id'            => $door->ID,
				'title'         => $door->post_title,
				'content'       => $door->post_content,
				'number'        => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 ),
				'unlockDate'    => (string) get_post_meta( $door->ID, '_ps_unlock_date', true ),
				'unlockTime'    => (string) get_post_meta( $door->ID, '_ps_unlock_time', true ),
				'emailSubject'  => (string) get_post_meta( $door->ID, '_ps_email_subject', true ),
				'emailContent'  => (string) get_post_meta( $door->ID, '_ps_email_content', true ),
				'mediaId'       => absint( get_post_meta( $door->ID, '_ps_media_id', true ) ),
				'mediaUrl'      => wp_get_attachment_url( absint( get_post_meta( $door->ID, '_ps_media_id', true ) ) ) ?: '',
				'mediaType'     => self::attachment_kind( absint( get_post_meta( $door->ID, '_ps_media_id', true ) ) ),
				'downloadId'    => absint( get_post_meta( $door->ID, '_ps_download_id', true ) ),
				'downloadUrl'   => wp_get_attachment_url( absint( get_post_meta( $door->ID, '_ps_download_id', true ) ) ) ?: '',
				'downloadLabel' => (string) get_post_meta( $door->ID, '_ps_download_label', true ),
				'buttonLabel'   => (string) get_post_meta( $door->ID, '_ps_button_label', true ),
				'buttonUrl'     => (string) get_post_meta( $door->ID, '_ps_button_url', true ),
				'productId'     => absint( get_post_meta( $door->ID, '_ps_product_id', true ) ),
				'eventId'       => absint( get_post_meta( $door->ID, '_ps_event_id', true ) ),
				'closedLabel'   => (string) get_post_meta( $door->ID, '_ps_closed_label', true ),
				'openLabel'     => (string) get_post_meta( $door->ID, '_ps_open_label', true ),
				'closedBg'      => (string) get_post_meta( $door->ID, '_ps_closed_background', true ),
				'closedColor'   => (string) get_post_meta( $door->ID, '_ps_closed_color', true ),
				'openBg'        => (string) get_post_meta( $door->ID, '_ps_open_background', true ),
				'openColor'     => (string) get_post_meta( $door->ID, '_ps_open_color', true ),
				'closedOpacity' => '' !== get_post_meta( $door->ID, '_ps_closed_opacity', true ) ? absint( get_post_meta( $door->ID, '_ps_closed_opacity', true ) ) : 100,
				'openOpacity'   => '' !== get_post_meta( $door->ID, '_ps_open_opacity', true ) ? absint( get_post_meta( $door->ID, '_ps_open_opacity', true ) ) : 100,
				'shape'         => (string) get_post_meta( $door->ID, '_ps_shape', true ) ?: 'rounded',
				'closedImageId' => absint( get_post_meta( $door->ID, '_ps_closed_image_id', true ) ),
				'closedImageUrl'=> wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_closed_image_id', true ) ), 'medium' ) ?: '',
				'openImageId'   => absint( get_post_meta( $door->ID, '_ps_open_image_id', true ) ),
				'openImageUrl'  => wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_open_image_id', true ) ), 'medium' ) ?: '',
				'quizEnabled'   => (bool) get_post_meta( $door->ID, '_ps_quiz_enabled', true ),
				'quizQuestion'  => (string) get_post_meta( $door->ID, '_ps_quiz_question', true ),
				'quizAnswers'   => array_values( array_filter( (array) get_post_meta( $door->ID, '_ps_quiz_answers', true ), 'strlen' ) ),
				'quizCorrect'   => absint( get_post_meta( $door->ID, '_ps_quiz_correct', true ) ),
				'quizSuccess'   => (string) get_post_meta( $door->ID, '_ps_quiz_success', true ),
				'quizFailure'   => (string) get_post_meta( $door->ID, '_ps_quiz_failure', true ),
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

		<div class="panda-seasons-editor-layout">
		<div class="panda-seasons-composer" data-panda-seasons-composer data-view="desktop" data-preview-state="actual">
			<div class="panda-seasons-composer__toolbar">
				<div class="panda-seasons-composer__views" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'panda-seasons' ); ?>">
					<button type="button" class="button button-primary" data-editor-view="desktop"><?php esc_html_e( 'Desktop', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-editor-view="mobile"><?php esc_html_e( 'Mobile', 'panda-seasons' ); ?></button>
				</div>
				<div class="panda-seasons-composer__states" role="group" aria-label="<?php esc_attr_e( 'Door state preview', 'panda-seasons' ); ?>">
					<button type="button" class="button button-primary" data-preview-state="actual"><?php esc_html_e( 'Actual', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-preview-state="closed"><?php esc_html_e( 'All closed', 'panda-seasons' ); ?></button>
					<button type="button" class="button" data-preview-state="open"><?php esc_html_e( 'All open', 'panda-seasons' ); ?></button>
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

		<aside class="panda-seasons-door-modal" data-door-modal hidden aria-labelledby="panda-seasons-door-modal-title">
			<div class="panda-seasons-door-modal__backdrop" data-close-door-modal></div>
			<div class="panda-seasons-door-modal__dialog">
				<div class="panda-seasons-door-modal__header">
					<h2 id="panda-seasons-door-modal-title"><?php esc_html_e( 'Edit door', 'panda-seasons' ); ?></h2>
					<button type="button" class="button-link panda-seasons-door-modal__close" data-close-door-modal aria-label="<?php esc_attr_e( 'Close', 'panda-seasons' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="panda-seasons-door-modal__body">
					<div class="panda-seasons-door-modal__fields">
						<details class="is-wide panda-seasons-door-modal__section" open>
							<summary><?php esc_html_e( 'Door basics', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label><span><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></span><input type="number" min="1" max="366" data-door-field="number"></label>
								<label><span><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></span><input type="date" data-door-field="unlockDate"></label>
								<label><span><?php esc_html_e( 'Optional unlock time', 'panda-seasons' ); ?></span><input type="time" data-door-field="unlockTime"></label>
								<label class="is-wide"><span><?php esc_html_e( 'Title', 'panda-seasons' ); ?></span><input type="text" data-door-field="title"></label>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section" open>
							<summary><?php esc_html_e( 'Door content and media', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'Text', 'panda-seasons' ); ?></strong><?php wp_editor( '', 'panda_seasons_door_content_editor', array( 'textarea_name' => 'panda_seasons_door_content_editor', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?></div>
								<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="media">
									<strong><?php esc_html_e( 'Image, video or audio', 'panda-seasons' ); ?></strong>
									<div class="panda-seasons-door-modal__asset-preview" data-door-asset-preview></div>
									<input type="hidden" data-door-field="mediaId"><input type="hidden" data-door-field="mediaUrl"><input type="hidden" data-door-field="mediaType">
									<div><button type="button" class="button" data-select-door-media><?php esc_html_e( 'Select media', 'panda-seasons' ); ?></button> <button type="button" class="button-link-delete" data-remove-door-media><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button></div>
								</div>
								<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="download">
									<strong><?php esc_html_e( 'Download', 'panda-seasons' ); ?></strong>
									<div class="panda-seasons-door-modal__asset-preview" data-door-asset-preview></div>
									<input type="hidden" data-door-field="downloadId"><input type="hidden" data-door-field="downloadUrl">
									<div><button type="button" class="button" data-select-door-download><?php esc_html_e( 'Select download file', 'panda-seasons' ); ?></button> <button type="button" class="button-link-delete" data-remove-door-download><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button></div>
								</div>
								<label class="is-wide"><span><?php esc_html_e( 'Download link text', 'panda-seasons' ); ?></span><input type="text" data-door-field="downloadLabel" placeholder="<?php esc_attr_e( 'Download file', 'panda-seasons' ); ?>"></label>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section">
							<summary><?php esc_html_e( 'Button and related content', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label><span><?php esc_html_e( 'Button text', 'panda-seasons' ); ?></span><input type="text" data-door-field="buttonLabel"></label>
								<label><span><?php esc_html_e( 'Button link', 'panda-seasons' ); ?></span><input type="url" data-door-field="buttonUrl" placeholder="https://"></label>
								<?php if ( $products ) : ?>
									<label class="is-wide"><span><?php esc_html_e( 'WooCommerce product', 'panda-seasons' ); ?></span><select data-door-field="productId"><option value=""><?php esc_html_e( 'No product', 'panda-seasons' ); ?></option><?php foreach ( $products as $product_id => $product_title ) : ?><option value="<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_title ); ?></option><?php endforeach; ?></select></label>
								<?php endif; ?>
								<?php if ( $events ) : ?>
									<label class="is-wide"><span><?php esc_html_e( 'Event', 'panda-seasons' ); ?></span><select data-door-field="eventId"><option value=""><?php esc_html_e( 'No event', 'panda-seasons' ); ?></option><?php foreach ( $events as $event_id => $event_title ) : ?><option value="<?php echo esc_attr( $event_id ); ?>"><?php echo esc_html( $event_title ); ?></option><?php endforeach; ?></select></label>
								<?php endif; ?>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section">
							<summary><?php esc_html_e( 'Calendar email', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label class="is-wide"><span><?php esc_html_e( 'Email subject override', 'panda-seasons' ); ?></span><input type="text" data-door-field="emailSubject"></label>
								<div class="is-wide panda-seasons-door-modal__content-editor"><strong><?php esc_html_e( 'Email content', 'panda-seasons' ); ?></strong><p class="description"><?php esc_html_e( 'Leave empty to use the regular door content.', 'panda-seasons' ); ?></p><?php wp_editor( '', 'panda_seasons_door_email_editor', array( 'textarea_name' => 'panda_seasons_door_email_editor', 'textarea_rows' => 8, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?></div>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section">
							<summary><?php esc_html_e( 'Appearance before and after opening', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label><span><?php esc_html_e( 'Label before opening', 'panda-seasons' ); ?></span><input type="text" data-door-field="closedLabel" placeholder="<?php esc_attr_e( 'Door number', 'panda-seasons' ); ?>"></label>
								<label><span><?php esc_html_e( 'Label after opening', 'panda-seasons' ); ?></span><input type="text" data-door-field="openLabel" placeholder="<?php esc_attr_e( 'Door number', 'panda-seasons' ); ?>"></label>
								<label><span><?php esc_html_e( 'Background before opening', 'panda-seasons' ); ?></span><input type="color" data-door-field="closedBg" value="#1f2937"></label>
								<label><span><?php esc_html_e( 'Text color before opening', 'panda-seasons' ); ?></span><input type="color" data-door-field="closedColor" value="#ffffff"></label>
								<label><span><?php esc_html_e( 'Background after opening', 'panda-seasons' ); ?></span><input type="color" data-door-field="openBg" value="#334fe8"></label>
								<label><span><?php esc_html_e( 'Text color after opening', 'panda-seasons' ); ?></span><input type="color" data-door-field="openColor" value="#ffffff"></label>
								<label><span><?php esc_html_e( 'Door shape', 'panda-seasons' ); ?></span><select data-door-field="shape"><option value="square"><?php esc_html_e( 'Square', 'panda-seasons' ); ?></option><option value="rounded"><?php esc_html_e( 'Rounded', 'panda-seasons' ); ?></option><option value="round"><?php esc_html_e( 'Round', 'panda-seasons' ); ?></option></select></label>
								<label><span><?php esc_html_e( 'Closed background opacity', 'panda-seasons' ); ?></span><input type="range" min="0" max="100" step="1" data-door-field="closedOpacity"><output data-opacity-output="closedOpacity"></output></label>
								<label><span><?php esc_html_e( 'Open background opacity', 'panda-seasons' ); ?></span><input type="range" min="0" max="100" step="1" data-door-field="openOpacity"><output data-opacity-output="openOpacity"></output></label>
								<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="closedImage"><strong><?php esc_html_e( 'Optional image on the closed door', 'panda-seasons' ); ?></strong><div class="panda-seasons-door-modal__asset-preview" data-door-asset-preview></div><input type="hidden" data-door-field="closedImageId"><input type="hidden" data-door-field="closedImageUrl"><div><button type="button" class="button" data-select-door-image="closedImage"><?php esc_html_e( 'Select image', 'panda-seasons' ); ?></button> <button type="button" class="button-link-delete" data-remove-door-image="closedImage"><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button></div></div>
								<div class="is-wide panda-seasons-door-modal__asset" data-door-asset="openImage"><strong><?php esc_html_e( 'Optional image on the open door', 'panda-seasons' ); ?></strong><div class="panda-seasons-door-modal__asset-preview" data-door-asset-preview></div><input type="hidden" data-door-field="openImageId"><input type="hidden" data-door-field="openImageUrl"><div><button type="button" class="button" data-select-door-image="openImage"><?php esc_html_e( 'Select image', 'panda-seasons' ); ?></button> <button type="button" class="button-link-delete" data-remove-door-image="openImage"><?php esc_html_e( 'Remove', 'panda-seasons' ); ?></button></div></div>
							</div>
						</details>

						<details class="is-wide panda-seasons-door-modal__section">
							<summary><?php esc_html_e( 'Quiz', 'panda-seasons' ); ?></summary>
							<div class="panda-seasons-door-modal__section-grid">
								<label class="is-wide"><span><input type="checkbox" data-door-field="quizEnabled"> <?php esc_html_e( 'Enable a quiz for this door', 'panda-seasons' ); ?></span></label>
								<label class="is-wide"><span><?php esc_html_e( 'Question', 'panda-seasons' ); ?></span><input type="text" data-door-field="quizQuestion"></label>
								<label class="is-wide"><span><?php esc_html_e( 'Answers (one per line)', 'panda-seasons' ); ?></span><textarea rows="5" data-door-field="quizAnswers"></textarea></label>
								<label><span><?php esc_html_e( 'Correct answer number', 'panda-seasons' ); ?></span><input type="number" min="1" max="20" data-door-field="quizCorrect"></label>
								<label class="is-wide"><span><?php esc_html_e( 'Message for a correct answer', 'panda-seasons' ); ?></span><input type="text" data-door-field="quizSuccess"></label>
								<label class="is-wide"><span><?php esc_html_e( 'Message for a wrong answer', 'panda-seasons' ); ?></span><input type="text" data-door-field="quizFailure"></label>
								<p class="description is-wide"><?php esc_html_e( 'In standalone mode answers are checked only in the visitor’s browser and no personal data is stored.', 'panda-seasons' ); ?></p>
							</div>
						</details>

						<div class="is-wide panda-seasons-door-modal__preview" data-door-preview hidden></div>
					</div>
				</div>
				<div class="panda-seasons-door-modal__footer">
					<button type="button" class="button-link-delete" data-delete-door><?php esc_html_e( 'Move door to trash', 'panda-seasons' ); ?></button>
					<div>
						<button type="button" class="button" data-preview-door><?php esc_html_e( 'Preview content before it opens', 'panda-seasons' ); ?></button>
						<button type="button" class="button" data-close-door-modal><?php esc_html_e( 'Cancel', 'panda-seasons' ); ?></button>
						<button type="button" class="button button-primary" data-save-door><?php esc_html_e( 'Apply door', 'panda-seasons' ); ?></button>
					</div>
				</div>
			</div>
		</aside>
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

	private static function attachment_kind( $attachment_id ) {
		$mime = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';
		if ( 0 === strpos( $mime, 'image/' ) ) return 'image';
		if ( 0 === strpos( $mime, 'video/' ) ) return 'video';
		if ( 0 === strpos( $mime, 'audio/' ) ) return 'audio';
		return '';
	}

	private static function product_options() {
		if ( ! post_type_exists( 'product' ) ) return array();
		$posts   = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 250, 'orderby' => 'title', 'order' => 'ASC' ) );
		$options = array();
		foreach ( $posts as $item ) $options[ $item->ID ] = $item->post_title;
		return apply_filters( 'panda_seasons_product_options', $options );
	}

	private static function event_options() {
		$post_types = (array) apply_filters( 'panda_seasons_event_post_types', array( 'tribe_events', 'mec-events', 'event' ) );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ), 'post_type_exists' ) );
		$options    = array();
		if ( $post_types ) {
			$posts = get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => 250, 'orderby' => 'date', 'order' => 'DESC' ) );
			foreach ( $posts as $item ) $options[ $item->ID ] = $item->post_title;
		}
		return apply_filters( 'panda_seasons_event_options', $options );
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
		$signup_title = get_post_meta( $post->ID, '_ps_signup_title', true ) ?: __( 'Receive this calendar by email', 'panda-seasons' );
		$signup_text  = (string) get_post_meta( $post->ID, '_ps_signup_text', true );
		$newsletter_mode = (string) get_post_meta( $post->ID, '_ps_newsletter_mode', true ) ?: 'none';
		?>
		<?php if ( ! $available ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The free ContactPanda plugin must be installed and active to use email calendars. The web calendar remains fully available.', 'panda-seasons' ); ?></p></div>
		<?php endif; ?>
		<p><label><input type="checkbox" name="panda_seasons_email_enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $available ); ?>> <?php esc_html_e( 'Send this calendar by email', 'panda-seasons' ); ?></label></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Daily delivery time', 'panda-seasons' ); ?></span><input type="time" name="panda_seasons_email_time" value="<?php echo esc_attr( $time ); ?>"></label>
			<label><span><?php esc_html_e( 'Subject template', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_email_subject" value="<?php echo esc_attr( $subject ); ?>"><small><?php esc_html_e( 'Available: {calendar}, {day}, {first_name}', 'panda-seasons' ); ?></small></label>
		</div>
		<h3><?php esc_html_e( 'Email-calendar signup', 'panda-seasons' ); ?></h3>
		<p><?php esc_html_e( 'The signup works independently of the doors and may be published weeks before the calendar starts.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Signup heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_signup_title" value="<?php echo esc_attr( $signup_title ); ?>"></label>
			<label><span><?php esc_html_e( 'Signup description', 'panda-seasons' ); ?></span><textarea name="panda_seasons_signup_text" rows="3"><?php echo esc_textarea( $signup_text ); ?></textarea></label>
		</div>
		<p><code>[panda_seasons_subscribe id="<?php echo esc_html( $post->ID ); ?>"]</code></p>
		<h3><?php esc_html_e( 'General newsletter signup', 'panda-seasons' ); ?></h3>
		<p><?php esc_html_e( 'Newsletter consent remains separate from the email-calendar consent.', 'panda-seasons' ); ?></p>
		<div class="panda-seasons-admin-grid">
			<label><span><?php esc_html_e( 'Signup type', 'panda-seasons' ); ?></span><select name="panda_seasons_newsletter_mode"><option value="none" <?php selected( $newsletter_mode, 'none' ); ?>><?php esc_html_e( 'None', 'panda-seasons' ); ?></option><option value="link" <?php selected( $newsletter_mode, 'link' ); ?>><?php esc_html_e( 'Link or button', 'panda-seasons' ); ?></option><option value="shortcode" <?php selected( $newsletter_mode, 'shortcode' ); ?>><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></option><option value="contactpanda" <?php selected( $newsletter_mode, 'contactpanda' ); ?> <?php disabled( ! $available ); ?>><?php esc_html_e( 'Simple ContactPanda form', 'panda-seasons' ); ?></option></select></label>
			<label><span><?php esc_html_e( 'Heading', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_title" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_title', true ) ); ?>"></label>
			<label><span><?php esc_html_e( 'Link text', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_link_label" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_link_label', true ) ); ?>"></label>
			<label><span><?php esc_html_e( 'Link URL', 'panda-seasons' ); ?></span><input type="url" name="panda_seasons_newsletter_link_url" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_link_url', true ) ); ?>"></label>
			<label class="is-wide"><span><?php esc_html_e( 'Form shortcode', 'panda-seasons' ); ?></span><input type="text" name="panda_seasons_newsletter_shortcode" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ps_newsletter_shortcode', true ) ); ?>" placeholder="[contact-form-7 id=&quot;123&quot;]"></label>
		</div>
		<p><?php esc_html_e( 'Verified ContactPanda contacts are enrolled immediately. All other addresses receive a double opt-in email. Calendar consent is stored separately from general marketing consent.', 'panda-seasons' ); ?></p>
		<?php
	}

	public static function calendar_shortcode_box( $post ) {
		echo '<p><code>[panda_seasons id="' . esc_html( $post->ID ) . '"]</code></p><p><code>[panda_seasons_subscribe id="' . esc_html( $post->ID ) . '"]</code></p>';
	}

	public static function door_box( $post ) {
		wp_nonce_field( 'panda_seasons_save_door', 'panda_seasons_door_nonce' );
		$calendars = get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$calendar  = $post->post_parent ?: absint( get_post_meta( $post->ID, '_ps_calendar_id', true ) );
		$number    = absint( get_post_meta( $post->ID, '_ps_door_number', true ) ) ?: 1;
		$date      = get_post_meta( $post->ID, '_ps_unlock_date', true );
		$time      = get_post_meta( $post->ID, '_ps_unlock_time', true );
		$subject   = get_post_meta( $post->ID, '_ps_email_subject', true );
		?>
		<p><label><strong><?php esc_html_e( 'Calendar', 'panda-seasons' ); ?></strong><select name="panda_seasons_calendar_id" required><option value=""><?php esc_html_e( 'Select calendar', 'panda-seasons' ); ?></option><?php foreach ( $calendars as $item ) : ?><option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( $calendar, $item->ID ); ?>><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?></select></label></p>
		<p><label><strong><?php esc_html_e( 'Door number', 'panda-seasons' ); ?></strong><input type="number" min="1" max="366" name="panda_seasons_door_number" value="<?php echo esc_attr( $number ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Unlock date', 'panda-seasons' ); ?></strong><input type="date" name="panda_seasons_unlock_date" value="<?php echo esc_attr( $date ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Optional unlock time', 'panda-seasons' ); ?></strong><input type="time" name="panda_seasons_unlock_time" value="<?php echo esc_attr( $time ); ?>"></label></p>
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
		if ( self::contactpanda_available() ) update_post_meta( $post_id, '_ps_email_enabled', ! empty( $_POST['panda_seasons_email_enabled'] ) ? 1 : 0 );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_time'] ?? '09:00' ) );
		update_post_meta( $post_id, '_ps_email_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '09:00' );
		update_post_meta( $post_id, '_ps_email_subject', sanitize_text_field( wp_unslash( $_POST['panda_seasons_email_subject'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_signup_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_signup_text', sanitize_textarea_field( wp_unslash( $_POST['panda_seasons_signup_text'] ?? '' ) ) );
		$newsletter_mode = sanitize_key( wp_unslash( $_POST['panda_seasons_newsletter_mode'] ?? 'none' ) );
		if ( ! in_array( $newsletter_mode, array( 'none', 'link', 'shortcode', 'contactpanda' ), true ) || ( 'contactpanda' === $newsletter_mode && ! self::contactpanda_available() ) ) $newsletter_mode = 'none';
		update_post_meta( $post_id, '_ps_newsletter_mode', $newsletter_mode );
		update_post_meta( $post_id, '_ps_newsletter_title', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_link_label', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_link_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_link_url', esc_url_raw( wp_unslash( $_POST['panda_seasons_newsletter_link_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_ps_newsletter_shortcode', sanitize_text_field( wp_unslash( $_POST['panda_seasons_newsletter_shortcode'] ?? '' ) ) );
		self::save_composer_doors( $post_id );
	}

	private static function save_composer_doors( $calendar_id ) {
		if ( ! isset( $_POST['panda_seasons_door_layout'] ) ) return;
		$items = json_decode( wp_unslash( $_POST['panda_seasons_door_layout'] ), true );
		if ( ! is_array( $items ) ) return;
		$event_options = self::event_options();

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
			$time = sanitize_text_field( (string) ( $item['unlockTime'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '' );
			update_post_meta( $door_id, '_ps_email_subject', sanitize_text_field( (string) ( $item['emailSubject'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_email_content', wp_kses_post( (string) ( $item['emailContent'] ?? '' ) ) );
			self::update_attachment_meta( $door_id, '_ps_media_id', $item['mediaId'] ?? 0 );
			self::update_attachment_meta( $door_id, '_ps_download_id', $item['downloadId'] ?? 0 );
			update_post_meta( $door_id, '_ps_download_label', sanitize_text_field( (string) ( $item['downloadLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_button_label', sanitize_text_field( (string) ( $item['buttonLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_button_url', esc_url_raw( (string) ( $item['buttonUrl'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_product_id', self::valid_related_post( $item['productId'] ?? 0, array( 'product' ) ) );
			$event_id = absint( $item['eventId'] ?? 0 );
			update_post_meta( $door_id, '_ps_event_id', $event_id && isset( $event_options[ $event_id ] ) ? $event_id : 0 );
			update_post_meta( $door_id, '_ps_closed_label', sanitize_text_field( (string) ( $item['closedLabel'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_open_label', sanitize_text_field( (string) ( $item['openLabel'] ?? '' ) ) );
			self::update_color_meta( $door_id, '_ps_closed_background', $item['closedBg'] ?? '' );
			self::update_color_meta( $door_id, '_ps_closed_color', $item['closedColor'] ?? '' );
			self::update_color_meta( $door_id, '_ps_open_background', $item['openBg'] ?? '' );
			self::update_color_meta( $door_id, '_ps_open_color', $item['openColor'] ?? '' );
			update_post_meta( $door_id, '_ps_closed_opacity', min( 100, max( 0, absint( $item['closedOpacity'] ?? 100 ) ) ) );
			update_post_meta( $door_id, '_ps_open_opacity', min( 100, max( 0, absint( $item['openOpacity'] ?? 100 ) ) ) );
			$shape = sanitize_key( (string) ( $item['shape'] ?? 'rounded' ) );
			update_post_meta( $door_id, '_ps_shape', in_array( $shape, array( 'square', 'rounded', 'round' ), true ) ? $shape : 'rounded' );
			self::update_attachment_meta( $door_id, '_ps_closed_image_id', $item['closedImageId'] ?? 0 );
			self::update_attachment_meta( $door_id, '_ps_open_image_id', $item['openImageId'] ?? 0 );
			$answers = $item['quizAnswers'] ?? array();
			if ( is_string( $answers ) ) $answers = preg_split( '/\r\n|\r|\n/', $answers );
			$answers = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) $answers ), 'strlen' ) ), 0, 20 );
			update_post_meta( $door_id, '_ps_quiz_enabled', ! empty( $item['quizEnabled'] ) ? 1 : 0 );
			update_post_meta( $door_id, '_ps_quiz_question', sanitize_text_field( (string) ( $item['quizQuestion'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_quiz_answers', $answers );
			update_post_meta( $door_id, '_ps_quiz_correct', min( count( $answers ), max( 1, absint( $item['quizCorrect'] ?? 1 ) ) ) );
			update_post_meta( $door_id, '_ps_quiz_success', sanitize_text_field( (string) ( $item['quizSuccess'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_quiz_failure', sanitize_text_field( (string) ( $item['quizFailure'] ?? '' ) ) );
			update_post_meta( $door_id, '_ps_position_desktop', self::sanitize_position( $item['desktop'] ?? array() ) );
			update_post_meta( $door_id, '_ps_position_mobile', self::sanitize_position( $item['mobile'] ?? array() ) );
		}
	}

	private static function update_attachment_meta( $door_id, $meta_key, $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		update_post_meta( $door_id, $meta_key, $attachment_id && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0 );
	}

	private static function valid_related_post( $post_id, $allowed_types ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) return 0;
		$allowed_types = array_filter( array_map( 'sanitize_key', (array) $allowed_types ) );
		return in_array( get_post_type( $post_id ), $allowed_types, true ) ? $post_id : 0;
	}

	private static function update_color_meta( $door_id, $meta_key, $color ) {
		$color = sanitize_hex_color( (string) $color );
		update_post_meta( $door_id, $meta_key, $color ?: '' );
	}

	public static function save_door( $post_id ) {
		if ( ! self::can_save( $post_id, 'panda_seasons_door_nonce', 'panda_seasons_save_door' ) ) return;
		$calendar = absint( $_POST['panda_seasons_calendar_id'] ?? 0 );
		update_post_meta( $post_id, '_ps_calendar_id', $calendar );
		update_post_meta( $post_id, '_ps_door_number', max( 1, absint( $_POST['panda_seasons_door_number'] ?? 1 ) ) );
		$date = sanitize_text_field( wp_unslash( $_POST['panda_seasons_unlock_date'] ?? '' ) );
		update_post_meta( $post_id, '_ps_unlock_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
		$time = sanitize_text_field( wp_unslash( $_POST['panda_seasons_unlock_time'] ?? '' ) );
		update_post_meta( $post_id, '_ps_unlock_time', preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '' );
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
				'deleteConfirm'  => __( 'Move this door to the trash? You can restore it from the door list.', 'panda-seasons' ),
				'untitledDoor'   => __( 'Door %d', 'panda-seasons' ),
				'untitledPreview' => __( 'Door preview', 'panda-seasons' ),
				'selectMedia'    => __( 'Select image, video or audio', 'panda-seasons' ),
				'selectImage'    => __( 'Select image', 'panda-seasons' ),
				'selectDownload' => __( 'Select download file', 'panda-seasons' ),
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

	private static function door_unlock_at( $door_id ) {
		$date = (string) get_post_meta( $door_id, '_ps_unlock_date', true );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) return null;
		$time = (string) get_post_meta( $door_id, '_ps_unlock_time', true );
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) $time = '00:00';
		$value = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return $value ?: null;
	}

	public static function door_is_available( $door_id ) {
		$unlock = self::door_unlock_at( $door_id );
		return ! $unlock || new DateTimeImmutable( 'now', wp_timezone() ) >= $unlock;
	}

	public static function door_content_markup( $door, $context = 'web' ) {
		$door = is_object( $door ) ? $door : get_post( absint( $door ) );
		if ( ! $door || self::DOOR_POST_TYPE !== $door->post_type ) return '';

		$html     = '';
		$media_id = absint( get_post_meta( $door->ID, '_ps_media_id', true ) );
		$kind     = self::attachment_kind( $media_id );
		$url      = $media_id ? wp_get_attachment_url( $media_id ) : '';
		if ( $media_id && $url ) {
			if ( 'image' === $kind ) {
				$html .= '<figure class="panda-seasons__door-media">' . wp_get_attachment_image( $media_id, 'large' ) . '</figure>';
			} elseif ( 'web' === $context && 'video' === $kind ) {
				$html .= '<div class="panda-seasons__door-media">' . wp_video_shortcode( array( 'src' => $url ) ) . '</div>';
			} elseif ( 'web' === $context && 'audio' === $kind ) {
				$html .= '<div class="panda-seasons__door-media">' . wp_audio_shortcode( array( 'src' => $url ) ) . '</div>';
			} elseif ( in_array( $kind, array( 'video', 'audio' ), true ) ) {
				$html .= '<p><a href="' . esc_url( $url ) . '">' . esc_html( 'video' === $kind ? __( 'Play video', 'panda-seasons' ) : __( 'Play audio', 'panda-seasons' ) ) . '</a></p>';
			}
		}

		$download_id = absint( get_post_meta( $door->ID, '_ps_download_id', true ) );
		$download_url = $download_id ? wp_get_attachment_url( $download_id ) : '';
		if ( $download_url ) {
			$label = get_post_meta( $door->ID, '_ps_download_label', true ) ?: get_the_title( $download_id ) ?: __( 'Download file', 'panda-seasons' );
			$html .= '<p class="panda-seasons__door-download"><a href="' . esc_url( $download_url ) . '"' . ( 'web' === $context ? ' download' : '' ) . '>' . esc_html( $label ) . '</a></p>';
		}

		$button_url   = (string) get_post_meta( $door->ID, '_ps_button_url', true );
		$button_label = (string) get_post_meta( $door->ID, '_ps_button_label', true );
		if ( $button_url && $button_label ) $html .= '<p><a class="panda-seasons__door-button" href="' . esc_url( $button_url ) . '">' . esc_html( $button_label ) . '</a></p>';

		$related = array(
			'product' => array( absint( get_post_meta( $door->ID, '_ps_product_id', true ) ), __( 'View product', 'panda-seasons' ) ),
			'event'   => array( absint( get_post_meta( $door->ID, '_ps_event_id', true ) ), __( 'View event', 'panda-seasons' ) ),
		);
		foreach ( $related as $type => $config ) {
			$related_post = $config[0] ? get_post( $config[0] ) : null;
			if ( ! $related_post || 'publish' !== $related_post->post_status ) continue;
			$html .= '<aside class="panda-seasons__related panda-seasons__related--' . esc_attr( $type ) . '"><strong>' . esc_html( $related_post->post_title ) . '</strong><a href="' . esc_url( get_permalink( $related_post ) ) . '">' . esc_html( $config[1] ) . '</a></aside>';
		}

		if ( 'web' === $context && get_post_meta( $door->ID, '_ps_quiz_enabled', true ) ) {
			$answers = array_values( array_filter( (array) get_post_meta( $door->ID, '_ps_quiz_answers', true ), 'strlen' ) );
			$question = (string) get_post_meta( $door->ID, '_ps_quiz_question', true );
			$correct = absint( get_post_meta( $door->ID, '_ps_quiz_correct', true ) );
			if ( $question && count( $answers ) > 1 && $correct > 0 && $correct <= count( $answers ) ) {
				$html .= '<form class="panda-seasons__quiz" data-season-quiz data-correct="' . esc_attr( $correct ) . '" data-success="' . esc_attr( get_post_meta( $door->ID, '_ps_quiz_success', true ) ?: __( 'Correct!', 'panda-seasons' ) ) . '" data-failure="' . esc_attr( get_post_meta( $door->ID, '_ps_quiz_failure', true ) ?: __( 'That is not correct yet.', 'panda-seasons' ) ) . '"><fieldset><legend>' . esc_html( $question ) . '</legend>';
				foreach ( $answers as $answer_index => $answer ) $html .= '<label><input type="radio" name="panda_seasons_quiz" value="' . esc_attr( $answer_index + 1 ) . '" required> <span>' . esc_html( $answer ) . '</span></label>';
				$html .= '<button type="submit">' . esc_html__( 'Check answer', 'panda-seasons' ) . '</button><p class="panda-seasons__quiz-result" data-quiz-result role="status"></p></fieldset></form>';
			}
		}

		return $html;
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
					$unlock_at  = self::door_unlock_at( $door->ID );
					$available  = self::door_is_available( $door->ID );
					$number     = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 );
					$closed_label = get_post_meta( $door->ID, '_ps_closed_label', true ) ?: $number;
					$open_label   = get_post_meta( $door->ID, '_ps_open_label', true ) ?: $number;
					$desktop_position = self::door_position( $door->ID, 'desktop', $index, count( $doors ) );
					$mobile_position  = self::door_position( $door->ID, 'mobile', $index, count( $doors ) );
					$item_style = '--ps-x:' . $desktop_position['x'] . '%;--ps-y:' . $desktop_position['y'] . '%;--ps-w:' . $desktop_position['w'] . '%;--ps-h:' . $desktop_position['h'] . '%;--ps-mobile-x:' . $mobile_position['x'] . '%;--ps-mobile-y:' . $mobile_position['y'] . '%;--ps-mobile-w:' . $mobile_position['w'] . '%;--ps-mobile-h:' . $mobile_position['h'] . '%;';
					$item_style .= '--ps-closed-bg:' . ( sanitize_hex_color( get_post_meta( $door->ID, '_ps_closed_background', true ) ) ?: '#1f2937' ) . ';--ps-closed-color:' . ( sanitize_hex_color( get_post_meta( $door->ID, '_ps_closed_color', true ) ) ?: '#ffffff' ) . ';--ps-open-bg:' . ( sanitize_hex_color( get_post_meta( $door->ID, '_ps_open_background', true ) ) ?: '#334fe8' ) . ';--ps-open-color:' . ( sanitize_hex_color( get_post_meta( $door->ID, '_ps_open_color', true ) ) ?: '#ffffff' ) . ';';
					$closed_opacity = '' !== get_post_meta( $door->ID, '_ps_closed_opacity', true ) ? absint( get_post_meta( $door->ID, '_ps_closed_opacity', true ) ) : 100;
					$open_opacity = '' !== get_post_meta( $door->ID, '_ps_open_opacity', true ) ? absint( get_post_meta( $door->ID, '_ps_open_opacity', true ) ) : 100;
					$item_style .= '--ps-closed-opacity:' . min( 100, $closed_opacity ) . '%;--ps-open-opacity:' . min( 100, $open_opacity ) . '%;';
					$closed_image = wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_closed_image_id', true ) ), 'large' );
					$open_image = wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_open_image_id', true ) ), 'large' );
					if ( $closed_image ) $item_style .= '--ps-closed-image:url("' . esc_url_raw( $closed_image ) . '");';
					if ( $open_image ) $item_style .= '--ps-open-image:url("' . esc_url_raw( $open_image ) . '");';
					$shape = get_post_meta( $door->ID, '_ps_shape', true );
					if ( ! in_array( $shape, array( 'square', 'rounded', 'round' ), true ) ) $shape = 'rounded';
					?>
					<article class="panda-seasons__door panda-seasons__door--<?php echo esc_attr( $shape ); ?><?php echo $available ? ' is-available' : ' is-locked'; ?>" role="listitem" style="<?php echo esc_attr( $item_style ); ?>" id="panda-season-door-<?php echo esc_attr( $door->ID ); ?>">
						<button type="button" class="panda-seasons__door-face" aria-haspopup="dialog" aria-controls="panda-season-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>>
							<span class="panda-seasons__door-number"><?php echo esc_html( $available ? $open_label : $closed_label ); ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $available ? sprintf( __( 'Open door %d', 'panda-seasons' ), $number ) : sprintf( __( 'Door %1$d opens on %2$s', 'panda-seasons' ), $number, $unlock_at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $unlock_at->getTimestamp(), wp_timezone() ) : '' ) ); ?></span>
						</button>
						<template class="panda-seasons__door-content" id="panda-season-content-<?php echo esc_attr( $door->ID ); ?>"><h3><?php echo esc_html( $door->post_title ); ?></h3><?php echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard filtered post content. ?><?php echo self::door_content_markup( $door ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped component markup. ?></template>
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
			<?php if ( get_post_meta( $id, '_ps_email_enabled', true ) && self::contactpanda_available() ) echo Panda_Seasons_Subscriptions::form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::newsletter_markup( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated mode-specific markup. ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function subscribe_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'panda_seasons_subscribe' );
		$id = absint( $atts['id'] );
		if ( ! self::contactpanda_available() || ! get_post_meta( $id, '_ps_email_enabled', true ) || self::CALENDAR_POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) return '';
		wp_enqueue_style( 'panda-seasons' );
		return Panda_Seasons_Subscriptions::form( $id );
	}

	private static function newsletter_markup( $calendar_id ) {
		$mode = (string) get_post_meta( $calendar_id, '_ps_newsletter_mode', true );
		if ( ! in_array( $mode, array( 'link', 'shortcode', 'contactpanda' ), true ) ) return '';
		$title = (string) get_post_meta( $calendar_id, '_ps_newsletter_title', true );
		$content = '';
		if ( 'link' === $mode ) {
			$url = (string) get_post_meta( $calendar_id, '_ps_newsletter_link_url', true );
			$label = (string) get_post_meta( $calendar_id, '_ps_newsletter_link_label', true ) ?: __( 'Subscribe to newsletter', 'panda-seasons' );
			if ( $url ) $content = '<a class="panda-seasons__newsletter-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		} elseif ( 'shortcode' === $mode ) {
			$shortcode = trim( (string) get_post_meta( $calendar_id, '_ps_newsletter_shortcode', true ) );
			if ( preg_match( '/^\[([A-Za-z0-9_-]+)/', $shortcode, $matches ) && shortcode_exists( $matches[1] ) ) $content = do_shortcode( $shortcode );
		} elseif ( self::contactpanda_available() && shortcode_exists( 'contactpanda_newsletter_signup' ) ) {
			$content = do_shortcode( '[contactpanda_newsletter_signup]' );
			if ( false === strpos( $content, '<form' ) ) $content = '';
		}
		if ( ! trim( wp_strip_all_tags( $content ) ) && false === strpos( $content, '<form' ) ) return '';
		return '<section class="panda-seasons__newsletter">' . ( $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' ) . $content . '</section>';
	}
}
