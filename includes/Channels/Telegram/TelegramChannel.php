<?php
/**
 * Telegram implementation of the messaging channel contract.
 *
 * @package WooPilot\Channels\Telegram
 */

namespace WooPilot\Channels\Telegram;

use WooPilot\Channels\MessagingChannelInterface;
use WooPilot\Channels\ParsedCommand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramChannel implements MessagingChannelInterface {

	/**
	 * Base URL of the Telegram Bot API.
	 */
	private const API_BASE_URL = 'https://api.telegram.org/bot';

	/** @var string */
	private $botToken;

	public function __construct( string $botToken ) {
		$this->botToken = $botToken;
	}

	/**
	 * {@inheritDoc}
	 */
	public function sendMessage( string $chatId, string $text, array $keyboard = [] ): void {
		// TODO: call Telegram's sendMessage endpoint via wp_remote_post().
		throw new \LogicException( 'TelegramChannel::sendMessage() is not implemented yet.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function parseIncomingCommand( array $payload ): ParsedCommand {
		// TODO: extract chat id, command name and args from Telegram's update payload.
		throw new \LogicException( 'TelegramChannel::parseIncomingCommand() is not implemented yet.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function setupWebhook( string $webhookUrl ): bool {
		// TODO: call Telegram's setWebhook endpoint via wp_remote_post().
		throw new \LogicException( 'TelegramChannel::setupWebhook() is not implemented yet.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function registerCommands( array $commands ): void {
		// TODO: call Telegram's setMyCommands endpoint via wp_remote_post().
		throw new \LogicException( 'TelegramChannel::registerCommands() is not implemented yet.' );
	}
}
