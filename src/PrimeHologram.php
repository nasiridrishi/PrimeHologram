<?php

/**
 *  Copyright (C) PrimeGames - All Rights Reserved
 *  Unauthorized copying of this file, via any medium is strictly prohibited
 *  Proprietary and confidential
 */

declare(strict_types=1);

namespace nasiridrishi\primehologram;

use nasiridrishi\primeplaceholder\PrimePlaceHolder;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

class PrimeHologram extends PluginBase{

    private static PrimeHologram $instance;

    private ?PrimePlaceHolder $placeholder = null;

    /**
     * @return PrimeHologram
     */
    public static function getInstance(): PrimeHologram {
        return self::$instance;
    }

    /**
     * @var Hologram[]
     */
    private array $holograms = [];

    /**
     * @var Hologram[][]|array<int, array<int, Hologram>>
     */
    private array $hologramWorlds = [];

    protected function onLoad(): void {
        self::$instance = $this;
    }

    /**
     * @throws \JsonException
     */
    protected function onEnable(): void {

        $this->placeholder = $this->findPlugins("PrimePlaceHolder");

        $this->getServer()->getPluginManager()->registerEvents(new HologramListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach($this->holograms as $hologram) {
                $hologram->doUpdate();
            }
        }), 20);
        $this->fromConfig();
    }

    /**
     * Register a hologram object to the manager.
     *
     * @param Hologram $hologram
     */
    public function registerHologram(Hologram $hologram): void {
            $this->holograms[$id = $hologram->getId()] = $hologram;
            $this->hologramWorlds[$hologram->getWorld()->getId()][$id] = $hologram;
    }

    /**
     * @param World $world
     *
     * @return Hologram[]|array<int, Hologram>
     */
    public function getHologramsForWorld(World $world): array {
        return $this->hologramWorlds[$world->getId()] ?? [];
    }

    /**
     * Remove a hologram object from the manager.
     *
     * @param Hologram $hologram
     */
    public function removeHologram(Hologram $hologram): void {
        if(isset($this->holograms[$id = $hologram->getId()])) {
            unset($this->holograms[$id], $this->hologramWorlds[$hologram->getWorld()->getId()][$id]);
        }
    }

    /**
     * @throws \JsonException
     */
    public function fromConfig(): void{
        $holoDir = $this->getDataFolder() . "holograms/";
        if(!is_dir($holoDir)) {
            mkdir($holoDir);
        }
        //get all .yml files in the hologram directory
        $files = array_diff(scandir($holoDir), ['.', '..']);
        if(empty($files)){
            $this->getLogger()->info(TextFormat::YELLOW ."No holograms found! Creating default hologram!");
            $this->getLogger()->info(TextFormat::YELLOW ."You can edit the default hologram in the holograms folder!");
            $this->getLogger()->info(TextFormat::YELLOW ."Or you can create your own holograms using the default.yml as an example!");
            $this->saveResource("holograms/default.yml");
        }else{
            foreach($files as $file) {
                $config = yaml_parse_file($holoDir . $file);
                if($config === false) {
                    $this->getLogger()->warning("Could not load hologram " . $file . "!");
                    continue;
                }
                if(!$config["enabled"]) {
                    continue;
                }
                //position: world;0;0;0
                $posString = $config["position"];
                $posArray = explode(";", $posString);
                $world = Server::getInstance()->getWorldManager()->getWorldByName($posArray[0]);
                if($world === null) {
                    $this->getLogger()->warning("Could not load hologram " . $file . "! World " . $posArray[0] . " not found!");
                    continue;
                }
                try {
                    $position = new Position((float) $posArray[1], (float) $posArray[2], (float) $posArray[3], $world);
                } catch(\Exception) {
                    $this->getLogger()->warning("Could not load hologram " . $file . "! Invalid position format!");
                    continue;
                }
                $lines = $config["lines"];
                $text = "";
                foreach($lines as $line) {
                    $text .= $line . "\n";
                }
                $this->registerHologram(new Hologram($position, $text));
            }
        }
    }

    private function findPlugins(string $name, bool $required = false): ?Plugin {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin($name);
        if($plugin === null){
            if($required){
                $this->getLogger()->error("Could not find plugin " . $name . "!");
                $this->getServer()->getPluginManager()->disablePlugin($this);
            }
            return null;
        }
        $this->getLogger()->info("Found supported plugin " . $name . "!");
        return $plugin;
    }

    /**
     * @return PrimePlaceHolder|null
     */
    public function getPlaceholder(): ?PrimePlaceHolder {
        return $this->placeholder;
    }
}