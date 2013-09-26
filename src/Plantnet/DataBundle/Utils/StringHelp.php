<?php

namespace Plantnet\DataBundle\Utils;

class StringHelp
{
	const ACCENT_STRINGS='ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËẼÌÍÎÏĨÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëẽìíîïĩðñòóôõöøùúûüýÿ';
	const NO_ACCENT_STRINGS='SOZsozYYuAAAAAAACEEEEEIIIIIDNOOOOOOUUUUYsaaaaaaaceeeeeiiiiionoooooouuuuyy';
	const WORD_COUNT_MASK="/\\p{L}[\\p{L}\\p{Mn}\\p{Pd}'\\x{2019}]*/u";

	static public function accentToRegex($text)
	{
		$tmp=utf8_decode($text);
		if(utf8_encode($tmp)!=$text){
			return $text;
		}
		unset($tmp);
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

	static public function cleanToPath($text)
	{
		$text=trim(mb_strtolower($text,'UTF-8'));
        $text=eregi_replace("[ ]+",'-',strtolower($text));
        $text=preg_replace('/([^.a-z0-9]+)/i','_',$text);
        return $text;
	}

	static public function truncate($string,$length)
	{
		if(strlen($string)>$length){
            $string=substr($string,0,$length);
            return substr($string,0,strrpos($string,' ')).'...';
        }
        return $string;
	}

	static public function glossary_highlight($collection_id,$glossary_terms,$html_string,$nl2br=false)
	{
		if($nl2br){
			$html_string=nl2br($html_string);
		}
		$d=new \DOMDocument;
		//single word
        @$d->loadHTML('<?xml encoding="UTF-8">'.$html_string);
        foreach($d->childNodes as $item){
        	if($item->nodeType==XML_PI_NODE){
        		$d->removeChild($item);
        	}
        }
        $x=new \DOMXPath($d);
        foreach($x->query('//text()') as $node){
            $string=$node->nodeValue;
            // ne prend pas les accents et les caractères non alphanumériques (hindi, urdu, ...)
            // $words=str_word_count($node->nodeValue,2);
            if(preg_match_all(self::WORD_COUNT_MASK,$node->nodeValue,$matches,PREG_OFFSET_CAPTURE)){
            	$words=array();
            	$original_words=array();
            	foreach($matches[0] as $m){
            		$words[$m[1]]=lcfirst($m[0]);
            		$original_words[$m[1]]=$m[0];
	            }
	            $words=array_intersect($words,$glossary_terms);
	            $original_words=array_intersect_key($original_words,$words);
	            $added_chars=0;
	            $end_tag='</span>';
	            foreach($original_words as $pos=>$word){
	            	$start_tag='<span class="glossary_term" data-term="'.$words[$pos].'" data-parent="'.$collection_id.'" data-content="0">';
                    $string=substr_replace($string,$start_tag.$word.$end_tag,$pos+$added_chars,strlen($word));
                    $added_chars+=strlen($start_tag)+strlen($end_tag);
	            }
	            $node->nodeValue=$string;
            }
        }
        $html_string=preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i','',$d->saveHTML());
        //compound word
        @$d->loadHTML('<?xml encoding="UTF-8">'.$html_string);
        foreach($d->childNodes as $item){
        	if($item->nodeType==XML_PI_NODE){
        		$d->removeChild($item);
        	}
        }
        usort($glossary_terms,function($a,$b){
    		return strlen($b)-strlen($a);
    	});
        $x=new \DOMXPath($d);
        foreach($x->query('//text()') as $node){
        	$string=$node->nodeValue;
        	foreach($glossary_terms as $compound){
        		if(preg_match('/\s/',$compound)==1&&preg_match('/\W'.$compound.'\W/i',$string)){
        			$start_tag='<span class="glossary_term" data-term="'.$compound.'" data-parent="'.$collection_id.'" data-content="0">';
        			$end_tag='</span>';
        			$string=str_replace($compound,$start_tag.$compound.$end_tag,$string);
        			$string=str_replace(ucfirst($compound),$start_tag.ucfirst($compound).$end_tag,$string);
        			$node->nodeValue=$string;
        		}
        	}
        }
        $html_string=preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i','',$d->saveHTML());
        //return
        return htmlspecialchars_decode($html_string);
	}
}