<?php

namespace nasiridrishi\primehologram;

use nasiridrishi\primehologram\animation\Animation;
use nasiridrishi\primehologram\animation\AnimationManager;
use nasiridrishi\primehologram\hologram\Hologram;
use nasiridrishi\primehologram\hook\PrimeHook;
use nasiridrishi\primehologram\hook\PrimePlaceHolderHook;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

class PrimeHologram extends PluginBase{

    private static PrimeHologram $instance;
    private ?PrimePlaceHolderHook  $placeHolderHook = null;

    private AnimationManager $animationManager;

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

    private TaskHandler $taskHandler;

    /**
     * @var Hologram[][]|array<int, array<int, Hologram>>
     */
    private array $hologramWorlds = [];

    protected function onLoad(): void {
        self::$instance = $this;
        $this->animationManager = new AnimationManager();
    }

    /**
     * @throws \JsonException
     */
    protected function onEnable(): void {
        $this->placeHolderHook = $this->findHook("PrimePlaceHolder", PrimePlaceHolderHook::class);
        $this->getServer()->getPluginManager()->registerEvents(new HologramListener($this), $this);
        $this->reloadPlugin();
    }

    /**
     * @throws \JsonException
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if($command->getName() == "holoreload") {
            $this->getLogger()->info(TextFormat::YELLOW . "Reloading PrimeHologram...");
            $this->reloadConfig();
            $this->reloadPlugin();
            $this->getLogger()->info(TextFormat::GREEN . "PrimeHologram reloaded!");
        }
        return true;
    }

    /**
     * @throws \JsonException
     */
    private function reloadPlugin(): void{
        //de-spawn all holograms
        foreach($this->holograms as $hologram) {
            $hologram->despawnFromAll();
        }
        $this->holograms = [];
        $this->hologramWorlds = [];
        $this->animationManager->setAnimations([]);
        $this->loadHolosFromConfig();
        $this->startHologramTicks();
        $this->loadAnimationsFromConfig();

        //show holograms to world players
        foreach($this->holograms as $hologram) {
            foreach($this->getServer()->getOnlinePlayers() as $player) {
                if($player->getWorld()->getId() === $hologram->getWorld()->getId()) {
                    $hologram->spawnTo($player);
                }
            }
        }
    }

    private function startHologramTicks(): void{
        if(isset($this->taskHandler) and !$this->taskHandler->isCancelled()){
            $this->taskHandler->cancel();
        }
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach($this->holograms as $hologram) {
                $hologram->doUpdate();
            }
        }), 20);
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

    public function loadHolosFromConfig(): void{
        $holoDir = $this->getDataFolder() . "holograms" . DIRECTORY_SEPARATOR;
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
                $config = new Config($holoDir . $file, Config::YAML);
                if(!$config->get("enabled", true)) {
                    continue;
                }
                //position: world;0;0;0
                $posString = $config->get("position");
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
                $lines = $config->get("lines");
                $this->registerHologram(new Hologram($position, $lines, $config->get("line-spacing", 0.3)));
            }
        }
    }

    private function loadAnimationsFromConfig(): void{
        $this->saveDefaultConfig();
        $animations = $this->getConfig()->get("animations");
        if(is_array($animations)){
            foreach($animations as $name => $animation){
                $this->animationManager->addAnimation($name, new Animation($animation["lines"]));
            }
        }else{
            $this->getLogger()->warning("Could not load animations from config! Invalid format!");
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

    /**
     * @return AnimationManager
     */
    public function getAnimationManager(): AnimationManager {
        return $this->animationManager;
    }
}