<?php

namespace nasiridrishi\primehologram\hologram;

use JsonException;
use nasiridrishi\primehologram\animation\AnimationManager;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function str_repeat;

class Hologram {

    private static $hid = 0;

    /** @var Player[] */
    private array $spawnedTo = [];

    /**
     * @var HologramLine[]
     */
    private array $lines = [];

    private int $id;


    /**
     * @param Position $position
     * @param string[] $lines
     */
    public function __construct(private Position $position, array $lines, private $lineSpacing = 0.3) {
        $lineNum = 1;
        foreach($lines as $k => $line) {
            $newPos = clone $position;
            $newPos->y -= $lineNum * $this->lineSpacing;
            $this->lines[$k] = new HologramLine($this, $newPos, $line);
            $lineNum++;
        }
        $this->id = self::$hid++;
    }

    public function getWorld(): World {
        return $this->position->getWorld();
    }

    /**
     * @return Position
     */
    public function getPosition(): Position {
        return $this->position;
    }

    public function doUpdate(): void {
        foreach($this->spawnedTo as $player) {
            $this->updateFor($player);
        }
    }

    /**
     * Update the hologram for a player.
     *
     * @param Player $player
     */
    public function updateFor(Player $player): void {
        if(!isset($this->spawnedTo[spl_object_id($player)]) or !$player->isOnline()) {
            return;
        }
        foreach($this->lines as $line){
            $line->updateFor($player);
        }
    }

    /**
     * @return array
     */
    public function getSpawnedTo(): array {
        return $this->spawnedTo;
    }

    /**
     * Spawn the hologram to a player.
     *
     * @param Player $player
     */
    public function spawnTo(Player $player): void {
        if(isset($this->spawnedTo[spl_object_id($player)]) or !$player->isOnline()) {
            return;
        }
        $this->spawnedTo[spl_object_id($player)] = $player;
        foreach($this->lines as $line){
            $line->spawnTo($player);
        }
    }

    /**
     * Despawn the hologram from a player.
     *
     * @param Player $player
     */
    public function despawnFrom(Player $player): void {
        if(!isset($this->spawnedTo[spl_object_id($player)]) or !$player->isOnline()) {
            return;
        }
        foreach($this->lines as $line){
            $line->despawnFrom($player);
        }
        unset($this->spawnedTo[spl_object_id($player)]);
    }

    public function deSpawnFromAll(): void {
        foreach(Server::getInstance()->getOnlinePlayers() as $player) {
            $this->despawnFrom($player);
        }
    }

    public function getId(): int {
        return $this->id;
    }

}
