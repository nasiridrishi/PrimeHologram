<?php

namespace nasiridrishi\primehologram\hook;

use nasiridrishi\primeplaceholder\PrimePlaceHolder;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class PrimePlaceHolderHook extends PrimeHook {

    /**
     * @return PrimePlaceHolder
     */
    public function getPlugin(): PluginBase {
        return $this->plugin;
    }

    public function setPlaceHolders(array|string $text, Player $player): array|string {
        return PrimePlaceHolder::getInstance()->setPlaceHolders($text, $player);
    }
}