<?php

defined( 'ABSPATH' ) || exit;

/**
 * Exposes Panda Seasons through the WordPress Abilities API and MCP Adapter.
 * WordPress versions before 6.9 simply ignore these hooks.
 */
final class Panda_Seasons_MCP {
	const CATEGORY = 'panda-seasons';

	public static function boot() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
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
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
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
		wp_register_ability( 'panda-seasons/' . $name, $args );
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
		return array( 'cover_desktop', 'cover_mobile', 'aspect_ratio', 'mobile_aspect_ratio', 'open_effect', 'effect_speed', 'default_closed_background', 'default_open_background', 'default_closed_text_color', 'default_open_text_color', 'default_closed_border_color', 'default_open_border_color', 'default_closed_border_width', 'default_open_border_width', 'default_closed_label_background', 'default_open_label_background', 'default_closed_label_border_color', 'default_open_label_border_color', 'default_closed_label_border_width', 'default_open_label_border_width', 'default_label_width_mode', 'default_label_width', 'default_label_padding', 'default_active_border_color', 'default_label_font', 'default_label_font_size', 'default_label_font_weight', 'default_border_style', 'default_closed_label_visible', 'default_open_label_visible', 'default_shape', 'newsletter_mode', 'newsletter_title', 'newsletter_link_label', 'newsletter_link_url', 'newsletter_shortcode' );
	}

	private static function allowed_door_meta() {
		return array( 'door_number', 'unlock_date', 'unlock_time', 'unlock_behavior', 'availability_window', 'media_id', 'download_id', 'download_label', 'button_label', 'button_url', 'product_id', 'coupon_id', 'cta_enabled', 'cta_title', 'cta_text', 'cta_type', 'cta_label', 'cta_url', 'cta_shortcode', 'event_id', 'closed_label', 'open_label', 'closed_background', 'open_background', 'closed_color', 'open_color', 'closed_label_visible', 'open_label_visible', 'closed_font', 'open_font', 'closed_font_size', 'open_font_size', 'closed_font_weight', 'open_font_weight', 'closed_text_align', 'open_text_align', 'closed_border_color', 'open_border_color', 'closed_border_width', 'open_border_width', 'closed_border_style', 'open_border_style', 'closed_label_background', 'open_label_background', 'closed_label_border_color', 'open_label_border_color', 'closed_label_border_width', 'open_label_border_width', 'closed_label_width_mode', 'open_label_width_mode', 'closed_label_width', 'open_label_width', 'closed_label_padding', 'open_label_padding', 'shape', 'open_effect', 'closed_image_id', 'open_image_id', 'quiz_enabled', 'quiz_questions', 'quiz_success', 'quiz_failure', 'position_desktop', 'position_mobile' );
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
}
