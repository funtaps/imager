<?php
/**
 * Created by JetBrains PhpStorm.
 * User: kirill.funtov
 * Date: 14.01.13
 * Time: 12:30
 * To change this template use File | Settings | File Templates.
 */

$config=parse_ini_file('config.ini');

$main=new NeedToBeRefactored($config);

while(true){
    $main->iterateThrougDirectory($config['TMP_INFO_PATH']);
}


class NeedToBeRefactored{

    private $cfg;
    private $pdo;

    private $current;
    private $path;
    private $mtime;

    public function __construct($config)
    {
        $this->cfg=$config;
        $this->pdo=new PDO(
            $this->cfg['DB_DSN'],
            $this->cfg['DB_USER'],
            $this->cfg['DB_PASSWORD']
        );

    }



    private function cleanup(){
        unlink($this->cfg['TMP_INFO_PATH'].$this->current,'l');
        unlink($this->cfg['TMP_IMAGE_PATH'].substr($this->current,0,-3).'jpg');
    }

    public function iterateThrougDirectory($dirname)
    {
        $infos=dir($dirname);
        foreach($infos as $info){
            if(substr($info,-4)!=='.info'){
                continue;
            }
            $this->current=$info;
            if(!$this->lock()){
                continue;
            }
            $this->parseImageData();
            if($this->pictureHasChanged()){
                $this->cleanup();
                continue;
            }
            $this->savePictureMTime();
            $this->resize();
            $this->cleanup();
        }
    }

    private function getInfoName()
    {
        return $this->cfg['TMP_INFO_PATH'].$this->current;
    }

    private function getLockedInfoName()
    {
        return $this->getInfoName().'l';
    }

    private function getSourceImageName()
    {
        return $this->cfg['TMP_IMAGE_PATH'].substr($this->current,0,-3).'jpg';
    }

    private function getTargetImageName()
    {
        return $this->cfg['EXPORT_PATH'].$this->path;
    }

    private function parseImageData()
    {
        $fp=fopen($this->getLockedInfoName(),'r');
        $this->path=fgets($fp);
        $this->mtime=fgets($fp);
        fclose($fp);
    }

    private function lock()
    {
        return rename($this->getInfoName(),$this->getLockedInfoName());
    }

    private function resize()
    {
        $from=$this->getSourceImageName();
        $to=$this->getTargetImageName();
        $w=$this->cfg['TARGET_WIDTH'];
        $h=$this->cfg['TARGET_HEIGHT'];
        exec("php resize_target_image.php \"$from\" \"$to\" $w $h");
    }

    private function pictureHasChanged()
    {
        return $this->mtime!==$this->getPictureMTime();
    }


    private $saveMTimeCommand=null;
     private function savePictureMTime()
     {
         if(is_null($this->saveMTimeCommand)){
             $this->saveMTimeCommand=$this->pdo->prepare(
                 'INSERT INTO textro.image_resizer_pictures (path, mtime)
                 VALUES (:path, :mtime)
                 ON DUPLICATE KEY UPDATE mtime=:mtime'
             );
         }
         $this->saveMTimeCommand->execute(array('path'=>$this->path, 'mtime'=>$this->mtime));
     }


    private $getMTimeCommand=null;
     private function getPictureMTime()
     {

         if(is_null($this->getMTimeCommand)){
             $this->getMTimeCommand=$this->pdo->prepare(
                 'SELECT mtime FROM textro.image_resizer_pictures
                  WHERE path=:path'
             );
         }

         $this->getMTimeCommand->execute(array('path'=>$this->path));
         $return=$this->getMTimeCommand->fetchColumn();
         $this->getMTimeCommand->closeCursor();
         return $return;
     }


}