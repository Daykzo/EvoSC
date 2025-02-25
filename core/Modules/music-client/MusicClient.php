<?php

namespace esc\Modules\MusicClient;

use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\KeyBinds;
use GuzzleHttp\Exception\GuzzleException;

class MusicClient
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $music;

    /**
     * @var \stdClass
     */
    private static $song;

    public function __construct()
    {
        $url = config('music.url');

        if (!$url) {
            self::enableMusicDisabledNotice();

            return;
        }

        try {
            $response = RestClient::get($url);
        } catch (GuzzleException $e) {
            Log::logAddLine('MusicClient', 'Failed to fetch music list from ' . $url);
            self::enableMusicDisabledNotice();

            return;
        }

        if ($response->getStatusCode() != 200) {
            Log::logAddLine('MusicClient', 'Failed to fetch music list from ' . $url);
            self::enableMusicDisabledNotice();

            return;
        }

        $musicJson   = $response->getBody()->getContents();
        self::$music = collect(json_decode($musicJson));

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('EndMap', [self::class, 'setNextSong']);

        ChatCommand::add('/music', [self::class, 'searchMusic'], 'Open and search the music list.');

        KeyBinds::add('reload_music_client', 'Reload music.', [self::class, 'reload'], 'F2', 'ms');
    }

    private function enableMusicDisabledNotice()
    {
        Hook::add('PlayerConnect', function (Player $player) {
            warningMessage('Music server not reachable, custom music is disabled.')->send($player);
        });
    }

    public static function setNextSong(Map $map = null)
    {
        self::$song = self::$music->random(1)->first();
        Server::setForcedMusic(true, config('music.url') . '?song=' . urlencode(self::$song->file));
        $song = json_encode(self::$song);

        if ($song != 'null') {
            Template::showAll('music-client.start-song', compact('song'));
        }
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'music-client.music-client');

        $url = Server::getForcedMusic()->url;

        if ($url) {
            $file = urldecode(preg_replace('/.+\?song=/', '', Server::getForcedMusic()->url));
            $song = json_encode(self::$music->where('file', $file)->first());
        } else {
            $song = json_encode(self::$song);
        }

        if ($song != 'null') {
            Template::showAll('music-client.start-song', compact('song'));
        }
    }

    public static function searchMusic(Player $player, $cmd, ...$arguments)
    {
        $query = implode(' ', $arguments);
        Template::show($player, 'music-client.search-command', compact('query'));
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::playerConnect($player);
    }
}