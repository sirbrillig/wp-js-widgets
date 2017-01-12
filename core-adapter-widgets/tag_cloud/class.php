<?php
/**
 * Class WP_JS_Widget_Tag_Cloud.
 *
 * @package JS_Widgets
 */

/**
 * Class WP_JS_Widget_Tag_Cloud
 *
 * @package JS_Widgets
 */
class WP_JS_Widget_Tag_Cloud extends WP_Adapter_JS_Widget {

	/**
	 * Icon name.
	 *
	 * @var string
	 */
	public $icon_name = 'dashicons-tagcloud';

	/**
	 * WP_JS_Widget_Tag_Cloud constructor.
	 *
	 * @param JS_Widgets_Plugin   $plugin         Plugin.
	 * @param WP_Widget_Tag_Cloud $adapted_widget Adapted/wrapped core widget.
	 */
	public function __construct( JS_Widgets_Plugin $plugin, WP_Widget_Tag_Cloud $adapted_widget ) {
		parent::__construct( $plugin, $adapted_widget );
	}

	/**
	 * Get instance schema properties.
	 *
	 * @return array Schema.
	 */
	public function get_item_schema() {
		$taxonomies = get_taxonomies( array( 'show_tagcloud' => true ), 'names' );
		if ( ! get_option( 'link_manager_enabled' ) ) {
			unset( $taxonomies['link_category'] );
		}
		$taxonomies = array_values( $taxonomies );

		$item_schema = array_merge(
			parent::get_item_schema(),
			array(
				'taxonomy' => array(
					'description' => __( 'Taxonomy', 'js-widgets' ),
					'type' => 'string',
					'enum' => $taxonomies,
					'default' => 1 === count( $taxonomies ) ? current( $taxonomies ) : 'post_tag',
					'context' => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'validate_callback' => array( $this, 'validate_taxonomy' ),
					),
				),
				'tag_links' => array(
					'description' => __( 'List of objects containing attributes for tag links generated by wp_generate_tag_cloud().', 'js-widgets' ),
					'type' => 'array',
					'items' => array(
						'type' => 'object',
					),
					'context' => array( 'view', 'edit', 'embed' ),
					'readonly' => true,
					'default' => array(),
				),
			)
		);
		$item_schema['title']['properties']['raw']['default'] = '';
		return $item_schema;
	}

	/**
	 * Validate taxonomy.
	 *
	 * Block a tag cloud widget from being saved if there are no tag cloud taxonomies.
	 * In reality, it would probably be better to just prevent the widget from being
	 * registered entirely.
	 *
	 * @param string          $taxonomy Taxonomy.
	 * @param WP_REST_Request $request  Request.
	 * @param string          $param    Param.
	 * @return true|WP_Error True if taxonomy is valid, or WP_Error.
	 */
	public function validate_taxonomy( $taxonomy, $request, $param ) {
		$validity = rest_validate_request_arg( $taxonomy, $request, $param );
		if ( true !== $validity ) {
			return $validity;
		}
		if ( 0 === count( get_taxonomies( array( 'show_tagcloud' => true ), 'names' ) ) ) {
			return new WP_Error( 'no_tagcloud_taxonomies', __( 'The tag cloud will not be displayed since there are no taxonomies that support the tag cloud widget.', 'default' ) );
		}
		return true;
	}

	/**
	 * Render a widget instance for a REST API response.
	 *
	 * @inheritdoc
	 *
	 * Code adapted from `WP_Widget_Tag_Cloud::widget()` and `wp_tag_cloud()`.
	 *
	 * @see WP_Widget_Tag_Cloud::widget()
	 * @see wp_tag_cloud()
	 *
	 * @param array           $instance Raw database instance.
	 * @param WP_REST_Request $request  REST request.
	 * @return array Widget item.
	 */
	public function prepare_item_for_response( $instance, $request ) {
		if ( empty( $instance['title'] ) ) {
			if ( 'post_tag' === $instance['taxonomy'] ) {
				$instance['title'] = __( 'Tags', 'default' );
			} else {
				$instance['title'] = get_taxonomy( $instance['taxonomy'] )->labels->name;
			}
		}

		$item = parent::prepare_item_for_response( $instance, $request );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-tag-cloud.php */
		$tag_cloud_args = apply_filters( 'widget_tag_cloud_args', array(
			'taxonomy' => $item['taxonomy'],
		) );

		$tag_cloud_args = wp_parse_args( $tag_cloud_args, array(
			'smallest' => 8,
			'largest' => 22,
			'number' => 45,
			'format' => 'flat',
			'orderby' => 'name',
			'order' => 'ASC',
			'exclude' => '',
			'include' => '',
			'link' => 'view',
			'taxonomy' => 'post_tag',
			'post_type' => '',
			'separator' => '',
		) );

		$tags = get_terms(
			$tag_cloud_args['taxonomy'],
			array_merge( $tag_cloud_args, array( 'orderby' => 'count', 'order' => 'DESC' ) ) // Always query top tags.
		);

		// @todo should these not be _links?
		$item['tag_links'] = array();
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $key => $tag ) {
				$link = get_term_link( intval( $tag->term_id ), $tag->taxonomy );
				if ( ! is_wp_error( $link ) ) {
					$tags[ $key ]->link = $link;
					$tags[ $key ]->id = $tag->term_id;
				}
			}
			$tag_cloud = wp_generate_tag_cloud( $tags, $tag_cloud_args );
			if ( ! empty( $tag_cloud ) ) {
				$doc = new DOMDocument();
				$doctype = sprintf( '<!DOCTYPE html><meta charset="%s">', get_bloginfo( 'charset' ) );
				$doc->loadHTML( $doctype . $tag_cloud );
				foreach ( $doc->getElementsByTagName( 'a' ) as $link ) {
					$link_data = array(
						'label' => $link->textContent,
					);
					foreach ( $link->attributes as $attribute ) {
						$link_data[ $attribute->nodeName ] = $attribute->nodeValue;
					}
					$item['tag_links'][] = $link_data;
				}
			}
		}

		return $item;
	}

	/**
	 * Render JS template contents minus the `<script type="text/template">` wrapper.
	 */
	public function render_form_template() {
		$item_schema = $this->get_item_schema();
		$this->render_title_form_field_template( array(
			'placeholder' => $item_schema['title']['properties']['raw']['default'],
		) );
		$taxonomies = get_taxonomies( array( 'show_tagcloud' => true ), 'object' );
		if ( ! get_option( 'link_manager_enabled' ) ) {
			unset( $taxonomies['link_category'] );
		}
		if ( count( $taxonomies ) > 1 ) {
			$taxonomy_choices = array();
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_choices[ $taxonomy->name ] = $taxonomy->label;
			}
			$this->render_form_field_template( array(
				'name' => 'taxonomy',
				'label' => __( 'Taxonomy:', 'default' ),
				'type' => 'select',
				'choices' => $taxonomy_choices,
			) );
		}
	}
}
