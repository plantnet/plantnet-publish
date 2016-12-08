<?php

function cl($data){
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
}



/*
 * étapes
 *
 * si image existe
 *      on la retourne
 *
 * sinon
 *      créer le chemin complet
 *      créer la vignette
 *      retourner la vignette
 *
 *
 * chemin ou sont stocké les images : media/cache_url_thumb/
 *  {{collection}}/{{module}}/{{sousmodule}}/thumb_{{h}}_{{w}}
 *
 */

// retourne le contenu d'une image
function sendimg($path,$filename){
    $contents = file_get_contents($path.$filename);
    header('Content-type: image/jpeg');
    echo $contents;
}

/**
 * crée une miniature dans le dossier $updir
 * @param $updir    : dossier ou crée la miniature
 * @param $img      : image orignale
 * @param $finalimg : image finale
 * @param $width
 * @param $height
 */
function makeThumbnails($updir, $img, $finalimg,$width=134, $height=189)
{
    $thumb_width = $width;
    $thumb_height = $height;
    $arr_image_details = getimagesize("$updir" . "$img");
    // cl($arr_image_details);
    $width = $arr_image_details[0];
    $height = $arr_image_details[1];


    $original_aspect = $width / $height;
    $thumb_aspect = $thumb_width / $thumb_height;

    if ( $original_aspect >= $thumb_aspect )
    {
        // If image is wider than thumbnail (in aspect ratio sense)
        $new_height = $thumb_height;
        $new_width = $width / ($height / $thumb_height);
    }
    else
    {
        // If the thumbnail is wider than the image
        $new_width = $thumb_width;
        $new_height = $height / ($width / $thumb_width);
    }


    if ($arr_image_details[2] == IMAGETYPE_GIF) {
        $imgt = "ImageGIF";
        $imgcreatefrom = "ImageCreateFromGIF";
    }
    if ($arr_image_details[2] == IMAGETYPE_JPEG) {
        $imgt = "ImageJPEG";
        $imgcreatefrom = "ImageCreateFromJPEG";
    }
    if ($arr_image_details[2] == IMAGETYPE_PNG) {
        $imgt = "ImagePNG";
        $imgcreatefrom = "ImageCreateFromPNG";
    }
    if ($imgt) {
        $image = $imgcreatefrom("$updir" . "$img");
        $thumb = imagecreatetruecolor( $thumb_width, $thumb_height );
        // Resize and crop
        imagecopyresampled($thumb,
            $image,
            0 - ($new_width - $thumb_width) / 2, // Center the image horizontally
            0 - ($new_height - $thumb_height) / 2, // Center the image vertically
            0, 0,
            $new_width, $new_height,
            $width, $height);
        imagejpeg($thumb, "$updir" . $finalimg, 80);

    }
}

//crypte le nom pour éviter les erreurs avec caractères bizarre dans nom fichiers
function toMd5($string){
    return md5($string);
}


// créer un dossier si il n'éxiste pas
function createDir($path){
    if (!is_dir($path)) {
        if (!mkdir("$path", 0777,true)) {
            die('Echec lors de la création du repertoire $path pour les thumb images distantes...');
        }
    }
}


$originalSrc = $_GET["src"];

// $originalSrc = 'http://publish.plantnet-project.org/tmp-data/CAY/JPG/CAY00/0/CAY000809.JPG';

$filename = toMd5($originalSrc);
$path = 'media/cache_url_thumb/'.$_GET["coll"]."/". $_GET["mod"]."/". $_GET["ssmod"]."/". "thumb_".$_GET["width"]."_".$_GET["height"]."/";
$path .=  substr($filename,0,3) . "/";

// vérifier si image existe
if(file_exists($path.$filename)){
    sendimg($path,$filename);
}else{
    // l'image n'existe pas
    createDir($path);

    // on telecharge l'image
    /*
        If you have allow_url_fopen set to true:

        $url = 'http://example.com/image.php';
        $img = '/my/folder/flower.gif';
        file_put_contents($img, file_get_contents($url));

        Else use cURL:

        $ch = curl_init('http://example.com/image.php');
        $fp = fopen('/my/folder/flower.gif', 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

    */
    $tmpfilename = 'original_'.$filename;

    if(copy( $originalSrc , $path.$tmpfilename)) {
        makeThumbnails($path, $tmpfilename, $filename, $_GET["width"], $_GET["height"]);
        unlink($path . $tmpfilename);
        sendimg($path, $filename);
    }
}



?>


