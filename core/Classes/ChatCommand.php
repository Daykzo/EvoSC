<?php

namespace esc\Classes;


use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class ChatCommand
 *
 * Create chat-commands and aliases
 *
 * @package esc\Classes
 */
class ChatCommand
{
    /**
     * @var Collection
     */
    private static $commands;

    public $command;
    public $callback;
    public $description;
    public $access;
    public $hidden;

    /**
     * ChatCommand constructor.
     *
     * @param string      $command
     * @param             $callback
     * @param string      $description
     * @param string|null $access
     * @param bool        $hidden
     */
    public function __construct(string $command, $callback, string $description = '', string $access = null, bool $hidden = false)
    {
        $this->command     = $command;
        $this->callback    = $callback;
        $this->description = $description;
        $this->access      = $access;
        $this->hidden      = $hidden;
    }

    /**
     * Add chat-command
     *
     * @param string      $command
     * @param             $callback
     * @param string      $description
     * @param string|null $access
     * @param bool        $hidden
     *
     * @return \esc\Classes\ChatCommand
     */
    public static function add(string $command, $callback, string $description = '-', string $access = null, bool $hidden = false): ChatCommand
    {
        if (!self::$commands) {
            self::$commands = collect();
        }

        $chatCommand = new ChatCommand($command, $callback, $description, $access, $hidden);
        self::$commands->put($command, $chatCommand);

        if ($access && $access != 'ma') {
            if (!AccessRight::whereName($access)->exists()) {
                Log::warning("Missing access-right: $access");
            }
        }

        return $chatCommand;
    }

    /**
     * Add chat-command alias (chainable)
     *
     * @param string $alias
     *
     * @return \esc\Classes\ChatCommand
     */
    public function addAlias(string $alias): ChatCommand
    {
        $description = sprintf('%s (Alias for %s)', $this->description, $this->command);
        ChatCommand::add($alias, $this->callback, $description, $this->access, $this->hidden);

        return $this;
    }

    /**
     * Checks if a command already exists.
     *
     * @param string $command
     *
     * @return bool
     */
    public static function has(string $command): bool
    {
        return self::$commands->has($command);
    }

    /**
     * Gets the command-object by the chat-command
     *
     * @param string $command
     *
     * @return \esc\Classes\ChatCommand
     */
    public static function get(string $command): ChatCommand
    {
        return self::$commands->get($command);
    }

    /**
     * Get a collection of all active chat-commands
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getCommands(): Collection
    {
        return self::$commands;
    }

    /**
     * Method is called when a chat-command is detected by {@see ChatController::playerChat()}
     *
     * @param \esc\Models\Player $player
     * @param string             $text
     */
    public function execute(Player $player, string $text)
    {
        if ($this->access && !$player->hasAccess($this->access)) {
            warningMessage('Sorry, you are not allowed to do that.')->send($player);

            return;
        }

        //treat "this is a string" as single argument
        if (preg_match_all('/\"(.+?)\"/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                //Replace all spaces in quotes to ;:§;
                $new  = str_replace(' ', ';:§;', $match);
                $text = str_replace("\"$match\"", $new, $text);
            }
        }

        //Split input string in arguments
        $arguments = explode(' ', $text);

        foreach ($arguments as $key => $argument) {
            //Change ;:§; back to spaces
            $arguments[$key] = str_replace(';:§;', ' ', $argument);
        }

        //Set calling player as first argument
        array_unshift($arguments, $player);

        if ($this->callback instanceof \Closure) {
            $callback = $this->callback;
            $callback(...$arguments);
        } else {
            Log::logAddLine('ChatCommand', sprintf('Call: %s -> %s(%s)', $this->callback[0], $this->callback[1], implode(', ', $arguments)), isVeryVerbose());
            call_user_func_array($this->callback, $arguments);
        }
    }
}