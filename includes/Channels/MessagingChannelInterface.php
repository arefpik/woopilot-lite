<?php
/**
 * Contract that every messaging channel (Telegram, WhatsApp, Discord, ...) must implement.
 *
 * @package WooPilot\Channels
 */

namespace WooPilot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MessagingChannelInterface {

	/**
	 * Sends a text message to a chat, optionally with an inline keyboard.
	 *
	 * @param string $chatId   Destination chat identifier.
	 * @param string $text     Message body.
	 * @param array  $keyboard Optional inline keyboard definition.
	 */
	public function sendMessage( string $chatId, string $text, array $keyboard = [] ): void;

	/**
	 * Parses a raw incoming webhook payload into a normalized command.
	 *
	 * @param array $payload Raw payload received from the channel's webhook.
	 */
	public function parseIncomingCommand( array $payload ): ParsedCommand;

	/**
	 * Registers the given webhook URL with the channel provider.
	 *
	 * @param string $webhookUrl Publicly reachable webhook endpoint.
	 */
	public function setupWebhook( string $webhookUrl ): bool;

	/**
	 * Registers the list of bot commands with the channel provider.
	 *
	 * @param array $commands Command definitions to register.
	 */
	public function registerCommands( array $commands ): void;
}
