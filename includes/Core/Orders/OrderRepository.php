<?php
/**
 * Data access layer for orders, backed by WooCommerce's own CRUD classes.
 *
 * @package WooPilot\Core\Orders
 */

namespace WooPilot\Core\Orders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderRepository {

	public function find( int $orderId ): ?\WC_Order {
		$order = wc_get_order( $orderId );

		return $order instanceof \WC_Order ? $order : null;
	}

	public function updateStatus( \WC_Order $order, string $status ): void {
		$order->update_status( $status );
	}
}
