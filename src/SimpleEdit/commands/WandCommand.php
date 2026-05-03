<?php

namespace SimpleEdit\commands;

use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use SimpleEdit\Main;

class WandCommand extends Command{

    public function __construct(private Main $plugin){
        parent::__construct("wand", "Donne la baguette de sélection", "wand");
        $this->setPermission("simpleedit.command.wand");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        if(!$this->testPermission($sender)){
            return true;
        }

        $sender->getInventory()->addItem(VanillaItems::WOODEN_AXE());
        $sender->sendMessage("§a[SimpleEdit] Baguette donnée (hache en bois).");
        return true;
    }
}
