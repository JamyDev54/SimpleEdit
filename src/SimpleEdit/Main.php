<?php

namespace SimpleEdit;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use SimpleEdit\manager\ChangeManager;
use SimpleEdit\manager\UndoManager;

class Main extends PluginBase implements Listener{
    /** @var Main */
    public static $main;

    private ChangeManager $changeManager;
    private UndoManager $undoManager;
    public const BLOCKS_PER_TICK = 500; // 10 000 blocs/s à 20 TPS

    protected function onEnable():void{
        self::$main = $this;

        $this->changeManager = new ChangeManager();
        $this->undoManager = new UndoManager();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

    }

    public function onInteract(PlayerInteractEvent $event): void{
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if(!$item->equals(VanillaItems::WOODEN_AXE(), false, false)){
            return;
        }

        if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            $this->changeManager->setPos2($player, $block->getPosition());
            $player->sendMessage("§a[SimpleEdit] Position 2 définie: {$block->getPosition()->getFloorX()}, {$block->getPosition()->getFloorY()}, {$block->getPosition()->getFloorZ()}");
            $event->cancel();
            return;
        }

        if($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK){
            $this->changeManager->setPos1($player, $block->getPosition());
            $player->sendMessage("§a[SimpleEdit] Position 1 définie: {$block->getPosition()->getFloorX()}, {$block->getPosition()->getFloorY()}, {$block->getPosition()->getFloorZ()}");
            $event->cancel();
        }
    }

    public function onBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $item = $event->getItem();

        if(!$item->equals(VanillaItems::WOODEN_AXE(), false, false)){
            $event->cancel();
            return;
        }
    }

    public function getChangeManager(): ChangeManager{
        return $this->changeManager;
    }

    public function getUndoManager(): UndoManager{
        return $this->undoManager;
    }

    /**
     * @param array<int, \pocketmine\math\Vector3> $vectors
     */
    private function applyBlockChangesAsync(Player $player, array $vectors, Block $block, string $doneMessage): void{
        $world = $player->getWorld();
        $total = count($vectors);

        if($total === 0){
            $player->sendMessage($doneMessage);
            return;
        }

        $index = 0;
        $this->getScheduler()->scheduleRepeatingTask(new class($player, $world, $vectors, $block, $doneMessage, $total, $index, self::BLOCKS_PER_TICK) extends Task{
            public function __construct(
                private Player $player,
                private \pocketmine\world\World $world,
                private array $vectors,
                private Block $block,
                private string $doneMessage,
                private int $total,
                private int $index,
                private int $blocksPerTick
            ){}

            public function onRun(): void{
                if(!$this->player->isConnected()){
                    $this->getHandler()?->cancel();
                    return;
                }

                $max = min($this->index + $this->blocksPerTick, $this->total);
                for(;$this->index < $max; $this->index++){
                    $this->world->setBlock($this->vectors[$this->index], $this->block);
                }

                if($this->index >= $this->total){
                    $this->player->sendMessage($this->doneMessage);
                    $this->getHandler()?->cancel();
                }
            }
        }, 1);
    }

    /**
     * @param array<int, array{0:\pocketmine\math\Vector3,1:Block}> $snapshot
     */
    private function applySnapshotAsync(Player $player, array $snapshot, string $doneMessage): void{
        $world = $player->getWorld();
        $total = count($snapshot);

        if($total === 0){
            $player->sendMessage($doneMessage);
            return;
        }

        $index = 0;
        $this->getScheduler()->scheduleRepeatingTask(new class($player, $world, $snapshot, $doneMessage, $total, $index, self::BLOCKS_PER_TICK) extends Task{
            public function __construct(
                private Player $player,
                private \pocketmine\world\World $world,
                private array $snapshot,
                private string $doneMessage,
                private int $total,
                private int $index,
                private int $blocksPerTick
            ){}

            public function onRun(): void{
                if(!$this->player->isConnected()){
                    $this->getHandler()?->cancel();
                    return;
                }

                $max = min($this->index + $this->blocksPerTick, $this->total);
                for(;$this->index < $max; $this->index++){
                    [$vec, $block] = $this->snapshot[$this->index];
                    $this->world->setBlock($vec, $block);
                }

                if($this->index >= $this->total){
                    $this->player->sendMessage($this->doneMessage);
                    $this->getHandler()?->cancel();
                }
            }
        }, 1);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        switch(strtolower($command->getName())){
            case "wand":
                if(!$sender->hasPermission("simpleedit.command.wand")){
                    $sender->sendMessage("§cTu n'as pas la permission.");
                    return true;
                }
                $sender->getInventory()->addItem(VanillaItems::WOODEN_AXE());
                $sender->sendMessage("§a[SimpleEdit] Baguette donnée (hache en bois).");
                return true;

            case "change":
                if(!$sender->hasPermission("simpleedit.command.change")){
                    $sender->sendMessage("§cTu n'as pas la permission.");
                    return true;
                }

                $itemInHand = $sender->getInventory()->getItemInHand();
                $block = null;

                if($itemInHand->equals(VanillaItems::WATER_BUCKET(), false, false)){
                    $block = VanillaBlocks::WATER();
                }elseif($itemInHand->equals(VanillaItems::LAVA_BUCKET(), false, false)){
                    $block = VanillaBlocks::LAVA();
                }else{
                    $handBlock = $itemInHand->getBlock();
                    if($handBlock instanceof Block){
                        $block = $handBlock;
                    }
                }

                if($block === null){
                    $sender->sendMessage("§cTiens un bloc (ou un seau d'eau/lave) dans ta main.");
                    return true;
                }

                if(!$this->changeManager->hasSelection($sender)){
                    $sender->sendMessage("§cTu dois d'abord définir pos1 et pos2 avec la wand.");
                    return true;
                }

                $vectors = $this->changeManager->getSelectionVectors($sender);
                if($vectors === []){
                    $sender->sendMessage("§cSélection invalide (mondes différents ?).");
                    return true;
                }

                $this->undoManager->push($sender, $this->changeManager->snapshot($sender));
                $this->applyBlockChangesAsync(
                    $sender,
                    $vectors,
                    $block,
                    "§a[SimpleEdit] " . count($vectors) . " blocs modifiés avec l'item en main."
                );
                return true;

            case "cut":
                if(!$sender->hasPermission("simpleedit.command.cut")){
                    $sender->sendMessage("§cTu n'as pas la permission.");
                    return true;
                }

                if(!$this->changeManager->hasSelection($sender)){
                    $sender->sendMessage("§cTu dois d'abord définir pos1 et pos2 avec la wand.");
                    return true;
                }

                $vectors = $this->changeManager->getSelectionVectors($sender);
                if($vectors === []){
                    $sender->sendMessage("§cSélection invalide (mondes différents ?).");
                    return true;
                }

                $this->undoManager->push($sender, $this->changeManager->snapshot($sender));
                $this->applyBlockChangesAsync(
                    $sender,
                    $vectors,
                    VanillaBlocks::AIR(),
                    "§a[SimpleEdit] " . count($vectors) . " blocs coupés."
                );
                return true;

            case "undo":
                if(!$sender->hasPermission("simpleedit.command.undo")){
                    $sender->sendMessage("§cTu n'as pas la permission.");
                    return true;
                }

                if(!$this->undoManager->canUndo($sender)){
                    $sender->sendMessage("§cAucune action à annuler.");
                    return true;
                }

                $snapshot = $this->undoManager->pop($sender);
                if($snapshot === null){
                    $sender->sendMessage("§cImpossible d'annuler l'action.");
                    return true;
                }

                $this->applySnapshotAsync($sender, $snapshot, "§a[SimpleEdit] Dernière action annulée.");
                return true;
        }

        return false;
    }
}