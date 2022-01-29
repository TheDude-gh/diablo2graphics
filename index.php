<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='cs'>
<head>
	<title>Diablo 2 Graphics Reader</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="content-language" content="en" />
	<link rel="icon" href="./graphics/favicon.ico" />
	<link rel="stylesheet" type="text/css" href="./css/itemlist.css?v=1" />
	<style>
	* {background: #ddc; font-family: calibri, arial, sans-serif; }
	div { margin: 2em auto; width: 80%;}
	table {border-collapse:collapse; margin: 1em; border: solid 1px #000;}
	th { background: #dd1;}
	th, td {border: solid 1px #000; min-width: 1em; padding: 1px 5px;}
	.ar { text-align:right; }
	.ac { text-align:center; }
	.al { text-align:left; }
	.vac { vertical-align:middle; }

	a, a:visited { color: #00f; text-decoration: none; }
	a:hover { text-decoration: underline; }
	select {min-width: 50px; text-align:center; background: #eed; }
	input { background: #eed; }
	h2 a, h2 a:visited { color : #000; }

	.smalltable {font-size: 14px;}
	.smalltable1 { width: 75%;  margin: 1em auto;}
	.cof { border:none; }
	.cof th, .cof td { font-family: 'Courier New', monospace; font-size: 12px; border:none; padding: 0px 8px;}

	.inormal { color: #555; font-weight: bold; }
	.imagic { color: #4850B8; font-weight: bold;}
	.irare { color: #FFFF00; font-weight: bold; }
	.icraft { color: #FFA500; font-weight: bold; }
	.iset { color: #00C400; font-weight: bold; }
	.iunique { color: #908858; font-weight: bold; }
	.iruneword { color: #990099; font-weight: bold; }
	.mono { font-family: 'courier new', monospace; font-size: 12px;}
	/*img { width: 256px; margin: 0px auto;}*/
	.img1 { height:80px; image-rendering: pixelated; }
	.img2 { height:100px; image-rendering: pixelated; }
	</style>
</head>
<body>
<h2><a href="index.php">Diablo 2 Graphics Reader</a></h2>
<div>
<p>

</p>
<form method="post">
<?php

	require_once './fun/config.php';
	require_once './fun/mi.php';

	require_once './fun/bytereader.php';
	require_once './fun/d2cof.php';
	require_once './fun/d2dc6.php';
	require_once './fun/d2dcc.php';
	require_once './fun/d2data.php';
	require_once './fun/d2sprite.php';

	//modes
	$mondo = 1; //creates form to choose monster
	$paldo = 1; //creates palette
	$sprdo = 1; //creates form for choosing monster mode and creates sprites and animated GIF

	$palettePath = D2PALETTEPATH;

	//parameter for choosing monster
	$monid = expost('mon', '');

	//globals for diablo txt data and palltte files
	global $D2DATA;
	global $D2PALETTE;
	$D2DATA = null;
	$D2PALETTE = null;

	//form for choosing monsters
	if($mondo) {
		$D2DATA = new D2Data();


		$dccf = scandir(D2DCCPATH_MONSTER);
		$options = [];

		//MONSTERS
		foreach($D2DATA->MONSTATS as $mond) {
			$moncode = strtolower($mond['Code']);
			if(!in_array($moncode, $dccf)) {
				continue;
			}
			$options[$mond['Id']] = $D2DATA->GetString($mond['NameStr']).'-'.$mond['Id'].' ('.$moncode.')';
		}
		natsort($options);

		
		//CHARACTERS
		$dccf = scandir(D2DCCPATH_CHAR);
		$optionsChar = [];
		foreach($D2DATA->PLRTYPE as $char) {
			$charcode = strtolower($char['Token']);
			if(!in_array($charcode, $dccf)) {
				continue;
			}
			$optionsChar[$char['Token']] = $char['Name'];
		}
		natsort($optionsChar);

		$select = '<select name="mon"><option></option>';
		foreach($optionsChar as $mid => $opt) {
			$sel = $monid == $mid ? ' selected="selected"' : '';
			$select .= '<option value="'.$mid.'"'.$sel.'>'.$opt.'</option>';
		}
		$select .= '<option></option>'; //spacer between chars and monsters
		foreach($options as $mid => $opt) {
			$sel = $monid == $mid ? ' selected="selected"' : '';
			$select .= '<option value="'.$mid.'"'.$sel.'>'.$opt.'</option>';
		}
		$select .= '</select>'.EOL;
		echo $select;
		echo '
			<input type="hidden" name="monprev" value="'.$monid.'" />
			<input type="submit" name="ok" value="ok" /><br />';

	}

	if($paldo) {
		$paletteFile = expost('palette', 'act1');
		$exp = '.dat';
		$D2PALETTE = new D2Palette(D2PALETTEPATH.$paletteFile.$exp);
	}

	if($sprdo) {
		if($monid) {
			$d2sprite = new D2SpriteMaker($monid);
		}
	}


function ScanDirM($dir, $mask = '') {
	$files = [];

	if(!file_exists($dir)) {
		return $files;
	}

	$sd = scandir($dir);
	foreach($sd as $f) {
		if(is_dir($f)) {
			continue;
		}
		if($mask != '' && !preg_match($mask, $f)) {
			continue;
		}

		$files[] = $dir.$f;
	}
	return $files;
}

?>
</form>
</div>
</body>
</html>
