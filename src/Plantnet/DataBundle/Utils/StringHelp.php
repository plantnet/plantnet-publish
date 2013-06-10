<?php

namespace Plantnet\DataBundle\Utils;

class StringHelp
{
	const ACCENT_STRINGS='ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËẼÌÍÎÏĨÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëẽìíîïĩðñòóôõöøùúûüýÿ';
	const NO_ACCENT_STRINGS='SOZsozYYuAAAAAAACEEEEEIIIIIDNOOOOOOUUUUYsaaaaaaaceeeeeiiiiionoooooouuuuyy';

	static public function accentToRegex($text)
	{
		$from=str_split(utf8_decode(self::ACCENT_STRINGS));
		$to=str_split(strtolower(self::NO_ACCENT_STRINGS));
		$text=utf8_decode($text);
		$regex=array();
		foreach($to as $key=>$value){
			if(isset($regex[$value])){
				$regex[$value].=$from[$key];
			}
			else{
				$regex[$value]=$value;
			}
		}
		foreach($regex as $rg_key=>$rg){
			$text=preg_replace("/[$rg]/","_{$rg_key}_",$text);
		}
		foreach($regex as $rg_key=>$rg){
			$text=preg_replace("/_{$rg_key}_/","[$rg]",$text);
		}
		return utf8_encode($text);
	}

	static public function isGoodForUrl($text)
	{
		if($text==urlencode($text)){
			return true;
		}
		return false;
	}

	static function cleanToPath($text)
	{
		$text=trim(mb_strtolower($text,'UTF-8'));
        $text=eregi_replace("[ ]+",'-',strtolower($text));
        $text=preg_replace('/([^.a-z0-9]+)/i','_',$text);
        return $text;
	}
}