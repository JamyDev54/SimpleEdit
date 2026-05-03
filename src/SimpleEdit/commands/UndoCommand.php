<?php

namespace SimpleEdit\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SimpleEdit\Main;

class UndoCommand extends Command{

    public function __construct(private Main $plugin){
        parent::__construct("undo", "Annule la dernière modification", "undo");
        $this->setPermission("simpleedit.command.undo");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        if(!$this->testPermission($sender)){
            return true;
        }

        $undoManager = $this->plugin->getUndoManager();
        if(!$undoManager->canUndo($sender)){
            $sender->sendMessage("§cAucune action à annuler.");
            return true;
        }

        if(!$undoManager->undo($sender)){
            $sender->sendMessage("§cImpossible d'annuler l'action.");
            return true;
        }

        $sender->sendMessage("§a[SimpleEdit] Dernière action annulée.");
        return true;
    }
}
