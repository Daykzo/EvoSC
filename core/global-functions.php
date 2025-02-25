<?php

use esc\Classes\Server;
use esc\Models\Player;

function chatMessage(...$message)
{
    return new \esc\Classes\ChatMessage(...$message);
}

function infoMessage(...$message)
{
    return (new \esc\Classes\ChatMessage(...$message))->setIsInfoMessage();
}

function warningMessage(...$message)
{
    return (new \esc\Classes\ChatMessage(...$message))->setIsWarning();
}

function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms      = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

function serverName(): string
{
    global $serverName;

    return $serverName;
}

function formatScoreNoMinutes(int $score): string
{
    $seconds = floor($score / 1000);
    $ms      = $score - ($seconds * 1000);

    return sprintf('%d.%03d', $seconds, $ms);
}

function stripColors(?string $colored): string
{
    return preg_replace('/(?<![$])\${1}(?:[\w\d]{1,3})/i', '', $colored);
}

function stripStyle(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}(?:l(?:\[.+?\])|[iwngosz]{1})/i', '', $styled);
}

function stripAll(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}((l|m)(?:\[.+?\])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
}

function config(string $id, $default = null)
{
    return \esc\Controllers\ConfigController::getConfig(strtolower($id)) ?: $default;
}

function cacheDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../cache/' . $filename);
}

function logDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../logs/' . $filename);
}

function ghost(string $filename = ''): stringex
{
    return \esc\Classes\Server::GameDataDirectory() . str_replace('/', DIRECTORY_SEPARATOR, '/Replays/Ghosts/' . $filename . '.Replay.Gbx');
}

function coreDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/' . $filename);
}

function configDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../config/' . $filename);
}

function baseDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../' . $filename);
}

function onlinePlayers(bool $withSpectators = true): \Illuminate\Support\Collection
{
    $logins = collect(Server::getPlayerList())->pluck('login');

    return Player::whereIn('Login', $logins)->get();
}

function player(string $login, bool $addToOnlineIfOffline = false): \esc\Models\Player
{
    if (\esc\Controllers\PlayerController::hasPlayer($login)) {
        return esc\Controllers\PlayerController::getPlayer($login);
    }

    $player = \esc\Models\Player::find($login);

    if (!$player || !isset($player->Login)) {
        \esc\Classes\Log::logAddLine('global-functions', 'Failed to find player: ' . $login);

        return null;
    }

    if ($addToOnlineIfOffline) {
        \esc\Controllers\PlayerController::addPlayer($player);
    }

    return $player;
}

function echoPlayers(): \Illuminate\Support\Collection
{
    $players = onlinePlayers()->filter(function (\esc\Models\Player $player) {
        return $player->hasAccess('admin_echoes');
    });

    return $players;
}

function finishPlayers(): \Illuminate\Support\Collection
{
    return \esc\Models\Player::where('Score', '>', 0)->get();
}

function now(): \Carbon\Carbon
{
    return (new \Carbon\Carbon())->now();
}

function cutZeroes(string $formattedScore): string
{
    return preg_replace('/^[0\:\.]+/', '', $formattedScore);
}

function secondary(string $str = ""): string
{
    return '$z$s$' . config('colors.secondary') . $str;
}

function primary(string $str = ""): string
{
    return '$' . config('colors.primary') . $str;
}

function warning(string $str = ""): string
{
    return '$' . config('colors.warning') . $str;
}

function info(string $str = ""): string
{
    return '$' . config('colors.info') . $str;
}

function getEscVersion(): string
{
    global $escVersion;

    return $escVersion;
}

function maps()
{
    return \esc\Models\Map::whereEnabled(true)->get();
}

function matchSettings(string $filename = null)
{
    return \esc\Classes\Server::getMapsDirectory() . '/MatchSettings/' . ($filename);
}

function getMapInfoFromFile(string $filename)
{
    $mps  = config('server.mps');
    $maps = config('server.maps') . '/';
    if (file_exists($maps . $filename) && file_exists($mps)) {
        $process = new \Symfony\Component\Process\Process($mps . ' /parsegbx=' . $maps . $filename);
        $process->run();

        return json_decode($process->getOutput());
    }

    return null;
}

function isVerbose(): bool
{
    global $_isVerbose;
    global $_isVeryVerbose;
    global $_isDebug;

    return ($_isVerbose || $_isVeryVerbose || $_isDebug);
}

function isVeryVerbose(): bool
{
    global $_isVeryVerbose;
    global $_isDebug;

    return ($_isVeryVerbose || $_isDebug);
}

function isDebug(): bool
{
    global $_isDebug;

    return $_isDebug;
}

function isWindows(): bool
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}