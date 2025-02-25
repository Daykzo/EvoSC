<?php

namespace esc\Modules;

use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Clock
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'displayClock']);
        Hook::add('ConfigUpdated', [self::class, 'configUpdated']);
    }

    public static function displayClock(Player $player)
    {
        Template::show($player, 'clock.clock');
    }

    public static function configUpdated(Config $config = null)
    {
        if ($config && isset($config->id) && $config->id == "clock" || $config->id == "colors") {
            onlinePlayers()->each(function (Player $player) use ($config) {
                $clock = $config->data;
                Template::show($player, 'clock.clock', compact('clock'));
            });
        }
    }
}