<?php

namespace SimpleEdit\commands;

use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SimpleEdit\Main;

class CutCommand extends Command{

    public function __construct(private Main $plugin){
        parent::__construct("cut", "Coupe la sélection (air)", "cut");
        $this->setPermission("simpleedit.command.cut");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        if(!$this->testPermission($sender)){
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
            $world->setBlock($vec, VanillaBlocks::AIR());
        }

        $sender->sendMessage("§a[SimpleEdit] " . count($vectors) . " blocs coupés.");
        return true;
    }
}
