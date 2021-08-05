<?php

namespace SkyBlock\Bluelands\Entity;

use pocketmine\entity\Human;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\math\Vector2;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use pocketmine\Server;

use SkyBlock\Bluelands\Main;
use SkyBlock\Bluelands\Form\SimpleForm;

class KingSlime extends Human{

    private $i = 0;
    private $reverse = false;

    public function __construct(Level $level, CompoundTag $nbt){
     $nbt->setTag(new CompoundTag("Skin", [
      new StringTag("Name", "KingSlime"),
      new ByteArrayTag("Data", base64_decode(file_get_contents(__DIR__ . "/kingslime.txt"))),
      new ByteArrayTag("CapeData", ""),
      new StringTag("GeometryName", "geometry.kingslime"),
      new ByteArrayTag("GeometryData", file_get_contents(__DIR__ . "/geometry.json"))
     ]));
     parent::__construct($level, $nbt);
    }

    public function onUpdate($tick):bool{
     if(!parent::onUpdate($tick) && $this->isClosed()){
      return false;
     }
     $players = 0;
     if(!$this->reverse){
      $this->i += 0.04;
      if($this->i >= 1.6){
       $this->reverse = true;
      }
     }else{
      $this->i -= 0.04;
      if($this->i <= 0){
       $this->reverse = false;
      }
     }
     foreach(Main::getInstance()->getServer()->getOnlinePlayers() as $p){
      if(Main::getInstance()->isSkyBlockLevel($p->getLevel()) && $p->getLevel()->getFolderName() === $this->getLevel()->getFolderName()){
       $pk = new MovePlayerPacket();
       $pk->entityRuntimeId = $this->getId();
       $xdiff = $p->x - $this->x;
       $zdiff = $p->z - $this->z;
       $angle = atan2($zdiff, $xdiff);
       $pk->yaw = (($angle * 180) / M_PI) - 90;
       $ydiff = $p->y - $this->y;
       $vec = new Vector2($this->x, $this->z);
       $dist = $vec->distance($p->x, $p->z);
       $angle = atan2($dist, $ydiff);
       $pk->pitch = (($angle * 180) / M_PI) - 90;
       $pk->position = $this->asLocation()->add(0, 1.62 + $this->i);
       $pk->headYaw = $pk->yaw;
       $p->dataPacket($pk);
       $players++;
      }
     }
     if($players <= 0){
      $this->close();
      return false;
     }
     return true;
    }

    public function hasMovementUpdate():bool{
     return false;
    }

    public function attack(EntityDamageEvent $e):void{
     if(($e instanceof EntityDamageByEntityEvent) && (($p = $e->getDamager()) instanceof Player)){
      $form = new SimpleForm(function(Player $p, $result){
       if($result === null) return;
       switch($result){
        case 0:{
         $command = "skyblock";
         break;
        }
        case 1:{
         $command = "shop";
         break;
        }
        case 2:{
         $command = "eshop";
         break;
        }
        case 3:{
         $command = "job";
         break;
        }
        case 4:{
         $command = "kit";
         break;
        }
        case 5:{
         $command = "hub";
         break;
        }
        case 6:{
         $command = "bank";
         break;
        }
        case 7:{
         $command = "warpui";
         break;
        }
        case 8:{
         $command = "bookshop";
         break;
        }
        case 9:{
         $command = "eshop";
         break;
        }
       }
       Server::getInstance()->getCommandMap()->dispatch($p, $command);
      });
      $form->setTitle("§l§eSKYBLOCK SETTINGS");
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§ePlease select from the menu§r§f!\n".str_repeat("=", 33));
      $form->addButton("§lSKYBLOCK MENU\n§l§6»» §r§f§oTap to next", "textures/ui/icon_recipe_nature");
      $form->addButton("§lSHOP MENU\n§l§6»» §r§f§oTap to next", "textures/ui/MCoin");
      $form->addButton("§lENCHANTER MENU\n§l§6»» §r§f§oTap to next", "textures/ui/anvil_icon");
      $form->addButton("§lJOB\n§l§6»» §r§f§oTap to next", "textures/ui/icon_winter");
      $form->addButton("§lKIT MENU\n§l§6»» §r§f§oTap to next", "textures/ui/recipe_book_icon");
      $form->addButton("§lGO BACK TO HUB\n§l§6»» §r§f§oTap to next", "textures/ui/icon_book_writable");
      $form->addButton("§lBANK\n§l§6»» §r§f§oTap to next", "textures/ui/resistance_effect");
      $form->addButton("§lWARP MENU\n§l§6»» §r§f§oTap to next", "textures/ui/filledStar");
      $form->addButton("§lCE SHOP MENU\n§l§6»» §r§f§oTap to next", "textures/ui/smithing_icon");
      $form->addButton("§lUNIQUE ENCHANT MENU\n§l§6»» §r§f§oTap to next", "textures/ui/haste_effect");
      $p->sendForm($form);
     }
     $e->setCancelled();
    }

}
