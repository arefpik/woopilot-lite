<?php
/**
 * wp-admin settings screen for connecting the Telegram bot.
 *
 * @package WooPilot\Admin
 */

namespace WooPilot\Admin;

use WooPilot\Channels\Telegram\TelegramChannel;
use WooPilot\Channels\Telegram\TelegramWebhookController;
use WooPilot\Support\Config;
use WooPilot\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	private const MENU_SLUG    = 'woopilot-settings';
	private const CAPABILITY   = 'manage_woocommerce';
	private const NONCE_ACTION = 'woopilot_save_telegram_settings';
	private const NONCE_FIELD  = 'woopilot_telegram_nonce';

	public function registerMenu(): void {
		add_menu_page(
			__( 'WooPilot', 'woopilot' ),
			__( 'WooPilot', 'woopilot' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render' ],
			'dashicons-telegram'
		);
	}

	/**
	 * Persists the submitted Bot Token / Chat ID and re-syncs the webhook.
	 * Hooked on admin_init so it runs before the page is rendered.
	 */
	public function handleSave(): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change WooPilot settings.', 'woopilot' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$botToken = isset( $_POST['woopilot_bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['woopilot_bot_token'] ) ) : '';
		$chatId   = isset( $_POST['woopilot_chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['woopilot_chat_id'] ) ) : '';

		Config::setTelegramBotToken( $botToken );
		Config::setTelegramChatId( $chatId );

		$this->syncWebhook( $botToken );

		add_action( 'admin_notices', [ $this, 'renderSavedNotice' ] );
	}

	/**
	 * Registers the webhook and bot commands with Telegram right after the
	 * token is saved, per the "no manual webhook setup" UX rule.
	 */
	private function syncWebhook( string $botToken ): void {
		if ( empty( $botToken ) ) {
			return;
		}

		$channel = new TelegramChannel( $botToken, Config::getTelegramWebhookSecret() );

		if ( ! $channel->setupWebhook( TelegramWebhookController::getWebhookUrl() ) ) {
			Logger::error( 'Failed to configure the Telegram webhook after saving settings.' );
			return;
		}

		$channel->registerCommands(
			[
				[
					'command'     => 'start',
					'description' => __( 'Start using WooPilot', 'woopilot' ),
				],
			]
		);
	}

	public function renderSavedNotice(): void {
		echo '<div class="notice notice-success"><p>' .
			esc_html__( 'WooPilot settings saved.', 'woopilot' ) .
			'</p></div>';
	}

	/**
	 * Exceeds the usual 40-line limit because it is a plain HTML form
	 * template; splitting the markup into helpers would hurt readability
	 * without reducing complexity.
	 */
	public function render(): void {
		$botToken = Config::getTelegramBotToken();
		$chatId   = Config::getTelegramChatId();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooPilot Settings', 'woopilot' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="woopilot_bot_token"><?php esc_html_e( 'Telegram Bot Token', 'woopilot' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="woopilot_bot_token"
								name="woopilot_bot_token"
								value="<?php echo esc_attr( $botToken ); ?>"
								class="regular-text"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="woopilot_chat_id"><?php esc_html_e( 'Admin Chat ID', 'woopilot' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="woopilot_chat_id"
								name="woopilot_chat_id"
								value="<?php echo esc_attr( $chatId ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Get this from a bot such as @userinfobot after messaging your bot.', 'woopilot' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'woopilot' ) ); ?>
			</form>
		</div>
		<?php
	}
}
