<?php
/**
 * REST endpoint that receives Telegram's webhook updates.
 *
 * @package WooPilot\Channels\Telegram
 */

namespace WooPilot\Channels\Telegram;

use WooPilot\Support\Config;
use WooPilot\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramWebhookController {

	private const ROUTE_NAMESPACE = 'woopilot/v1';
	private const ROUTE_PATH      = '/telegram-webhook';
	private const SECRET_HEADER   = 'x-telegram-bot-api-secret-token';

	public function registerRoute(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'verifySecretToken' ],
			]
		);
	}

	/**
	 * Confirms the request actually came from Telegram by comparing the
	 * secret token header against the one we registered via setupWebhook().
	 */
	public function verifySecretToken( \WP_REST_Request $request ): bool {
		$expected = Config::getTelegramWebhookSecret();
		$received = (string) $request->get_header( self::SECRET_HEADER );

		return ! empty( $expected ) && hash_equals( $expected, $received );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new \WP_REST_Response( null, 400 );
		}

		try {
			$channel = new TelegramChannel( Config::getTelegramBotToken(), Config::getTelegramWebhookSecret() );
			$command = $channel->parseIncomingCommand( $payload );

			// TODO: dispatch $command to the relevant Core service (Orders, etc.) once that layer exists.
			unset( $command );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to process an incoming Telegram webhook update.', [ 'exception' => $e->getMessage() ] );
		}

		// Telegram only cares about a 2xx response; errors are logged, not surfaced here.
		return new \WP_REST_Response( null, 200 );
	}

	public static function getWebhookUrl(): string {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}
}
