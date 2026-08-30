<?php
/**
 * Formats and sends order notifications through the active messaging channel.
 *
 * Depends only on MessagingChannelInterface, never on a concrete channel,
 * so swapping Telegram for another channel later requires no change here.
 *
 * @package WooPilot\Core\Notifications
 */

namespace WooPilot\Core\Notifications;

use WooPilot\Channels\MessagingChannelInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationDispatcher {

	private MessagingChannelInterface $channel;

	private string $chatId;

	public function __construct( MessagingChannelInterface $channel, string $chatId ) {
		$this->channel = $channel;
		$this->chatId  = $chatId;
	}

	/**
	 * Sends a new-order notification with inline status-change buttons.
	 *
	 * @param array $order Order summary, as returned by OrderService::getOrderSummary().
	 */
	public function dispatchNewOrder( array $order ): void {
		if ( empty( $this->chatId ) ) {
			return;
		}

		$this->channel->sendMessage(
			$this->chatId,
			$this->formatNewOrderMessage( $order ),
			$this->buildStatusKeyboard( $order['id'] )
		);
	}

	private function formatNewOrderMessage( array $order ): string {
		$lines = [
			sprintf( 'New order #%s', $order['number'] ),
			sprintf( 'Customer: %s', $order['customer'] ),
			sprintf( 'Total: %s', $order['total'] ),
		];

		foreach ( $order['items'] as $item ) {
			$lines[] = sprintf( '- %s x%d', $item['name'], $item['quantity'] );
		}

		return implode( "\n", $lines );
	}

	private function buildStatusKeyboard( int $orderId ): array {
		return [
			[ [ 'text' => 'Mark as Processing', 'callback_data' => "order_status:{$orderId}:processing" ] ],
			[ [ 'text' => 'Mark as Completed', 'callback_data' => "order_status:{$orderId}:completed" ] ],
		];
	}
}
