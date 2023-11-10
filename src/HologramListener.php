<?php

namespace nasiridrishi\primehologram;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;

class HologramListener implements Listener {

    /**
     * @var PrimeHologram
     */
    private PrimeHologram $manager;

    public function __construct(PrimeHologram $manager) {
        $this->manager = $manager;
    }

    /**
     * @param PlayerJoinEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        foreach($this->manager->getHologramsForWorld($event->getPlayer()->getWorld()) as $hologram) {
            $hologram->spawnTo($event->getPlayer());
        }
    }

    /**
     * @param EntityTeleportEvent $event
     *
     * @priority MONITOR
     */
    public function onLevelChange(EntityTeleportEvent $event): void {
        $player = $event->getEntity();
        if($event->getTo()->getWorld()->getId() === $event->getFrom()->getWorld()->getId()) {
            return;
        }
        if($player instanceof Player) {
            foreach($this->manager->getHologramsForWorld($event->getFrom()->getWorld()) as $old) {
                $old->despawnFrom($player);
            }

            foreach($this->manager->getHologramsForWorld($event->getTo()->getWorld()) as $new) {
                $new->spawnTo($player);
            }
        }
    }

}