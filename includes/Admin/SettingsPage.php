<?php
/**
 * wp-admin settings screen for connecting the Telegram bot and customizing
 * the new-order notification message and status-change buttons.
 *
 * @package WooPilot\Admin
 */

namespace WooPilot\Admin;

use WooPilot\Admin\Dashboard\DashboardPage;
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

	/**
	 * Placeholders available in the notification template, and what each
	 * one resolves to; shared between the save handler's validation and the
	 * legend shown to the admin.
	 */
	private const TEMPLATE_PLACEHOLDERS = [
		'{order_number}' => 'Order number',
		'{customer}'     => 'Customer name',
		'{phone}'        => 'Customer phone number',
		'{email}'        => 'Customer email address',
		'{total}'        => 'Order total',
		'{status}'       => 'Order status',
		'{items}'        => 'Ordered items, one per line, each with its price and any custom fields the product added at checkout (e.g. a game top-up account ID) — no separate placeholder needed for those, they show automatically',
	];

	public function registerMenu(): void {
		add_submenu_page(
			DashboardPage::MENU_SLUG,
			__( 'WooPilot Settings', 'woopilot' ),
			__( 'Settings', 'woopilot' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Persists the submitted settings and re-syncs the webhook. Hooked on
	 * admin_init so it runs before the page is rendered.
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

		if ( ! $this->isValidChatId( $chatId ) ) {
			add_action( 'admin_notices', [ $this, 'renderInvalidChatIdNotice' ] );
			return;
		}

		Config::setTelegramBotToken( $botToken );
		Config::setTelegramChatId( $chatId );
		Config::setStatusButtons( $this->sanitizeStatusButtons() );

		$template = isset( $_POST['woopilot_notification_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['woopilot_notification_template'] ) ) : '';

		if ( '' !== trim( $template ) ) {
			Config::setNotificationTemplate( $template );
		}

		if ( $this->syncWebhook( $botToken ) ) {
			add_action( 'admin_notices', [ $this, 'renderSavedNotice' ] );
		} else {
			add_action( 'admin_notices', [ $this, 'renderWebhookSyncFailedNotice' ] );
		}
	}

	/**
	 * A Telegram chat id is either empty (not yet linked) or an integer —
	 * negative for groups/supergroups/channels — never arbitrary text.
	 */
	private function isValidChatId( string $chatId ): bool {
		return '' === $chatId || 1 === preg_match( '/^-?\d+$/', $chatId );
	}

	/**
	 * Reads the label/status repeater fields and drops any row with a blank
	 * label or a status that isn't a real WooCommerce order status, so a
	 * forged or stale field never produces a broken or dangerous button.
	 */
	private function sanitizeStatusButtons(): array {
		$labels        = isset( $_POST['woopilot_button_label'] ) ? (array) wp_unslash( $_POST['woopilot_button_label'] ) : [];
		$statuses      = isset( $_POST['woopilot_button_status'] ) ? (array) wp_unslash( $_POST['woopilot_button_status'] ) : [];
		$validStatuses = wc_get_order_statuses();
		$buttons       = [];

		foreach ( $labels as $index => $rawLabel ) {
			$label  = sanitize_text_field( $rawLabel );
			$status = isset( $statuses[ $index ] ) ? sanitize_key( $statuses[ $index ] ) : '';

			if ( '' === $label || ! array_key_exists( 'wc-' . $status, $validStatuses ) ) {
				continue;
			}

			$buttons[] = [
				'label'  => $label,
				'status' => $status,
			];
		}

		return $buttons;
	}

	/**
	 * Registers the webhook and bot commands with Telegram right after the
	 * token is saved, per the "no manual webhook setup" UX rule. Returns
	 * false only when a sync was actually attempted and failed, so the admin
	 * is told, instead of seeing a false "saved" success message.
	 */
	private function syncWebhook( string $botToken ): bool {
		if ( empty( $botToken ) ) {
			return true;
		}

		$channel = new TelegramChannel( $botToken, Config::getTelegramWebhookSecret() );

		if ( ! $channel->setupWebhook( TelegramWebhookController::getWebhookUrl() ) ) {
			Logger::error( 'Failed to configure the Telegram webhook after saving settings.' );
			return false;
		}

		$channel->registerCommands(
			[
				[
					'command'     => 'start',
					'description' => __( 'Start using WooPilot', 'woopilot' ),
				],
				[
					'command'     => 'products',
					'description' => __( 'Manage products (Pro)', 'woopilot' ),
				],
			]
		);

		return true;
	}

	public function renderSavedNotice(): void {
		echo '<div class="notice notice-success"><p>' .
			esc_html__( 'WooPilot settings saved.', 'woopilot' ) .
			'</p></div>';
	}

	public function renderWebhookSyncFailedNotice(): void {
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'Settings were saved, but WooPilot could not reach Telegram to configure the webhook. Double-check the bot token and your server\'s outbound internet access, then save again.', 'woopilot' ) .
			'</p></div>';
	}

	public function renderInvalidChatIdNotice(): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'Chat ID must be a numeric Telegram chat identifier, or left empty.', 'woopilot' ) .
			'</p></div>';
	}

	/**
	 * Exceeds the usual 40-line limit because it is a plain HTML form
	 * template; splitting the markup into helpers would hurt readability
	 * without reducing complexity.
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooPilot Settings', 'woopilot' ); ?></h1>
			<?php $this->renderGettingStartedNotice(); ?>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<table class="form-table">
					<?php $this->renderConnectionFields(); ?>
					<?php $this->renderTemplateField(); ?>
					<?php $this->renderStatusButtonsField(); ?>
				</table>
				<?php submit_button( __( 'Save Settings', 'woopilot' ) ); ?>
			</form>
		</div>
		<?php $this->renderRepeaterScript(); ?>
		<?php
	}

	/**
	 * Shown only while no bot token is saved yet, so a first-time admin
	 * knows exactly what to do before the connection fields mean anything.
	 * Disappears automatically once a token is on file.
	 */
	private function renderGettingStartedNotice(): void {
		if ( '' !== Config::getTelegramBotToken() ) {
			return;
		}
		?>
		<div class="notice notice-info" style="padding: 12px 16px;">
			<p><strong><?php esc_html_e( 'Get WooPilot connected to Telegram in three steps:', 'woopilot' ); ?></strong></p>
			<ol style="margin-left: 1.2em; list-style: decimal;">
				<li>
					<?php
					printf(
						/* translators: %s: link to @BotFather on Telegram */
						esc_html__( 'Open a chat with %s, send /newbot, and follow its prompts to get a bot token.', 'woopilot' ),
						'<a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>'
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: link to @userinfobot on Telegram */
						esc_html__( 'Message your new bot at least once, then get your numeric Chat ID from %s (add it to a group first if you want order notifications sent there instead).', 'woopilot' ),
						'<a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer">@userinfobot</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Paste both values below and click Save Settings — WooPilot configures the Telegram webhook automatically, no manual setup needed.', 'woopilot' ); ?></li>
			</ol>
		</div>
		<?php
	}

	private function renderConnectionFields(): void {
		$botToken = Config::getTelegramBotToken();
		$chatId   = Config::getTelegramChatId();
		?>
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
				<label for="woopilot_chat_id"><?php esc_html_e( 'Chat ID', 'woopilot' ); ?></label>
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
					<?php esc_html_e( 'A personal chat, a group, or a supergroup — get its numeric ID from a bot such as @userinfobot (add the bot to a group first to get that group\'s ID).', 'woopilot' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	private function renderTemplateField(): void {
		$template = Config::getNotificationTemplate();
		?>
		<tr>
			<th scope="row">
				<label for="woopilot_notification_template"><?php esc_html_e( 'New Order Message', 'woopilot' ); ?></label>
			</th>
			<td>
				<textarea
					id="woopilot_notification_template"
					name="woopilot_notification_template"
					rows="5"
					class="large-text code"
				><?php echo esc_textarea( $template ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Click a placeholder to insert it at the cursor:', 'woopilot' ); ?>
				</p>
				<p>
					<?php foreach ( self::TEMPLATE_PLACEHOLDERS as $placeholder => $description ) : ?>
						<button
							type="button"
							class="button woopilot-insert-placeholder"
							data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
							title="<?php echo esc_attr( $description ); ?>"
						><?php echo esc_html( $placeholder ); ?></button>
					<?php endforeach; ?>
				</p>
			</td>
		</tr>
		<?php
	}

	private function renderStatusButtonsField(): void {
		$buttons        = Config::getStatusButtons();
		$orderStatuses  = wc_get_order_statuses();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status Buttons', 'woopilot' ); ?></th>
			<td>
				<div id="woopilot-status-buttons">
					<?php foreach ( $buttons as $button ) : ?>
						<?php $this->renderStatusButtonRow( $button['label'], $button['status'], $orderStatuses ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button" id="woopilot-add-status-button">
					<?php esc_html_e( '+ Add Button', 'woopilot' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'These appear as inline buttons under every new-order message, in this order. Remove all of them to send a notification with no buttons.', 'woopilot' ); ?>
				</p>
				<template id="woopilot-status-button-template">
					<?php $this->renderStatusButtonRow( '', '', $orderStatuses ); ?>
				</template>
			</td>
		</tr>
		<?php
	}

	private function renderStatusButtonRow( string $label, string $status, array $orderStatuses ): void {
		?>
		<div class="woopilot-status-button-row" style="display:flex; gap:8px; margin-bottom:8px;">
			<input
				type="text"
				name="woopilot_button_label[]"
				value="<?php echo esc_attr( $label ); ?>"
				placeholder="<?php esc_attr_e( 'Button text', 'woopilot' ); ?>"
				class="regular-text"
			/>
			<select name="woopilot_button_status[]">
				<?php foreach ( $orderStatuses as $key => $statusLabel ) : ?>
					<?php $statusValue = str_replace( 'wc-', '', $key ); ?>
					<option value="<?php echo esc_attr( $statusValue ); ?>" <?php selected( $status, $statusValue ); ?>>
						<?php echo esc_html( $statusLabel ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button woopilot-remove-status-button">
				<?php esc_html_e( 'Remove', 'woopilot' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Plain vanilla JS (no build step) for the status-button repeater and
	 * the template placeholder inserter — this is a classic wp-admin
	 * screen, not part of dashboard-app/, so the Tailwind-only rule
	 * doesn't apply here.
	 */
	private function renderRepeaterScript(): void {
		?>
		<script>
		( function () {
			var container = document.getElementById( 'woopilot-status-buttons' );
			var template  = document.getElementById( 'woopilot-status-button-template' );
			var addButton = document.getElementById( 'woopilot-add-status-button' );

			addButton.addEventListener( 'click', function () {
				container.appendChild( template.content.cloneNode( true ) );
			} );

			container.addEventListener( 'click', function ( event ) {
				if ( event.target.classList.contains( 'woopilot-remove-status-button' ) ) {
					event.target.closest( '.woopilot-status-button-row' ).remove();
				}
			} );

			// Inserts the clicked placeholder at the textarea's current
			// cursor position (or replaces the current selection), instead
			// of always appending to the end.
			var templateField = document.getElementById( 'woopilot_notification_template' );

			document.querySelectorAll( '.woopilot-insert-placeholder' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					templateField.focus();
					templateField.setRangeText(
						button.dataset.placeholder,
						templateField.selectionStart,
						templateField.selectionEnd,
						'end'
					);
				} );
			} );
		} )();
		</script>
		<?php
	}
}
