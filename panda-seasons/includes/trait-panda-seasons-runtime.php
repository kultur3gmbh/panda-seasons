<?php

defined( 'ABSPATH' ) || exit;

/**
 * Public calendar rendering, availability and content runtime.
 */
trait Panda_Seasons_Runtime {
	public static function register_frontend_assets() {
		wp_register_style( 'panda-seasons', PANDA_SEASONS_URL . 'assets/calendar.css', array(), PANDA_SEASONS_VERSION );
		wp_register_script( 'panda-seasons', PANDA_SEASONS_URL . 'assets/frontend.js', array(), PANDA_SEASONS_VERSION, true );
		wp_localize_script( 'panda-seasons', 'pandaSeasonsFrontend', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'clickNonce' => wp_create_nonce( 'panda_seasons_track_door_click' ) ) );
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
		return self::door_is_available_at( $door_id, new DateTimeImmutable( 'now', wp_timezone() ) );
	}

	private static function door_is_available_at( $door_id, $now ) {
		$unlock = self::door_unlock_at( $door_id );
		if ( ! $unlock ) return true;
		if ( ! $now instanceof DateTimeInterface ) $now = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $now < $unlock ) return false;
		$opening_mode = (string) get_post_meta( $door_id, '_ps_opening_mode', true ) ?: 'auto-permanent';
		return 'auto-permanent' === $opening_mode || $now->format( 'Y-m-d' ) === $unlock->format( 'Y-m-d' );
	}

	private static function door_media_markup( $door, $context = 'web' ) {
		$door = is_object( $door ) ? $door : get_post( absint( $door ) );
		if ( ! $door || self::DOOR_POST_TYPE !== $door->post_type ) return '';
		$content_type = (string) get_post_meta( $door->ID, '_ps_content_type', true ) ?: 'general';
		$media_id = absint( get_post_meta( $door->ID, '_ps_media_id', true ) );
		$kind = self::attachment_kind( $media_id );
		$url = $media_id ? wp_get_attachment_url( $media_id ) : '';
		if ( ! in_array( $content_type, array( 'general', 'video' ), true ) || ! $media_id || ! $url ) return '';
		if ( 'image' === $kind ) return '<figure class="panda-seasons__door-media">' . wp_get_attachment_image( $media_id, 'large' ) . '</figure>';
		if ( 'web' === $context && 'video' === $kind ) return '<div class="panda-seasons__door-media">' . wp_video_shortcode( array( 'src' => $url ) ) . '</div>';
		if ( 'web' === $context && 'audio' === $kind ) return '<div class="panda-seasons__door-media">' . wp_audio_shortcode( array( 'src' => $url ) ) . '</div>';
		if ( in_array( $kind, array( 'video', 'audio' ), true ) ) return '<p><a href="' . esc_url( $url ) . '">' . esc_html( 'video' === $kind ? __( 'Play video', 'panda-seasons' ) : __( 'Play audio', 'panda-seasons' ) ) . '</a></p>';
		return '';
	}

	public static function door_content_markup( $door, $context = 'web' ) {
		$door = is_object( $door ) ? $door : get_post( absint( $door ) );
		if ( ! $door || self::DOOR_POST_TYPE !== $door->post_type ) return '';

		$html     = 'web' === $context ? '' : self::door_media_markup( $door, $context );
		$content_type = (string) get_post_meta( $door->ID, '_ps_content_type', true ) ?: 'general';
		if ( ! in_array( $content_type, array( 'general', 'video', 'product', 'quiz' ), true ) ) $content_type = 'general';
		$download_id = absint( get_post_meta( $door->ID, '_ps_download_id', true ) );
		$download_url = $download_id ? wp_get_attachment_url( $download_id ) : '';
		if ( 'general' === $content_type && $download_url ) {
			$label = get_post_meta( $door->ID, '_ps_download_label', true ) ?: get_the_title( $download_id ) ?: __( 'Download file', 'panda-seasons' );
			$html .= '<p class="panda-seasons__door-download"><a href="' . esc_url( $download_url ) . '"' . ( 'web' === $context ? ' download' : '' ) . '>' . esc_html( $label ) . '</a></p>';
		}

		$button_url   = (string) get_post_meta( $door->ID, '_ps_button_url', true );
		$button_label = (string) get_post_meta( $door->ID, '_ps_button_label', true );
		if ( 'general' === $content_type && $button_url && $button_label ) $html .= '<p><a class="panda-seasons__door-button" href="' . esc_url( $button_url ) . '">' . esc_html( $button_label ) . '</a></p>';

		$product_id = absint( get_post_meta( $door->ID, '_ps_product_id', true ) );
		if ( 'product' === $content_type && $product_id && function_exists( 'wc_get_product' ) && ( $product = wc_get_product( $product_id ) ) && $product->is_visible() ) {
			$html .= '<aside class="panda-seasons__product">' . $product->get_image( 'woocommerce_thumbnail' ) . '<div><strong>' . esc_html( $product->get_name() ) . '</strong><p>' . wp_kses_post( $product->get_price_html() ) . '</p><a class="panda-seasons__door-button" href="' . esc_url( get_permalink( $product_id ) ) . '">' . esc_html__( 'View product', 'panda-seasons' ) . '</a></div></aside>';
		}
		$related = array( 'event' => array( absint( get_post_meta( $door->ID, '_ps_event_id', true ) ), __( 'View event', 'panda-seasons' ) ) );
		foreach ( 'general' === $content_type ? $related : array() as $type => $config ) {
			$related_post = $config[0] ? get_post( $config[0] ) : null;
			if ( ! $related_post || 'publish' !== $related_post->post_status ) continue;
			$html .= '<aside class="panda-seasons__related panda-seasons__related--' . esc_attr( $type ) . '"><strong>' . esc_html( $related_post->post_title ) . '</strong><a href="' . esc_url( get_permalink( $related_post ) ) . '">' . esc_html( $config[1] ) . '</a></aside>';
		}
		$coupon_id = absint( get_post_meta( $door->ID, '_ps_coupon_id', true ) );
		if ( 'product' === $content_type && $coupon_id && 'publish' === get_post_status( $coupon_id ) ) $html .= '<p class="panda-seasons__coupon"><strong>' . esc_html__( 'Coupon code:', 'panda-seasons' ) . '</strong> <code>' . esc_html( get_the_title( $coupon_id ) ) . '</code></p>';
		if ( 'quiz' === $content_type && 'web' === $context && get_post_meta( $door->ID, '_ps_quiz_enabled', true ) ) {
			$questions = get_post_meta( $door->ID, '_ps_quiz_questions', true );
			if ( is_array( $questions ) && $questions ) {
				$html .= '<form class="panda-seasons__quiz" data-season-quiz data-success="' . esc_attr( get_post_meta( $door->ID, '_ps_quiz_success', true ) ?: __( 'Correct!', 'panda-seasons' ) ) . '" data-failure="' . esc_attr( get_post_meta( $door->ID, '_ps_quiz_failure', true ) ?: __( 'That is not correct yet.', 'panda-seasons' ) ) . '">';
				foreach ( $questions as $question_index => $question ) {
					$question_answers = array_values( array_filter( (array) ( $question['answers'] ?? array() ), 'strlen' ) );
					$correct = absint( $question['correct'] ?? 0 );
					if ( empty( $question['question'] ) || count( $question_answers ) < 2 || $correct < 1 || $correct > count( $question_answers ) ) continue;
					$html .= '<fieldset data-quiz-question data-correct="' . esc_attr( $correct ) . '"><legend>' . esc_html( $question['question'] ) . '</legend>';
					foreach ( $question_answers as $answer_index => $answer ) $html .= '<label><input type="radio" name="panda_seasons_quiz_' . esc_attr( $question_index ) . '" value="' . esc_attr( $answer_index + 1 ) . '" required> <span>' . esc_html( $answer ) . '</span></label>';
					$html .= '</fieldset>';
				}
				$html .= '<button type="submit">' . esc_html__( 'Check answers', 'panda-seasons' ) . '</button><p class="panda-seasons__quiz-result" data-quiz-result role="status"></p></form>';
			}
		}

		return $html;
	}

	private static function door_cta_markup( $door ) {
		if ( ! $door ) return '';
		$use_defaults = 'default' === (string) get_post_meta( $door->ID, '_ps_cta_mode', true );
		$source_id = $use_defaults ? (int) $door->post_parent : (int) $door->ID;
		$prefix = $use_defaults ? '_ps_default_cta_' : '_ps_cta_';
		if ( ! get_post_meta( $source_id, $prefix . 'enabled', true ) ) return '';
		$cta_type = (string) get_post_meta( $source_id, $prefix . 'type', true );
		$cta_body = '';
		if ( 'shortcode' === $cta_type ) {
			$shortcode = trim( (string) get_post_meta( $source_id, $prefix . 'shortcode', true ) );
			if ( preg_match( '/^\[([A-Za-z0-9_-]+)/', $shortcode, $matches ) && shortcode_exists( $matches[1] ) ) $cta_body = do_shortcode( $shortcode );
		} elseif ( 'contactpanda' === $cta_type && self::contactpanda_newsletter_available() ) {
			$cta_body = self::contact_signup_shortcode( (int) $door->post_parent, (int) $door->ID );
		} else {
			$url = (string) get_post_meta( $source_id, $prefix . 'url', true );
			$label = (string) get_post_meta( $source_id, $prefix . 'label', true );
			if ( $url && $label ) $cta_body = '<a class="panda-seasons__door-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		if ( ! $cta_body ) return '';
		$title = (string) get_post_meta( $source_id, $prefix . 'title', true );
		$text = (string) get_post_meta( $source_id, $prefix . 'text', true );
		return '<aside class="panda-seasons__cta">' . ( $title ? '<h4>' . esc_html( $title ) . '</h4>' : '' ) . ( $text ? '<div class="panda-seasons__cta-text">' . wp_kses_post( wpautop( $text ) ) . '</div>' : '' ) . $cta_body . '</aside>';
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'preview' => 0, 'preview_date' => '', 'preview_time' => '', 'cover_desktop' => 0, 'cover_mobile' => 0, 'cache_bust' => '', 'show_title' => 'yes', 'show_signup' => 'yes', 'frame' => 'no', 'shadow' => 'no', 'margin_top' => '', 'margin_bottom' => '' ), $atts, 'panda_seasons' );
		$id   = absint( $atts['id'] );
		$is_enabled = static function ( $value, $default = false ) {
			$value = strtolower( trim( (string) $value ) );
			if ( '' === $value ) return $default;
			return ! in_array( $value, array( '0', 'false', 'no', 'off' ), true );
		};
		$show_title = $is_enabled( $atts['show_title'], true );
		$show_frame = $is_enabled( $atts['frame'] );
		$show_shadow = $is_enabled( $atts['shadow'] );
		$show_signup = $is_enabled( $atts['show_signup'], true );
		$css_length = static function ( $value ) { $value = trim( (string) $value ); if ( preg_match( '/^-?\d+(?:\.\d+)?$/', $value ) ) $value .= 'px'; return preg_match( '/^-?\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $value ) ? $value : ''; };
		$preview_mode = ! empty( $atts['preview'] ) && current_user_can( 'edit_post', $id );
		$preview_now = null;
		if ( $preview_mode && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $atts['preview_date'] ) ) {
			$preview_time = preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $atts['preview_time'] ) ? (string) $atts['preview_time'] : '12:00';
			$preview_now = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', (string) $atts['preview_date'] . ' ' . $preview_time, wp_timezone() ) ?: null;
		}
		$post = get_post( $id );
		if ( ! $post || self::CALENDAR_POST_TYPE !== $post->post_type || ( 'publish' !== $post->post_status && ! $preview_mode ) ) return '';
		$doors = self::doors( $id );
		if ( ! $doors && ! get_post_meta( $id, '_ps_design_layers', true ) ) return '<p>' . esc_html__( 'This calendar does not have any doors yet.', 'panda-seasons' ) . '</p>';

		wp_enqueue_style( 'panda-seasons' );
		wp_enqueue_script( 'panda-seasons' );
		$ratio        = (float) get_post_meta( $id, '_ps_aspect_ratio', true ) ?: 1.5;
		$mobile_ratio = (float) get_post_meta( $id, '_ps_mobile_aspect_ratio', true ) ?: 0.75;
		$desktop_id   = $preview_mode && absint( $atts['cover_desktop'] ) && 'attachment' === get_post_type( absint( $atts['cover_desktop'] ) ) ? absint( $atts['cover_desktop'] ) : absint( get_post_meta( $id, '_ps_cover_desktop', true ) );
		$mobile_id    = $preview_mode && absint( $atts['cover_mobile'] ) && 'attachment' === get_post_type( absint( $atts['cover_mobile'] ) ) ? absint( $atts['cover_mobile'] ) : absint( get_post_meta( $id, '_ps_cover_mobile', true ) );
		$desktop      = wp_get_attachment_image_url( $desktop_id, 'full' );
		$mobile       = wp_get_attachment_image_url( $mobile_id, 'full' ) ?: $desktop;
		if ( $preview_mode && $atts['cache_bust'] ) {
			if ( $desktop ) $desktop = add_query_arg( 'ps_refresh', rawurlencode( (string) $atts['cache_bust'] ), $desktop );
			if ( $mobile ) $mobile = add_query_arg( 'ps_refresh', rawurlencode( (string) $atts['cache_bust'] ), $mobile );
		}
		$open_effect  = (string) get_post_meta( $id, '_ps_open_effect', true ) ?: 'door-left';
		$effect_speed = (string) get_post_meta( $id, '_ps_effect_speed', true ) ?: 'normal';
		if ( ! in_array( $open_effect, array( 'none', 'door-left', 'door-right', 'page' ), true ) ) $open_effect = 'door-left';
		if ( ! in_array( $effect_speed, array( 'fast', 'normal', 'slow' ), true ) ) $effect_speed = 'normal';
		$style        = '--ps-ratio:' . $ratio . ';--ps-mobile-ratio:' . $mobile_ratio . ';';
		$style       .= '--ps-cover-desktop-bg:' . ( (string) get_post_meta( $id, '_ps_cover_desktop_background', true ) ?: 'rgba(23,32,51,1)' ) . ';--ps-cover-mobile-bg:' . ( (string) get_post_meta( $id, '_ps_cover_mobile_background', true ) ?: 'rgba(23,32,51,1)' ) . ';';
		$active_border = (string) get_post_meta( $id, '_ps_default_active_border_color', true ) ?: 'rgba(255, 193, 7, 1)';
		$default_closed_bg = (string) get_post_meta( $id, '_ps_default_closed_background', true ) ?: 'rgba(99, 99, 99, 0.2)';
		$default_open_bg = (string) get_post_meta( $id, '_ps_default_open_background', true ) ?: 'rgba(51, 79, 232, 1)';
		$style .= '--ps-active-border-color:' . $active_border . ';--ps-popup-bg:' . ( (string) get_post_meta( $id, '_ps_popup_background', true ) ?: 'rgba(255,255,255,1)' ) . ';--ps-popup-color:' . ( (string) get_post_meta( $id, '_ps_popup_text_color', true ) ?: 'rgba(31,41,55,1)' ) . ';--ps-popup-border-color:' . ( (string) get_post_meta( $id, '_ps_popup_border_color', true ) ?: 'rgba(216,221,231,1)' ) . ';--ps-popup-border-width:' . min( 20, absint( get_post_meta( $id, '_ps_popup_border_width', true ) ) ) . 'px;--ps-popup-radius:' . min( 80, absint( get_post_meta( $id, '_ps_popup_radius', true ) ) ?: 16 ) . 'px;--ps-popup-padding:' . min( 100, absint( get_post_meta( $id, '_ps_popup_padding', true ) ) ?: 32 ) . 'px;--ps-popup-width:' . min( 1600, max( 320, absint( get_post_meta( $id, '_ps_popup_width', true ) ) ?: 720 ) ) . 'px;';
		$style .= '--ps-cover-desktop:' . ( $desktop ? 'url("' . esc_url_raw( $desktop ) . '")' : 'none' ) . ';';
		$style .= '--ps-cover-mobile:' . ( $mobile ? 'url("' . esc_url_raw( $mobile ) . '")' : 'none' ) . ';';
		if ( $css_length( $atts['margin_top'] ) ) $style .= 'margin-top:' . $css_length( $atts['margin_top'] ) . ';';
		if ( $css_length( $atts['margin_bottom'] ) ) $style .= 'margin-bottom:' . $css_length( $atts['margin_bottom'] ) . ';';

		ob_start();
		?>
		<section class="panda-seasons panda-seasons--speed-<?php echo esc_attr( $effect_speed ); ?><?php echo $preview_mode ? ' is-preview' : ''; ?><?php echo $show_frame ? ' panda-seasons--has-frame' : ''; ?><?php echo $show_shadow ? ' panda-seasons--has-shadow' : ''; ?>" id="panda-seasons-<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $style ); ?>" data-calendar="<?php echo esc_attr( $id ); ?>" data-open-effect="<?php echo esc_attr( $open_effect ); ?>">
			<?php if ( $preview_mode ) : ?><p class="panda-seasons__preview-notice"><?php echo esc_html( $preview_now ? sprintf( __( 'Preview for %s – external actions are disabled.', 'panda-seasons' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $preview_now->getTimestamp(), wp_timezone() ) ) : __( 'Preview mode – external actions are disabled.', 'panda-seasons' ) ); ?></p><?php endif; ?>
			<?php if ( $show_title || trim( (string) $post->post_content ) ) : ?><header class="panda-seasons__header"><?php if ( $show_title ) : ?><h2><?php echo esc_html( get_the_title( $id ) ); ?></h2><?php endif; ?><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></header><?php endif; ?>
			<div class="panda-seasons__canvas" role="list">
				<?php foreach ( self::sanitize_design_layers( get_post_meta( $id, '_ps_design_layers', true ) ) as $layer ) :
					$layer_image = $layer['imageId'] ? wp_get_attachment_image_url( $layer['imageId'], 'full' ) : '';
					$lp = $layer['desktop']; $lpm = $layer['mobile'];
					$layer_style = '--ps-layer-x:' . $lp['x'] . '%;--ps-layer-y:' . $lp['y'] . '%;--ps-layer-w:' . $lp['w'] . '%;--ps-layer-h:' . $lp['h'] . '%;--ps-layer-mobile-x:' . $lpm['x'] . '%;--ps-layer-mobile-y:' . $lpm['y'] . '%;--ps-layer-mobile-w:' . $lpm['w'] . '%;--ps-layer-mobile-h:' . $lpm['h'] . '%;--ps-layer-bg:' . $layer['background'] . ';--ps-layer-color:' . $layer['color'] . ';--ps-layer-font-size:' . $layer['fontSize'] . 'px;--ps-layer-padding:' . $layer['padding'] . 'px;--ps-layer-z:' . $layer['z'] . ';--ps-layer-radius:' . $layer['radius'] . 'px;--ps-layer-image-position:' . str_replace( '-', ' ', $layer['imagePosition'] ) . ';--ps-layer-image-fit:' . $layer['imageFit'] . ';--ps-layer-shadow:' . ( $layer['shadowEnabled'] ? '0 0 ' . $layer['shadowBlur'] . 'px ' . $layer['shadowColor'] : 'none' ) . ';';
					$layer_content = 'surface' === $layer['type'] && $layer_image ? '<img src="' . esc_url( $layer_image ) . '" alt="">' : ( 'text' === $layer['type'] ? wp_kses_post( $layer['text'] ) : '' );
				?><div class="panda-seasons__layer panda-seasons__layer--<?php echo esc_attr( $layer['type'] ); ?>" style="<?php echo esc_attr( $layer_style ); ?>"><?php if ( $layer['url'] ) : ?><a href="<?php echo esc_url( $layer['url'] ); ?>"><?php echo $layer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php else : ?><?php echo $layer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?></div><?php endforeach; ?>
				<?php foreach ( $doors as $index => $door ) :
					$unlock_at  = self::door_unlock_at( $door->ID );
					$available  = $preview_mode ? self::door_is_available_at( $door->ID, $preview_now ) : self::door_is_available( $door->ID );
					$opening_mode = (string) get_post_meta( $door->ID, '_ps_opening_mode', true ) ?: 'auto-permanent';
					$visually_open = $available && 'manual-day' !== $opening_mode;
					$number     = absint( get_post_meta( $door->ID, '_ps_door_number', true ) ) ?: ( $index + 1 );
					$closed_label = get_post_meta( $door->ID, '_ps_closed_label', true ) ?: $number;
					$open_label   = get_post_meta( $door->ID, '_ps_open_label', true ) ?: $number;
					$desktop_position = self::door_position( $door->ID, 'desktop', $index, count( $doors ) );
					$mobile_position  = self::door_position( $door->ID, 'mobile', $index, count( $doors ) );
					$item_style = '--ps-x:' . $desktop_position['x'] . '%;--ps-y:' . $desktop_position['y'] . '%;--ps-w:' . $desktop_position['w'] . '%;--ps-h:' . $desktop_position['h'] . '%;--ps-mobile-x:' . $mobile_position['x'] . '%;--ps-mobile-y:' . $mobile_position['y'] . '%;--ps-mobile-w:' . $mobile_position['w'] . '%;--ps-mobile-h:' . $mobile_position['h'] . '%;';
					$closed_bg = (string) get_post_meta( $door->ID, '_ps_closed_background', true ) ?: $default_closed_bg;
					$open_bg = (string) get_post_meta( $door->ID, '_ps_open_background', true ) ?: $default_open_bg;
					$closed_color = (string) get_post_meta( $door->ID, '_ps_closed_color', true ) ?: 'rgba(255,255,255,1)';
					$open_color = (string) get_post_meta( $door->ID, '_ps_open_color', true ) ?: 'rgba(255,255,255,1)';
					$item_style .= '--ps-closed-bg:' . $closed_bg . ';--ps-closed-color:' . $closed_color . ';--ps-open-bg:' . $open_bg . ';--ps-open-color:' . $open_color . ';';
					$item_style .= '--ps-door-shadow:' . ( get_post_meta( $door->ID, '_ps_shadow_enabled', true ) ? '0 0 ' . min( 80, absint( get_post_meta( $door->ID, '_ps_shadow_blur', true ) ) ) . 'px ' . ( (string) get_post_meta( $door->ID, '_ps_shadow_color', true ) ?: 'rgba(0,0,0,.25)' ) : 'none' ) . ';';
					foreach ( array( 'closed', 'open' ) as $state ) {
						$font = (string) get_post_meta( $door->ID, '_ps_' . $state . '_font', true );
						if ( ! in_array( $font, array( 'inherit', 'sans-serif', 'serif', 'monospace' ), true ) ) $font = 'inherit';
						$weight = (string) get_post_meta( $door->ID, '_ps_' . $state . '_font_weight', true );
						if ( ! in_array( $weight, array( '400', '600', '700', '800' ), true ) ) $weight = '700';
						$align = (string) get_post_meta( $door->ID, '_ps_' . $state . '_text_align', true );
						if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) $align = 'center';
						$border_style = (string) get_post_meta( $door->ID, '_ps_' . $state . '_border_style', true );
						if ( ! in_array( $border_style, array( 'none', 'solid', 'dashed', 'dotted' ), true ) ) $border_style = 'dashed';
						$border_color = (string) get_post_meta( $door->ID, '_ps_' . $state . '_border_color', true ) ?: 'rgba(255,255,255,.9)';
						$border_width = '' !== get_post_meta( $door->ID, '_ps_' . $state . '_border_width', true ) ? min( 20, absint( get_post_meta( $door->ID, '_ps_' . $state . '_border_width', true ) ) ) : 2;
						$font_size = min( 96, max( 8, absint( get_post_meta( $door->ID, '_ps_' . $state . '_font_size', true ) ) ?: 20 ) );
						$label_visible = '' === get_post_meta( $door->ID, '_ps_' . $state . '_label_visible', true ) || (bool) get_post_meta( $door->ID, '_ps_' . $state . '_label_visible', true );
						$label_bg = (string) get_post_meta( $door->ID, '_ps_' . $state . '_label_background', true ) ?: 'rgba(10,18,30,.76)';
						$label_border_color = (string) get_post_meta( $door->ID, '_ps_' . $state . '_label_border_color', true ) ?: 'rgba(255,255,255,1)';
						$label_border_width = '' !== get_post_meta( $door->ID, '_ps_' . $state . '_label_border_width', true ) ? min( 20, absint( get_post_meta( $door->ID, '_ps_' . $state . '_label_border_width', true ) ) ) : 1;
						$label_width_mode = (string) get_post_meta( $door->ID, '_ps_' . $state . '_label_width_mode', true );
						if ( ! in_array( $label_width_mode, array( 'content', 'px', 'percent' ), true ) ) $label_width_mode = 'content';
						$label_width = min( 'px' === $label_width_mode ? 300 : 100, max( 10, absint( get_post_meta( $door->ID, '_ps_' . $state . '_label_width', true ) ) ?: 60 ) );
						$label_shape = (string) get_post_meta( $door->ID, '_ps_' . $state . '_label_shape', true );
						if ( ! in_array( $label_shape, array( 'rectangle', 'rounded', 'square', 'round' ), true ) ) $label_shape = 'rounded';
						$label_position = self::sanitize_image_position( get_post_meta( $door->ID, '_ps_' . $state . '_label_position', true ) ?: get_post_meta( $id, '_ps_default_' . $state . '_label_position', true ) );
						list( $label_vertical, $label_horizontal ) = array_pad( explode( '-', 'center' === $label_position ? 'center-center' : $label_position, 2 ), 2, 'center' );
						$label_align = array( 'top' => 'start', 'center' => 'center', 'bottom' => 'end' )[ $label_vertical ] ?? 'center';
						$label_justify = array( 'left' => 'start', 'center' => 'center', 'right' => 'end' )[ $label_horizontal ] ?? 'center';
						$equal_sides = in_array( $label_shape, array( 'square', 'round' ), true );
						$label_css_width = 'percent' === $label_width_mode ? $label_width . '%' : ( 'px' === $label_width_mode ? $label_width . 'px' : 'auto' );
						$item_style .= '--ps-' . $state . '-font:' . $font . ';--ps-' . $state . '-font-size:' . $font_size . 'px;--ps-' . $state . '-font-weight:' . $weight . ';--ps-' . $state . '-text-align:' . $align . ';--ps-' . $state . '-border-color:' . $border_color . ';--ps-' . $state . '-border-width:' . $border_width . 'px;--ps-' . $state . '-border-style:' . $border_style . ';--ps-' . $state . '-label-display:' . ( $label_visible ? 'inline-grid' : 'none' ) . ';--ps-' . $state . '-label-bg:' . $label_bg . ';--ps-' . $state . '-label-border-color:' . $label_border_color . ';--ps-' . $state . '-label-border-width:' . $label_border_width . 'px;--ps-' . $state . '-label-width:' . $label_css_width . ';--ps-' . $state . '-label-height:' . ( $equal_sides ? $label_css_width : 'auto' ) . ';--ps-' . $state . '-label-aspect:' . ( $equal_sides ? '1' : 'auto' ) . ';--ps-' . $state . '-label-radius:' . ( 'round' === $label_shape ? '999px' : ( 'rounded' === $label_shape ? '8px' : '0' ) ) . ';--ps-' . $state . '-label-padding:4px;--ps-' . $state . '-label-align:' . $label_align . ';--ps-' . $state . '-label-justify:' . $label_justify . ';';
					}
					$door_image = wp_get_attachment_image_url( absint( get_post_meta( $door->ID, '_ps_door_image_id', true ) ), 'large' );
					if ( $door_image ) {
						$closed_overlay = (string) get_post_meta( $door->ID, '_ps_closed_image_overlay', true ) ?: ( (string) get_post_meta( $id, '_ps_default_closed_image_overlay', true ) ?: 'rgba(0,0,0,0)' );
						$open_overlay = (string) get_post_meta( $door->ID, '_ps_open_image_overlay', true ) ?: ( (string) get_post_meta( $id, '_ps_default_open_image_overlay', true ) ?: 'rgba(0,0,0,0)' );
						$item_style .= '--ps-closed-image:url("' . esc_url_raw( $door_image ) . '");--ps-open-image:url("' . esc_url_raw( $door_image ) . '");--ps-closed-image-overlay:' . $closed_overlay . ';--ps-open-image-overlay:' . $open_overlay . ';--ps-door-image-position:' . str_replace( '-', ' ', self::sanitize_image_position( get_post_meta( $door->ID, '_ps_door_image_position', true ) ) ) . ';';
					}
					$shape = get_post_meta( $door->ID, '_ps_shape', true );
					if ( ! in_array( $shape, array( 'square', 'rounded', 'round' ), true ) ) $shape = 'rounded';
					$door_effect = (string) get_post_meta( $door->ID, '_ps_open_effect', true );
					if ( ! in_array( $door_effect, array( 'none', 'door-left', 'door-right', 'page' ), true ) ) $door_effect = $open_effect;
					?>
					<article class="panda-seasons__door panda-seasons__door--<?php echo esc_attr( $shape ); ?><?php echo $available ? ' is-available' : ' is-locked'; ?><?php echo $visually_open ? ' is-open-visual' : ' is-closed-visual'; ?><?php echo $available && 'manual-day' === $opening_mode ? ' is-current' : ''; ?>" role="listitem" style="<?php echo esc_attr( $item_style ); ?>" id="panda-season-door-<?php echo esc_attr( $door->ID ); ?>" data-door-id="<?php echo esc_attr( $door->ID ); ?>" data-door-effect="<?php echo esc_attr( $door_effect ); ?>">
						<button type="button" class="panda-seasons__door-face" aria-haspopup="dialog" aria-controls="panda-season-content-<?php echo esc_attr( $door->ID ); ?>" <?php disabled( ! $available ); ?>>
							<span class="panda-seasons__door-number" data-closed-label="<?php echo esc_attr( $closed_label ); ?>" data-open-label="<?php echo esc_attr( $open_label ); ?>"><?php echo esc_html( $visually_open ? $open_label : $closed_label ); ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $available ? sprintf( __( 'Open door %d', 'panda-seasons' ), $number ) : sprintf( __( 'Door %1$d opens on %2$s', 'panda-seasons' ), $number, $unlock_at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $unlock_at->getTimestamp(), wp_timezone() ) : '' ) ); ?></span>
						</button>
						<?php
						$content_heading = (string) get_post_meta( $door->ID, '_ps_content_heading', true );
						if ( ! $content_heading ) $content_heading = $door->post_title ?: sprintf( __( 'Door %d', 'panda-seasons' ), $number );
						$release = self::door_unlock_at( $door->ID );
						$content_date = $release ? wp_date( get_option( 'date_format' ), $release->getTimestamp(), wp_timezone() ) : '';
						$media_markup = self::door_media_markup( $door );
						ob_start();
						if ( 'general' === ( (string) get_post_meta( $door->ID, '_ps_content_type', true ) ?: 'general' ) ) echo apply_filters( 'the_content', $door->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard filtered post content.
						echo self::door_content_markup( $door ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped component markup.
						$text_markup = ob_get_clean();
						?>
						<template class="panda-seasons__door-content" id="panda-season-content-<?php echo esc_attr( $door->ID ); ?>"><h3><?php echo esc_html( $content_heading ); ?></h3><?php if ( $content_date ) : ?><p class="panda-seasons__content-date"><?php echo esc_html( $content_date ); ?></p><?php endif; ?><div class="panda-seasons__content-layout<?php echo $media_markup ? ' has-media' : ''; ?>"><?php if ( $media_markup ) : ?><div class="panda-seasons__content-media"><?php echo $media_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped component markup. ?></div><?php endif; ?><div class="panda-seasons__content-text"><?php echo $text_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped component markup. ?></div></div><?php echo self::door_cta_markup( $door ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped component markup. ?></template>
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
			<?php if ( $show_signup && get_post_meta( $id, '_ps_email_enabled', true ) && self::contactpanda_available() ) echo Panda_Seasons_Subscriptions::form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $show_signup ) echo self::newsletter_markup( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated mode-specific markup. ?>
		</section>
		<?php
		$output = ob_get_clean();
		if ( class_exists( 'Panda_Seasons_Integrations' ) && Panda_Seasons_Integrations::is_builder_preview() ) $output .= Panda_Seasons_Integrations::builder_selection_hint( $id );
		return $output;
	}

	public static function preview_page() {
		$id = absint( $_GET['calendar'] ?? 0 );
		check_admin_referer( 'panda_seasons_preview_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || self::CALENDAR_POST_TYPE !== get_post_type( $id ) ) wp_die( esc_html__( 'You are not allowed to preview this calendar.', 'panda-seasons' ) );
		nocache_headers();
		show_admin_bar( false );
		self::register_frontend_assets();
		$content = self::shortcode( array( 'id' => $id, 'preview' => 1, 'preview_date' => sanitize_text_field( wp_unslash( $_GET['preview_date'] ?? '' ) ), 'preview_time' => sanitize_text_field( wp_unslash( $_GET['preview_time'] ?? '' ) ), 'cover_desktop' => absint( $_GET['cover_desktop'] ?? 0 ), 'cover_mobile' => absint( $_GET['cover_mobile'] ?? 0 ), 'cache_bust' => sanitize_text_field( wp_unslash( $_GET['cache_bust'] ?? '' ) ) ) );
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head><body class="panda-seasons-preview-page"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	public static function subscribe_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'margin_top' => '', 'margin_bottom' => '' ), $atts, 'panda_seasons_subscribe' );
		$id = absint( $atts['id'] );
		if ( ! self::contactpanda_available() || ! get_post_meta( $id, '_ps_email_enabled', true ) || self::CALENDAR_POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) return '';
		wp_enqueue_style( 'panda-seasons' );
		$style = '';
		foreach ( array( 'margin_top' => 'margin-top', 'margin_bottom' => 'margin-bottom' ) as $key => $property ) { $value = trim( (string) $atts[ $key ] ); if ( preg_match( '/^-?\d+(?:\.\d+)?$/', $value ) ) $value .= 'px'; if ( preg_match( '/^-?\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $value ) ) $style .= $property . ':' . $value . ';'; }
		return '<div class="panda-seasons-signup-embed"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' . Panda_Seasons_Subscriptions::form( $id ) . '</div>';
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
		} elseif ( self::contactpanda_newsletter_available() ) {
			$content = self::contact_signup_shortcode( $calendar_id );
			if ( false === strpos( $content, '<form' ) ) $content = '';
		}
		if ( ! trim( wp_strip_all_tags( $content ) ) && false === strpos( $content, '<form' ) ) return '';
		return '<section class="panda-seasons__newsletter">' . ( $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' ) . $content . '</section>';
	}
}
