<?php

defined( 'ABSPATH' ) || exit;

/**
 * Exposes Panda Seasons through the WordPress Abilities API and MCP Adapter.
 * WordPress versions before 6.9 simply ignore these hooks.
 */
final class Panda_Seasons_MCP {
	const CATEGORY = 'panda-seasons';
	const FALLBACK_CATEGORY = 'site';

	public static function boot() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );

		// Some production stacks initialize the lazy Abilities registry while
		// active plugins are still being loaded. WordPress only fires the init
		// action once, so a plugin loaded afterwards would otherwise never get a
		// registration callback. Register directly with the existing registry in
		// that exceptional late-load case.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		}
	}

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) return;
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Panda Seasons', 'panda-seasons' ),
				'description' => __( 'Securely read and manage seasonal calendars and their doors.', 'panda-seasons' ),
			)
		);
	}

	private static function ability( $name, $label, $description, $callback, $permission, $input_schema = null, $readonly = false, $destructive = false ) {
		// The category registry may already have been initialized by another plugin
		// before Panda Seasons is loaded. In that case WordPress no longer fires the
		// category registration hook, so use the built-in category instead of
		// silently losing every Panda Seasons ability.
		$category = function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY )
			? self::CATEGORY
			: self::FALLBACK_CATEGORY;
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => $category,
			'execute_callback'    => array( __CLASS__, $callback ),
			'permission_callback' => array( __CLASS__, $permission ),
			'meta'                => array(
				'public'      => true,
				'show_in_rest'=> true,
				'mcp'         => array( 'type' => 'tool', 'public' => true ),
				'annotations' => array( 'readonly' => $readonly, 'destructive' => $destructive ),
			),
		);
		if ( null !== $input_schema ) $args['input_schema'] = $input_schema;
		$ability_name = 'panda-seasons/' . $name;
		if ( doing_action( 'wp_abilities_api_init' ) ) {
			wp_register_ability( $ability_name, $args );
			return;
		}

		if ( class_exists( 'WP_Abilities_Registry' ) ) {
			$registry = WP_Abilities_Registry::get_instance();
			if ( $registry && ! $registry->is_registered( $ability_name ) ) {
				$registry->register( $ability_name, $args );
			}
		}
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) return;
		$id_schema = array(
			'type'       => 'object',
			'properties' => array( 'calendar_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			'required'   => array( 'calendar_id' ),
		);
		self::ability( 'list-calendars', __( 'List calendars', 'panda-seasons' ), __( 'Lists Panda Seasons calendars the current WordPress user may edit. No subscriber data is returned.', 'panda-seasons' ), 'list_calendars', 'can_list', array( 'type' => 'object', 'properties' => array() ), true );
		self::ability( 'get-calendar', __( 'Get calendar', 'panda-seasons' ), __( 'Returns one editable calendar with its settings and doors. No subscriber data is returned.', 'panda-seasons' ), 'get_calendar', 'can_edit_calendar', $id_schema, true );
		self::ability( 'save-calendar', __( 'Create or update calendar', 'panda-seasons' ), __( 'Creates a calendar or updates its title, content, status and supported settings.', 'panda-seasons' ), 'save_calendar', 'can_save_calendar', self::calendar_input_schema() );
		self::ability( 'save-door', __( 'Create or update door', 'panda-seasons' ), __( 'Creates a door in a calendar or updates an existing door and its supported settings.', 'panda-seasons' ), 'save_door', 'can_save_door', self::door_input_schema() );
		self::ability( 'duplicate-door', __( 'Duplicate door', 'panda-seasons' ), __( 'Duplicates an existing door in the same calendar.', 'panda-seasons' ), 'duplicate_door', 'can_edit_door', array( 'type' => 'object', 'properties' => array( 'door_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), 'required' => array( 'door_id' ) ) );
		self::ability( 'delete-door', __( 'Move door to trash', 'panda-seasons' ), __( 'Moves one calendar door to the WordPress trash.', 'panda-seasons' ), 'delete_door', 'can_delete_door', array( 'type' => 'object', 'properties' => array( 'door_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), 'required' => array( 'door_id' ) ), false, true );
		self::ability( 'export-template', __( 'Export calendar template', 'panda-seasons' ), __( 'Returns a portable Panda Seasons template object without subscriber data.', 'panda-seasons' ), 'export_template', 'can_edit_calendar', $id_schema, true );
		self::ability( 'create-layout-draft', __( 'Create layout-only calendar draft', 'panda-seasons' ), __( 'Creates a draft calendar from an analyzed reference image, door rectangles and visual defaults. Door content may be added later.', 'panda-seasons' ), 'create_layout_draft', 'can_create_layout', self::layout_input_schema() );
		self::ability( 'apply-document-content', __( 'Apply document content to calendar', 'panda-seasons' ), __( 'Adds normalized content from a document to existing doors by door number without changing their layout.', 'panda-seasons' ), 'apply_document_content', 'can_apply_document_content', self::document_input_schema() );
	}

	private static function calendar_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'minimum' => 0 ),
				'title'       => array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private' ) ),
				'settings'    => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		);
	}

	private static function door_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'door_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
				'title'       => array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'settings'    => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'required' => array( 'calendar_id' ),
		);
	}

	private static function layout_input_schema() {
		$position = array(
			'type' => 'object',
			'properties' => array(
				'number' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 366 ),
				'x' => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 100 ),
				'y' => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 100 ),
				'w' => array( 'type' => 'number', 'minimum' => 1, 'maximum' => 100 ),
				'h' => array( 'type' => 'number', 'minimum' => 1, 'maximum' => 100 ),
			),
			'required' => array( 'number', 'x', 'y', 'w', 'h' ),
		);
		return array(
			'type' => 'object',
			'properties' => array(
				'title' => array( 'type' => 'string' ),
				'background_media_id' => array( 'type' => 'integer', 'minimum' => 0 ),
				'desktop_aspect_ratio' => array( 'type' => 'number', 'minimum' => 0.4, 'maximum' => 3 ),
				'mobile_aspect_ratio' => array( 'type' => 'number', 'minimum' => 0.35, 'maximum' => 2 ),
				'doors' => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 366, 'items' => $position ),
				'mobile_doors' => array( 'type' => 'array', 'items' => $position ),
				'style' => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'required' => array( 'doors' ),
		);
	}

	private static function document_input_schema() {
		return array(
			'type' => 'object',
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'create_missing_doors' => array( 'type' => 'boolean', 'default' => false ),
				'import_mode' => array( 'type' => 'string', 'enum' => array( 'empty', 'merge', 'overwrite' ), 'default' => 'merge' ),
				'doors' => array(
					'type' => 'array',
					'items' => array(
						'type' => 'object',
						'properties' => array(
							'number' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 366 ),
							'title' => array( 'type' => 'string' ),
							'content' => array( 'type' => 'string' ),
							'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
						),
						'required' => array( 'number' ),
					),
				),
			),
			'required' => array( 'calendar_id', 'doors' ),
		);
	}

	private static function input_id( $input, $key ) {
		return absint( is_array( $input ) ? ( $input[ $key ] ?? 0 ) : 0 );
	}

	public static function can_list() { return current_user_can( 'edit_posts' ); }
	public static function can_edit_calendar( $input ) {
		$id = self::input_id( $input, 'calendar_id' );
		return $id && Panda_Seasons_Plugin::CALENDAR_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}
	public static function can_save_calendar( $input ) {
		$id = self::input_id( $input, 'calendar_id' );
		return $id ? self::can_edit_calendar( $input ) : current_user_can( 'edit_posts' );
	}
	public static function can_edit_door( $input ) {
		$id = self::input_id( $input, 'door_id' );
		return $id && Panda_Seasons_Plugin::DOOR_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}
	public static function can_save_door( $input ) {
		$calendar_id = self::input_id( $input, 'calendar_id' );
		$door_id = self::input_id( $input, 'door_id' );
		if ( ! $calendar_id || Panda_Seasons_Plugin::CALENDAR_POST_TYPE !== get_post_type( $calendar_id ) || ! current_user_can( 'edit_post', $calendar_id ) ) return false;
		return ! $door_id || ( self::can_edit_door( $input ) && absint( wp_get_post_parent_id( $door_id ) ) === $calendar_id );
	}
	public static function can_delete_door( $input ) {
		$id = self::input_id( $input, 'door_id' );
		return self::can_edit_door( $input ) && current_user_can( 'delete_post', $id );
	}
	public static function can_create_layout() { return current_user_can( 'edit_posts' ); }
	public static function can_apply_document_content( $input ) { return self::can_edit_calendar( $input ); }

	private static function post_meta( $post_id ) {
		$result = array();
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( 0 !== strpos( $key, '_ps_' ) ) continue;
			$value = maybe_unserialize( $values[0] ?? '' );
			$result[ substr( $key, 4 ) ] = $value;
		}
		return $result;
	}

	private static function door_data( $door ) {
		return array(
			'id'       => $door->ID,
			'title'    => $door->post_title,
			'content'  => $door->post_content,
			'status'   => $door->post_status,
			'settings' => self::post_meta( $door->ID ),
		);
	}

	public static function list_calendars() {
		$items = array();
		foreach ( get_posts( array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) continue;
			$items[] = array( 'id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status, 'shortcode' => '[panda_seasons id="' . $post->ID . '"]', 'modified' => get_post_modified_time( DATE_ATOM, true, $post ) );
		}
		return array( 'calendars' => $items, 'count' => count( $items ) );
	}

	public static function get_calendar( $input ) {
		$id = self::input_id( $input, 'calendar_id' );
		$post = get_post( $id );
		$doors = get_posts( array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_parent' => $id, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'meta_key' => '_ps_door_number', 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
		return array( 'id' => $id, 'title' => $post->post_title, 'content' => $post->post_content, 'status' => $post->post_status, 'shortcode' => '[panda_seasons id="' . $id . '"]', 'settings' => self::post_meta( $id ), 'doors' => array_map( array( __CLASS__, 'door_data' ), $doors ) );
	}

	private static function allowed_calendar_meta() {
		return array( 'cover_desktop', 'cover_mobile', 'aspect_ratio', 'mobile_aspect_ratio', 'open_effect', 'effect_speed', 'design_layers', 'popup_background', 'popup_text_color', 'popup_border_color', 'popup_border_width', 'popup_radius', 'popup_padding', 'popup_width', 'default_closed_background', 'default_open_background', 'default_closed_image_overlay', 'default_open_image_overlay', 'default_closed_text_color', 'default_open_text_color', 'default_closed_border_color', 'default_open_border_color', 'default_closed_border_width', 'default_open_border_width', 'default_closed_label_background', 'default_open_label_background', 'default_closed_label_border_color', 'default_open_label_border_color', 'default_closed_label_border_width', 'default_open_label_border_width', 'default_label_width_mode', 'default_label_width', 'default_label_padding', 'default_active_border_color', 'default_label_font', 'default_label_font_size', 'default_label_font_weight', 'default_border_style', 'default_closed_label_visible', 'default_open_label_visible', 'default_shape', 'newsletter_mode', 'newsletter_title', 'newsletter_link_label', 'newsletter_link_url', 'newsletter_shortcode' );
	}

	private static function allowed_door_meta() {
		return array( 'door_number', 'unlock_date', 'unlock_time', 'opening_mode', 'unlock_behavior', 'availability_window', 'content_type', 'media_id', 'download_id', 'download_label', 'button_label', 'button_url', 'product_id', 'coupon_id', 'cta_enabled', 'cta_title', 'cta_text', 'cta_type', 'cta_label', 'cta_url', 'cta_shortcode', 'event_id', 'closed_label', 'open_label', 'closed_background', 'open_background', 'closed_color', 'open_color', 'closed_label_visible', 'open_label_visible', 'open_compact_label', 'open_label_corner', 'open_compact_label_size', 'closed_font', 'open_font', 'closed_font_size', 'open_font_size', 'closed_font_weight', 'open_font_weight', 'closed_text_align', 'open_text_align', 'closed_border_color', 'open_border_color', 'closed_border_width', 'open_border_width', 'closed_border_style', 'open_border_style', 'closed_label_background', 'open_label_background', 'closed_label_border_color', 'open_label_border_color', 'closed_label_border_width', 'open_label_border_width', 'closed_label_width_mode', 'open_label_width_mode', 'closed_label_width', 'open_label_width', 'closed_label_padding', 'open_label_padding', 'shape', 'open_effect', 'closed_image_id', 'open_image_id', 'closed_image_overlay', 'open_image_overlay', 'quiz_enabled', 'quiz_questions', 'quiz_success', 'quiz_failure', 'position_desktop', 'position_mobile' );
	}

	private static function document_content_meta() {
		return array( 'content_type', 'media_id', 'download_id', 'download_label', 'button_label', 'button_url', 'product_id', 'coupon_id', 'event_id', 'quiz_enabled', 'quiz_questions', 'quiz_success', 'quiz_failure', 'cta_enabled', 'cta_title', 'cta_text', 'cta_type', 'cta_label', 'cta_url', 'cta_shortcode' );
	}

	private static function door_has_document_content( $door_id ) {
		$door = get_post( $door_id );
		if ( $door && '' !== trim( wp_strip_all_tags( $door->post_content ) ) ) return true;
		foreach ( self::document_content_meta() as $key ) {
			$value = get_post_meta( $door_id, '_ps_' . $key, true );
			if ( ! in_array( $key, array( 'content_type', 'quiz_enabled', 'cta_enabled' ), true ) && ! empty( $value ) ) return true;
			if ( in_array( $key, array( 'quiz_enabled', 'cta_enabled' ), true ) && $value ) return true;
		}
		return false;
	}

	private static function update_settings( $post_id, $settings, $allowed ) {
		if ( ! is_array( $settings ) ) return;
		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! in_array( $key, $allowed, true ) ) continue;
			if ( is_array( $value ) ) $value = map_deep( $value, 'sanitize_text_field' );
			elseif ( false !== strpos( $key, 'url' ) ) $value = esc_url_raw( $value );
			else $value = sanitize_text_field( (string) $value );
			update_post_meta( $post_id, '_ps_' . $key, $value );
		}
	}

	public static function save_calendar( $input ) {
		$id = self::input_id( $input, 'calendar_id' );
		$data = array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE );
		if ( $id ) $data['ID'] = $id;
		$data['post_title'] = sanitize_text_field( (string) ( $input['title'] ?? ( $id ? get_the_title( $id ) : __( 'New calendar', 'panda-seasons' ) ) ) );
		if ( isset( $input['content'] ) ) $data['post_content'] = wp_kses_post( (string) $input['content'] );
		$status = sanitize_key( (string) ( $input['status'] ?? ( $id ? get_post_status( $id ) : 'draft' ) ) );
		$data['post_status'] = in_array( $status, array( 'draft', 'publish', 'private' ), true ) ? $status : 'draft';
		if ( 'publish' === $data['post_status'] && ! current_user_can( 'publish_posts' ) ) $data['post_status'] = 'draft';
		$result = $id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) return $result;
		self::update_settings( $result, $input['settings'] ?? array(), self::allowed_calendar_meta() );
		return self::get_calendar( array( 'calendar_id' => $result ) );
	}

	public static function save_door( $input ) {
		$calendar_id = self::input_id( $input, 'calendar_id' );
		$door_id = self::input_id( $input, 'door_id' );
		$data = array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_parent' => $calendar_id, 'post_status' => 'publish' );
		if ( $door_id ) $data['ID'] = $door_id;
		$data['post_title'] = sanitize_text_field( (string) ( $input['title'] ?? ( $door_id ? get_the_title( $door_id ) : __( 'New door', 'panda-seasons' ) ) ) );
		if ( isset( $input['content'] ) ) $data['post_content'] = wp_kses_post( (string) $input['content'] );
		$result = $door_id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) return $result;
		update_post_meta( $result, '_ps_calendar_id', $calendar_id );
		self::update_settings( $result, $input['settings'] ?? array(), self::allowed_door_meta() );
		return self::door_data( get_post( $result ) );
	}

	public static function duplicate_door( $input ) {
		$door_id = self::input_id( $input, 'door_id' );
		$door = get_post( $door_id );
		$new_id = wp_insert_post( array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_parent' => $door->post_parent, 'post_status' => 'publish', 'post_title' => $door->post_title . ' ' . __( '(copy)', 'panda-seasons' ), 'post_content' => $door->post_content ), true );
		if ( is_wp_error( $new_id ) ) return $new_id;
		foreach ( get_post_meta( $door_id ) as $key => $values ) foreach ( $values as $value ) add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
		return self::door_data( get_post( $new_id ) );
	}

	public static function delete_door( $input ) {
		$id = self::input_id( $input, 'door_id' );
		$result = wp_trash_post( $id );
		return array( 'door_id' => $id, 'trashed' => (bool) $result );
	}

	public static function export_template( $input ) {
		$data = self::get_calendar( $input );
		return array( 'format' => 'panda-seasons-template', 'format_version' => 1, 'exported_at' => gmdate( 'c' ), 'calendar' => $data );
	}

	private static function layout_position( $position ) {
		$x = min( 99, max( 0, (float) ( $position['x'] ?? 0 ) ) );
		$y = min( 99, max( 0, (float) ( $position['y'] ?? 0 ) ) );
		$w = min( 100 - $x, max( 1, (float) ( $position['w'] ?? 10 ) ) );
		$h = min( 100 - $y, max( 1, (float) ( $position['h'] ?? 10 ) ) );
		return array( 'x' => round( $x, 3 ), 'y' => round( $y, 3 ), 'w' => round( $w, 3 ), 'h' => round( $h, 3 ) );
	}

	private static function mobile_positions( $doors ) {
		$result = array();
		$count = max( 1, count( $doors ) );
		$columns = 3;
		$rows = (int) ceil( $count / $columns );
		$gap = 2;
		$width = ( 100 - ( $columns + 1 ) * $gap ) / $columns;
		$height = ( 100 - ( $rows + 1 ) * $gap ) / max( 1, $rows );
		foreach ( array_values( $doors ) as $index => $door ) {
			$result[ absint( $door['number'] ?? ( $index + 1 ) ) ] = array( 'x' => round( $gap + ( $index % $columns ) * ( $width + $gap ), 3 ), 'y' => round( $gap + floor( $index / $columns ) * ( $height + $gap ), 3 ), 'w' => round( $width, 3 ), 'h' => round( $height, 3 ) );
		}
		return $result;
	}

	public static function create_layout_draft( $input ) {
		$title = sanitize_text_field( (string) ( $input['title'] ?? __( 'Layout draft', 'panda-seasons' ) ) );
		$calendar_id = wp_insert_post( array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ), true );
		if ( is_wp_error( $calendar_id ) ) return $calendar_id;
		$background_id = absint( $input['background_media_id'] ?? 0 );
		if ( $background_id && 'attachment' === get_post_type( $background_id ) ) update_post_meta( $calendar_id, '_ps_cover_desktop', $background_id );
		update_post_meta( $calendar_id, '_ps_aspect_ratio', min( 3, max( 0.4, (float) ( $input['desktop_aspect_ratio'] ?? 1.5 ) ) ) );
		update_post_meta( $calendar_id, '_ps_mobile_aspect_ratio', min( 2, max( 0.35, (float) ( $input['mobile_aspect_ratio'] ?? 0.75 ) ) ) );
		$style_map = array(
			'closed_background' => 'default_closed_background', 'open_background' => 'default_open_background',
			'closed_text_color' => 'default_closed_text_color', 'open_text_color' => 'default_open_text_color',
			'closed_border_color' => 'default_closed_border_color', 'open_border_color' => 'default_open_border_color',
			'closed_border_width' => 'default_closed_border_width', 'open_border_width' => 'default_open_border_width',
			'border_style' => 'default_border_style', 'active_border_color' => 'default_active_border_color',
			'label_font' => 'default_label_font', 'label_font_size' => 'default_label_font_size', 'label_font_weight' => 'default_label_font_weight',
			'shape' => 'default_shape', 'open_effect' => 'open_effect', 'effect_speed' => 'effect_speed',
		);
		$calendar_style = array();
		foreach ( (array) ( $input['style'] ?? array() ) as $key => $value ) if ( isset( $style_map[ $key ] ) ) $calendar_style[ $style_map[ $key ] ] = $value;
		self::update_settings( $calendar_id, $calendar_style, self::allowed_calendar_meta() );
		$mobile = array();
		foreach ( (array) ( $input['mobile_doors'] ?? array() ) as $position ) $mobile[ absint( $position['number'] ?? 0 ) ] = self::layout_position( $position );
		if ( ! $mobile ) $mobile = self::mobile_positions( (array) $input['doors'] );
		$created = array();
		foreach ( (array) $input['doors'] as $position ) {
			$number = min( 366, max( 1, absint( $position['number'] ?? 1 ) ) );
			$door_id = wp_insert_post( array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_parent' => $calendar_id, 'post_status' => 'publish', 'post_title' => sprintf( __( 'Door %d', 'panda-seasons' ), $number ) ), true );
			if ( is_wp_error( $door_id ) ) continue;
			update_post_meta( $door_id, '_ps_calendar_id', $calendar_id );
			update_post_meta( $door_id, '_ps_door_number', $number );
			update_post_meta( $door_id, '_ps_position_desktop', self::layout_position( $position ) );
			update_post_meta( $door_id, '_ps_position_mobile', $mobile[ $number ] ?? self::layout_position( $position ) );
			foreach ( $style_map as $style_key => $calendar_key ) {
				if ( ! array_key_exists( $style_key, (array) ( $input['style'] ?? array() ) ) ) continue;
				$door_key = preg_replace( '/^default_/', '', $calendar_key );
				if ( in_array( $door_key, self::allowed_door_meta(), true ) ) self::update_settings( $door_id, array( $door_key => $input['style'][ $style_key ] ), self::allowed_door_meta() );
			}
			$created[] = $door_id;
		}
		return array( 'calendar_id' => $calendar_id, 'status' => 'draft', 'doors_created' => count( $created ), 'door_ids' => $created, 'edit_url' => get_edit_post_link( $calendar_id, 'raw' ), 'shortcode' => '[panda_seasons id="' . $calendar_id . '"]' );
	}

	public static function apply_document_content( $input ) {
		$calendar_id = self::input_id( $input, 'calendar_id' );
		$mode = sanitize_key( (string) ( $input['import_mode'] ?? 'merge' ) );
		if ( ! in_array( $mode, array( 'empty', 'merge', 'overwrite' ), true ) ) $mode = 'merge';
		$existing = array();
		foreach ( get_posts( array( 'post_type' => Panda_Seasons_Plugin::DOOR_POST_TYPE, 'post_parent' => $calendar_id, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1 ) ) as $door ) $existing[ absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ] = $door->ID;
		$updated = array();
		$skipped = array();
		foreach ( (array) ( $input['doors'] ?? array() ) as $item ) {
			$number = min( 366, max( 1, absint( $item['number'] ?? 1 ) ) );
			$door_id = $existing[ $number ] ?? 0;
			if ( ! $door_id && empty( $input['create_missing_doors'] ) ) { $skipped[] = $number; continue; }
			if ( $door_id && 'empty' === $mode && self::door_has_document_content( $door_id ) ) { $skipped[] = $number; continue; }
			if ( $door_id && 'overwrite' === $mode ) {
				foreach ( self::document_content_meta() as $key ) delete_post_meta( $door_id, '_ps_' . $key );
				wp_update_post( array( 'ID' => $door_id, 'post_content' => '' ) );
			}
			$save = array(
				'calendar_id' => $calendar_id,
				'door_id'     => $door_id,
				'settings'    => array_merge( array( 'door_number' => $number ), array_intersect_key( (array) ( $item['settings'] ?? array() ), array_flip( self::document_content_meta() ) ) ),
			);
			if ( array_key_exists( 'title', $item ) ) $save['title'] = $item['title'];
			elseif ( ! $door_id ) $save['title'] = sprintf( __( 'Door %d', 'panda-seasons' ), $number );
			if ( array_key_exists( 'content', $item ) ) $save['content'] = $item['content'];
			$result = self::save_door( $save );
			if ( ! is_wp_error( $result ) ) $updated[] = $number;
		}
		return array( 'calendar_id' => $calendar_id, 'import_mode' => $mode, 'updated_door_numbers' => $updated, 'skipped_door_numbers' => $skipped, 'status' => get_post_status( $calendar_id ) );
	}
}
