<?php

namespace SimpleEdit\manager;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

class ChangeManager{

    /** @var array<string, Position> */
    private array $pos1 = [];

    /** @var array<string, Position> */
    private array $pos2 = [];

    public function setPos1(Player $player, Position $position): void{
        $this->pos1[strtolower($player->getName())] = $position;
    }

    public function setPos2(Player $player, Position $position): void{
        $this->pos2[strtolower($player->getName())] = $position;
    }

    public function hasSelection(Player $player): bool{
        $key = strtolower($player->getName());
        return isset($this->pos1[$key], $this->pos2[$key]);
    }

    /**
     * @return array{minX:int,minY:int,minZ:int,maxX:int,maxY:int,maxZ:int}|null
     */
    public function getBounds(Player $player): ?array{
        $key = strtolower($player->getName());
        if(!isset($this->pos1[$key], $this->pos2[$key])){
            return null;
        }

        $a = $this->pos1[$key];
        $b = $this->pos2[$key];

        if($a->getWorld()->getFolderName() !== $b->getWorld()->getFolderName()){
            return null;
        }

        return [
            "minX" => min($a->getFloorX(), $b->getFloorX()),
            "minY" => min($a->getFloorY(), $b->getFloorY()),
            "minZ" => min($a->getFloorZ(), $b->getFloorZ()),
            "maxX" => max($a->getFloorX(), $b->getFloorX()),
            "maxY" => max($a->getFloorY(), $b->getFloorY()),
            "maxZ" => max($a->getFloorZ(), $b->getFloorZ()),
        ];
    }

    /**
     * @return Vector3[]
     */
    public function getSelectionVectors(Player $player): array{
        $bounds = $this->getBounds($player);
        if($bounds === null){
            return [];
        }

        $vectors = [];
        for($x = $bounds["minX"]; $x <= $bounds["maxX"]; $x++){
            for($y = $bounds["minY"]; $y <= $bounds["maxY"]; $y++){
                for($z = $bounds["minZ"]; $z <= $bounds["maxZ"]; $z++){
                    $vectors[] = new Vector3($x, $y, $z);
                }
            }
        }
        return $vectors;
    }

    /**
     * @return array<int, array{0:Vector3,1:Block}>
     */
    public function snapshot(Player $player): array{
        $world = $player->getWorld();
        $snapshot = [];

        foreach($this->getSelectionVectors($player) as $vec){
            $snapshot[] = [$vec, $world->getBlock($vec)];
        }

        return $snapshot;
    }
}
