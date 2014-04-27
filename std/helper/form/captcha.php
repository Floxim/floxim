<?php
/***************************************************************************
 *                            usercp_captcha.php
 *                            -------------------
 *   begin                : Friday, 14 April 2006
 *   copyright            : (C) 2006 paul sohier
 *   email                : webmaster@paulscripts.nl
 *
 *   $Id: usercp_captcha.php,v 1.4 2006/11/18 21:18:21 paulsohier Exp $
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/
 

require_once $_SERVER['DOCUMENT_ROOT'].'/boot.php';
session_start();
$c_field = !empty($_GET['fx_field_name']) ? $_GET['fx_field_name'] : '';
$code = (string) rand(1000, 9999);

$_SESSION['captcha_code_'.$c_field] = $code;

/**
  * The next part is orginnaly written by ted from mastercode.nl and modified for using in this mod.
  **/
#header("Content-type: image/png");
header("content-type:text/plain");
header('Cache-control: no-cache, no-store');

$width = 130;
$height = 40;
$img = imagecreatetruecolor($width,$height);
$background = imagecolorallocate($img, color("bg"), color("bg"), color("bg"));

srand(make_seed());

imagefilledrectangle($img, 0, 0, 199, 39, $background);
for($g = 0;$g < 10; $g++)
{
        
	$t = dss_rand();
	$t = $t[0];

	$ypos = rand(0,$height);
	$xpos = rand(0,$width);

	$kleur = imagecolorallocate($img, color("bgtekst"), color("bgtekst"), color("bgtekst"));
        
	imagettftext($img, size(), move(), $xpos, $ypos, $kleur, font(), $t);
        
}

$stukje = $width / (strlen($code) + 1);

for($j = 0;$j < strlen($code); $j++)
{


	$tek = $code[$j];
	$ypos = rand(31,31);
	$xpos = $stukje * ($j+1);

	$color2 = imagecolorallocate($img, color("tekst"), color("tekst"), color("tekst"));

	imagettftext($img, size(), move(), $xpos, $ypos, $color2, font() , $tek);
}

imagepng($img);
imagedestroy($img);
die;
/**
  * Some functions :)
  * Also orginally written by mastercode.nl
  **/
/**
  * Function to create a random color
  * @auteur mastercode.nl
  * @param $type string Mode for the color
  * @return int
  **/
function color($type)
{
	switch($type)
	{
		case "bg":
			$color = rand(224,255);
		break;
		case "tekst":
			$color = rand(0,127);
		break;
		case "bgtekst":
			$color = rand(200,224);
		break;
		default:
			$color = rand(0,255);
		break;
	}
	return $color;
}
/**
  * Function to ranom the size
  * @auteur mastercode.nl
  * @return int
  **/
function size()
{
	return rand(18,30);
}
/**
  * Function to random the posistion
  * @auteur mastercode.nl
  * @return int
  **/
function move()
{
	return rand(-22,22);
}
/**
  * Function to return a ttf file from fonts map
  * @auteur mastercode.nl
  * @return string
  **/
function font()
{
	static $ar;
	$f = opendir('fonts');
	if(!is_array($ar))
	{
		$ar = array();
		while(($file = @readdir($f)) !== false)
		{
			if(!in_array($file,array('.','..')) && preg_match('~\.ttf$~',$file))
			{
		 		$ar[] = $file;
		 	}
		}
	}
	if(count($ar))
	{
	//	shuffle($ar);
		$i = rand(0,(count($ar) - 1));
		return 'fonts/' . $ar[$i];
	}
}
function make_seed()
{
   list($usec, $sec) = explode(' ', microtime());
   return (float) $sec + ((float) $usec * 100000);
}

function dss_rand()
   {
      return 'Z';
       global $db, $board_config, $dss_seeded;
   
       $val = $board_config['rand_seed'] . microtime();
       $val = md5($val);
       $board_config['rand_seed'] = md5($board_config['rand_seed'] . $val . 'a');
      
       if($dss_seeded !== true)
       {
           $sql = "UPDATE " . CONFIG_TABLE . " SET
               config_value = '" . $board_config['rand_seed'] . "'
               WHERE config_name = 'rand_seed'";
           
           if( !$db->sql_query($sql) )
           {
               message_die(GENERAL_ERROR, "Unable to reseed PRNG", "", __LINE__, __FILE__, $sql);
           }
   
    $dss_seeded = true;
  }
 
 return substr($val, 4, 16);
}