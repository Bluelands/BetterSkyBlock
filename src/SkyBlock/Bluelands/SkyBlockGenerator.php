<?php

namespace SkyBlock\Bluelands;

use pocketmine\level\generator\Generator;
use pocketmine\level\ChunkManager;
use pocketmine\utils\Random;
use pocketmine\math\Vector3;

class SkyBlockGenerator extends Generator{

    private static $chunks = array();

    public function __construct(array $settings = []){
     if(isset($settings["preset"])){
      $settings = json_decode($settings["preset"], true);
      if($settings === false){
       $settings = [];
      }
     }else{
      $settings = [];
     }
     $this->settings = $settings;
     self::$chunks = [];
     $structures = unserialize(gzinflate(gzinflate(base64_decode($settings["schematic"]))));
     foreach($structures as $structureString){
      $structure = explode(", ", str_replace(["[", "]"], "", $structureString));
      self::$chunks[($structure[0] >> 4)."|".($structure[2] >> 4)][] = $structure;
     }
    }

    public function init(ChunkManager $level, Random $random):void{
     $this->level = $level;
     $this->random = $random;
    }

    public function getName():string{
     return "SkyBlock Island";
    }

    public function getSettings():array{
     return $this->settings;
    }

    public function generateChunk(int $chunkX, int $chunkZ):void{
     ($this->level->getChunk($chunkX, $chunkZ))->setGenerated();
     foreach(self::$chunks as $chunkPos => $structure){
      $pos = explode("|", $chunkPos);
      if((string) $pos[0] === (string) $chunkX && (string) $pos[1] === (string) $chunkZ){
       $chunk = $this->level->getChunk($pos[0], $pos[1]);
       foreach($structure as $data){
        $chunk->setBlock($data[0] - (16 * $pos[0]), $data[1], $data[2] - (16 * $pos[1]), $data[3], (empty($data[4]) ? 0 : $data[4]));
        $chunk->setX($pos[0]);
        $chunk->setZ($pos[1]);
        $this->level->setChunk($pos[0], $pos[1], $chunk);
       }
      }
     }
    }

    public function populateChunk(int $chunkX, int $chunkZ):void{
   }

    public function getSpawn():Vector3{
     return new Vector3(0, 10, 0);
    }

}