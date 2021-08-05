<?php

namespace SkyBlock\Bluelands\Task;

use pocketmine\scheduler\Task;
use pocketmine\Player;

use SkyBlock\Bluelands\Main;

class Scoreboard extends Task{

    public function __construct(Main $pl){
     $this->pl = $pl;
    }

    public function onRun($tick){
     foreach($this->pl->getServer()->getOnlinePlayers() as $p){
      $this->pl->removeScoreboard($p, "objektName");
      $this->pl->createScoreboard($p, "§l§eSKYBLOCK", "objektName");
      date_default_timezone_set('Asia/Jakarta');
      $this->pl->setScoreboardEntry($p, 0, "§7".date("d/m/y")." WIB", "objektName");
      $this->pl->setScoreboardEntry($p, 1, "§1", "objektName");
      $this->pl->setScoreboardEntry($p, 2, "§l§ePLAYER", "objektName");
      $economy = $this->pl->getServer()->getPluginManager()->getPlugin("EconomyAPI");
      if(is_null($economy)){
       $money = "§cnone plugin";
      }else{
       $money = $economy->myMoney($p->getName());
      }
      $this->pl->setScoreboardEntry($p, 3, "§7- Balance: §a".$money, "objektName");
      $bedrockclans = $this->pl->getServer()->getPluginManager()->getPlugin("BedrockClans");
      if(is_null($bedrockclans)){
       $clanName = "§cnone plugin";
      }else{
       $clan = $bedrockclans->getPlayer($p)->getClan();
       if($clan === null){
        $clanName = "§cnone clan";
       }else{
        $clanName = $clan->getName();
       }
      }
      $this->pl->setScoreboardEntry($p, 4, "§7- Clan: §a".$clanName, "objektName");
      $this->pl->setScoreboardEntry($p, 5, "§7- Xp: §a".$p->getXpLevel(), "objektName");
      $this->pl->setScoreboardEntry($p, 6, "§2", "objektName");
      $this->pl->setScoreboardEntry($p, 7, "§l§eISLAND", "objektName");
      $points = $resultFriend = "§cnone island";
      if(!empty(($data = Main::getInstance()->data->get($p->getName())))){
       $points = $data["points"];
       $countFriend = 0;
       $onlineFriend = 0;
       if(!empty($data["friends"]) && is_array($data["friends"])){
        foreach($data["friends"] as $friendName){
         $friend = Main::getInstance()->getServer()->getPlayerExact($friendName);
         if($friend instanceof Player) $onlineFriend++;
         $countFriend++;
        }
       }
       $resultFriend = $onlineFriend."/".$countFriend;
      }
      $this->pl->setScoreboardEntry($p, 8, "§7- Point: §a".$points, "objektName");
      $this->pl->setScoreboardEntry($p, 9, "§7- Friend: §a".$resultFriend, "objektName");
      $this->pl->setScoreboardEntry($p, 10, "§3", "objektName");
      $this->pl->setScoreboardEntry($p, 11, "§ebluelandsofficial.us.to", "objektName");
     }
    }

}