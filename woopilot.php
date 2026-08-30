<?php
/**
 * Plugin Name:       WooPilot
 * Plugin URI:        https://woopilot.example
 * Description:       Manage your WooCommerce store from Telegram and a built-in wp-admin dashboard.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WooPilot
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woopilot
 * Domain Path:       /languages
 *
 * @package WooPilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WOOPILOT_VERSION', '0.1.0' );
define( 'WOOPILOT_PLUGIN_FILE', __FILE__ );
define( 'WOOPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 style autoloader for the WooPilot\ namespace, mapped to includes/.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'WooPilot\\';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = WOOPILOT_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Boots the plugin once WooCommerce is confirmed active.
 */
function woopilot_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woopilot_missing_woocommerce_notice' );
		return;
	}

	add_action(
		'rest_api_init',
		function () {
			( new \WooPilot\Channels\Telegram\TelegramWebhookController() )->registerRoute();
		}
	);

	if ( is_admin() ) {
		$settings_page = new \WooPilot\Admin\SettingsPage();
		add_action( 'admin_menu', [ $settings_page, 'registerMenu' ] );
		add_action( 'admin_init', [ $settings_page, 'handleSave' ] );
	}

	add_action(
		'woocommerce_checkout_order_processed',
		function ( $order_id ) {
			$dispatcher = woopilot_build_notification_dispatcher();

			if ( ! $dispatcher ) {
				return;
			}

			$listener = new \WooPilot\Core\Notifications\NewOrderListener(
				new \WooPilot\Core\Orders\OrderService( new \WooPilot\Core\Orders\OrderRepository() ),
				$dispatcher
			);

			$listener->handle( (int) $order_id );
		}
	);
}
add_action( 'plugins_loaded', 'woopilot_init' );

/**
 * Builds a NotificationDispatcher wired to the configured Telegram bot, or
 * null when the bot token / admin chat id haven't been set up yet.
 */
function woopilot_build_notification_dispatcher(): ?\WooPilot\Core\Notifications\NotificationDispatcher {
	$bot_token = \WooPilot\Support\Config::getTelegramBotToken();
	$chat_id   = \WooPilot\Support\Config::getTelegramChatId();

	if ( empty( $bot_token ) || empty( $chat_id ) ) {
		return null;
	}

	$channel = new \WooPilot\Channels\Telegram\TelegramChannel( $bot_token, \WooPilot\Support\Config::getTelegramWebhookSecret() );

	return new \WooPilot\Core\Notifications\NotificationDispatcher( $channel, $chat_id );
}

/**
 * Shows an admin notice when WooCommerce is not active.
 */
function woopilot_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'WooPilot requires WooCommerce to be installed and active.', 'woopilot' ) .
		'</p></div>';
}
