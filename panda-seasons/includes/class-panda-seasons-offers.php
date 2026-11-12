<?php

defined( 'ABSPATH' ) || exit;

/** Door-owned coupons use WooCommerce CRUD and its ordinary cart validation. */
final class Panda_Seasons_Offers {
	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'cart_action' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon' ), 10, 3 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'validate_product' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'variation_fields' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'apply_after_product_selection' ), 20, 6 );
		add_action( 'woocommerce_checkout_create_order_coupon_item', array( __CLASS__, 'attribute_coupon' ), 10, 4 );
	}

	public static function mode( $door_id ) {
		$mode = get_post_meta( $door_id, '_ps_discount_mode', true );
		return $mode ?: ( get_post_meta( $door_id, '_ps_coupon_id', true ) ? 'existing' : 'none' );
	}

	/** Inclusive local dates become an exclusive next-midnight upper bound. */
	public static function window( $start, $end, $start_time = '00:00', $end_time = '23:59' ) {
		$a = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $start, wp_timezone() );
		$b = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $end, wp_timezone() );
		if ( ! $a || ! $b || $a->format( 'Y-m-d' ) !== $start || $b->format( 'Y-m-d' ) !== $end || $a > $b ) return false;
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start_time ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end_time ) ) return false;
		list( $ah, $am ) = array_map( 'intval', explode( ':', $start_time ) );
		list( $bh, $bm ) = array_map( 'intval', explode( ':', $end_time ) );
		$a = $a->setTime( $ah, $am ); $b = $b->setTime( $bh, $bm, 59 );
		if ( $a > $b ) return false;
		return array( $a->getTimestamp(), $b->getTimestamp() + 1 );
	}

	public static function door_window( $door_id ) {
		$date = (string) get_post_meta( $door_id, '_ps_unlock_date', true );
		return self::window( (string) get_post_meta( $door_id, '_ps_discount_start', true ) ?: $date, (string) get_post_meta( $door_id, '_ps_discount_end', true ) ?: $date, (string) get_post_meta( $door_id, '_ps_discount_start_time', true ) ?: '00:00', (string) get_post_meta( $door_id, '_ps_discount_end_time', true ) ?: '23:59' );
	}

	public static function editor_fields( $coupons ) {
		?>
		<label class="is-wide"><span><?php esc_html_e( 'Discount', 'panda-seasons' ); ?></span><select data-door-field="discountMode"><option value="none"><?php esc_html_e( 'No discount', 'panda-seasons' ); ?></option><option value="existing"><?php esc_html_e( 'Use existing coupon', 'panda-seasons' ); ?></option><option value="new"><?php esc_html_e( 'Create daily coupon', 'panda-seasons' ); ?></option></select></label>
		<label class="is-wide" data-discount-existing><span><?php esc_html_e( 'WooCommerce coupon', 'panda-seasons' ); ?></span><select data-door-field="couponId"><option value=""><?php esc_html_e( 'No coupon', 'panda-seasons' ); ?></option><?php foreach ( $coupons as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></label>
		<label data-discount-new><span><?php esc_html_e( 'Discount type', 'panda-seasons' ); ?></span><select data-door-field="discountType"><option value="percent"><?php esc_html_e( 'Percent', 'panda-seasons' ); ?></option><option value="fixed_product"><?php esc_html_e( 'Amount per product', 'panda-seasons' ); ?></option></select></label>
		<label data-discount-new><span><?php esc_html_e( 'Discount value', 'panda-seasons' ); ?></span><input type="number" min="0.01" step="0.01" data-door-field="discountAmount"></label>
		<label data-discount-new><span><?php esc_html_e( 'Valid from (optional)', 'panda-seasons' ); ?></span><input type="datetime-local" data-discount-datetime="Start" step="60"></label>
		<label data-discount-new><span><?php esc_html_e( 'Valid through (optional)', 'panda-seasons' ); ?></span><input type="datetime-local" data-discount-datetime="End" step="60"></label>
		<input type="hidden" data-door-field="discountStart"><input type="hidden" data-door-field="discountEnd"><input type="hidden" data-door-field="discountStartTime" value="00:00"><input type="hidden" data-door-field="discountEndTime" value="23:59">
		<p class="description is-wide" data-discount-new><?php echo esc_html( sprintf( __( 'Empty dates use the door opening day. Time zone: %s. Saving updates the same generated coupon. Existing coupons keep their own WooCommerce rules.', 'panda-seasons' ), wp_timezone_string() ) ); ?></p>
		<?php
	}

	public static function save( $door_id, $item ) {
		// Older API clients must not accidentally replace a managed discount.
		if ( ! array_key_exists( 'discountMode', $item ) ) {
			if ( 'new' !== self::mode( $door_id ) && isset( $item['couponId'] ) ) {
				$id = absint( $item['couponId'] );
				update_post_meta( $door_id, '_ps_coupon_id', 'shop_coupon' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0 );
			}
			return;
		}
		$mode = in_array( $item['discountMode'], array( 'none', 'existing', 'new' ), true ) ? $item['discountMode'] : 'none';
		if ( 'new' === $mode && ! current_user_can( 'manage_woocommerce' ) ) {
			update_post_meta( $door_id, '_ps_discount_error', __( 'WooCommerce management permission is required to change discounts.', 'panda-seasons' ) );
			return;
		}
		update_post_meta( $door_id, '_ps_discount_mode', $mode );
		delete_post_meta( $door_id, '_ps_discount_error' );
		if ( 'none' === $mode ) { update_post_meta( $door_id, '_ps_coupon_id', 0 ); return; }
		if ( 'existing' === $mode ) {
			$id = absint( $item['couponId'] ?? 0 );
			$coupon = class_exists( 'WC_Coupon' ) ? new WC_Coupon( $id ) : null;
			$product_id = absint( get_post_meta( $door_id, '_ps_product_id', true ) );
			$usable = $coupon && $coupon->get_id() && 'publish' === get_post_status( $id ) && ! $coupon->get_meta( '_ps_door_id' ) && self::product_coupon( $coupon, $product_id );
			update_post_meta( $door_id, '_ps_coupon_id', $usable ? $id : 0 );
			if ( ! $usable ) update_post_meta( $door_id, '_ps_discount_error', __( 'Choose an existing product coupon restricted to this product only. Generated calendar coupons belong to their original door.', 'panda-seasons' ) );
			return; // Never edit a third-party coupon.
		}
		$type = 'fixed_product' === ( $item['discountType'] ?? '' ) ? 'fixed_product' : 'percent';
		$amount = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $item['discountAmount'] ?? '' ) : 0;
		update_post_meta( $door_id, '_ps_discount_type', $type );
		update_post_meta( $door_id, '_ps_discount_amount', $amount );
		foreach ( array( 'Start', 'End' ) as $part ) update_post_meta( $door_id, '_ps_discount_' . strtolower( $part ), sanitize_text_field( (string) ( $item[ 'discount' . $part ] ?? '' ) ) );
		foreach ( array( 'Start' => '00:00', 'End' => '23:59' ) as $part => $default ) update_post_meta( $door_id, '_ps_discount_' . strtolower( $part ) . '_time', sanitize_text_field( (string) ( $item[ 'discount' . $part . 'Time' ] ?? $default ) ) );
		$product_id = absint( get_post_meta( $door_id, '_ps_product_id', true ) );
		$window = self::door_window( $door_id );
		if ( ! class_exists( 'WC_Coupon' ) || ! $product_id || ! $window || (float) $amount <= 0 || ( 'percent' === $type && (float) $amount > 100 ) ) {
			update_post_meta( $door_id, '_ps_coupon_id', 0 );
			update_post_meta( $door_id, '_ps_discount_error', __( 'Check the product, discount value and valid dates. No discount has been activated.', 'panda-seasons' ) );
			return;
		}
		$lock = 'ps_coupon_lock_' . $door_id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			update_post_meta( $door_id, '_ps_discount_error', __( 'Another save is updating this coupon. Please save again.', 'panda-seasons' ) );
			return;
		}
		try {
			$id = absint( get_post_meta( $door_id, '_ps_generated_coupon_id', true ) );
			$coupon = new WC_Coupon( $id );
			if ( $id && ( ! $coupon->get_id() || absint( $coupon->get_meta( '_ps_door_id' ) ) !== $door_id ) ) throw new RuntimeException( __( 'The generated coupon assignment is invalid. Please check it in WooCommerce.', 'panda-seasons' ) );
			if ( ! $id ) $coupon->set_code( 'ps-' . $door_id . '-' . strtolower( wp_generate_password( 12, false ) ) );
			$coupon->set_discount_type( $type );
			$coupon->set_amount( $amount );
			$coupon->set_product_ids( array( $product_id ) );
			$coupon->set_date_expires( $window[1] - 1 );
			$coupon->update_meta_data( '_ps_door_id', $door_id );
			$coupon->update_meta_data( '_ps_offer_calendar_id', absint( wp_get_post_parent_id( $door_id ) ) );
			$id = $coupon->save();
			if ( ! $id ) throw new RuntimeException( __( 'The coupon could not be saved.', 'panda-seasons' ) );
			update_post_meta( $door_id, '_ps_generated_coupon_id', $id );
			update_post_meta( $door_id, '_ps_coupon_id', $id );
		} catch ( Throwable $error ) {
			update_post_meta( $door_id, '_ps_coupon_id', 0 );
			update_post_meta( $door_id, '_ps_discount_error', $error->getMessage() );
		} finally {
			delete_option( $lock );
		}
	}

	public static function errors( $door_ids ) {
		$errors = array();
		foreach ( $door_ids as $id ) {
			$error = get_post_meta( $id, '_ps_discount_error', true );
			if ( $error ) $errors[] = sprintf( __( 'Door %1$d: %2$s', 'panda-seasons' ), absint( get_post_meta( $id, '_ps_door_number', true ) ), $error );
		}
		return $errors;
	}

	public static function discount_label( $id ) {
		if ( 'none' === self::mode( $id ) || ! class_exists( 'WC_Coupon' ) ) return '';
		$coupon = new WC_Coupon( absint( get_post_meta( $id, '_ps_coupon_id', true ) ) );
		if ( ! $coupon->get_id() ) return '';
		return $coupon->is_type( 'percent' ) ? wc_format_localized_decimal( $coupon->get_amount() ) . ' %' : html_entity_decode( wp_strip_all_tags( wc_price( $coupon->get_amount() ) ), ENT_QUOTES, 'UTF-8' );
	}

	public static function tokens( $id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( get_post_meta( $id, '_ps_product_id', true ) ) ) : false;
		$date = (string) get_post_meta( $id, '_ps_unlock_date', true );
		$window = self::window( $date, $date );
		return array( '{date}' => $window ? wp_date( 'j. F', $window[0], wp_timezone() ) : '', '{discount}' => self::discount_label( $id ), '{product}' => $product ? $product->get_name() : '' );
	}

	public static function heading( $id ) {
		$heading = (string) get_post_meta( $id, '_ps_content_heading', true );
		return strtr( $heading ?: get_the_title( $id ), self::tokens( $id ) );
	}

	public static function suggest_defaults( $id ) {
		if ( 'product' !== get_post_meta( $id, '_ps_content_type', true ) || get_post_meta( $id, '_ps_offer_heading_initialized', true ) ) return;
		$tokens = self::tokens( $id );
		if ( ! $tokens['{product}'] || ! $tokens['{date}'] ) return;
		$pattern = $tokens['{discount}'] ? __( '{date} – Today with {discount} discount: {product}', 'panda-seasons' ) : __( '{date} – Today: {product}', 'panda-seasons' );
		foreach ( array( '_ps_content_heading', '_ps_email_heading' ) as $key ) if ( '' === (string) get_post_meta( $id, $key, true ) ) update_post_meta( $id, $key, strtr( $pattern, $tokens ) );
		// A calendar-wide custom subject has priority over a new product suggestion.
		if ( '' === (string) get_post_meta( $id, '_ps_email_subject', true ) && ! get_post_meta( wp_get_post_parent_id( $id ), '_ps_email_subject', true ) ) update_post_meta( $id, '_ps_email_subject', strtr( $pattern, $tokens ) );
		update_post_meta( $id, '_ps_offer_heading_initialized', 1 );
	}

	public static function product_markup( $id, $product, $context ) {
		$image = $product->get_image( 'woocommerce_thumbnail', array( 'style' => 'width:160px;max-width:100%;height:auto;display:block;margin:0 auto 14px', 'width' => 160 ) );
		$html = '<aside class="panda-seasons__product" style="display:block"><div class="panda-seasons__product-image">' . $image . '</div><div><strong>' . esc_html( $product->get_name() ) . '</strong><div class="panda-seasons__product-description">' . wp_kses_post( wpautop( $product->get_short_description() ) ) . '</div><p>' . wp_kses_post( $product->get_price_html() ) . '</p>';
		$discount = self::discount_label( $id );
		if ( $discount ) {
			$html .= '<div class="panda-seasons__offer-notice" style="padding:12px;margin:12px 0;border:1px solid #dcdcde;border-radius:6px"><strong>' . esc_html( sprintf( __( '%s discount', 'panda-seasons' ), $discount ) ) . '</strong>';
			if ( 'new' === self::mode( $id ) ) {
				$window = self::door_window( $id );
				if ( $window ) {
					$html .= '<p>' . esc_html( sprintf( __( 'Valid from %1$s until %2$s (%3$s)', 'panda-seasons' ), wp_date( 'd.m.Y H:i', $window[0], wp_timezone() ), wp_date( 'd.m.Y H:i', $window[1] - 1, wp_timezone() ), wp_timezone_string() ) ) . '</p>';
					if ( 'web' === $context && ( time() < $window[0] || time() >= $window[1] ) ) $html .= '<p>' . esc_html__( 'This discount is not currently available.', 'panda-seasons' ) . '</p>';
				}
			} else {
				$coupon = new WC_Coupon( absint( get_post_meta( $id, '_ps_coupon_id', true ) ) );
				$html .= '<p>' . esc_html__( 'The WooCommerce coupon conditions apply.', 'panda-seasons' ) . '</p>';
				if ( $coupon->get_date_expires() ) $html .= '<p>' . esc_html( sprintf( __( 'Valid until %s', 'panda-seasons' ), wp_date( 'd.m.Y H:i', $coupon->get_date_expires()->getTimestamp(), wp_timezone() ) ) ) . '</p>';
			}
			$html .= '</div>';
		}
		return $html . self::product_action( $id, $product, $context ) . '</div></aside>';
	}

	public static function live_door( $id ) {
		$calendar = absint( wp_get_post_parent_id( $id ) );
		return 'panda_door' === get_post_type( $id ) && 'panda_calendar' === get_post_type( $calendar ) && 'publish' === get_post_status( $id ) && 'publish' === get_post_status( $calendar ) && 'product' === get_post_meta( $id, '_ps_content_type', true ) && Panda_Seasons_Plugin::door_is_available( $id );
	}

	public static function product_coupon( $coupon, $product_id ) {
		return $coupon->is_type( array( 'percent', 'fixed_product' ) ) && array( absint( $product_id ) ) === array_values( array_map( 'absint', $coupon->get_product_ids() ) );
	}

	public static function validate_coupon( $valid, $coupon, $discounts = null ) {
		$id = absint( $coupon->get_meta( '_ps_door_id' ) );
		if ( ! $id ) return $valid;
		$window = self::door_window( $id );
		// Real server time only. No preview parameter is read here.
		$now = time();
		return $valid && 'publish' === get_post_status( $coupon->get_id() ) && self::live_door( $id ) && 'new' === self::mode( $id ) && ! get_post_meta( $id, '_ps_discount_error', true ) && absint( get_post_meta( $id, '_ps_coupon_id', true ) ) === $coupon->get_id() && $window && $now >= $window[0] && $now < $window[1];
	}

	public static function validate_product( $valid, $product, $coupon, $values = array() ) {
		$id = absint( $coupon->get_meta( '_ps_door_id' ) );
		if ( ! $id ) return $valid;
		$target = absint( get_post_meta( $id, '_ps_product_id', true ) );
		return $valid && self::validate_coupon( true, $coupon ) && in_array( $target, array( $product->get_id(), $product->get_parent_id() ), true );
	}

	public static function check_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( $coupon->get_meta( '_ps_door_id' ) && ! self::validate_coupon( true, $coupon ) ) {
				WC()->cart->remove_coupon( $code );
				wc_add_notice( __( 'The calendar discount is no longer valid. Please check your cart.', 'panda-seasons' ), 'error' );
			}
		}
	}

	public static function product_action( $door_id, $product, $context ) {
		if ( 'preview' === $context ) return '<span>' . esc_html__( 'Preview – cart actions are disabled.', 'panda-seasons' ) . '</span>';
		if ( 'web' !== $context ) return '<a class="contactpanda-email-button" href="' . esc_url( add_query_arg( 'ps_offer_view', $door_id, home_url( '/' ) ) ) . '" style="display:inline-block;padding:12px 20px;background:#5c5c5c;color:#fff;text-decoration:none;border-radius:6px">' . esc_html__( 'Buy now', 'panda-seasons' ) . '</a>';
		if ( ! self::live_door( $door_id ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) return '';
		$mode = self::mode( $door_id );
		$label = __( 'Buy now', 'panda-seasons' );
		if ( 'none' !== $mode ) {
			$coupon = new WC_Coupon( absint( get_post_meta( $door_id, '_ps_coupon_id', true ) ) );
			if ( ! $coupon->get_id() || ! self::validate_coupon( true, $coupon ) || ! self::product_coupon( $coupon, $product->get_id() ) || ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) ) return '<span>' . esc_html__( 'This discount is not currently available.', 'panda-seasons' ) . '</span>';
			$value = $coupon->is_type( 'percent' ) ? wc_format_localized_decimal( $coupon->get_amount() ) . ' %' : wp_strip_all_tags( wc_price( $coupon->get_amount() ) );
		}
		if ( ! $product->is_type( 'simple' ) ) {
			$url = add_query_arg( array( 'ps_offer' => $door_id, 'ps_offer_nonce' => wp_create_nonce( 'ps_offer_' . $door_id ) ), get_permalink( $product->get_id() ) );
			return '<a class="panda-seasons__door-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Buy now', 'panda-seasons' ) . '</a>';
		}
		return '<form method="post" action="' . esc_url( home_url( '/' ) ) . '"><input type="hidden" name="ps_offer" value="' . absint( $door_id ) . '"><input type="hidden" name="ps_offer_nonce" value="' . esc_attr( wp_create_nonce( 'ps_offer_' . $door_id ) ) . '"><button type="submit" class="panda-seasons__door-button" name="ps_offer_add" value="1">' . esc_html( $label ) . '</button></form>';
	}

	private static function request_door( $source ) {
		$id = absint( $source['ps_offer'] ?? 0 );
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $source['ps_offer_nonce'] ?? '' ) ), 'ps_offer_' . $id ) || ! self::live_door( $id ) ) return 0;
		return $id;
	}

	private static function apply( $id, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! self::live_door( $id ) || ! in_array( absint( get_post_meta( $id, '_ps_product_id', true ) ), array( $product->get_id(), $product->get_parent_id() ), true ) ) return false;
		if ( 'none' === self::mode( $id ) ) return true;
		$coupon = new WC_Coupon( absint( get_post_meta( $id, '_ps_coupon_id', true ) ) );
		if ( ! $coupon->get_id() || ! self::product_coupon( $coupon, absint( get_post_meta( $id, '_ps_product_id', true ) ) ) || ! self::validate_coupon( true, $coupon ) || ! $coupon->is_valid_for_product( $product ) ) return false;
		return WC()->cart->has_discount( $coupon->get_code() ) || WC()->cart->apply_coupon( $coupon->get_code() );
	}

	public static function cart_action() {
		if ( isset( $_GET['ps_offer_view'] ) && function_exists( 'wc_get_product' ) ) {
			$id = absint( $_GET['ps_offer_view'] );
			if ( ! self::live_door( $id ) ) wp_die( esc_html__( 'This offer is not currently available.', 'panda-seasons' ), '', array( 'response' => 404 ) );
			$product = wc_get_product( absint( get_post_meta( $id, '_ps_product_id', true ) ) );
			if ( ! $product || ! $product->is_visible() ) wp_die( esc_html__( 'This offer is not currently available.', 'panda-seasons' ), '', array( 'response' => 404 ) );
			nocache_headers(); get_header();
			echo '<main class="panda-seasons-offer-landing" style="max-width:640px;margin:32px auto;padding:24px"><h1>' . esc_html( self::heading( $id ) ) . '</h1>' . self::product_markup( $id, $product, 'web' ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component markup escaped below.
			get_footer(); exit;
		}
		if ( empty( $_POST['ps_offer_add'] ) || ! function_exists( 'WC' ) ) return;
		if ( ! WC()->cart ) wc_load_cart();
		$id = self::request_door( $_POST );
		$product = $id ? wc_get_product( absint( get_post_meta( $id, '_ps_product_id', true ) ) ) : false;
		$key = false;
		try {
			if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() || ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product->get_id(), 1 ) ) throw new RuntimeException();
			// Unique key lets failure roll back only the newly added line.
			$key = WC()->cart->add_to_cart( $product->get_id(), 1, 0, array(), array( 'ps_offer_request' => wp_generate_uuid4() ) );
			if ( ! $key || ! self::apply( $id, $product->get_id() ) ) throw new RuntimeException();
		} catch ( Throwable $error ) {
			if ( $key ) WC()->cart->remove_cart_item( $key );
			wc_add_notice( __( 'The product or discount is not available. Please check the current offer.', 'panda-seasons' ), 'error' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	public static function variation_fields() {
		$id = self::request_door( $_GET );
		if ( ! $id ) return;
		echo '<input type="hidden" name="ps_offer" value="' . absint( $id ) . '"><input type="hidden" name="ps_offer_nonce" value="' . esc_attr( wp_create_nonce( 'ps_offer_' . $id ) ) . '">';
	}

	public static function apply_after_product_selection( $key, $product_id, $quantity, $variation_id, $variation, $data ) {
		if ( empty( $_POST['ps_offer'] ) || isset( $_POST['ps_offer_add'] ) ) return;
		$id = self::request_door( $_POST );
		if ( ! $id || ! self::apply( $id, $variation_id ?: $product_id ) ) {
			// Product selection remains valid; tell the shopper explicitly if the offer expired.
			wc_add_notice( __( 'The calendar discount could not be applied. Please check the price in your cart.', 'panda-seasons' ), 'error' );
		}
	}

	public static function attribute_coupon( $item, $code, $coupon, $order ) {
		$id = absint( $coupon->get_meta( '_ps_door_id' ) );
		if ( ! $id || ! self::validate_coupon( true, $coupon ) ) return;
		$item->add_meta_data( '_ps_door_id', $id, true );
		$item->add_meta_data( '_ps_offer_calendar_id', absint( wp_get_post_parent_id( $id ) ), true );
	}
}
