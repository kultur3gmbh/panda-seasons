<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Free_Plugin {
	const CALENDAR_POST_TYPE = 'panda_season';
	const DOOR_POST_TYPE     = 'panda_door';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_shortcode( 'panda_seasons', array( __CLASS__, 'shortcode' ) );
		if ( ! is_admin() ) return;
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'save_post_' . self::CALENDAR_POST_TYPE, array( __CLASS__, 'save_calendar' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'mark_free_calendar' ), 10, 3 );
		add_action( 'admin_post_panda_seasons_free_preview', array( __CLASS__, 'preview_page' ) );
		add_filter( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_columns', array( __CLASS__, 'calendar_columns' ) );
		add_action( 'manage_' . self::CALENDAR_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'calendar_column' ), 10, 2 );
	}

	public static function mark_free_calendar( $post_id, $post, $update ) {
		if ( ! $update && $post instanceof WP_Post && self::CALENDAR_POST_TYPE === $post->post_type ) {
			update_post_meta( $post_id, '_ps_origin_edition', 'free' );
		}
	}

	public static function register_post_types() {
		register_post_type(
			self::CALENDAR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Panda Seasons Free', 'panda-seasons-free' ),
					'singular_name' => __( 'Calendar', 'panda-seasons-free' ),
					'add_new_item'  => __( 'Add calendar', 'panda-seasons-free' ),
					'edit_item'     => __( 'Edit calendar', 'panda-seasons-free' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
		register_post_type(
			self::DOOR_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Doors', 'panda-seasons-free' ),
					'singular_name' => __( 'Door', 'panda-seasons-free' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'supports'     => array( 'title', 'editor' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function admin_menu() {
		add_menu_page(
			__( 'Panda Seasons Free', 'panda-seasons-free' ),
			__( 'Seasons Free', 'panda-seasons-free' ),
			'edit_posts',
			'edit.php?post_type=' . self::CALENDAR_POST_TYPE,
			'',
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page(
			'edit.php?post_type=' . self::CALENDAR_POST_TYPE,
			__( 'Add calendar', 'panda-seasons-free' ),
			__( 'Add calendar', 'panda-seasons-free' ),
			'edit_posts',
			'post-new.php?post_type=' . self::CALENDAR_POST_TYPE
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'panda-seasons-free-editor',
			__( 'Visual calendar editor', 'panda-seasons-free' ),
			array( __CLASS__, 'editor_box' ),
			self::CALENDAR_POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::CALENDAR_POST_TYPE !== $screen->post_type ) return;
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'panda-seasons-free-admin', PANDA_SEASONS_FREE_URL . 'assets/admin.css', array( 'wp-color-picker' ), PANDA_SEASONS_FREE_VERSION );
		wp_enqueue_script( 'panda-seasons-free-admin', PANDA_SEASONS_FREE_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-resizable', 'wp-editor', 'wp-color-picker' ), PANDA_SEASONS_FREE_VERSION, true );
		wp_localize_script(
			'panda-seasons-free-admin',
			'pandaSeasonsFreeAdmin',
			array(
				'addDoor'       => __( 'Add door', 'panda-seasons-free' ),
				'saved'         => __( 'Calendar saved.', 'panda-seasons-free' ),
				'confirmDelete' => __( 'Delete this door?', 'panda-seasons-free' ),
				'selectImage'   => __( 'Select calendar background', 'panda-seasons-free' ),
				'useImage'      => __( 'Use this image', 'panda-seasons-free' ),
			)
		);
	}

	private static function doors( $calendar_id ) {
		return get_posts(
			array(
				'post_type'      => self::DOOR_POST_TYPE,
				'post_parent'    => absint( $calendar_id ),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'meta_key'       => '_ps_door_number',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
	}

	private static function position( $door_id, $layout, $index, $count ) {
		$value = get_post_meta( $door_id, '_ps_position_' . $layout, true );
		if ( is_array( $value ) && isset( $value['x'], $value['y'], $value['w'], $value['h'] ) ) return self::sanitize_position( $value );
		$columns = 'mobile' === $layout ? 3 : 6;
		$rows = max( 1, (int) ceil( max( 1, $count ) / $columns ) );
		return array(
			'x' => round( ( $index % $columns ) * ( 100 / $columns ), 4 ),
			'y' => round( floor( $index / $columns ) * ( 100 / $rows ), 4 ),
			'w' => round( 100 / $columns, 4 ),
			'h' => round( 100 / $rows, 4 ),
		);
	}

	private static function editor_doors( $calendar_id ) {
		$doors = self::doors( $calendar_id );
		$data  = array();
		foreach ( $doors as $index => $door ) {
			$data[] = array(
				'id'          => (int) $door->ID,
				'number'      => absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: $index + 1,
				'unlockDate'  => (string) get_post_meta( $door->ID, '_ps_unlock_date', true ),
				'unlockTime'  => (string) get_post_meta( $door->ID, '_ps_unlock_time', true ),
				'heading'     => (string) get_post_meta( $door->ID, '_ps_content_heading', true ),
				'content'     => (string) $door->post_content,
				'closedBg'    => (string) get_post_meta( $door->ID, '_ps_closed_background', true ),
				'closedColor' => (string) get_post_meta( $door->ID, '_ps_closed_color', true ),
				'openBg'      => (string) get_post_meta( $door->ID, '_ps_open_background', true ),
				'openColor'   => (string) get_post_meta( $door->ID, '_ps_open_color', true ),
				'desktop'     => self::position( $door->ID, 'desktop', $index, count( $doors ) ),
				'mobile'      => self::position( $door->ID, 'mobile', $index, count( $doors ) ),
			);
		}
		return $data;
	}

	public static function editor_box( $post ) {
		wp_nonce_field( 'panda_seasons_free_save_' . $post->ID, 'panda_seasons_free_nonce' );
		$cover_id = absint( get_post_meta( $post->ID, '_ps_cover_desktop', true ) );
		$cover    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
		$ratio    = (float) get_post_meta( $post->ID, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $post->ID, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$background = self::color( get_post_meta( $post->ID, '_ps_cover_desktop_background', true ), '#f4f4f4' );
		$closed_bg = self::color( get_post_meta( $post->ID, '_ps_default_closed_background', true ), 'rgba(44,62,80,.86)' );
		$closed_color = self::color( get_post_meta( $post->ID, '_ps_default_closed_text_color', true ), '#ffffff' );
		$open_bg = self::color( get_post_meta( $post->ID, '_ps_default_open_background', true ), 'rgba(255,255,255,.80)' );
		$open_color = self::color( get_post_meta( $post->ID, '_ps_default_open_text_color', true ), '#1f2937' );
		$doors = self::editor_doors( $post->ID );
		$preview = wp_nonce_url( admin_url( 'admin-post.php?action=panda_seasons_free_preview&calendar=' . $post->ID ), 'panda_seasons_free_preview_' . $post->ID );
		?>
		<div class="panda-seasons-free-editor" data-seasons-free-editor data-cover-url="<?php echo esc_url( $cover ); ?>">
			<div class="panda-seasons-free-settings-card">
				<div class="panda-seasons-free-background-picker" data-select-cover role="button" tabindex="0">
					<div data-cover-preview<?php echo $cover ? ' style="background-image:url(' . esc_url( $cover ) . ')"' : ''; ?>>
						<span<?php echo $cover ? ' hidden' : ''; ?>><?php esc_html_e( 'Select one general background image', 'panda-seasons-free' ); ?></span>
					</div>
					<input type="hidden" name="panda_seasons_free_cover" value="<?php echo esc_attr( $cover_id ); ?>" data-cover-id>
				</div>
				<button type="button" class="button" data-remove-cover<?php echo $cover ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove background image', 'panda-seasons-free' ); ?></button>
				<div class="panda-seasons-free-settings-grid">
					<label><span><?php esc_html_e( 'Desktop aspect ratio', 'panda-seasons-free' ); ?></span><input type="number" min="0.2" max="5" step="0.01" name="panda_seasons_free_ratio" value="<?php echo esc_attr( $ratio ); ?>" data-ratio="desktop"></label>
					<label><span><?php esc_html_e( 'Mobile aspect ratio', 'panda-seasons-free' ); ?></span><input type="number" min="0.2" max="5" step="0.01" name="panda_seasons_free_mobile_ratio" value="<?php echo esc_attr( $mobile_ratio ); ?>" data-ratio="mobile"></label>
					<label><span><?php esc_html_e( 'Background color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_background" value="<?php echo esc_attr( $background ); ?>" data-calendar-color></label>
				</div>
			</div>
			<div class="panda-seasons-free-defaults">
				<h3><?php esc_html_e( 'Default door colors', 'panda-seasons-free' ); ?></h3>
				<div class="panda-seasons-free-settings-grid is-four">
					<label><span><?php esc_html_e( 'Closed background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_closed_bg" value="<?php echo esc_attr( $closed_bg ); ?>" data-default="closedBg"></label>
					<label><span><?php esc_html_e( 'Closed text', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_closed_color" value="<?php echo esc_attr( $closed_color ); ?>" data-default="closedColor"></label>
					<label><span><?php esc_html_e( 'Opened background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_open_bg" value="<?php echo esc_attr( $open_bg ); ?>" data-default="openBg"></label>
					<label><span><?php esc_html_e( 'Opened text', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" name="panda_seasons_free_open_color" value="<?php echo esc_attr( $open_color ); ?>" data-default="openColor"></label>
				</div>
			</div>
			<div class="panda-seasons-free-toolbar">
				<div><button type="button" class="button button-primary" data-add-door><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add door', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-layout="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-layout="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-state="closed" aria-pressed="true"><?php esc_html_e( 'All closed', 'panda-seasons-free' ); ?></button><button type="button" class="button" data-state="open" aria-pressed="false"><?php esc_html_e( 'All opened', 'panda-seasons-free' ); ?></button></div>
				<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'panda-seasons-free' ); ?></a>
			</div>
			<p class="description"><?php esc_html_e( 'Drag doors to position them. Use the handle in the lower-right corner to resize them. Desktop and mobile positions are saved separately.', 'panda-seasons-free' ); ?></p>
			<div class="panda-seasons-free-workspace">
				<div class="panda-seasons-free-canvas-wrap"><div class="panda-seasons-free-canvas" data-canvas></div></div>
				<aside class="panda-seasons-free-inspector" data-inspector hidden>
					<h3><?php esc_html_e( 'Edit door', 'panda-seasons-free' ); ?> <span data-door-title></span></h3>
					<div class="panda-seasons-free-settings-grid">
						<label><span><?php esc_html_e( 'Door number', 'panda-seasons-free' ); ?></span><input type="number" min="1" max="999" data-door-field="number"></label>
						<label><span><?php esc_html_e( 'Unlock date', 'panda-seasons-free' ); ?></span><input type="date" data-door-field="unlockDate"></label>
						<label><span><?php esc_html_e( 'Unlock time', 'panda-seasons-free' ); ?></span><input type="time" data-door-field="unlockTime"></label>
						<label class="is-wide"><span><?php esc_html_e( 'Content heading', 'panda-seasons-free' ); ?></span><input type="text" data-door-field="heading"></label>
					</div>
					<div class="panda-seasons-free-state-colors">
						<h4><?php esc_html_e( 'Closed door', 'panda-seasons-free' ); ?></h4><label><span><?php esc_html_e( 'Background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="closedBg"></label><label><span><?php esc_html_e( 'Text color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="closedColor"></label>
						<h4><?php esc_html_e( 'Opened door', 'panda-seasons-free' ); ?></h4><label><span><?php esc_html_e( 'Background', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="openBg"></label><label><span><?php esc_html_e( 'Text color', 'panda-seasons-free' ); ?></span><input type="text" class="panda-seasons-free-color" data-door-field="openColor"></label>
					</div>
					<div class="panda-seasons-free-content-editor"><strong><?php esc_html_e( 'Text content', 'panda-seasons-free' ); ?></strong><?php wp_editor( '', 'panda_seasons_free_door_content', array( 'textarea_name' => 'panda_seasons_free_door_content_visual', 'textarea_rows' => 10, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' ) ) ); ?></div>
					<div class="panda-seasons-free-inspector-actions"><button type="button" class="button button-primary" data-apply-door><?php esc_html_e( 'Apply door', 'panda-seasons-free' ); ?></button><button type="button" class="button button-link-delete" data-delete-door><?php esc_html_e( 'Move door to trash', 'panda-seasons-free' ); ?></button></div>
				</aside>
			</div>
			<input type="hidden" name="panda_seasons_free_doors" data-doors-input>
			<input type="hidden" name="panda_seasons_free_deleted" data-deleted-input>
			<script type="application/json" data-doors-json><?php echo wp_json_encode( $doors ); ?></script>
		</div>
		<?php
	}

	public static function save_calendar( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		if ( ! isset( $_POST['panda_seasons_free_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['panda_seasons_free_nonce'] ) ), 'panda_seasons_free_save_' . $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$cover = absint( $_POST['panda_seasons_free_cover'] ?? 0 );
		if ( $cover && 'attachment' !== get_post_type( $cover ) ) $cover = 0;
		$free_origin = 'free' === (string) get_post_meta( $post_id, '_ps_origin_edition', true );
		update_post_meta( $post_id, '_ps_cover_desktop', $cover );
		if ( $free_origin ) update_post_meta( $post_id, '_ps_cover_mobile', $cover );
		update_post_meta( $post_id, '_ps_aspect_ratio', self::ratio( $_POST['panda_seasons_free_ratio'] ?? 1.5, 1.5 ) );
		update_post_meta( $post_id, '_ps_mobile_aspect_ratio', self::ratio( $_POST['panda_seasons_free_mobile_ratio'] ?? .75, .75 ) );
		$background = self::color( wp_unslash( $_POST['panda_seasons_free_background'] ?? '' ), '#f4f4f4' );
		update_post_meta( $post_id, '_ps_cover_desktop_background', $background );
		if ( $free_origin ) update_post_meta( $post_id, '_ps_cover_mobile_background', $background );
		$defaults = array(
			'_ps_default_closed_background' => self::color( wp_unslash( $_POST['panda_seasons_free_closed_bg'] ?? '' ), 'rgba(44,62,80,.86)' ),
			'_ps_default_closed_text_color' => self::color( wp_unslash( $_POST['panda_seasons_free_closed_color'] ?? '' ), '#ffffff' ),
			'_ps_default_open_background'   => self::color( wp_unslash( $_POST['panda_seasons_free_open_bg'] ?? '' ), 'rgba(255,255,255,.80)' ),
			'_ps_default_open_text_color'   => self::color( wp_unslash( $_POST['panda_seasons_free_open_color'] ?? '' ), '#1f2937' ),
		);
		foreach ( $defaults as $key => $value ) update_post_meta( $post_id, $key, $value );

		$items = json_decode( wp_unslash( $_POST['panda_seasons_free_doors'] ?? '[]' ), true );
		if ( ! is_array( $items ) ) $items = array();
		$valid_ids = wp_list_pluck( self::doors( $post_id ), 'ID' );
		foreach ( array_slice( $items, 0, 366 ) as $item ) {
			if ( ! is_array( $item ) ) continue;
			$door_id = absint( $item['id'] ?? 0 );
			$is_new  = ! $door_id || ! in_array( $door_id, $valid_ids, true );
			if ( $is_new ) {
				$door_id = wp_insert_post(
					array(
						'post_type'   => self::DOOR_POST_TYPE,
						'post_status' => 'publish',
						'post_parent' => $post_id,
						'post_title'  => sprintf( __( 'Door %d', 'panda-seasons-free' ), absint( $item['number'] ?? 1 ) ),
					)
				);
			}
			if ( ! $door_id || is_wp_error( $door_id ) ) continue;
			$number = min( 999, max( 1, absint( $item['number'] ?? 1 ) ) );
			$post_update = array(
				'ID'           => $door_id,
				'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
			);
			if ( $is_new ) $post_update['post_title'] = sprintf( __( 'Door %d', 'panda-seasons-free' ), $number );
			wp_update_post( wp_slash( $post_update ) );
			update_post_meta( $door_id, '_ps_door_number', $number );
			update_post_meta( $door_id, '_ps_unlock_date', self::date( $item['unlockDate'] ?? '' ) );
			update_post_meta( $door_id, '_ps_unlock_time', self::time( $item['unlockTime'] ?? '' ) );
			update_post_meta( $door_id, '_ps_content_heading', sanitize_text_field( (string) ( $item['heading'] ?? '' ) ) );
			if ( $is_new ) {
				update_post_meta( $door_id, '_ps_content_type', 'general' );
				update_post_meta( $door_id, '_ps_opening_mode', 'auto-permanent' );
			}
			if ( $is_new ) {
				update_post_meta( $door_id, '_ps_closed_label', (string) $number );
				update_post_meta( $door_id, '_ps_open_label', (string) $number );
			}
			update_post_meta( $door_id, '_ps_closed_background', self::color( $item['closedBg'] ?? '', $defaults['_ps_default_closed_background'] ) );
			update_post_meta( $door_id, '_ps_closed_color', self::color( $item['closedColor'] ?? '', $defaults['_ps_default_closed_text_color'] ) );
			update_post_meta( $door_id, '_ps_open_background', self::color( $item['openBg'] ?? '', $defaults['_ps_default_open_background'] ) );
			update_post_meta( $door_id, '_ps_open_color', self::color( $item['openColor'] ?? '', $defaults['_ps_default_open_text_color'] ) );
			update_post_meta( $door_id, '_ps_position_desktop', self::sanitize_position( $item['desktop'] ?? array() ) );
			update_post_meta( $door_id, '_ps_position_mobile', self::sanitize_position( $item['mobile'] ?? array() ) );
			// Set neutral advanced styling only for doors created in Free. Existing
			// Pro-only styling remains untouched while the free edition is active.
			if ( $is_new ) {
				foreach ( array( 'closed', 'open' ) as $state ) {
					update_post_meta( $door_id, '_ps_' . $state . '_border_width', 0 );
					update_post_meta( $door_id, '_ps_' . $state . '_border_style', 'none' );
					update_post_meta( $door_id, '_ps_' . $state . '_label_background', 'rgba(0,0,0,0)' );
					update_post_meta( $door_id, '_ps_' . $state . '_label_border_width', 0 );
					update_post_meta( $door_id, '_ps_' . $state . '_label_visible', 1 );
				}
				update_post_meta( $door_id, '_ps_shadow_enabled', 0 );
			}
		}
		$deleted = json_decode( wp_unslash( $_POST['panda_seasons_free_deleted'] ?? '[]' ), true );
		foreach ( is_array( $deleted ) ? $deleted : array() as $door_id ) {
			$door_id = absint( $door_id );
			if ( $door_id && in_array( $door_id, $valid_ids, true ) ) wp_trash_post( $door_id );
		}
	}

	private static function ratio( $value, $fallback ) {
		$value = (float) $value;
		return $value >= .2 && $value <= 5 ? $value : $fallback;
	}

	private static function color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) || preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)$/i', $value ) || 'transparent' === strtolower( $value ) ) return $value;
		return $fallback;
	}

	private static function date( $value ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function time( $value ) {
		$value = (string) $value;
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	private static function sanitize_position( $value ) {
		$value = is_array( $value ) ? $value : array();
		$x = min( 100, max( 0, (float) ( $value['x'] ?? 0 ) ) );
		$y = min( 100, max( 0, (float) ( $value['y'] ?? 0 ) ) );
		$w = min( 100, max( 2, (float) ( $value['w'] ?? 15 ) ) );
		$h = min( 100, max( 2, (float) ( $value['h'] ?? 15 ) ) );
		if ( $x + $w > 100 ) $x = 100 - $w;
		if ( $y + $h > 100 ) $y = 100 - $h;
		return array( 'x' => round( $x, 4 ), 'y' => round( $y, 4 ), 'w' => round( $w, 4 ), 'h' => round( $h, 4 ) );
	}

	public static function register_frontend_assets() {
		wp_register_style( 'panda-seasons-free', PANDA_SEASONS_FREE_URL . 'assets/calendar.css', array(), PANDA_SEASONS_FREE_VERSION );
		wp_register_script( 'panda-seasons-free', PANDA_SEASONS_FREE_URL . 'assets/frontend.js', array(), PANDA_SEASONS_FREE_VERSION, true );
	}

	private static function unlock_at( $door_id ) {
		$date = self::date( get_post_meta( $door_id, '_ps_unlock_date', true ) );
		if ( ! $date ) return null;
		$time = self::time( get_post_meta( $door_id, '_ps_unlock_time', true ) ) ?: '00:00';
		$value = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return $value ?: null;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'preview' => 0, 'show_title' => 'yes' ), $atts, 'panda_seasons' );
		$id = absint( $atts['id'] );
		$post = get_post( $id );
		$preview = ! empty( $atts['preview'] ) && current_user_can( 'edit_post', $id );
		if ( ! $post || self::CALENDAR_POST_TYPE !== $post->post_type || ( 'publish' !== $post->post_status && ! $preview ) ) return '';
		$doors = self::doors( $id );
		if ( ! $doors ) return '<p>' . esc_html__( 'This calendar does not have any doors yet.', 'panda-seasons-free' ) . '</p>';
		wp_enqueue_style( 'panda-seasons-free' );
		wp_enqueue_script( 'panda-seasons-free' );
		$cover_id = absint( get_post_meta( $id, '_ps_cover_desktop', true ) );
		$cover = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
		$ratio = (float) get_post_meta( $id, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $id, '_ps_mobile_aspect_ratio', true ) ?: .75;
		$background = self::color( get_post_meta( $id, '_ps_cover_desktop_background', true ), '#f4f4f4' );
		$default_closed_bg = self::color( get_post_meta( $id, '_ps_default_closed_background', true ), 'rgba(44,62,80,.86)' );
		$default_closed_color = self::color( get_post_meta( $id, '_ps_default_closed_text_color', true ), '#ffffff' );
		$default_open_bg = self::color( get_post_meta( $id, '_ps_default_open_background', true ), 'rgba(255,255,255,.80)' );
		$default_open_color = self::color( get_post_meta( $id, '_ps_default_open_text_color', true ), '#1f2937' );
		$show_title = ! in_array( strtolower( (string) $atts['show_title'] ), array( '0', 'false', 'no', 'off' ), true );
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		ob_start();
		?>
		<section class="panda-seasons-free" style="--psf-ratio:<?php echo esc_attr( $ratio ); ?>;--psf-mobile-ratio:<?php echo esc_attr( $mobile_ratio ); ?>;--psf-background:<?php echo esc_attr( $background ); ?>;--psf-cover:<?php echo $cover ? 'url(&quot;' . esc_url( $cover ) . '&quot;)' : 'none'; ?>">
			<?php if ( $show_title ) : ?><h2 class="panda-seasons-free__title"><?php echo esc_html( get_the_title( $id ) ); ?></h2><?php endif; ?>
			<div class="panda-seasons-free__canvas" role="list">
				<?php foreach ( $doors as $index => $door ) :
					$unlock = self::unlock_at( $door->ID );
					$available = ! $unlock || $now >= $unlock;
					$number = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: $index + 1;
					$desktop = self::position( $door->ID, 'desktop', $index, count( $doors ) );
					$mobile = self::position( $door->ID, 'mobile', $index, count( $doors ) );
					$closed_bg = self::color( get_post_meta( $door->ID, '_ps_closed_background', true ), $default_closed_bg );
					$closed_color = self::color( get_post_meta( $door->ID, '_ps_closed_color', true ), $default_closed_color );
					$open_bg = self::color( get_post_meta( $door->ID, '_ps_open_background', true ), $default_open_bg );
					$open_color = self::color( get_post_meta( $door->ID, '_ps_open_color', true ), $default_open_color );
					$style = '--psf-x:' . $desktop['x'] . '%;--psf-y:' . $desktop['y'] . '%;--psf-w:' . $desktop['w'] . '%;--psf-h:' . $desktop['h'] . '%;--psf-mx:' . $mobile['x'] . '%;--psf-my:' . $mobile['y'] . '%;--psf-mw:' . $mobile['w'] . '%;--psf-mh:' . $mobile['h'] . '%;--psf-closed-bg:' . $closed_bg . ';--psf-closed-color:' . $closed_color . ';--psf-open-bg:' . $open_bg . ';--psf-open-color:' . $open_color . ';';
					$heading = (string) get_post_meta( $door->ID, '_ps_content_heading', true ) ?: sprintf( __( 'Door %d', 'panda-seasons-free' ), $number );
					?>
					<article class="panda-seasons-free__door <?php echo $available ? 'is-available' : 'is-locked'; ?>" style="<?php echo esc_attr( $style ); ?>" role="listitem">
						<button type="button" aria-haspopup="dialog" aria-controls="panda-seasons-free-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>><span><?php echo esc_html( $number ); ?></span><span class="screen-reader-text"><?php echo esc_html( $available ? sprintf( __( 'Open door %d', 'panda-seasons-free' ), $number ) : sprintf( __( 'Door %d is not available yet', 'panda-seasons-free' ), $number ) ); ?></span></button>
						<template id="panda-seasons-free-content-<?php echo esc_attr( $door->ID ); ?>"><h3><?php echo esc_html( $heading ); ?></h3><?php if ( $unlock ) : ?><p class="panda-seasons-free__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $unlock->getTimestamp(), wp_timezone() ) ); ?></p><?php endif; ?><div><?php echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></template>
					</article>
				<?php endforeach; ?>
			</div>
			<div class="panda-seasons-free__modal" data-psf-modal hidden><div class="panda-seasons-free__backdrop" data-psf-close></div><div class="panda-seasons-free__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Calendar door', 'panda-seasons-free' ); ?>"><button type="button" class="panda-seasons-free__close" data-psf-close aria-label="<?php esc_attr_e( 'Close', 'panda-seasons-free' ); ?>">&times;</button><div data-psf-content></div></div></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function preview_page() {
		$id = absint( $_GET['calendar'] ?? 0 );
		check_admin_referer( 'panda_seasons_free_preview_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || self::CALENDAR_POST_TYPE !== get_post_type( $id ) ) wp_die( esc_html__( 'You are not allowed to preview this calendar.', 'panda-seasons-free' ) );
		nocache_headers();
		show_admin_bar( false );
		self::register_frontend_assets();
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head><body class="panda-seasons-free-preview"><?php echo self::shortcode( array( 'id' => $id, 'preview' => 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	public static function register_block() {
		wp_register_script( 'panda-seasons-free-block', PANDA_SEASONS_FREE_URL . 'assets/block.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), PANDA_SEASONS_FREE_VERSION, true );
		$choices = array();
		if ( is_admin() ) {
			foreach ( get_posts( array( 'post_type' => self::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 200, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC' ) ) as $calendar ) $choices[] = array( 'label' => $calendar->post_title, 'value' => (int) $calendar->ID );
		}
		wp_localize_script( 'panda-seasons-free-block', 'pandaSeasonsFreeBlock', array( 'calendars' => $choices, 'selectCalendar' => __( 'Select calendar', 'panda-seasons-free' ), 'showTitle' => __( 'Show title', 'panda-seasons-free' ) ) );
		register_block_type(
			'panda-seasons/calendar',
			array(
				'api_version'     => 2,
				'editor_script'   => 'panda-seasons-free-block',
				'attributes'      => array( 'id' => array( 'type' => 'integer', 'default' => 0 ), 'showTitle' => array( 'type' => 'boolean', 'default' => true ) ),
				'render_callback' => static function ( $attributes ) { return self::shortcode( array( 'id' => absint( $attributes['id'] ?? 0 ), 'show_title' => ! empty( $attributes['showTitle'] ) ? 'yes' : 'no' ) ); },
			)
		);
	}

	public static function calendar_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Calendar', 'panda-seasons-free' ), 'doors' => __( 'Doors', 'panda-seasons-free' ), 'shortcode' => __( 'Shortcode', 'panda-seasons-free' ), 'date' => $columns['date'] );
	}

	public static function calendar_column( $column, $post_id ) {
		if ( 'doors' === $column ) echo esc_html( count( self::doors( $post_id ) ) );
		if ( 'shortcode' === $column ) echo '<code>[panda_seasons id="' . esc_html( $post_id ) . '"]</code>';
	}
}

