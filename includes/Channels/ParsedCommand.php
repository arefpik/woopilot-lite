<?php
/**
 * Normalized representation of an incoming channel command, independent of the source channel.
 *
 * @package WooPilot\Channels
 */

namespace WooPilot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParsedCommand {

	/** @var string */
	public $chatId;

	/** @var string */
	public $command;

	/** @var array */
	public $args;

	/** @var array */
	public $rawPayload;

	/**
	 * @param string $chatId     Identifier of the chat the command came from.
	 * @param string $command    Command name, without leading slash (e.g. "start").
	 * @param array  $args       Extra arguments parsed from the command.
	 * @param array  $rawPayload Original payload as received from the channel.
	 */
	public function __construct( string $chatId, string $command, array $args = [], array $rawPayload = [] ) {
		$this->chatId     = $chatId;
		$this->command    = $command;
		$this->args       = $args;
		$this->rawPayload = $rawPayload;
	}
}
