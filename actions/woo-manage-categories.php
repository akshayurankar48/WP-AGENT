<?php
/**
 * WooCommerce Manage Categories Action.
 *
 * @package WPAgent\Actions
 * @since   1.1.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_Manage_Categories
 *
 * Handles WooCommerce product category management including list, create, update, and delete.
 *
 * @package WP_Agent
 * @since   1.1.0
 */
class Woo_Manage_Categories implements Action_Interface {

	/**
	 * Get the action identifier.
	 *
	 * @since  1.1.0
	 * @return string Action identifier.
	 */
	public function get_name(): string {
		return 'woo_manage_categories';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage WooCommerce product categories. List, create, update, or delete product categories.';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'operation'   => array(
					'type' => 'string',
					'enum' => array( 'list', 'create', 'update', 'delete' ),
				),
				'category_id' => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent'      => array(
					'type'        => 'integer',
					'description' => 'Parent category ID.',
				),
			),
			'required'   => array( 'operation' ),
		);
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return true;
	}

	/**
	 * Execute the action.
	 *
	 * @since 1.1.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	public function execute( array $params ): array {
		$operation = $params['operation'] ?? '';

		switch ( $operation ) {
			case 'list':
				$terms = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => false,
					)
				);
				$list  = array();
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$list[] = array(
							'id'     => $term->term_id,
							'name'   => $term->name,
							'slug'   => $term->slug,
							'count'  => $term->count,
							'parent' => $term->parent,
						);
					}
				}
				return array(
					'success' => true,
					'data'    => array( 'categories' => $list ),
					/* translators: %d: number of categories */
					'message' => sprintf( __( '%d category(ies).', 'wp-agent' ), count( $list ) ),
				);

			case 'create':
				$name = sanitize_text_field( $params['name'] ?? '' );
				if ( empty( $name ) ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => __( 'name is required.', 'wp-agent' ),
					);
				}
				$args = array();
				if ( isset( $params['slug'] ) ) {
					$args['slug'] = sanitize_title( $params['slug'] );
				}
				if ( isset( $params['description'] ) ) {
					$args['description'] = sanitize_textarea_field( $params['description'] );
				}
				if ( isset( $params['parent'] ) ) {
					$args['parent'] = absint( $params['parent'] );
				}
				$result = wp_insert_term( $name, 'product_cat', $args );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => $result->get_error_message(),
					);
				}
				return array(
					'success' => true,
					'data'    => array( 'category_id' => $result['term_id'] ),
					/* translators: %s: category name */
					'message' => sprintf( __( 'Category "%s" created.', 'wp-agent' ), $name ),
				);

			case 'update':
				$id = absint( $params['category_id'] ?? 0 );
				if ( ! $id ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => __( 'category_id required.', 'wp-agent' ),
					);
				}
				$args = array();
				if ( isset( $params['name'] ) ) {
					$args['name'] = sanitize_text_field( $params['name'] );
				}
				if ( isset( $params['slug'] ) ) {
					$args['slug'] = sanitize_title( $params['slug'] );
				}
				if ( isset( $params['description'] ) ) {
					$args['description'] = sanitize_textarea_field( $params['description'] );
				}
				$result = wp_update_term( $id, 'product_cat', $args );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => $result->get_error_message(),
					);
				}
				return array(
					'success' => true,
					'data'    => array( 'category_id' => $id ),
					/* translators: %d: category ID */
					'message' => sprintf( __( 'Category #%d updated.', 'wp-agent' ), $id ),
				);

			case 'delete':
				$id = absint( $params['category_id'] ?? 0 );
				if ( ! $id ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => __( 'category_id required.', 'wp-agent' ),
					);
				}
				$result = wp_delete_term( $id, 'product_cat' );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'data'    => null,
						'message' => $result->get_error_message(),
					);
				}
				return array(
					'success' => true,
					'data'    => array( 'category_id' => $id ),
					/* translators: %d: category ID */
					'message' => sprintf( __( 'Category #%d deleted.', 'wp-agent' ), $id ),
				);

			default:
				return array(
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation.', 'wp-agent' ),
				);
		}
	}
}
