<?php
/**
 * WooCommerce Manage Inventory Action.
 *
 * @package WPAgent\Actions
 * @since   1.1.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Woo_Manage_Inventory
 *
 * Handles WooCommerce product inventory management including stock checks, updates, and reports.
 *
 * @package WP_Agent
 * @since   1.1.0
 */
class Woo_Manage_Inventory implements Action_Interface {

	/**
	 * Get the action identifier.
	 *
	 * @since  1.1.0
	 * @return string Action identifier.
	 */
	public function get_name(): string {
		return 'woo_manage_inventory';
	}

	/**
	 * Get the human-readable description.
	 *
	 * @since  1.1.0
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return 'Manage WooCommerce product inventory. Check stock levels, update stock quantities, get low stock reports, or bulk update inventory.';
	}

	/**
	 * Get the JSON Schema definition for action parameters.
	 *
	 * @since  1.1.0
	 * @return array JSON Schema definition for action parameters.
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'operation'  => array(
					'type' => 'string',
					'enum' => array( 'check_stock', 'update_stock', 'low_stock_report', 'bulk_update' ),
				),
				'product_id' => array( 'type' => 'integer' ),
				'quantity'   => array(
					'type'        => 'integer',
					'description' => 'New stock quantity for update_stock.',
				),
				'threshold'  => array(
					'type'        => 'integer',
					'description' => 'Stock threshold for low_stock_report. Default 5.',
				),
				'updates'    => array(
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array( 'type' => 'integer' ),
							'quantity'   => array( 'type' => 'integer' ),
						),
					),
					'description' => 'Array of {product_id, quantity} for bulk_update.',
				),
			),
			'required'   => array( 'operation' ),
		);
	}

	/**
	 * Get the required WordPress capability.
	 *
	 * @since  1.1.0
	 * @return string Required capability.
	 */
	public function get_capabilities_required(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Check whether this action is reversible.
	 *
	 * @since  1.1.0
	 * @return bool True if reversible.
	 */
	public function is_reversible(): bool {
		return true;
	}

	/**
	 * Execute the inventory management action.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $params Action parameters.
	 * @return array Result with success status, data, and message.
	 */
	public function execute( array $params ): array {
		$operation = $params['operation'] ?? '';

		switch ( $operation ) {
			case 'check_stock':
				return $this->check_stock( $params );
			case 'update_stock':
				return $this->update_stock( $params );
			case 'low_stock_report':
				return $this->low_stock_report( $params );
			case 'bulk_update':
				return $this->bulk_update( $params );
			default:
				return array(
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation.', 'wp-agent' ),
				);
		}
	}

	/**
	 * Check the stock level for a specific product.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $params Action parameters including product_id.
	 * @return array Result with stock quantity, status, and backorder info.
	 */
	private function check_stock( array $params ) {
		$product_id = absint( $params['product_id'] ?? 0 );
		if ( ! $product_id ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'product_id required.', 'wp-agent' ),
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'Product not found.', 'wp-agent' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'product_id'     => $product_id,
				'name'           => $product->get_name(),
				'manage_stock'   => $product->get_manage_stock(),
				'stock_quantity' => $product->get_stock_quantity(),
				'stock_status'   => $product->get_stock_status(),
				'backorders'     => $product->get_backorders(),
			),
			/* translators: 1: product name, 2: stock status, 3: stock quantity */
			'message' => sprintf( __( '%1$s: %2$s (qty: %3$s).', 'wp-agent' ), $product->get_name(), $product->get_stock_status(), $product->get_stock_quantity() ?? 'N/A' ),
		);
	}

	/**
	 * Update the stock quantity for a specific product.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $params Action parameters including product_id and quantity.
	 * @return array Result with old and new quantity values.
	 */
	private function update_stock( array $params ) {
		$product_id = absint( $params['product_id'] ?? 0 );
		$quantity   = isset( $params['quantity'] ) ? (int) $params['quantity'] : null;

		if ( ! $product_id || null === $quantity ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'product_id and quantity required.', 'wp-agent' ),
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'Product not found.', 'wp-agent' ),
			);
		}

		$old_qty = $product->get_stock_quantity();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );
		$product->save();

		return array(
			'success' => true,
			'data'    => array(
				'product_id'   => $product_id,
				'old_quantity' => $old_qty,
				'new_quantity' => $quantity,
			),
			/* translators: 1: product name, 2: old stock quantity, 3: new stock quantity */
			'message' => sprintf( __( '%1$s stock: %2$s -> %3$d.', 'wp-agent' ), $product->get_name(), $old_qty ?? 'N/A', $quantity ),
		);
	}

	/**
	 * Generate a report of products with stock at or below a threshold.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $params Action parameters including optional threshold.
	 * @return array Result with list of low-stock products.
	 */
	private function low_stock_report( array $params ) {
		$threshold = isset( $params['threshold'] ) ? absint( $params['threshold'] ) : 5;

		$args = array(
			'limit'        => 50,
			'manage_stock' => true,
			'stock_status' => 'instock',
			'orderby'      => 'meta_value_num',
			'meta_key'     => '_stock', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'        => 'ASC',
		);

		$products = wc_get_products( $args );
		$low      = array();

		foreach ( $products as $product ) {
			$qty = $product->get_stock_quantity();
			if ( null !== $qty && $qty <= $threshold ) {
				$low[] = array(
					'id'     => $product->get_id(),
					'name'   => $product->get_name(),
					'sku'    => $product->get_sku(),
					'stock'  => $qty,
					'status' => $product->get_stock_status(),
				);
			}
		}

		return array(
			'success' => true,
			'data'    => array(
				'threshold' => $threshold,
				'products'  => $low,
			),
			/* translators: 1: number of low-stock products, 2: stock threshold */
			'message' => sprintf( __( '%1$d product(s) with stock <= %2$d.', 'wp-agent' ), count( $low ), $threshold ),
		);
	}

	/**
	 * Bulk update stock quantities for multiple products.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $params Action parameters including updates array of product_id and quantity pairs.
	 * @return array Result with list of updated products.
	 */
	private function bulk_update( array $params ) {
		$updates = isset( $params['updates'] ) && is_array( $params['updates'] ) ? $params['updates'] : array();

		if ( empty( $updates ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'updates array required.', 'wp-agent' ),
			);
		}

		$results = array();
		foreach ( array_slice( $updates, 0, 100 ) as $update ) {
			$pid = absint( $update['product_id'] ?? 0 );
			$qty = isset( $update['quantity'] ) ? (int) $update['quantity'] : null;

			if ( ! $pid || null === $qty ) {
				continue;
			}

			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->save();

			$results[] = array(
				'product_id' => $pid,
				'quantity'   => $qty,
			);
		}

		return array(
			'success' => true,
			'data'    => array( 'updated' => $results ),
			'message' => sprintf( __( 'Updated stock for %d product(s).', 'wp-agent' ), count( $results ) ),
		);
	}
}
