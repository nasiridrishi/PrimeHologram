<?php

namespace nasiridrishi\primehologram;

use nasiridrishi\primehologram\hook\PrimeHook;
use nasiridrishi\primehologram\hook\PrimePlaceHolderHook;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

class PrimeHologram extends PluginBase{

    private static PrimeHologram $instance;
    private ?PrimePlaceHolderHook  $placeHolderHook = null;

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

        $this->placeHolderHook = $this->findHook("PrimePlaceHolder", PrimePlaceHolderHook::class);

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

    private function findHook(string $pluginName, string $hookClass, bool $required = false): ?PrimeHook {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName);
        if($plugin === null){
            if($required){
                $this->getLogger()->error("Could not find plugin " . $pluginName . "!");
                $this->getServer()->getPluginManager()->disablePlugin($this);
            }
            return null;
        }
        $this->getLogger()->info("Found supported plugin " . $pluginName . "!");
        return new $hookClass($plugin);
    }

    /**
     * @return PrimePlaceHolderHook|null
     */
    public function getPlaceHolderHook(): ?PrimePlaceHolderHook {
        return $this->placeHolderHook;
    }
}