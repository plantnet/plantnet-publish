<?php

namespace Plantnet\FileManagerBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;

class ZipData
{
	public $zipFile;
	public $zipPath;
	private $mimeTypes;
	public function __construct()
	{
		$this->mimeTypes=array(
			'image/gif',
			'image/jpeg',
			'image/png',
			'image/tiff',
		);
	}
	public function extractTo($path)
	{
		$zip=new \ZipArchive;
        if($zip->open($this->zipPath)===true){
            $zip->extractTo($path);
            $zip->close();
            $this->clean($path,$path);
            $this->removeZipFile();
        }
	}
	private function clean($root,$path)
	{
		$dels=array();
		$dir=opendir($path);
		while($entry=@readdir($dir)){
			if(is_dir($path.'/'.$entry)&&$entry!='.'&&$entry!='..'){
				$dels[]=$path.'/'.$entry;
				$this->clean($root,$path.'/'.$entry);
			}
			elseif($entry!='.'&&$entry!='..'){
				$file=new File($path.'/'.$entry);
				if(in_array($file->getMimeType(),$this->mimeTypes)){
					$file->move($root,$entry);
				}
				else{
					$this->remove($path.'/'.$entry);
				}
			}
		}
		closedir($dir);
		foreach($dels as $del){
			$this->remove($del);
		}
	}
	private function removeZipFile()
	{
		$this->remove($this->zipPath);
	}
	private function remove($path)
	{
		if(file_exists($path)){
			if(is_dir($path)){
				rmdir($path);
			}
			else{
				unlink($path);
			}
		}
	}
}