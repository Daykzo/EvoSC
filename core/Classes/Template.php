<?php

namespace esc\Classes;


use esc\Controllers\TemplateController;
use esc\Controllers\TemplateControllerTwig;
use esc\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class Template
 *
 * Render manialink-templates and send them to all or single players.
 *
 * @package esc\Classes
 */
class Template
{
    public $index;
    public $template;

    /**
     * @var Collection
     */
    private static $multiCalls;

    /**
     * Template constructor.
     *
     * @param string $index
     * @param string $template
     */
    public function __construct(string $index, string $template)
    {
        $this->index    = $index;
        $this->template = $template;
    }

    /**
     * Show the template to everyone.
     *
     * @param string     $index
     * @param array|null $values
     */
    public static function showAll(string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $xml = TemplateController::getTemplate($index, $values);
        Server::sendDisplayManialinkPage('', $xml);
    }

    /**
     * Hide a manialink with the given id for everyone.
     *
     * @param string $index
     */
    public static function hideAll(string $index)
    {
        Server::sendDisplayManialinkPage('', sprintf('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><manialink id="%s" version="3"></manialink>', $index));
    }

    /**
     * Hide a manialink with the given id for a single player.
     *
     * @param string $index
     */
    public static function hide(Player $player, string $index)
    {
        Server::sendDisplayManialinkPage($player->Login, sprintf('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><manialink id="%s" version="3"></manialink>', $index));
    }

    /**
     * Render and send a template to a player.
     *
     * @param \esc\Models\Player $player
     * @param string             $index
     * @param null               $values
     * @param bool               $multicall
     */
    public static function show(Player $player, string $index, $values = null, bool $multicall = false)
    {
        $data = [];

        if ($values instanceof Collection) {
            foreach ($values as $key => $value) {
                $data[$key] = $value;
            }
        } else {
            $data = $values;
        }

        $data['localPlayer'] = $player;
        $xml                 = TemplateController::getTemplate($index, $data);

        if ($xml != '') {
            if ($multicall) {
                if (!self::$multiCalls) {
                    self::$multiCalls = collect();
                }

                self::$multiCalls->put($player->Login, $xml);
            } else {
                Server::sendDisplayManialinkPage($player->Login, $xml);
            }
        }
    }

    /**
     * Send all collected templates.
     */
    public static function executeMulticall()
    {
        if (!self::$multiCalls) {
            return;
        }

        self::$multiCalls->each(function ($xml, $login) {
            Server::sendDisplayManialinkPage($login, $xml, 0, false, true);
        });

        Server::executeMulticall();

        self::$multiCalls = collect();
    }

    /**
     * Render the template and get the xml as string.
     *
     * @param string     $index
     * @param array|null $values
     *
     * @return string
     */
    public static function toString(string $index, array $values = null): string
    {
        if (!$values) {
            $values = [];
        }

        return TemplateController::getTemplate($index, $values);
    }
}