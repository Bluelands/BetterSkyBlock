<?php

namespace SkyBlock\Bluelands;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\Position;
use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest;
use pocketmine\level\Level;
use pocketmine\event\Event;
use pocketmine\item\Item;
use pocketmine\utils\UUID;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\math\Vector3;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;

use SkyBlock\Bluelands\Entity\KingSlime;
use SkyBlock\Bluelands\Task\Scoreboard;
use SkyBlock\Bluelands\Form\SimpleForm;
use SkyBlock\Bluelands\Form\CustomForm;

class Main extends PluginBase implements Listener{

    private static $instance;

    public $index = array();
    public $eid = array();
    public $mode = array();
    public $prefix = "§9§l§oSkyBlock§r§f: §6§o";

    public function onEnable(){
     $this->getLogger()->info("SkyBlock plugin made by Bluelands_MC");
     self::$instance = $this;
     $this->saveDefaultConfig();
     $this->reloadConfig();
     $this->data = new Config($this->getDataFolder()."skyblock.yml", Config::YAML, array());
     GeneratorManager::addGenerator(SkyBlockGenerator::class, "basic", true);
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
     Entity::registerEntity(KingSlime::class, true);
     $this->getScheduler()->scheduleRepeatingTask(new Scoreboard($this), 20);
    }

    public static function getInstance():Main{
     return self::$instance;
    }

    public function onCommand(CommandSender $p, Command $command, string $label,array $args):bool{
     if($command->getName() !== "is") return false;
     if(!$p instanceof Player){
      $p->sendMessage($this->prefix."Please use this command in game!§r§f!");
      return false;
     }
     if(isset($args[0]) && $args[0] === "leaderboard"){
      if(!$p->isOp()){
       $p->sendMessage($this->prefix."You do not have permission to use this command§r§f!");
       return false;
      }
      $this->mode[$p->getName()][$p->getLevel()->getFolderName()] = 3;
      $p->sendMessage($this->prefix."You do not have permission to use this command§r§f!");
      return true;
     }
     if(!$this->hasSkyBlockIsland($p)){
      $this->createSkyBlock($p);
     }else{
      $this->menuSkyBlock($p);
     }
     return true;
    }

    public function createSkyBlock(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->chooseNewIsland($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eSKYBLOCK");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eYou have not created a Skyblock island§r§f, please create it first in order to play§r§f\n".str_repeat("=", 33));
     $form->addButton("§lCREATE ISLAND\n§l§e»» §r§f§oTap to confirm", "textures/ui/icon_recipe_nature");
     $p->sendForm($form);
    }

    public function chooseNewIsland(Player $p){
     $models = $this->getConfig()->get("models-island");
     $options = [];
     foreach($models as $name => $data){
      $options[] = [["§l".ucwords(str_replace("-", " ", $name))." Model\n§l§9»» §r§f§oTap to create", $data["image"]], [$name, $data]];
     }
     $form = new SimpleForm(function(Player $p, $result) use ($options){
      if($result === null) return;
      $datas = [];
      foreach($options as $option){
       $datas[] = $option[1];
      }
      $this->confirmMenu($p, "create", [$datas[$result][0], $datas[$result][1]]);
     });
     $form->setTitle("§l§eChoose SkyBlock Island");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select the Skyblock island model you will be creating§r§f!\n".str_repeat("=", 33));
     $islands = [];
     foreach($options as $option){
      $islands[] = $option[0];
     }
     foreach($islands as $island){
      $form->addButton($island[0], $island[1], "url");
     }
     $p->sendForm($form);
    }

    public function menuSkyBlock(Player $p){
     if(!empty(($data = $this->data->get($p->getName())))){
      $form = new SimpleForm(function(Player $p, $result) use ($data){
       if($result === null) return;
       switch($result){
        case 0:{
         if(!$this->teleportToIsland($p, $p->getName(), $data)){
          $p->sendMessage($this->prefix."Your SkyBlock World Island cannot be loaded§r§f, §eplease delete and re-create§r§f!");
          break;
         }
         $p->sendMessage($this->prefix."You have successfully teleported to your own SkyBlock island§r§f.");
         break;
        }
        case 1:{
         if(!$this->infoIslandMenu($p)) $p->sendMessage($this->prefix."Your SkyBlock Island data was not found§r§f!");
         break;
        }
        case 2:{
         $this->friendsMenu($p);
         break;
        }
        case 3:{
         $this->settingMenu($p);
         break;
        }
        case 4:{
         $this->confirmMenu($p, "delete");
         break;
        }
       }
      });
      $form->setTitle("§l§eSkyBlock Menu");
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select from the menu§r§f!\n".str_repeat("=", 33));
      $form->addButton("§lTELEPORT ISLAND\n§l§6»» §r§fTap to teleport", "textures/ui/icon_recipe_item");
      $form->addButton("§lINFO ISLAND\n§l§6»» §r§fTap to see", "textures/ui/creative_icon");
      $form->addButton("§lFRIENDS ISLAND\n§l§6» §r§fTap to next", "textures/ui/FriendsIcon");
      $form->addButton("§lSETTING ISLAND\n§l§6»» §r§fTap to set", "textures/ui/accessibility_glyph_color");
      $form->addButton("§lREMOVE ISLAND\n§l§6»» §r§fTap to remove", "textures/ui/trash");
      $p->sendForm($form);
     }else{
      $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §6Please re-island or Redo your island§r§f!");
     }
    }

