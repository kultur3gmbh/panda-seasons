<?php

defined( 'ABSPATH' ) || exit;

final class Panda_Seasons_Integrations {
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_block' ), 30 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
		add_action( 'et_builder_ready', array( __CLASS__, 'register_divi_module' ) );
		add_action( 'fusion_builder_before_init', array( __CLASS__, 'register_avada_element' ) );
	}

	private static function calendars() {
		$choices = array( '' => __( 'Select a calendar', 'panda-seasons' ) );
		foreach ( get_posts( array( 'post_type' => Panda_Seasons_Plugin::CALENDAR_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $calendar ) {
			$choices[ (string) $calendar->ID ] = $calendar->post_title ?: sprintf( __( 'Calendar #%d', 'panda-seasons' ), $calendar->ID );
		}
		return $choices;
	}

	public static function builder_selection_hint( $id ) {
		$title = $id ? get_the_title( $id ) : '';
		$text = $title ? sprintf( __( 'Selected calendar: %s', 'panda-seasons' ), $title ) : __( 'No calendar selected', 'panda-seasons' );
		return '<small class="panda-seasons-builder-selection">' . esc_html( $text ) . '</small>';
	}

	public static function is_builder_preview() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) return true;
		if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) return true;
		if ( function_exists( 'fusion_is_builder_frame' ) && fusion_is_builder_frame() ) return true;
		if ( function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame() ) return true;
		return isset( $_GET['et_fb'] ) || isset( $_GET['fb-edit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function render( $attributes ) {
		$id = absint( $attributes['id'] ?? 0 );
		$show_title = ! array_key_exists( 'showTitle', $attributes ) || (bool) $attributes['showTitle'];
		return $id ? Panda_Seasons_Plugin::shortcode( array(
			'id'         => $id,
			'show_title' => $show_title ? 'yes' : 'no',
			'show_signup' => ! array_key_exists( 'showSignup', $attributes ) || $attributes['showSignup'] ? 'yes' : 'no',
			'frame'      => ! empty( $attributes['frame'] ) ? 'yes' : 'no',
			'shadow'     => ! empty( $attributes['shadow'] ) ? 'yes' : 'no',
			'margin_top' => $attributes['marginTop'] ?? '',
			'margin_bottom' => $attributes['marginBottom'] ?? '',
		) ) : '';
	}

	public static function render_signup( $attributes ) {
		$id = absint( $attributes['id'] ?? 0 );
		return $id ? Panda_Seasons_Plugin::subscribe_shortcode( array( 'id' => $id, 'margin_top' => $attributes['marginTop'] ?? '', 'margin_bottom' => $attributes['marginBottom'] ?? '' ) ) : '';
	}

	public static function register_block() {
		wp_register_script( 'panda-seasons-block-editor', PANDA_SEASONS_URL . 'assets/block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), PANDA_SEASONS_VERSION, true );
		$calendars = array();
		foreach ( self::calendars() as $value => $label ) $calendars[] = array( 'value' => $value, 'label' => $label );
		wp_localize_script( 'panda-seasons-block-editor', 'pandaSeasonsBlock', array( 'calendars' => $calendars, 'title' => __( 'Panda Seasons calendar', 'panda-seasons' ), 'signupTitle' => __( 'Panda Seasons calendar signup', 'panda-seasons' ), 'selectLabel' => __( 'Calendar', 'panda-seasons' ), 'selectedLabel' => __( 'Selected calendar: %s', 'panda-seasons' ), 'noneSelected' => __( 'No calendar selected', 'panda-seasons' ), 'showTitle' => __( 'Show calendar title', 'panda-seasons' ), 'showSignup' => __( 'Show calendar signup', 'panda-seasons' ), 'frame' => __( 'Show calendar frame', 'panda-seasons' ), 'shadow' => __( 'Show calendar shadow', 'panda-seasons' ), 'marginTop' => __( 'Margin top', 'panda-seasons' ), 'marginBottom' => __( 'Margin bottom', 'panda-seasons' ) ) );
		register_block_type( 'panda-seasons/calendar', array(
			'api_version'     => 2,
			'editor_script'   => 'panda-seasons-block-editor',
			'attributes'      => array( 'id' => array( 'type' => 'integer', 'default' => 0 ), 'showTitle' => array( 'type' => 'boolean', 'default' => true ), 'showSignup' => array( 'type' => 'boolean', 'default' => true ), 'frame' => array( 'type' => 'boolean', 'default' => false ), 'shadow' => array( 'type' => 'boolean', 'default' => false ), 'marginTop' => array( 'type' => 'string', 'default' => '' ), 'marginBottom' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => array( __CLASS__, 'render' ),
		) );
		register_block_type( 'panda-seasons/signup', array(
			'api_version'     => 2,
			'editor_script'   => 'panda-seasons-block-editor',
			'attributes'      => array( 'id' => array( 'type' => 'integer', 'default' => 0 ), 'marginTop' => array( 'type' => 'string', 'default' => '' ), 'marginBottom' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => array( __CLASS__, 'render_signup' ),
		) );
	}

	public static function register_elementor_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;
		$widgets_manager->register( new class() extends \Elementor\Widget_Base {
			public function get_name() { return 'panda_seasons_calendar'; }
			public function get_title() { return __( 'Panda Seasons calendar', 'panda-seasons' ); }
			public function get_icon() { return 'eicon-calendar'; }
			public function get_categories() { return array( 'general' ); }
			protected function register_controls() {
				$this->start_controls_section( 'calendar_section', array( 'label' => __( 'Calendar', 'panda-seasons' ) ) );
				$this->add_control( 'calendar_id', array( 'label' => __( 'Select calendar', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array_slice( Panda_Seasons_Integrations::elementor_choices(), 1, null, true ) ) );
				$this->add_control( 'show_title', array( 'label' => __( 'Show calendar title', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
				$this->add_control( 'show_signup', array( 'label' => __( 'Show calendar signup', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
				$this->add_control( 'frame', array( 'label' => __( 'Show calendar frame', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
				$this->add_control( 'shadow', array( 'label' => __( 'Show calendar shadow', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
				$this->add_control( 'margin_top', array( 'label' => __( 'Margin top', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => '0px' ) );
				$this->add_control( 'margin_bottom', array( 'label' => __( 'Margin bottom', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => '0px' ) );
				$this->end_controls_section();
			}
			protected function render() {
				$settings = $this->get_settings_for_display();
				echo Panda_Seasons_Integrations::render( array( 'id' => $settings['calendar_id'] ?? 0, 'showTitle' => 'yes' === ( $settings['show_title'] ?? 'yes' ), 'showSignup' => 'yes' === ( $settings['show_signup'] ?? 'yes' ), 'frame' => 'yes' === ( $settings['frame'] ?? '' ), 'shadow' => 'yes' === ( $settings['shadow'] ?? '' ), 'marginTop' => $settings['margin_top'] ?? '', 'marginBottom' => $settings['margin_bottom'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} );
		$widgets_manager->register( new class() extends \Elementor\Widget_Base {
			public function get_name() { return 'panda_seasons_signup'; }
			public function get_title() { return __( 'Panda Seasons calendar signup', 'panda-seasons' ); }
			public function get_icon() { return 'eicon-form-horizontal'; }
			public function get_categories() { return array( 'general' ); }
			protected function register_controls() {
				$this->start_controls_section( 'calendar_section', array( 'label' => __( 'Calendar', 'panda-seasons' ) ) );
				$this->add_control( 'calendar_id', array( 'label' => __( 'Select calendar', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array_slice( Panda_Seasons_Integrations::elementor_choices(), 1, null, true ) ) );
				$this->add_control( 'margin_top', array( 'label' => __( 'Margin top', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => '0px' ) );
				$this->add_control( 'margin_bottom', array( 'label' => __( 'Margin bottom', 'panda-seasons' ), 'type' => \Elementor\Controls_Manager::TEXT, 'placeholder' => '0px' ) );
				$this->end_controls_section();
			}
			protected function render() { $settings = $this->get_settings_for_display(); echo Panda_Seasons_Integrations::render_signup( array( 'id' => $settings['calendar_id'] ?? 0, 'marginTop' => $settings['margin_top'] ?? '', 'marginBottom' => $settings['margin_bottom'] ?? '' ) ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} );
	}

	public static function elementor_choices() { return self::calendars(); }

	public static function register_divi_module() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) return;
		new class() extends ET_Builder_Module {
			public $slug = 'panda_seasons_divi';
			public $vb_support = 'on';
			public function init() { $this->name = __( 'Panda Seasons calendar', 'panda-seasons' ); }
			public function get_fields() { return array( 'calendar_id' => array( 'label' => __( 'Select calendar', 'panda-seasons' ), 'type' => 'select', 'options' => Panda_Seasons_Integrations::elementor_choices(), 'toggle_slug' => 'main_content' ), 'show_title' => array( 'label' => __( 'Show calendar title', 'panda-seasons' ), 'type' => 'yes_no_button', 'options' => array( 'on' => __( 'Yes', 'panda-seasons' ), 'off' => __( 'No', 'panda-seasons' ) ), 'default' => 'on', 'toggle_slug' => 'main_content' ), 'show_signup' => array( 'label' => __( 'Show calendar signup', 'panda-seasons' ), 'type' => 'yes_no_button', 'options' => array( 'on' => __( 'Yes', 'panda-seasons' ), 'off' => __( 'No', 'panda-seasons' ) ), 'default' => 'on', 'toggle_slug' => 'main_content' ), 'frame' => array( 'label' => __( 'Show calendar frame', 'panda-seasons' ), 'type' => 'yes_no_button', 'options' => array( 'on' => __( 'Yes', 'panda-seasons' ), 'off' => __( 'No', 'panda-seasons' ) ), 'default' => 'off', 'toggle_slug' => 'main_content' ), 'shadow' => array( 'label' => __( 'Show calendar shadow', 'panda-seasons' ), 'type' => 'yes_no_button', 'options' => array( 'on' => __( 'Yes', 'panda-seasons' ), 'off' => __( 'No', 'panda-seasons' ) ), 'default' => 'off', 'toggle_slug' => 'main_content' ), 'margin_top' => array( 'label' => __( 'Margin top', 'panda-seasons' ), 'type' => 'text', 'toggle_slug' => 'main_content' ), 'margin_bottom' => array( 'label' => __( 'Margin bottom', 'panda-seasons' ), 'type' => 'text', 'toggle_slug' => 'main_content' ) ); }
			public function render( $attrs, $content = null, $render_slug = '' ) { return Panda_Seasons_Integrations::render( array( 'id' => $this->props['calendar_id'] ?? 0, 'showTitle' => 'off' !== ( $this->props['show_title'] ?? 'on' ), 'showSignup' => 'off' !== ( $this->props['show_signup'] ?? 'on' ), 'frame' => 'on' === ( $this->props['frame'] ?? 'off' ), 'shadow' => 'on' === ( $this->props['shadow'] ?? 'off' ), 'marginTop' => $this->props['margin_top'] ?? '', 'marginBottom' => $this->props['margin_bottom'] ?? '' ) ); }
		};
		new class() extends ET_Builder_Module {
			public $slug = 'panda_seasons_signup_divi';
			public $vb_support = 'on';
			public function init() { $this->name = __( 'Panda Seasons calendar signup', 'panda-seasons' ); }
			public function get_fields() { return array( 'calendar_id' => array( 'label' => __( 'Select calendar', 'panda-seasons' ), 'type' => 'select', 'options' => Panda_Seasons_Integrations::elementor_choices(), 'toggle_slug' => 'main_content' ), 'margin_top' => array( 'label' => __( 'Margin top', 'panda-seasons' ), 'type' => 'text', 'toggle_slug' => 'main_content' ), 'margin_bottom' => array( 'label' => __( 'Margin bottom', 'panda-seasons' ), 'type' => 'text', 'toggle_slug' => 'main_content' ) ); }
			public function render( $attrs, $content = null, $render_slug = '' ) { return Panda_Seasons_Integrations::render_signup( array( 'id' => $this->props['calendar_id'] ?? 0, 'marginTop' => $this->props['margin_top'] ?? '', 'marginBottom' => $this->props['margin_bottom'] ?? '' ) ); }
		};
	}

	public static function register_avada_element() {
		if ( ! function_exists( 'fusion_builder_map' ) ) return;
		fusion_builder_map( array(
			'name'       => __( 'Panda Seasons calendar', 'panda-seasons' ),
			'shortcode'  => 'panda_seasons',
			'icon'       => 'fusiona-calendar',
			'params'     => array( array( 'type' => 'select', 'heading' => __( 'Select calendar', 'panda-seasons' ), 'param_name' => 'id', 'value' => self::calendars() ), array( 'type' => 'radio_button_set', 'heading' => __( 'Show calendar title', 'panda-seasons' ), 'param_name' => 'show_title', 'default' => 'yes', 'value' => array( 'yes' => __( 'Yes', 'panda-seasons' ), 'no' => __( 'No', 'panda-seasons' ) ) ), array( 'type' => 'radio_button_set', 'heading' => __( 'Show calendar signup', 'panda-seasons' ), 'param_name' => 'show_signup', 'default' => 'yes', 'value' => array( 'yes' => __( 'Yes', 'panda-seasons' ), 'no' => __( 'No', 'panda-seasons' ) ) ), array( 'type' => 'radio_button_set', 'heading' => __( 'Show calendar frame', 'panda-seasons' ), 'param_name' => 'frame', 'default' => 'no', 'value' => array( 'yes' => __( 'Yes', 'panda-seasons' ), 'no' => __( 'No', 'panda-seasons' ) ) ), array( 'type' => 'radio_button_set', 'heading' => __( 'Show calendar shadow', 'panda-seasons' ), 'param_name' => 'shadow', 'default' => 'no', 'value' => array( 'yes' => __( 'Yes', 'panda-seasons' ), 'no' => __( 'No', 'panda-seasons' ) ) ), array( 'type' => 'textfield', 'heading' => __( 'Margin top', 'panda-seasons' ), 'param_name' => 'margin_top' ), array( 'type' => 'textfield', 'heading' => __( 'Margin bottom', 'panda-seasons' ), 'param_name' => 'margin_bottom' ) ),
		) );
		fusion_builder_map( array(
			'name'       => __( 'Panda Seasons calendar signup', 'panda-seasons' ),
			'shortcode'  => 'panda_seasons_subscribe',
			'icon'       => 'fusiona-registration',
			'params'     => array( array( 'type' => 'select', 'heading' => __( 'Select calendar', 'panda-seasons' ), 'param_name' => 'id', 'value' => self::calendars() ), array( 'type' => 'textfield', 'heading' => __( 'Margin top', 'panda-seasons' ), 'param_name' => 'margin_top' ), array( 'type' => 'textfield', 'heading' => __( 'Margin bottom', 'panda-seasons' ), 'param_name' => 'margin_bottom' ) ),
		) );
	}
}
