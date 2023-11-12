<?php

namespace nasiridrishi\primehologram\animation;

use nasiridrishi\primehologram\PrimeHologram;

class AnimationManager {

    private static AnimationManager $instance;

    /**
     * @return AnimationManager
     */
    public static function getInstance(): AnimationManager {
        return self::$instance;
    }

    private array $animations = [];

    public function __construct() {
        self::$instance = $this;
        $this->animations = [];
    }


    public function addAnimation(string $name, Animation $animation): void{
        $name = "animation:" . strtolower($name);
        if(isset($this->animations[$name])){
            PrimeHologram::getInstance()->getLogger()->warning("Tried to register animation with name $name but it already exists");
            return;
        }
        $this->animations[$name] = $animation;
    }

    public function getAnimation(string $name): ?Animation{
        return $this->animations[$name] ?? null;
    }

    public function removeAnimation(string $name): void{
        unset($this->animations[$name]);
    }

    public function getAnimations(): array{
        return $this->animations;
    }

    public function setAnimations(string $c): string {
        if(preg_match_all("/\{([^}]+)\}/", $c, $matches)){
            foreach($matches[1] as $match){
                $animation = $this->getAnimation($match);
                if($animation !== null){
                    $c = str_replace("{" . $match . "}", $animation->getNext(), $c);
                }else{
                    PrimeHologram::getInstance()->getLogger()->warning("Could not find animation with name " . $match);
                }
            }
        }
        return $c;
    }
}