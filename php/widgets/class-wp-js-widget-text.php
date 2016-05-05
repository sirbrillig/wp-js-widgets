<?php
/**
 * Class WP_JS_Widget_Text.
 *
 * @package JSWidgets
 */

/**
 * Class WP_JS_Widget_Text
 *
 * @package JSWidgets
 */
class WP_JS_Widget_Text extends WP_JS_Widget {

	/**
	 * Proxied widget.
	 *
	 * @var WP_Widget
	 */
	public $proxied_widget;

	/**
	 * Widget constructor.
	 *
	 * @throws Exception If the `$proxied_widget` is not the expected class.
	 *
	 * @param WP_Widget $proxied_widget Proxied widget.
	 */
	public function __construct( WP_Widget $proxied_widget ) {
		if ( $proxied_widget instanceof WP_JS_Widget ) {
			throw new Exception( 'Do not proxy WP_Customize_Widget instances.' );
		}
		$this->proxied_widget = $proxied_widget;
		parent::__construct( $proxied_widget->id_base, $proxied_widget->name, $proxied_widget->widget_options, $proxied_widget->control_options );
	}

	/**
	 * Enqueue scripts needed for the control.s
	 */
	public function enqueue_control_scripts() {
		wp_enqueue_script( 'customize-widget-text' );
	}

