<?php

namespace nasiridrishi\primehologram\animation;

class Animation {

    private array $frames = [];

    private int $currentFrame = 0;

    public function __construct(array $frames) {
        $this->frames = $frames;
    }

    public function getFrames(): array {
        return $this->frames;
    }

    public function getNext(): string{
        $frame = $this->frames[$this->currentFrame];
        $this->currentFrame++;
        if($this->currentFrame >= count($this->frames)){
            $this->currentFrame = 0;
        }
        return $frame;
    }
}