    public function infoIslandMenu(Player $p):bool{
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->menuSkyBlock($p);
        break;
       }
      }
     });
     if(!empty(($data = $this->data->get($p->getName())))){
      $form->setTitle("§l§eIsland Info Menu");
      $friend = "empty";
      if(!empty($data["friends"]) && is_array($data["friends"])){
       $friend = "";
       $i = 1;
       foreach($data["friends"] as $name){
        $friend .= "\n  §r§f".$i."). §7§o".$name."§r";
        $i++;
       }
      }
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r§f\n                ".str_repeat("-", 13)."\n§l§6»» §r§eBelow is your island info§r§f:\n".str_repeat("=", 33)."\n- §e§oWelcome Message§r§f:§7\n  ".$data["welcome"]."§r§f\n- §e§oLock Island§r§f:§7 ".($data["lock"] ? "on" : "off")."§r§f\n- §e§oPvP Active§r§f:§7 ".($data["pvp"] ? "on" : "off")."§r§f\n- §e§oPoints§r§f:§7 ".$data["points"]."§r§f\n- §e§oFriends§r§f:§7 ".$friend."\n§r§f".str_repeat("=", 33));
      $form->addButton("§lBACK TO MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
      $p->sendForm($form);
      return true;
     }
     return false;
    }

    public function friendsMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->addFriendMenu($p);
        break;
       }
       case 1:{
        $this->removeFriendMenu($p);
        break;
       }
       case 2:{
        $this->visitFriendMenu($p);
        break;
       }
       case 3:{
        $this->menuSkyBlock($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eFriends Menu");
     $form->setContent(str_repeat("=", 33)."\n                   §9§l§oSky Block§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§ePlease select from the menu§r§f, §6or return to the previous menu§r§f!\n".str_repeat("=", 33));
     $form->addButton("§lADD FRIEND\n§l§6»» §r§f§oTap to add", "textures/ui/confirm");
     $form->addButton("§lREMOVE FRIEND\n§l§6»» §r§f§oTap to remove", "textures/ui/cancel");
     $form->addButton("§lVISIT FRIEND ISLAND\n§l§6»» §r§f§oTap to visit", "textures/ui/conduit_power_effect");
     $form->addButton("§lBACK TO MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function addFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->addFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->addFriendViaOnlineList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eAdd Friends Menu");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select via whether to search for the player name that you will make friends with§r§f! §6or return to the previous menu§r§f.\n".str_repeat("=", 33));
     $form->addButton("§lVIA INPUT THE NAME\n§l§6»» §r§f§oTap to next", "textures/ui/confirm");
     $form->addButton("§lVIA ONLINE LIST\n§l§9»» §r§f§oTap to next", "textures/ui/confirm");
     $form->addButton("§lBACK TO FRIENDS MENU\n§l§9»» §r§f§oTap to back", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function addFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Please enter the name of the player you want to add friends to§r§f!");
       return;
      }
      $this->addFriend($p, $result[0]);
     });
     $form->setTitle("§e§lAdd Friends Menu");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease enter the name of the player that you will make friends with§r§f! §6make sure the player you will make friends is not offline§r§f!\n".str_repeat("=", 33)."\n§l§e»» §r§6New Friend Name§r§f:");
     $p->sendForm($form);
    }

    public function addFriendViaOnlineList(Player $p){
     $players = [];
     foreach($this->getServer()->getOnlinePlayers() as $player){
      if($player->getName() !== $p->getName()){
       $players[] = $player->getName();
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($players){
      if($result === null) return;
      if(empty($players)) return;
      $this->addFriend($p, $players[$result[0]]);
     });
     $form->setTitle("§e§lAdd Friends Menu");
     if(empty($players)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eIt looks like there are no other players online so nothing can be added as your friend§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select the player that you will make friends with§r§f! §6make sure the player you will make friends is not offline§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oSelect New Friend§r§f:", $players);
     }
     $p->sendForm($form);
    }

    public function removeFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->removeFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->removeFriendViaFriendList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eRemove Friends Menu");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Please select through whether to search for the friend's name that you want to remove from your friends list§r§f! §6or return to the previous menu§r§f.\n".str_repeat("=", 33));
     $form->addButton("§lVIA INPUT THE NAME\n§l§6»» §r§f§oTap to next", "textures/ui/cancel");
     $form->addButton("§lVIA FRIEND LIST\n§l§6»» §r§f§oTap to next", "textures/ui/cancel");
     $form->addButton("§lBACK TO FRIENDS MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function removeFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Please enter the friend's name that you want to delete as a friend§r§f!");
       return;
      }
      $this->removeFriend($p, $result[0]);
     });
     $form->setTitle("§e§lRemove Friends Menu");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§ePlease enter the friend's name that you want to delete as a friend§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oRemove Friend Name§r§f:");
     $p->sendForm($form);
    }

    public function removeFriendViaFriendList(Player $p){
     $friends = [];
     if(!empty(($data = $this->data->get($p->getName())))){
      foreach($data["friends"] as $friend){
       $friends[] = $friend;
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($friends){
      if($result === null) return;
      if(empty($friends)) return;
      $this->removeFriend($p, $friends[$result[0]]);
     });
     $form->setTitle("§e§lRemove Friends Menu");
     if(empty($friends)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eLooks like you don't have any friends to remove from your friendship§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select the friend you wish to delete as a friend§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oSelect Remove Friend§r§f:", $friends);
     }
     $p->sendForm($form);
    }

    public function visitFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->visitFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->visitFriendViaFriendList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eVisit Friends Menu");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Please select via whether to search for the name of the friend whose SkyBlock island you wish to visit§r§f! §6or return to the previous menu§r§f.\n".str_repeat("=", 33));
     $form->addButton("§lVIA INPUT THE NAME\n§l§6»» §r§f§oTap to next", "textures/ui/conduit_power_effect");
     $form->addButton("§lVIA FRIEND LIST\n§l§6»» §r§f§oTap to next", "textures/ui/conduit_power_effect");
     $form->addButton("§lBACK TO FRIENDS MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function visitFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Please enter the name of the friend you wish to visit the SkyBlock island§r§f!");
       return;
      }
      $this->visitFriend($p, $result[0]);
     });
     $form->setTitle("§e§lVisit Friends Menu");
     $form->addInput(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Please enter the name of the friend you wish to visit the SkyBlock island§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oVisit Friend Name§r§f:");
     $p->sendForm($form);
    }

    public function visitFriendViaFriendList(Player $p){
     $friends = [];
     if(!empty(($data = $this->data->get($p->getName())))){
      foreach($data["friends"] as $friend){
       $friends[] = $friend;
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($friends){
      if($result === null) return;
      if(empty($friends)) return;
      $this->visitFriend($p, $friends[$result[0]]);
     });
     $form->setTitle("§e§lVisit Friends Menu");
     if(empty($friends)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eLooks like you don't have friends for you to visit the SkyBlock island§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease select the friends you want to visit the SkyBlock island§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oSelect Visit Friend§r§f:", $friends);
     }
     $p->sendForm($form);
    }

    public function settingMenu(Player $p){
     if(!empty(($data = $this->data->get($p->getName())))){
      $form = new SimpleForm(function(Player $p, $result) use ($data){
       if($result === null) return;
       switch($result){
        case 0:{
         $this->setWelcomeMenu($p);
         break;
        }
        case 1:{
         if(!$data["lock"]){
          $this->data->setNested($p->getName().".lock", true);
          $this->data->save();
          $p->sendMessage($this->prefix."You have successfully locked your SkyBlock Island§r§f.");
         }else{
          $this->data->setNested($p->getName().".lock", false);
          $this->data->save();
          $p->sendMessage($this->prefix."You have successfully unlocked your SkyBlock island§r§f.");
         }
         $this->settingMenu($p);
         break;
        }
        case 2:{
         if(!$data["pvp"]){
          $this->data->setNested($p->getName().".pvp", true);
          $this->data->save();
          $p->sendMessage($this->prefix."You have successfully activated pvp on your SkyBlock island§r§f.");
         }else{
          $this->data->setNested($p->getName().".pvp", false);
          $this->data->save();
          $p->sendMessage($this->prefix."You have successfully disabled pvp on your SkyBlock island§r§f.");
         }
         $this->settingMenu($p);
         break;
        }
        case 3:{
         $this->menuSkyBlock($p);
         break;
        }
       }
      });
      if(!$data["lock"]){
       $lockText = "§cOFF";
      }else{
       $lockText = "§aON";
      }
      if(!$data["pvp"]){
       $pvpText = "§cOFF";
      }else{
       $pvpText = "§aON";
      }
      $form->setTitle("§l§eSetting Menu");
      $form->setContent(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Please choose which one you want to change from your SkyBlock island data§r§f! §6or return to the SkyBlock menu§r§f.\n".str_repeat("=", 33));
      $form->addButton("§lWELCOME MESSAGE\n§l§6»» §r§f§oTap to set", "textures/ui/comment");
      $form->addButton("§lLOCK ISLAND: ".$lockText."\n§l§6»» §r§f§oTap to set", "textures/ui/lock");
      $form->addButton("§lPVP ISLAND: ".$pvpText."\n§l§6»» §r§f§oTap to set", "textures/ui/strength_effect");
      $form->addButton("§lBACK TO MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
      $p->sendForm($form);
     }else{
      $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §6please re-island§r§f!");
     }
    }

    public function setWelcomeMenu(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Please enter the incoming message that you will modify§r§f!");
       return;
      }
      if(!empty(($data = $this->data->get($p->getName())))){
       $this->data->setNested($p->getName().".welcome", str_replace("§", "", $result[0]));
       $this->data->save();
       $p->sendMessage($this->prefix."You have successfully changed your message coming to your SkyBlock island§r§f.");
       $this->settingMenu($p);
      }else{
       $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §6please re-island§r§f!");
      }
     });
     $form->setTitle("§e§lSet Welcome Message");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§ePlease enter a new incoming message that you would like to modify§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oNew Welcome Message§r§f:");
     $p->sendForm($form);
    }

    public function confirmMenu(Player $p, string $type, array $datas = []){
     if($type === "create" || $type === "delete"){
      $form = new SimpleForm(function(Player $p, $result) use ($type, $datas){
       if($result === null) return;
       switch($result){
        case 0:{
         if($type === "create"){
          $this->data->setNested($p->getName().".model", $datas[0]);
          $this->data->setNested($p->getName().".welcome", "Welcome to SkyBlock Island");
          $this->data->setNested($p->getName().".friends", []);
          $this->data->setNested($p->getName().".lock", false);
          $this->data->setNested($p->getName().".pvp", false);
          $this->data->setNested($p->getName().".points", 0);
          $this->data->setNested($p->getName().".king-slime", $datas[1]["king-slime"]);
          $this->data->save();
          $settings = ["preset" => json_encode($datas[1])];
          $this->getServer()->generateLevel($p->getName(), null, SkyBlockGenerator::class, $settings);
          $this->getServer()->loadLevel($p->getName());
          $this->menuSkyBlock($p);
          $p->sendMessage($this->prefix."§r§eYou have successfully created a SkyBlock island with a model §r§f".ucwords(str_replace("-", " ", $datas[0])).". §eteleport now§r§f!");
         }else if($type === "delete"){
          if($p->getLevel()->getFolderName() === $p->getName()) $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
          if($this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->unloadLevel($this->getServer()->getLevelByName($p->getName()));
          $this->data->remove($p->getName());
          $this->data->save();
          $this->removeDirectory("worlds/".$p->getName()."/region");
          $this->removeDirectory("worlds/".$p->getName());
          $p->sendMessage($this->prefix."You have successfully wiped the SkyBlock island§r§f.");
         }
         break;
        }
        case 1:{
         if($type === "delete") $this->menuSkyBlock($p);
         if($type === "create") $this->chooseNewIsland($p);
         break;
        }
       }
      });
      $form->setTitle("§l§eConfirm ".ucwords($type)." Menu");
      $text = [];
      if($type === "create"){
       $text[] = "membuat";
       $text[] = " dengan model §r§f".ucwords(str_replace("-", " ", $datas[0]));
      }else if($type === "delete"){
       $text[] = "menghapus";
      }
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§eAre you sure to".$text[0]."Skyblock island".(empty($text[1]) ? "" : $text[1])."§r§f? §eplease confirm§r§f!\n".str_repeat("=", 33));
      $form->addButton("§lYES, I AGREE\n§l§6»» §r§f§oTap to ".$type, "textures/ui/confirm");
      $form->addButton("§lBACK\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
      $p->sendForm($form);
     }
    }

    public function onPlayerJoin(PlayerJoinEvent $e){
     $p = $e->getPlayer();
     $this->index[$p->getName()] = 1;
     $this->eid[$p->getName()] = Entity::$entityCount++;
     $this->setLeaderboardText($p, true);
     if(!empty(($this->data->get($p->getName())))){
      if(!$this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->loadLevel($p->getName());
     }
     if($this->isSkyBlockLevel($p->getLevel())){
      if(!empty(($data = $this->data->get($p->getLevel()->getFolderName())))){
       $i = 0;
       foreach($p->getLevel()->getEntities() as $en){
        if($en instanceof KingSlime) $i++;
       }
       if($i <= 0) $this->spawnSlimeKing($p, $data);
      }
     }
    }

    public function onPlayerQuit(PlayerQuitEvent $e){
     $p = $e->getPlayer();
     unset($this->index[$p->getName()]);
     unset($this->eid[$p->getName()]);
     if(!empty(($this->data->get($p->getName())))){
      if($this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->unloadLevel($this->getServer()->getLevelByName($p->getName()));
     }
    }

    public function onPlayerInteract(PlayerInteractEvent $e){
     $this->doInteractEvent($e);
     if($e::RIGHT_CLICK_BLOCK === $e->getAction()){
      $p = $e->getPlayer();
      $block = $e->getBlock();
      $button = $this->isButton($block);
      $name = $p->getName();
      $world = $p->getLevel()->getFolderName();
      if(isset($this->mode[$name][$world])){
       if($this->mode[$name][$world] === 3){
        $this->getConfig()->setNested("leaderboard-points.board", array($block->getX() + 0.5, $block->getY() + 3, $block->getZ() + 0.5));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."You have successfully determined the location of the island points leaderboard§r§f.");
        $p->sendMessage($this->prefix."Please specify the location of the next button§r§f!");
        --$this->mode[$name][$world];
       }else if($this->mode[$name][$world] === 2){
        $this->getConfig()->setNested("leaderboard-points.next", array($block->getX(), $block->getY(), $block->getZ()));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."You have successfully determined the location of the next button§r§f.");
        $p->sendMessage($this->prefix."Please specify the location of the back button§r§f!");
        --$this->mode[$name][$world];
       }else if($this->mode[$name][$world] === 1){
        $this->getConfig()->setNested("leaderboard-points.back", array($block->getX(), $block->getY(), $block->getZ()));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."You have successfully determined the location of the back button§r§f.");
        unset($this->mode[$name][$world]);
       }
      }else if($block->getId() === 77){
       if($button !== null){
        if(empty($this->index[$name])) $this->index[$name] = 1;
        switch($button){
         case "next":{
          if($this->getRankings($this->index[$name] + 1) === null){
           $p->sendMessage($this->prefix."The next page is no longer found§r§f!");
           return false;
          }
          $this->index[$name] = $this->index[$name] + 1;
          $this->setLeaderboardText($p);
          break;
         }
         case "back":{
          if($this->index[$name] <= 1){
           $p->sendMessage($this->prefix."The previous page is no longer found§r§f!");
           return false;
          }
          $this->index[$name] = $this->index[$name] - 1;
          $this->setLeaderboardText($p);
          break;
         }
        }
       }
      }
     }
    }

    public function onBlockPlace(BlockPlaceEvent $e){
     $this->doInteractEvent($e);
    }

    public function onBlockBreak(BlockBreakEvent $e){
     $this->doInteractEvent($e);
    }

    public function onEntityDamage(EntityDamageEvent $e){
     if($e instanceof EntityDamageByEntityEvent){
      $damager = $e->getDamager();
      $victim = $e->getEntity();
      if($damager instanceof Player && $victim instanceof Player){
       if($damager->getLevel()->getFolderName() === $this->getServer()->getDefaultLevel()->getFolderName()){
        $e->setCancelled(true);
        if(!empty(($this->data->get($victim->getName())))){
         $form = new SimpleForm(function(Player $p, $result) use ($victim){
          if($result === null) return;
          switch($result){
           case 0:{
            $this->visitFriend($p, $victim->getName(), true);
            break;
           }
          }
         });
         $form->setTitle("§l§eVisit ".$victim->getName()." Island");
         $form->setContent(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6§oApakah anda ingin untuk berkunjung ke pulau SkyBlock milik §r§f".$victim->getName()."? §6§osilahkan konfirmasi§r§f!\n".str_repeat("=", 33));
         $form->addButton("§lYES, I WANT\n§l§9»» §r§f§oTap to teleport", "textures/ui/confirm");
         $damager->sendForm($form);
        }else{
         $damager->sendMessage($this->prefix."§r§f".$victim->getName()." §edoes not have SkyBlock Island§r§f!");
        }
       }else if(!empty(($data = $this->data->get($victim->getName())))){
        if(!$data["pvp"]){
         $e->setCancelled(true);
         $damager->addTitle("", "§c§oPVP OFF§r§f!", 20, 20, 20);
        }
       }
      }
     }
     $fallPlayer = $e->getEntity();
     if($fallPlayer instanceof Player){
      if($e->getCause() === EntityDamageEvent::CAUSE_VOID && $this->isSkyBlockLevel($fallPlayer->getLevel())){
       $e->setCancelled();
       $fallPlayer->teleport(new Position(8.5, 36, 9.5, $this->getServer()->getLevelByName($fallPlayer->getLevel()->getFolderName())));
      }
     }
    }

    public function onChunkLoad(ChunkLoadEvent $e){
     $level = $e->getLevel();
     if(!$this->isSkyBlockLevel($level)) return;
     $data = $this->getConfig()->getNested("models-island.".($this->data->getNested($level->getFolderName().".model")));
     foreach($data["fill-chest"] as $chestPos){
      $position = new Position($chestPos[0][0], $chestPos[0][1], $chestPos[0][2]);
      if($level->getChunk($position->x >> 4, $position->z >> 4) === $e->getChunk() && $e->isNewChunk()){
       $chest = Tile::createTile(Tile::CHEST, $level, Chest::createNBT($position));
       $inventory = $chest->getInventory();
       unset($chestPos[0]);
       foreach($this->parseItems($chestPos) as $item){
        $inventory->addItem($item);
       }
      }
     }
    }

    public function hasSkyBlockIsland(Player $p):bool{
     foreach($this->data->getAll() as $name => $data){
      if($name === $p->getName()) return true;
     }
     return false;
    }

    public function isSkyBlockLevel(Level $level):bool{
     foreach($this->data->getAll() as $name => $data){
      if($name === $level->getFolderName()) return true;
     }
     return false;
    }

    public function addFriend(Player $p, string $friendName):void{
     if(strtolower($friendName) === strtolower($p->getName())){
      $p->sendMessage($this->prefix."You cannot add yourself as a friend§r§f!");
      return;
     }
     $friend = $this->getServer()->getPlayerExact($friendName);
     if(!$friend instanceof Player){
      $p->sendMessage($this->prefix."Player by name §r§f".$friendName." §6§otidak ditemukan atau sedang tidak online saat ini§r§f!");
      return;
     }
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if($findFriend){
       $p->sendMessage($this->prefix."Player by name §r§f".$friendName." §ealready become your friend§r§f!");
       return;
      }
      $friends = $data["friends"];
      $friends[] = $friendName;
      $this->data->setNested($p->getName().".friends", $friends);
      $this->data->save();
      $friend->sendMessage($this->prefix."You have been added friends by§r§f".$p->getName().".");
      $p->sendMessage($this->prefix."You added successfully§r§f".$friend->getName()." §eas a friend§r§f.");
     }else{
      $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §eplease re-island§r§f!");
     }
     return;
    }

    public function removeFriend(Player $p, string $friendName):void{
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if(!$findFriend){
       $p->sendMessage($this->prefix."Player by name§r§f".$friendName." §eis not your friend§r§f!");
       return;
      }
      $friends = [];
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name !== $friendName){
         $friends[] = $name;
        }
       }
      }
      $this->data->setNested($p->getName().".friends", $friends);
      $this->data->save();
      $friend = $this->getServer()->getPlayerExact($friendName);
      if($friend instanceof Player){
       $friend->sendMessage($this->prefix."You are no longer friends by now §r§f".$p->getName().".");
      }
      $p->sendMessage($this->prefix."You have successfully deleted §r§f".$friendName." §eas a friend§r§f.");
     }else{
      $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §ePlease re-island§r§f!");
     }
     return;
    }

    public function visitFriend(Player $p, string $friendName, $mustNotFriend = false):void{
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if(!$findFriend && !$mustNotFriend){
       $p->sendMessage($this->prefix."Player by name§r§f".$friendName." §eIs not your friend§r§f!");
       return;
      }
      if(!$this->teleportToIsland($p, $friendName, $data)){
       $p->sendMessage($this->prefix."SkyBlock Island belongs to the world §r§f".$friendName." §ecannot be loaded§r§f!");
       return;
      }
      $friend = $this->getServer()->getPlayerExact($friendName);
      if($friend instanceof Player){
       $friend->sendMessage($this->prefix."§r§f".$p->getName()." §ehave visited your SkyBlock island§r§f!.");
      }
      $p->sendMessage($this->prefix.$data["welcome"]."§r§f.");
     }else{
      $p->sendMessage($this->prefix."SkyBlock island data belongs to §r§f".$friendName." §elooks like it's broken§r§f!");
     }
     return;
    }

    public function removeDirectory(string $at){
     $dir = $this->getServer()->getDataPath().$at;
     $dir = rtrim($dir, "/\\")."/";
     foreach(scandir($dir) as $file){
      if($file === "." || $file === "..") continue;
      $path = $dir.$file;
      if(!is_dir($path)) unlink($path);
     }
     rmdir($dir);
    }

    public function isAsFriend(string $nameOfCheck, string $nameOfOwner):bool{
     if(!empty(($data = $this->data->get($nameOfOwner)))){
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $nameOfCheck){
         return true;
         break;
        }
       }
      }
     }
     return false;
    }

    public function doInteractEvent(Event $e){
     $p = $e->getPlayer();
     if($this->isSkyBlockLevel($p->getLevel()) && $p->getPlayer()->getLevel()->getFolderName() !== $this->getServer()->getDefaultLevel()->getFolderName()){
      if(!empty(($data = $this->data->get($p->getLevel()->getFolderName())))){
       if($p->getName() !== $p->getLevel()->getFolderName()){
        if(!$data["lock"]){
         if(!$this->isAsFriend($p->getName(), $p->getLevel()->getFolderName())){
          $p->sendMessage($this->prefix."You are not a friend of §r§f".$p->getLevel()->getFolderName()."!");
          $e->setCancelled(true);
         }
        }else{
         $p->sendMessage($this->prefix."The island has been locked by§r§f".$p->getLevel()->getFolderName()."!");
         $e->setCancelled(true);
        }
       }
       if(!$e->isCancelled()){
        $points = [57 => 4, 133 => 4, 41 => 3, 42 => 2, 22 => 1, 152 => 1];
        if($e instanceof BlockPlaceEvent){
         foreach($points as $id => $point){
          if($e->getBlock()->getId() === $id){
           $this->data->setNested($p->getName().".points", ($this->data->getNested($p->getName().".points") + $point));
           $this->data->save();
          }
         }
        }
        if($e instanceof BlockBreakEvent){
         foreach($points as $id => $point){
          if($e->getBlock()->getId() === $id){
           $result = ($this->data->getNested($p->getName().".points") - $point);
           if($result > 0){
            $this->data->setNested($p->getName().".points", $result);
           }else{
            $this->data->setNested($p->getName().".points", 0);
           }
           $this->data->save();
          }
         }
        }
       }
      }
     }
    }

    public function parseItems($items){
     $result = [];
     foreach($items as $parts){
      foreach($parts as $key => $value){
       $parts[$key] = (int) $value;
      }
      if(isset($parts[0])){
       $result[] = Item::get($parts[0], $parts[1] ?? 0, $parts[2] ?? 1);
      }
     }
     return $result;
    }

    public function teleportToIsland(Player $p, string $levelName, array $data):bool{
     if(!$this->getServer()->loadLevel($levelName)) return false;
     $level = $this->getServer()->getLevelByName($levelName);
     $p->teleport(new Position(8.5, 36, 9.5, $level));
     $i = 0;
     foreach($level->getEntities() as $en){
      if($en instanceof KingSlime) $i++;
     }
     if($i <= 0){
      $this->spawnSlimeKing($p, $data);
     }
     return true;
    }

    public function spawnSlimeKing(Player $p, array $data){
     $nbt = Entity::createBaseNBT(new Vector3($data["king-slime"][0] + 0.5, $data["king-slime"][1] + 1, $data["king-slime"][2] + 0.5));
     $kingslime = new KingSlime($p->getLevel(), $nbt);
     $kingslime->setNameTag("§e§lKing Slime");
     $kingslime->setNameTagAlwaysVisible(true);
     $kingslime->setNameTagVisible(true);
     $kingslime->spawnToAll();
    }

    public function getRankings(int $index){
     $allPoints = [];
     foreach($this->data->getAll() as $islandOwner => $islandData){
      $allPoints[$islandOwner] = $islandData["points"];
     }
     $allkeys = array_keys($allPoints);
     $i = 0;
     $text = "";
     arsort($allPoints, SORT_NUMERIC);
     foreach($allPoints as $name => $money){
      $i++;
      $allkeys[$i] = $name;
     }
     $count = $index * 10;
     for($i = ($index * 10) - 10; $i < $count;){
      $i++;
      if(empty($allkeys[$i])){
       $text = $text."-\n";
      }else{
       $text = $text."§f§l(§r§e".$i."§f§l) §r§6§o".$allkeys[$i]."§r§f: ".$this->data->getNested($allkeys[$i].".points")." §r§7§opoints§r\n";
      }
     }
     if($index !== 1){
      if($text === str_repeat("-\n", 10)){
       return null;
      }
     }
     return $text;
    }

    public function isButton($block){
     if(!empty(($posData = $this->getConfig()->get("leaderboard-points")))){
      $posNext = $posBack = null;
      if(!empty($posData["next"])) $posNext = $posData["next"];
      if(!empty($posData["back"])) $posBack = $posData["back"];
      $next = $back = "";
      if($posNext !== null) $next = $posNext[0]." ".$posNext[1]." ".$posNext[2];
      if($posBack !== null) $back = $posBack[0]." ".$posBack[1]." ".$posBack[2];
      $pos = $block->x." ".$block->y." ".$block->z;
      if($next === $pos){
       return "next";
      }elseif($back === $pos){
       return "back";
      }
     }
     return null;
    }

    public function setLeaderboardText(Player $p, $join = false){
     $to = 1;
     if($join) $to = 3;
     $posData = $this->getConfig()->get("leaderboard-points");
     for($i = 0; $i < $to; $i++){
      if($i === 0){
       $text = "§l§a» §9LEADERBOARD SKYBLOCK POINTS §a«\n".$this->getRankings($this->index[$p->getName()]);
       $eid = $this->eid[$p->getName()];
       $type = "board";
      }else if($i === 1){
       $text = "§l§9>§f>§a>";
       $eid = 114514;
       $type = "next";
      }else if($i === 2){
       $text = "§l§a<§f<§9<";
       $eid = 114515;
       $type = "back";
      }
      if(!empty($posData[$type])){
       $uuid = UUID::fromRandom();
       $add = new PlayerListPacket();
       $add->type = PlayerListPacket::TYPE_ADD;
       $add->entries = [PlayerListEntry::createAdditionEntry($uuid, $eid, $text, SkinAdapterSingleton::get()->toSkinData(new Skin("Standard_Custom", str_repeat("\x00", 8192))))];
       $p->sendDataPacket($add);
       $pk = new AddPlayerPacket();
       $pk->uuid = $uuid;
       $pk->username = $text;
       $pk->entityRuntimeId = $eid;
       if($type === "next" || $type === "back"){
        $pk->position = new Vector3($posData[$type][0] + 0.5, $posData[$type][1], $posData[$type][2] + 0.5);
       }else{
        $pk->position = new Vector3($posData[$type][0], $posData[$type][1], $posData[$type][2]);
       }
       $pk->item = ItemFactory::get(Item::AIR, 0, 0);
       $flags = (1 << Entity::DATA_FLAG_IMMOBILE);
       $pk->metadata = [
        Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
        Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01]
       ];
       $p->sendDataPacket($pk);
       $remove = new PlayerListPacket();
       $remove->type = PlayerListPacket::TYPE_REMOVE;
       $remove->entries = [PlayerListEntry::createRemovalEntry($uuid)];
       $p->sendDataPacket($remove);
      }
     }
    }

    public function setScoreboardEntry(Player $p, int $score, string $msg, string $objName){
     $entry = new ScorePacketEntry();
     $entry->objectiveName = $objName;
     $entry->type = 3;
     $entry->customName = "$msg";
     $entry->score = $score;
     $entry->scoreboardId = $score;
     $pk = new SetScorePacket();
     $pk->type = 0;
     $pk->entries[$score] = $entry;
     $p->sendDataPacket($pk);
    }

    public function createScoreboard(Player $p, string $title, string $objName, string $slot = "sidebar", $order = 0){
     $pk = new SetDisplayObjectivePacket();
     $pk->displaySlot = $slot;
     $pk->objectiveName = $objName;
     $pk->displayName = $title;
     $pk->criteriaName = "dummy";
     $pk->sortOrder = $order;
     $p->sendDataPacket($pk);
    }

    public function removeScoreboard(Player $p, string $objName){
     $pk = new RemoveObjectivePacket();
     $pk->objectiveName = $objName;
     $p->sendDataPacket($pk);
    }

} 