	/**
	 * Get instance schema properties.
	 *
	 * @return array Schema.
	 */
	public function get_instance_schema() {
		$schema = array(
			'title' => array(
				'description' => __( 'The title for the widget.', 'js-widgets' ),
				'type' => 'string',
				'context' => array( 'embed', 'view', 'edit' ),
				'required' => true,
				'arg_options' => array(
					'validate_callback' => array( $this, 'validate_title_field' ),
				),
			),
			'text' => array(
				'description' => __( 'The content for the object.', 'js-widgets' ),
				'type' => 'string',
				'context' => array( 'embed', 'view', 'edit' ),
				'required' => true,
				'arg_options' => array(
					'validate_callback' => array( $this, 'validate_text_field' ),
				),
			),
			'filter' => array(
				'description' => __( 'Whether paragraphs will be added for double line breaks (wpautop).', 'js-widgets' ),
				'type' => 'boolean',
				'context' => array( 'embed', 'view', 'edit' ),
				'arg_options' => array(
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
		);
		return $schema;
	}

	/**
	 * Get rest fields for registering additional rendered dynamic fields.
	 *
	 * @inheritdoc
	 * @return array
	 */
	public function get_rendered_rest_fields() {
		return array(
			'title_rendered' => array(
				'get_callback' => array( $this, 'get_rendered_title' ),
				'schema' => array(
					'description' => __( 'The rendered title for the object.', 'js-widgets' ),
					'type' => 'string',
				),
			),
			'text_rendered' => array(
				'get_callback' => array( $this, 'get_rendered_text' ),
				'schema' => array(
					'description' => __( 'The rendered text for the object.', 'js-widgets' ),
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Get rendered title.
	 *
	 * @see WP_JS_Widget_Text::get_rendered_rest_fields()
	 * @see WP_Widget_Text::widget()
	 *
	 * @param array $instance Instance data.
	 * @return string
	 */
	public function get_rendered_title( $instance ) {
		$title_rendered = isset( $instance['title'] ) ? $instance['title'] : '';

		/** This filter is documented in src/wp-includes/widgets/class-wp-widget-text.php */
		$title_rendered = apply_filters( 'widget_title', $title_rendered, $instance, $this->id_base );
		return $title_rendered;
	}

	/**
	 * Get rendered text.
	 *
	 * @see WP_Widget_Text::widget()
	 * @see WP_JS_Widget_Text::get_rendered_rest_fields()
	 *
	 * @param array $instance Instance data.
	 * @return string
	 */
	public function get_rendered_text( $instance ) {
		$text_rendered = isset( $instance['text'] ) ? $instance['text'] : '';

		/** This filter is documented in src/wp-includes/widgets/class-wp-widget-text.php */
		$text_rendered = apply_filters( 'widget_text', $text_rendered, $instance, $this->proxied_widget );

		if ( ! empty( $instance['filter'] ) ) {
			$text_rendered = wpautop( $text_rendered );
		}
		return $text_rendered;
	}

	/**
	 * Validate a title request argument based on details registered to the route.
	 *
	 * @param  mixed           $value   Value.
	 * @param  WP_REST_Request $request Request.
	 * @param  string          $param   Param.
	 * @return WP_Error|boolean
	 */
	public function validate_title_field( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $this->should_validate_strictly( $request ) ) {
			if ( preg_match( '#</?\w+.*?>#', $value ) ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s cannot contain markup', 'js-widgets' ), $param ) );
			}
			if ( trim( $value ) !== $value ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s contains whitespace padding', 'js-widgets' ), $param ) );
			}
			if ( preg_match( '/%[a-f0-9]{2}/i', $value ) ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s contains illegal characters (octets)', 'js-widgets' ), $param ) );
			}
		}
		return true;
	}

	/**
	 * Validate a text request argument based on details registered to the route.
	 *
	 * @param  mixed           $value   Value.
	 * @param  WP_REST_Request $request Request.
	 * @param  string          $param   Param.
	 * @return WP_Error|boolean
	 */
	public function validate_text_field( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $this->should_validate_strictly( $request ) ) {
			if ( ! current_user_can( 'unfiltered_html' ) && wp_kses_post( $value ) !== $value ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s contains illegal markup', 'js-widgets' ), $param ) );
			}
		}
		return true;
	}

	/**
	 * Sanitize instance data.
	 *
	 * @inheritdoc
	 *
	 * @param array $new_instance New instance.
	 * @param array $args {
	 *     Additional context for sanitization.
	 *
	 *     @type array $old_instance Old instance.
	 *     @type WP_Customize_Setting $setting Setting.
	 *     @type bool $strict Validate. @todo REMOVE.
	 * }
	 *
	 * @return array|null|WP_Error Array instance if sanitization (and validation) passed. Returns `WP_Error` on failure if `$strict`, and `null` otherwise.
	 */
	public function sanitize( $new_instance, $args = array() ) {
		$instance = $this->proxied_widget->update( $new_instance, $args['old_instance'] );
		return $instance;
	}

	/**
	 * Render JS Template.
	 *
	 * This template is intended to be agnostic to the JS template technology used.
	 */
	public function form_template() {
		?>
		<script id="tmpl-customize-widget-<?php echo esc_attr( $this->id_base ) ?>" type="text/template">
			<p>
				<label>
					<?php esc_html_e( 'Title:', 'js-widgets' ) ?>
					<input class="widefat" type="text" name="title" value="{{ data.title }}">
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Content:', 'js-widgets' ) ?>
					<textarea class="widefat" rows="16" cols="20" name="text">{{ data.text }}</textarea>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="filter" <# if ( data.filter ) { #> checked <# } #> >
					<?php esc_html_e( 'Automatically add paragraphs', 'js-widgets' ); ?>
				</label>
			</p>
		</script>
		<?php
	}

	/**
	 * Render widget.
	 *
	 * @param array $args     Widget args.
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function render( $args, $instance ) {
		 $this->proxied_widget->widget( $args, $instance );
	}

	/**
	 * Get configuration data for the form.
	 *
	 * This can include information such as whether the user can do `unfiltered_html`.
	 *
	 * @return array
	 */
	public function get_form_args() {
		return array(
			'can_unfiltered_html' => current_user_can( 'unfiltered_html' ),
			'l10n' => array(
				'title_tags_invalid' => __( 'Tags will be stripped from the title.', 'js-widgets' ),
				'text_unfiltered_html_invalid' => __( 'Protected HTML such as script tags will be stripped from the content.', 'js-widgets' ),
			),
		);
	}
}
