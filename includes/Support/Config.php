<?php
/**
 * Single point of access for WooPilot's wp_options-backed settings.
 *
 * @package WooPilot\Support
 */

namespace WooPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	private const OPTION_BOT_TOKEN      = 'woopilot_telegram_bot_token';
	private const OPTION_CHAT_ID        = 'woopilot_telegram_chat_id';
	private const OPTION_WEBHOOK_SECRET = 'woopilot_telegram_webhook_secret';

	private const WEBHOOK_SECRET_LENGTH = 32;

	public static function getTelegramBotToken(): string {
		return (string) get_option( self::OPTION_BOT_TOKEN, '' );
	}

	public static function setTelegramBotToken( string $token ): void {
		update_option( self::OPTION_BOT_TOKEN, $token );
	}

	public static function getTelegramChatId(): string {
		return (string) get_option( self::OPTION_CHAT_ID, '' );
	}

	public static function setTelegramChatId( string $chatId ): void {
		update_option( self::OPTION_CHAT_ID, $chatId );
	}

	/**
	 * Returns the secret used to verify Telegram's webhook requests, generating
	 * and persisting one on first use so the value stays stable across calls.
	 */
	public static function getTelegramWebhookSecret(): string {
		$secret = get_option( self::OPTION_WEBHOOK_SECRET, '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( self::WEBHOOK_SECRET_LENGTH, false );
			update_option( self::OPTION_WEBHOOK_SECRET, $secret );
		}

		return (string) $secret;
	}
}
