<?php
/**
 * Class WP_JS_Widget_Archives.
 *
 * @package JS_Widgets
 */

/**
 * Class WP_JS_Widget_Archives
 *
 * @package JS_Widgets
 */
class WP_JS_Widget_Archives extends WP_Adapter_JS_Widget {

	/**
	 * WP_JS_Widget_Archives constructor.
	 *
	 * @param JS_Widgets_Plugin  $plugin         Plugin.
	 * @param WP_Widget_Archives $adapted_widget Adapted/wrapped core widget.
	 */
	public function __construct( JS_Widgets_Plugin $plugin, WP_Widget_Archives $adapted_widget ) {
		parent::__construct( $plugin, $adapted_widget );
	}

	/**
	 * Get instance schema properties.
	 *
	 * @return array Schema.
	 */
	public function get_item_schema() {
		$schema = array_merge(
			parent::get_item_schema(),
			array(
				'dropdown' => array(
					'description' => __( 'Display as dropdown', 'default' ),
					'type' => 'boolean',
					'default' => false,
					'context' => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
				'count' => array(
					'description' => __( 'Show post counts', 'default' ),
					'type' => 'boolean',
					'default' => false,
					'context' => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
		return $schema;
	}

	/**
	 * Render JS Template.
	 */
	public function form_template() {
		?>
		<script id="tmpl-customize-widget-form-<?php echo esc_attr( $this->id_base ) ?>" type="text/template">
			<?php
			$this->render_title_form_field();
			$this->render_form_field( array(
				'name' => 'dropdown',
				'label' => __( 'Display as dropdown', 'default' ),
				'type' => 'checkbox',
			) );
			$this->render_form_field( array(
				'name' => 'count',
				'label' => __( 'Show post counts', 'default' ),
				'type' => 'checkbox',
			) );
			?>
		</script>
		<?php
	}
}
