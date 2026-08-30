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

	private string $messageTemplate;

	/** @var array Admin-defined list of ['label' => string, 'status' => string]. */
	private array $statusButtons;

	/**
	 * @param string $messageTemplate Message text with {order_number}/{customer}/
	 *                                {total}/{status}/{items} placeholders.
	 * @param array  $statusButtons   List of ['label' => string, 'status' => string]
	 *                                shown as inline buttons under the message.
	 */
	public function __construct( MessagingChannelInterface $channel, string $chatId, string $messageTemplate, array $statusButtons ) {
		$this->channel         = $channel;
		$this->chatId          = $chatId;
		$this->messageTemplate = $messageTemplate;
		$this->statusButtons   = $statusButtons;
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
			$this->renderTemplate( $order ),
			OrderStatusKeyboard::build( $this->statusButtons, $order['id'], $order['status'] )
		);
	}

	/**
	 * Substitutes order placeholders into the admin-configured template.
	 * Uses strtr() (simultaneous replacement) instead of chained str_replace()
	 * calls so a value that happens to contain "{...}" text can never be
	 * mistaken for another placeholder.
	 */
	private function renderTemplate( array $order ): string {
		$tokens = [
			'{order_number}' => (string) $order['number'],
			'{customer}'     => (string) $order['customer'],
			'{total}'        => (string) $order['total'],
			'{status}'       => (string) $order['status'],
			'{items}'        => $this->formatItemsList( $order['items'] ),
		];

		return strtr( $this->messageTemplate, $tokens );
	}

	private function formatItemsList( array $items ): string {
		$lines = [];

		foreach ( $items as $item ) {
			$lines[] = sprintf( '- %s x%d', $item['name'], $item['quantity'] );
		}

		return implode( "\n", $lines );
	}
}
