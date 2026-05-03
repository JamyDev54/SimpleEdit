<?php

namespace SimpleEdit\manager;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class UndoManager{

    /** @var array<string, array<int, array{0:Vector3,1:Block}>> */
    private array $history = [];

    /**
     * @param array<int, array{0:Vector3,1:Block}> $snapshot
     */
    public function push(Player $player, array $snapshot): void{
        $key = strtolower($player->getName());
        $this->history[$key][] = $snapshot;

        if(count($this->history[$key]) > 10){
            array_shift($this->history[$key]);
        }
    }

    public function canUndo(Player $player): bool{
        $key = strtolower($player->getName());
        return !empty($this->history[$key]);
    }

    /**
     * @return array<int, array{0:Vector3,1:Block}>|null
     */
    public function pop(Player $player): ?array{
        $key = strtolower($player->getName());
        if(empty($this->history[$key])){
            return null;
        }

        return array_pop($this->history[$key]);
    }

    public function undo(Player $player): bool{
        $snapshot = $this->pop($player);
        if($snapshot === null){
            return false;
        }

        $world = $player->getWorld();

        foreach($snapshot as [$vec, $block]){
            $world->setBlock($vec, $block);
        }

        return true;
    }
}
