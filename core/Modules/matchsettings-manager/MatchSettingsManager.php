<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MatchSettings;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\TemplateController;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MatchSettingsManager
{
    private static $path;
    private static $objects;

    public function __construct()
    {
        self::$path    = Server::getMapsDirectory() . '/MatchSettings/';
        self::$objects = collect();

        AccessRight::createIfNonExistent('ms_edit', 'Change match-settings.');

        ChatCommand::add('//ms', [self::class, 'showMatchSettingsOverview'], 'Show MatchSettingsManager', 'ms_edit');

        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchSetting'], 'ms_edit');
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchSettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchSettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.overview', [self::class, 'showMatchSettingsOverview'], 'ms_edit');
        ManiaLinkEvent::add('msm.save', [self::class, 'saveMatchSettings'], 'ms_edit');

        ManiaLinkEvent::add('msm.edit', [self::class, 'editMatchSettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit_mss', [self::class, 'editModeScriptSettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit_maps', [self::class, 'editMaps'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit_gameinfo', [self::class, 'editGameInfo'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit_filter', [self::class, 'editFilter'], 'ms_edit');
        ManiaLinkEvent::add('msm.update', [self::class, 'updateMatchSettings'], 'ms_edit');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'MatchSetting Manager', 'msm.overview', 'map.edit');
        }

//        KeyController::createBind('Y', [self::class, 'reload']);
    }

    /**
     * Debug: reload
     *
     * @param Player $player
     */
    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        $settings = preg_replace('/\.txt$/', '', MatchSettingsManager::getMatchSettings()->first());
        MatchSettingsManager::editMatchSettings($player, $settings);
    }

    /**
     * Show the match settings overview window
     *
     * @param Player $player
     */
    public static function showMatchSettingsOverview(Player $player)
    {
        TemplateController::loadTemplates();

        $settings = self::getMatchSettings()->map(function (String $file) {
            return preg_replace('/\.txt$/', '', $file);
        });

        Template::show($player, 'matchsettings-manager.overview', compact('settings'));
    }

    /**
     * Get all match settings files
     *
     * @return Collection
     */
    public static function getMatchSettings(): Collection
    {
        $files = File::getDirectoryContents(self::$path, '/\.txt$/');

        return $files;
    }

    /**
     * Creates MatchSettings instance and opens editor window
     *
     * @param Player $player
     * @param string $matchSettingsFile
     */
    public static function editMatchSettings(Player $player, string $matchSettingsFile)
    {
        $content = File::get(self::$path . $matchSettingsFile . '.txt');
        $xml     = new \SimpleXMLElement($content);
        $ms      = new MatchSettings($xml, uniqid(), $matchSettingsFile . '.txt');

        self::$objects->push($ms);
        self::editGameInfo($player, $ms->id);
    }

    /**
     * Show edit mode script settings window
     *
     * @param Player $player
     * @param string $reference
     */
    public static function editModeScriptSettings(Player $player, string $reference)
    {
        $ms = self::getMatchSettingsObject($reference);

        $modeScriptSettings = collect();
        foreach ($ms->xml->mode_script_settings->setting as $setting) {
            $modeScriptSettings->push([
                'name'  => $setting['name'] . "",
                'value' => $setting['value'] . "",
                'type'  => $setting['type'] . "",
            ]);
        }

        $modeScriptSettings = $modeScriptSettings->sortBy('type', SORT_REGULAR, SORT_DESC)->split(2);

        Template::show($player, 'matchsettings-manager.edit-modescript-settings', compact('ms', 'modeScriptSettings'));
    }

    /**
     * Show edit game info window
     *
     * @param Player $player
     * @param string $reference
     */
    public static function editGameInfo(Player $player, string $reference)
    {
        $ms = self::getMatchSettingsObject($reference);

        $gameInfos = collect();
        foreach ($ms->xml->gameinfos->children() as $node) {
            $gameInfos->put($node->getName(), sprintf("%s", $node));
        }

        Template::show($player, 'matchsettings-manager.edit-gameinfo', compact('ms', 'gameInfos'));
    }

    /**
     * Show edit filter window
     *
     * @param Player $player
     * @param string $reference
     */
    public static function editFilter(Player $player, string $reference)
    {
        $ms = self::getMatchSettingsObject($reference);

        $filter = collect();
        foreach ($ms->xml->filter->children() as $node) {
            $filter->put($node->getName(), sprintf("%s", $node));
        }

        Template::show($player, 'matchsettings-manager.edit-filter', compact('ms', 'filter'));
    }

    /**
     * Show edit enabled maps window
     *
     * @param Player $player
     * @param string $reference
     */
    public static function editMaps(Player $player, string $reference)
    {
        $ms = self::getMatchSettingsObject($reference);

        $enabledMaps = collect();
        foreach ($ms->xml->map as $map) {
            $enabledMaps->push("$map->ident");
        }

        //Get currently enabled maps
        $enabledMaps = Map::whereIn('uid', $enabledMaps)->get();

        //Get currently disabled maps
        $disabledMaps = Map::all()->diff($enabledMaps);

        //make MS compatible
        $enabledMaps  = $enabledMaps->map(function (Map $map) {
            return sprintf('["id"=>"%s", "name"=>"%s", "login"=>"%s", "enabled"=>"%d"]', $map->id, $map->gbx->Name, $map->gbx->AuthorLogin, 1);
        });
        $disabledMaps = $disabledMaps->map(function (Map $map) {
            return sprintf('["id"=>"%s", "name"=>"%s", "login"=>"%s", "enabled"=>"%d"]', $map->id, $map->gbx->Name, $map->gbx->AuthorLogin, 0);
        });

        //Attach disabled at end of enabled maps
        $maps = $enabledMaps->concat($disabledMaps);

        //Make maniascript array
        $mapsArray = '[' . $maps->implode(',') . ']';

        Template::show($player, 'matchsettings-manager.edit-maps', compact('ms', 'maps', 'mapsArray'));
    }

    /**
     * Returns MatchSettings instance by reference
     *
     * @param string $reference
     *
     * @return MatchSettings
     */
    public static function getMatchSettingsObject(string $reference): MatchSettings
    {
        return self::$objects->where('id', $reference)->first();
    }

    /**
     * Route update value request to MatchSettings
     *
     * @param Player $player
     * @param string $reference
     * @param string ...$cmd
     */
    public static function updateMatchSettings(Player $player, string $reference, string ...$cmd)
    {
        $matchSettings = self::getMatchSettingsObject($reference);
        $matchSettings->handle($player, ...$cmd);
    }

    /**
     * Delete match settings file
     *
     * @param Player $player
     * @param string $matchSettingsFile
     */
    public static function deleteMatchSetting(Player $player, string $matchSettingsFile)
    {
        $file = self::$path . $matchSettingsFile . '.txt';
        File::delete($file);
        self::showMatchSettingsOverview($player);

        Log::logAddLine('MatchSettingsManager', "$player deleted MatchSettingsFile: $matchSettingsFile");
    }

    /**
     * Load match settings into server
     *
     * @param \esc\Models\Player $player
     * @param string             $matchSettingsFile
     * @param bool               $silent
     */
    public static function loadMatchSettings(Player $player, string $matchSettingsFile, bool $silent = false)
    {
        $file = 'MatchSettings/' . $matchSettingsFile . '.txt';
        Server::loadMatchSettings($file);

        $xml         = new \SimpleXMLElement(File::get(self::$path . $file));
        $enabledMaps = collect();
        foreach ($xml->map as $map) {
            $enabledMaps->push("$map->ident");
        }

        Map::whereNotNull('uid')->update([
            'enabled' => 0,
        ]);
        Map::whereIn('uid', $enabledMaps)->update([
            'enabled' => 1,
        ]);

        //Update maps
        onlinePlayers()->each([MapList::class, 'sendManialink']);

        if (!$silent) {
            infoMessage($player, ' loads new settings ', secondary($matchSettingsFile))->sendAll();
        }
        Log::logAddLine('MatchSettingsManager', "$player loads MatchSettings: $matchSettingsFile");
    }

    /**
     * Duplicate match settings file
     *
     * @param Player $player
     * @param string $name
     */
    public static function duplicateMatchSettings(Player $player, string $name)
    {
        $files = self::getMatchSettings();

        //check for existing copy
        $copyName = $files->map(function (string $file) use ($name) {
            if (preg_match("/$name - Copy \((\d+)\)/", $file, $matches)) {
                return $name . ' - Copy (' . (intval($matches[1]) + 1) . ')';
            }
        })->filter()->last();

        if (!$copyName) {
            //no existing copy, create first
            $copyName = $name . ' - Copy (1)';
        }

        $originalFile = self::$path . $name . '.txt';
        File::put(self::$path . $copyName . '.txt', File::get($originalFile));

        //update the manialink
        self::showMatchSettingsOverview($player);

        Log::logAddLine('MatchSettingsManager', "$player duplicated MatchSettingsFile: $name.txt");
    }
}