<?php

namespace SimpleEdit\commands;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use SimpleEdit\Main;

class ChangeCommand extends Command{

    public function __construct(private Main $plugin){
        parent::__construct("change", "Change les blocs de la sélection avec le bloc en main", "change");
        $this->setPermission("simpleedit.command.change");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        if(!$this->testPermission($sender)){
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

        $changeManager = $this->plugin->getChangeManager();
        if(!$changeManager->hasSelection($sender)){
            $sender->sendMessage("§cTu dois d'abord définir pos1 et pos2 avec la wand.");
            return true;
        }

        $vectors = $changeManager->getSelectionVectors($sender);
        if($vectors === []){
            $sender->sendMessage("§cSélection invalide (mondes différents ?).");
            return true;
        }

        $this->plugin->getUndoManager()->push($sender, $changeManager->snapshot($sender));

        $world = $sender->getWorld();
        foreach($vectors as $vec){
            $world->setBlock($vec, $block);
        }

        $sender->sendMessage("§a[SimpleEdit] " . count($vectors) . " blocs modifiés avec l'item en main.");
        return true;
    }
}
