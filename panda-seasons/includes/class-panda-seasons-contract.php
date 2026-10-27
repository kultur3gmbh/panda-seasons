<?php

defined( 'ABSPATH' ) || exit;

/**
 * Canonical data contract shared by the editor, template transport and MCP.
 *
 * Keeping these lists in one place prevents new fields from being saved by one
 * interface while silently disappearing from another one.
 */
final class Panda_Seasons_Contract {
	const DATA_VERSION     = 2;
	const TEMPLATE_VERSION = 2;

	public static function calendar_meta() {
		return array(
			'cover_desktop', 'cover_mobile', 'cover_desktop_background', 'cover_mobile_background',
			'aspect_ratio', 'mobile_aspect_ratio', 'open_effect', 'effect_speed', 'design_layers',
			'popup_background', 'popup_text_color', 'popup_border_color', 'popup_border_width',
			'popup_radius', 'popup_padding', 'popup_width', 'default_closed_background',
			'default_open_background', 'default_closed_image_overlay', 'default_open_image_overlay',
			'default_closed_text_color', 'default_open_text_color', 'default_closed_border_color',
			'default_open_border_color', 'default_closed_border_width', 'default_open_border_width',
			'default_closed_label_background', 'default_open_label_background',
			'default_closed_label_border_color', 'default_open_label_border_color',
			'default_closed_label_border_width', 'default_open_label_border_width',
			'default_closed_label_shape', 'default_open_label_shape', 'default_closed_label_position',
			'default_open_label_position', 'default_label_width_mode', 'default_label_width',
			'default_label_padding', 'default_active_border_color', 'default_label_font',
			'default_label_font_size', 'default_label_font_weight', 'default_border_style',
			'default_shadow_enabled', 'default_shadow_blur', 'default_shadow_color',
			'default_closed_label_visible', 'default_open_label_visible', 'default_shape',
			'default_opening_mode', 'default_cta_enabled', 'default_cta_title', 'default_cta_text',
			'default_cta_type', 'default_cta_label', 'default_cta_url', 'default_cta_shortcode',
			'email_enabled', 'email_time', 'email_subject', 'newsletter_mode', 'newsletter_title',
			'newsletter_link_label', 'newsletter_link_url', 'newsletter_shortcode', 'contact_tag',
			'signup_title', 'signup_text', 'signup_consent', 'signup_button_label',
			'signup_active_message', 'signup_pending_message', 'signup_error_message', 'doi_subject',
			'doi_text', 'doi_button_label', 'confirmation_title', 'confirmation_text', 'doi_reminder_count',
			'doi_reminder_delays', 'doi_reminder_subject', 'doi_reminder_text',
		);
	}

	public static function door_meta() {
		return array(
			'door_number', 'unlock_date', 'unlock_time', 'opening_mode', 'content_heading', 'content_type',
			'media_id', 'download_id', 'download_label', 'button_label', 'button_url', 'product_id',
			'coupon_id', 'event_id', 'email_subject', 'email_content', 'cta_mode', 'cta_enabled',
			'cta_title', 'cta_text', 'cta_type', 'cta_label', 'cta_url', 'cta_shortcode', 'closed_label',
			'open_label', 'closed_background', 'open_background', 'closed_color', 'open_color',
			'closed_label_visible', 'open_label_visible', 'closed_font', 'open_font', 'closed_font_size',
			'open_font_size', 'closed_font_weight', 'open_font_weight', 'closed_text_align',
			'open_text_align', 'closed_border_color', 'open_border_color', 'closed_border_width',
			'open_border_width', 'closed_border_style', 'open_border_style', 'closed_label_background',
			'open_label_background', 'closed_label_border_color', 'open_label_border_color',
			'closed_label_border_width', 'open_label_border_width', 'closed_label_shape',
			'open_label_shape', 'closed_label_position', 'open_label_position', 'closed_label_width_mode',
			'open_label_width_mode', 'closed_label_width', 'open_label_width', 'closed_label_padding',
			'open_label_padding', 'shape', 'open_effect', 'door_image_id', 'door_image_position',
			'shadow_enabled', 'shadow_blur', 'shadow_color',
			'closed_image_overlay', 'open_image_overlay', 'quiz_enabled', 'quiz_questions',
			'quiz_success', 'quiz_failure', 'position_desktop', 'position_mobile',
		);
	}

	public static function calendar_media() {
		return array( 'cover_desktop', 'cover_mobile' );
	}

	public static function door_media() {
		return array( 'media_id', 'download_id', 'door_image_id' );
	}

	public static function local_relations() {
		return array( 'product_id', 'coupon_id', 'event_id' );
	}

	public static function legacy_door_meta() {
		return array(
			'calendar_id', 'unlock_behavior', 'availability_window', 'quiz_question', 'quiz_answers',
			'quiz_correct', 'closed_opacity', 'open_opacity', 'closed_image_id', 'open_image_id',
		);
	}

	public static function prefixed( $keys ) {
		return array_map(
			static function ( $key ) {
				return '_ps_' . $key;
			},
			$keys
		);
	}
}
