<?php
/**
 * Business logic for reading order summaries and changing order status.
 *
 * @package WooPilot\Core\Orders
 */

namespace WooPilot\Core\Orders;

use WooPilot\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderService {

	private OrderRepository $repository;

	public function __construct( OrderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Returns a plain-array summary of an order, suitable for formatting a
	 * notification message, or null if the order doesn't exist.
	 */
	public function getOrderSummary( int $orderId ): ?array {
		$order = $this->repository->find( $orderId );

		if ( ! $order ) {
			return null;
		}

		return [
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'total'    => $order->get_formatted_order_total(),
			'customer' => trim( $order->get_formatted_billing_full_name() ),
			'status'   => $order->get_status(),
			'items'    => $this->formatItems( $order ),
		];
	}

	/**
	 * Changes an order's status after validating it against WooCommerce's
	 * own registered statuses, so an unknown/forged status is never applied.
	 */
	public function changeStatus( int $orderId, string $newStatus ): bool {
		if ( ! $this->isValidStatus( $newStatus ) ) {
			Logger::warning( "Rejected order status change to unknown status \"{$newStatus}\".", [ 'order_id' => $orderId ] );
			return false;
		}

		$order = $this->repository->find( $orderId );

		if ( ! $order ) {
			Logger::warning( 'Attempted to change status of a non-existent order.', [ 'order_id' => $orderId ] );
			return false;
		}

		$this->repository->updateStatus( $order, $newStatus );

		/**
		 * Fires after an order's status has been changed through WooPilot.
		 *
		 * @param int    $orderId   The order ID.
		 * @param string $newStatus The new order status, without the "wc-" prefix.
		 */
		do_action( 'woopilot_order_status_changed', $orderId, $newStatus );

		return true;
	}

	private function isValidStatus( string $status ): bool {
		return array_key_exists( 'wc-' . $status, wc_get_order_statuses() );
	}

	private function formatItems( \WC_Order $order ): array {
		$items = [];

		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
			];
		}

		return $items;
	}
}
