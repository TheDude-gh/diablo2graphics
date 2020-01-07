<?php

//class to choose and build sprites and gifs

class D2SpriteMaker {

	public const DCC_MAX_DIR = 32;

	public const PATH_COF = 'COF/';

	public $spritePath = D2DCCPATH;
  public $palettePath = D2PALETTEPATH;

	public $imagepath = '';
	private $showimg = true;

	private $mon_code;
	private $mon_name;
	private $monstats;

	private $modmodes = array();

	private $cof = null;
	private $cof_data = array();

	//possible layer parts names from txt file
	public $composit = array('HD', 'TR', 'LG', 'Ra', 'La', 'RH', 'LH', 'SH', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8');
  public $body_parts = array('HD' => 'head', 'TR' => 'torso', 'LG' => 'legs', 'RA' => 'right arm', 'LA' => 'left arm', 'RH' => 'right hand', 'LH' => 'left hand',
		'SH' => 'shield', 'S1' => 'special 1', 'S2' => 'special 2', 'S3' => 'special 3', 'S4' => 'special 4', 'S5' => 'special 5', 'S6' => 'special 6', 'S7' => 'special 7',
		'S8' => 'special 8');

	public $actions = array('DT' => 'Death', 'NU' => 'Neutral', 'WL' => 'Walk', 'RN' => 'Run', 'GH' => 'Get Hit', 'TN' => 'Town Neutral', 'TW' => 'Town Walk',
		'A1' => 'Attack1', 'A2' => 'Attack2', 'BL' => 'Block', 'SC' => 'Cast', 'TH' => 'Throw', 'KK' => 'Kick', 'S1' => 'Skill1', 'S2' => 'Skill2', 'S3' => 'Skill3',
		'S4' => 'Skill4', 'DD' => 'Dead', 'GHa' => 'Sequence', 'GHb' => 'Knock back');

	public $dirtext = array('SW', 'NW', 'NE', 'SE', 'S', 'W', 'N', 'E', 'All'); //directions texts

	//convert direction between COF and DCC so they match when buliding sprite
	public $cofdirconv8 = array(1, 3, 5, 7, 0, 2, 4, 6); //8 directions
	public $cofdirconv16 = array(2, 6, 10, 14, 0, 4, 8, 12, 1, 3, 5, 7, 9, 11, 13, 15); //16 directions

	//convert directions so they go from from clockwise to front again when building animation
	public $dirconv8 = array(4, 0, 5, 1, 6, 2, 7, 3); //8 directions
	public $dirconv16 = array(4, 8, 0, 9, 5, 10, 1, 11, 6, 12, 2, 13, 7, 14, 3, 15); //16 directions

	public $dcclist;
	public $movelist = array();
	public $weaponlist = array();
	public $dcc_sprites = array();

	public $transFileIndex = 0; //index of transform file, for monsters always 0, so probably useless
	public $transformLevel = 0; //index for palette transform from transform table file
	public $transformLevelLayer = array();
	public $dirSel = -1; //selected direction, when we want just one
	public $part_select = array();
	public $SkipTRimage = false;
	public $modesel = '';
  public $paletteShift = '';


	public function __construct($monsterID) {
		global $D2DATA;

		$this->showimg = true;

		$this->monstats = array_merge($this->GetMonData($monsterID), $this->GetMonData2($monsterID));

		$this->mon_name = $this->GetString($this->monstats['NameStr']);
		$this->mon_code = $this->monstats['Code'];

		$this->transformLevel = $this->monstats['TransLvl'] + 2; //first two shifts are empty, so we add offset +2

		$this->GetMonMods();
		$this->GetCOFDCCList();

		$this->ShowCOFdata();
		
		if($this->GetLayers()) {;
    	$this->GetPaletteShift($this->paletteShift);
			$this->DCCsprites();
		}
	}

	//get COF data for each monster mode
	public function GetMonMods() {
		global $D2DATA;

		$cofpath = $this->spritePath . $this->mon_code.'/' . D2SpriteMaker::PATH_COF;

		if(!file_exists($cofpath)) {
			echo 'COF path not exists ['.$cofpath.']<br />';
			return;
		}

		$coffiles = ScanDirM($cofpath, '/\.cof$/i');

		foreach($coffiles as $coffile) {
			$this->cof = new D2COF($coffile);
			$this->cof->GetCOF();

			$pi = pathinfo($coffile);
			$cofmode = strtoupper(substr($pi['filename'], 2, 2));
			$weapon = strtoupper(substr($pi['filename'], -3));

			//get possible movement/action states and weapons to list
			if(!in_array($cofmode, $this->movelist)) {
				$this->movelist[] = $cofmode;
			}
			if(!in_array($weapon, $this->weaponlist)) {
				$this->weaponlist[] = $weapon;
			}
			$this->cof_data[$weapon][$cofmode] = $this->cof;
		}
	}

	//function creates form for choosing monster configuration
	public function GetLayers() {
  	$monid = expost('mon');
    $monidprev = expost('monprev');

		$pact = expost('action');
		$pweap = expost('weapon');
		$players = expost('lay', array());

		$ppalette = expost('palette', 'act1');
    $premap = expost('remap', 0);
    
    $pdir = expost('direction', 4);

		$this->dirSel = $pdir == 8 ? -1 : $pdir; //-1 as all directions

		//some variable will reset on monster change / will be read only when monster is the same
		$premapi = array();
		if($monid == $monidprev) {
    	$premapi = expost('remapi', array()); //remap indexes for each layer
		}

		$res = false;

		$echo = '<table>
			<tr>
				<th>Variable</th>
        <th>Remap</th>
        <th>Options</th>
			</tr>';

		//palette
    $first = true;
    $echo .= '<tr><td>Palette</td><td></td><td>';
		for($i = 1; $i <= 5; $i++) {
    	if(!$first) {
				$echo .= ' | ';
			}
      $check = $ppalette == 'act'.$i ? ' checked="checked"' : '';
      $echo .= 'Act '.$i.'<input type="radio" name="palette" value="act'.$i.'" '.$check.' /> '.EOL;
      $first = false;
		}
		$echo .=  '</td></tr>';

		//remap tables
    $first = true;
		$rtables = array('Monster', 'Random', 'Green Blood'); //remap tables
    $rtablesC = array(8, 30, 1); //remap tables length, hardcoded, better would be, if we read it before, but it's unlikely it will change
    $echo .= '<tr><td>Remap tables</td><td></td><td>';
		foreach($rtables as $k => $rt) {
    	if(!$first) {
				$echo .= ' | ';
			}
      $check = $premap == $k ? ' checked="checked"' : '';
      $echo .= $rtables[$k].' <input type="radio" name="remap" value="'.$k.'" '.$check.' /> '.EOL;
      $first = false;
		}
		$echo .=  '</td></tr>';

		//directions
    $first = true;
    $echo .= '<tr><td>Direction</td><td></td><td>';
		for($i = 0; $i <= 8; $i++) {
    	if(!$first) {
				$echo .= ' | ';
			}
      $check = $pdir == $i ? ' checked="checked"' : '';
      $echo .= ($this->dirtext[$i]).'<input type="radio" name="direction" value="'.$i.'" '.$check.' /> '.EOL;
      $first = false;
		}
		$echo .=  '</td></tr>';
		

		//actions/moves
    $first = true;
    $echo .=  '<tr><td>Actions</td><td></td><td>';
		$count = count($this->movelist);
		foreach($this->movelist as $action) {
    	if(!$first) {
				$echo .= ' | ';
			}
			$check = ($count == 1 || $pact == $action || (!$pact && $first)) ? ' checked="checked"' : '';
			$echo .= $action.' <input type="radio" name="action" value="'.$action.'" '.$check.' />'.EOL;
      $first = false;
		}
		$echo .= '</td></tr>'.EOL;

		//weapons
    $first = true;
    $echo .=  '<tr><td>Weapons</td><td></td><td>';
		$count = count($this->weaponlist);
		foreach($this->weaponlist as $weapon) {
    	if(!$first) {
				$echo .= ' | ';
			}
			$check = ($count == 1 || $pweap == $weapon || (!$pweap && $first)) ? ' checked="checked"' : '';
			$echo .= $weapon.' <input type="radio" name="weapon" value="'.$weapon.'" '.$check.' />'.EOL;
      $first = false;
		}
		$echo .= '</td></tr>'.EOL;
	  
		//layers
	  foreach($this->dcclist as $layer => $parts) {
	    //select options for remap table
	  	$ropt = '';
			
			for($i = 0; $i < $rtablesC[$premap]; $i++) {
				$seli = $this->transformLevel;
				if(array_key_exists($layer, $premapi)) {
					$seli = $premapi[$layer];
          $this->transformLevelLayer[$layer] = $premapi[$layer];
				}

      	$sel = $seli == $i ? ' selected="selected"' : '';
	      $ropt .= '<option'.$sel.'>'.$i.'</option>';
			}

	  	$echo .= '<tr><td>'.$layer.'</td><td>'.EOL;
      $echo .= '<select name="remapi['.$layer.']">'.$ropt.'</select></td><td>';

      $first = true;
      $count = count($parts);

			$layerchecked = array_key_exists($layer, $players);

			$echo .= 'None <input type="radio" name="lay['.$layer.']" value="X" />'.EOL; //option for omitting layer

	    foreach($parts as $part => $dccfiles) {
	      if(!$first) {
					$echo .= ' | ';
				}

				$check = (($count == 1) || ($layerchecked && $players[$layer] == $part) || (!$layerchecked && $first)) ? ' checked="checked"' : '';

				$partname = $this->GetCompCode($part);
				$echo .= $partname.' <input type="radio" name="lay['.$layer.']" value="'.$part.'" '.$check.' />'.EOL;
        $first = false;
	    }
	    $echo .= '</td></tr>'.EOL;
		}
		$echo .= '</table>';
		echo $echo;

		//applying palette
		if($pact) {
			$this->modesel = $pact;
			$this->weaponsel = $pweap;
    	$this->part_select = $players;
			if($this->part_select['TR'] == 'X') {
        $this->part_select['TR'] = 'lit';
				$this->SkipTRimage = true;
			}
			

			if($premap == 1) {
      	$this->paletteShift = $this->palettePath.'RandTransforms.dat';
			}
			elseif($premap == 2) {
      	$this->paletteShift = $this->palettePath.'GreenBlood.dat';
			}
			else {
      	$this->paletteShift = null;
			}
			$res = true;
		}

		//when we selected new monster, first let user pick configuration
		if($monid != $monidprev) {
			$res = false;
		}

		return $res;
	}

	public function ShowCOFdata() {
	  $echo = '';
	  $n = 1;
	  foreach($this->cof_data as $weapon => $actions) {
			foreach($actions as $action => $cof) {
				$layers = implode($cof->monmode_list[0][0], ' ');
				$actname = $this->actions[$action];
		    $echo .= sprintf('%2d  %-30s  Move  %s  %-10s  Dc %2d  Fc %2d  Layers %2d  %s'.EOL,
					$n++, $cof->coffile, $action, $actname, $cof->directionsC, $cof->framesC, $cof->layers, $layers);
			}
		}
		spr($echo);
	}

	public function ShowDCCparts() {
	  $echo = '';
	  $n = 1;
	  foreach($this->dcclist as $layer => $parts) {
	  	$echo .= sprintf('%2d  %-4s', $n++, $layer);
	    foreach($parts as $part => $dccfiles) {
	    	$echo .= sprintf('  %s', $part);
	    }
	    $echo .= EOL;
		}
		spr($echo);
	}

	public function GetPaletteShift($palshift = null) {
		$palshift = $palshift ? $palshift : $this->spritePath . $this->mon_code.'/' . D2SpriteMaker::PATH_COF . 'palshift.dat';
		global $D2PALETTE;
		$D2PALETTE->RemapPalette($palshift);
	}

	//prepare dcc list of possible parts for each layer
	public function GetCOFDCCList() {
		$dir = 0; //does not matter much, which direction we read, because all directions have same layers and parts
		$this->dcclist = array();

		foreach($this->cof_data as $weapon => $actions) {
			foreach($actions as $mode => $cof) {
			  $layers = $cof->monmode_list[$dir][0];

				foreach($layers as $layer) {
					$dccnames = $this->mon_code.$layer;
					foreach($this->composit as $cmp) {
					  $layerU = strtoupper($cmp);
						if($layerU != $layer) continue; //if layer is not the same as composit, skip
	          if($this->monstats[$layerU] == '') continue; //if layer is blank, skip

						$layerv = $cmp.'v';
						if($this->monstats[$layerv] == '') {
	          	$parts = array('lit');
						}
	          else {
							$parts = explode(',', str_replace('"', '', $this->monstats[$layerv]));
						}

						foreach($parts as $part) {
							$dccname = strtoupper($dccnames.$part.$mode.$weapon); //$cof->name[0]
							$this->dcclist[$layer][$part][] = $dccname;
						}
						break;
					}
				}
				
			}
		}
	}

	//get DCC sprites for each layer
	public function DCCsprites() {
		$dirSel = $this->dirSel;
		$gifmake = true;
		$DCC_create_image = false;

		//pixelmaps for each layer and direction
		$pm = array();

		//layers order for each frame
		$flayers = array();

		foreach($this->cof_data as $weapon => $actions) {
		  if($weapon != $this->weaponsel) continue;
		  
			foreach($actions as $mode => $cof) {
				if($mode != $this->modesel) continue;

				$framesC = $cof->framesC;
				$dirC = $cof->directionsC;

				$layers = $cof->monmode_list[0][0];

				for($d = 0; $d < $dirC; $d++) {
					if($dirSel != -1 && $dirSel != $d) continue;
					$flayers[$d] = $cof->monmode_list[$this->cofdirconv8[$d]];
				}

				//each layer from COF
				$dccdone = array(); //dcc files, that were proccessed
				foreach($layers as $layer) {
				  //get frames for this direction for all layers
					if(!array_key_exists($layer, $this->dcclist)) continue;

					foreach($this->dcclist[$layer] as $part => $dccfiles) {
						foreach($dccfiles as $dccfile) {
							//SK LG LIT A1 BOW
					    $dccfmode = substr($dccfile, 7, 2);
					    $dccfweapon = substr($dccfile, 9, 3);
					    
					    if(!array_key_exists($layer, $this->part_select)) continue;
						  if($this->part_select[$layer] != $part) continue;
							if($this->modesel != $dccfmode) continue;
							if($this->weaponsel != $dccfweapon) continue;
							if(in_array($dccfile, $dccdone)) continue;

							$dccfileFull = $this->spritePath . $this->mon_code . '/' . $layer . '/' . $dccfile . '.dcc';

							if(!file_exists($dccfileFull)) continue;

							$dccdone[] = $dccfile;

							$dcc = new D2DCC($dccfileFull);
							$dcc->create_image = $DCC_create_image;
							$dcc->create_image = false; //create png files
							$dcc->create_pixelmap = true; //create pixel maps for sprites generation
							$dcc->dir_selected = $dirSel;

							$dcc->GetDCC();  //parse DCC headers
							$dcc->CreateImages(); //read encoded DCC data to make pixelmaps

							for($d = 0; $d < $dirC; $d++) {
								if($dirSel != -1 && $dirSel != $d) continue;
								$pm[$layer][$d] = $dcc->GetFramesBitmaps($d);

								if($DCC_create_image) {
									for($i = 0; $i < $framesC; $i++) {
										$imgs[$layer][$d][] = $dcc->GetImagePath($i);
									}
								}
							}
							$dcc = null;
						}
					}
				}
			}
		}
		
		if($DCC_create_image) {
			$echo = '<br /><br /><table>';
			for($i = 0; $i < $framesC; $i++) {
				$echo .= '<tr>';

				foreach($pm as $layer => $dir) {
					foreach($dir as $d => $bmps) {
						$bmp = $bmps[$i];
						$img = $imgs[$layer][$i];

						$echo .= "<td>Layer $layer:<br />";
						//$echo .= $dccfile.'<br />';

						$echo .= $bmp->w.'x'.$bmp->h.', xo '.$bmp->xoff.' yo '.$bmp->yoff
							.'<br />D '.$bmp->boxd->w.'x'.$bmp->boxd->h.', xn '.$bmp->boxd->xmin.' yn '.$bmp->boxd->ymin.', xx '.$bmp->boxd->xmax.' yx '.$bmp->boxd->ymax
							.'<br />F '.$bmp->boxf->w.'x'.$bmp->boxf->h.', xn '.$bmp->boxf->xmin.' yn '.$bmp->boxf->ymin.', xx '.$bmp->boxf->xmax.' yx '.$bmp->boxf->ymax
							.'<br /><img src="'.$img.'" style="height:80px; image-rendering: pixelated;" /><br /></td>';
						}
				}
				$echo .= '</tr>';
			}
			$echo .= '</table><br />';
			echo $echo;
		}


		$gifframes = array();

		echo 'Layers for this configuration: ';
		foreach($pm as $layer => $bitmaps) {
			echo $layer.' ';
		}
		echo '<br />';

		//sprite box for all frames
		$boxSp = new DCCBox();
		$boxSp->xmin = $boxSp->ymin = 0x80000000;
		$boxSp->xmax = $boxSp->ymax = -0x80000000;


		if(empty($pm)) {
			echo 'No pixelmaps were found<br />';
			return;
		}

		//go through all directions and frames and find the box
		for($d = 0; $d < $dirC; $d++) {
			if($dirSel != -1 && $dirSel != $d) continue;

			for($f = 0; $f < $framesC; $f++) {
				$w = 0;
				$h = 0;

				//Torso layer will be reference
				$bmpref = $pm['TR'][$d][$f];

				//find final sprite box dimensions
				foreach($pm as $layer => $bitmaps) {
					$bmp = $bitmaps[$d][$f];

					if($boxSp->xmin > $bmp->boxd->xmin) {
						$boxSp->xmin = $bmp->boxd->xmin;
					}
					if($boxSp->ymin > $bmp->boxd->ymin) {
						$boxSp->ymin = $bmp->boxd->ymin;
					}
					if($boxSp->xmax < $bmp->boxd->xmax) {
						$boxSp->xmax = $bmp->boxd->xmax;
					}
					if($boxSp->ymax < $bmp->boxd->ymax) {
						$boxSp->ymax = $bmp->boxd->ymax;
					}
					$boxSp->w = $boxSp->xmax - $boxSp->xmin + 1;
					$boxSp->h = $boxSp->ymax - $boxSp->ymin + 1;
				}
			}
		}

    for($d = 0; $d < $dirC; $d++) {
			if($dirSel != -1 && $dirSel != $d) continue;
			$dc = ($dirSel != -1) ? $d : $this->dirconv8[$d];

			for($f = 0; $f < $framesC; $f++) {

				//Torso layer will be reference
				$bmpref = $pm['TR'][$dc][$f];

				//find final sprite box dimensions
				foreach($pm as $layer => $bitmaps) {
					$bmp = $bitmaps[$dc][$f];
					
					//offset for drawing layer/part
					$bmp->x0 = $bmp->boxd->xmin - $bmpref->boxd->xmin;
					$bmp->y0 = $bmp->boxd->ymin - $bmpref->boxd->ymin;
				}


				//merge bitmaps
				$fbmp = new DCCBitmap();
				$fbmp->w = $boxSp->w;
				$fbmp->h = $boxSp->h;
				$fbmp->CreateBitmap();

				$xa = $bmpref->boxd->xmin - $boxSp->xmin;
				$ya = $bmpref->boxd->ymin - $boxSp->ymin;
				
				foreach($flayers[$dc][$f] as $layer) {
					if(!array_key_exists($layer, $pm)) continue; //skip non existed/selected layers
					if($this->SkipTRimage && $layer == 'TR') continue; //skip TR, if wanted. It's here, because we need it for reference before
					$bmp = $pm[$layer][$dc][$f];

					$trans = $this->transformLevelLayer[$layer];
					$this->BitmapCopy($bmp->bitmap, $fbmp->bitmap, $bmp->x0 + $xa, $bmp->y0 + $ya, 0, 0, $bmp->w, $bmp->h, $trans);
				}

				$this->imagepath = D2IMGPATH.'merge_D'.padleft($dc).'F'.padleft($f).'.png';
				$img = $this->PrintImage($fbmp->w, $fbmp->h, $fbmp->bitmap, $layer);

				if($this->showimg) {
					echo '<img src="'.$img.'" class="img1" /> '; //crisp-edges
				}

				if($gifmake) {
					$gifframes[$dc][] = $this->imagepath;
				}

			} //end frames
			echo '<br />';
		} //end directions

		if($gifmake) {
			$gfall = array();
			foreach($gifframes as $d => $frames) {
				$this->GifAnim($d, $frames);
				$gfall = array_merge($gfall, $frames);
			}
			if($this->dirSel == -1) {
				$this->GifAnim(99, $gfall);
			}
		}

	}

	public function BitmapCopy($bmp_src, &$bmp_dest, $dx, $dy, $sx, $sy, $sw, $sh, $trans) {
		//transform level will happen at this point when copying individual frames

		global $D2PALETTE;
		$remap = 1;
		$transformLevel = $trans;

		for ($x = 0; $x < $sw; $x++) {
			for ($y = 0; $y < $sh; $y++) {
				$colorindex = $bmp_src[$sx + $x][$sy + $y];
				if($colorindex == 0) continue;

				if($remap) {
					$colorindex = $D2PALETTE->GetRemapIndex($transformLevel, $colorindex);
				}

				$bmp_dest[$dx + $x][$dy + $y] = $colorindex;
			}
		}
	}

	public function PrintImage($w, $h, $pixelmap, $layer) {
		$im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
		global $D2PALETTE;

		$bgc = $D2PALETTE->GetColor(0);
		$bg = imagecolorallocate($im, $bgc[0], $bgc[1], $bgc[2]);
		imagecolortransparent($im, $bg);

		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$colorRGB = $D2PALETTE->GetColor($pixelmap[$x][$y]);
				$color = imagecolorallocate($im, $colorRGB[0], $colorRGB[1], $colorRGB[2]);
				imagesetpixel($im, $x, $y, $color);
			}
		}

		//make image 8bit, it changes colors a little, but only catchable by experienced eye
		//imagetruecolortopalette($im, false, 255);

		imagepng($im, $this->imagepath);
		imagedestroy($im);

		return $this->imagepath;
	}

	public function GifAnim($d, $frames) {
		//https://github.com/Sybio/GifCreator
		require_once './fun/GifCreator/GifCreator.php';

  		// Create an array containing the duration (in millisecond) of each frames (in order too)
		$durations = array_fill(0, count($frames), 5);

		// Initialize and create the GIF !
		$gc = new GifCreator();
		//frames, durations, loops
		$gc->create($frames, $durations, 0);

		$giffile = D2IMGPATH.'animD'.padleft($d, 2).'.gif';
		file_write($giffile, $gc->getGif());

		if($this->showimg) {
			echo '<img src="'.$giffile.'" class="img2" /> ';
		}
	}

	public function GetString($strid) {
		global $D2DATA;
		if(array_key_exists($strid, $D2DATA->STRINGS)) {
			return $D2DATA->STRINGS[$strid];
		}
		else {
			return null;
		}
	}

	public function GetMonData($id) {
		global $D2DATA;
		foreach($D2DATA->MONSTATS as $row) {
			if($row['Id'] == $id) {
				return $row;
			}
		}
	}

	public function GetMonData2($id) {
		global $D2DATA;
		foreach($D2DATA->MONSTATS2 as $row) {
			if($row['Id'] == $id) {
				return $row;
			}
		}
	}

	public function GetMonMode($monname) {
		global $D2DATA;
		foreach($D2DATA->MONMODE as $row) {
			if($row['name'] == $monname) {
				return $row;
			}
		}
	}

	public function GetCompCode($code) {
		global $D2DATA;
		if(array_key_exists($code, $D2DATA->COMPCODE)) {
			return $D2DATA->COMPCODE[$code];
		}
		return $code;
	}
}

/*
name	token	code
death	DT	DT
neutral	NU	NU
walk	WL	WL
gethit	GH	GH
attack1	A1	A1
attack2	A2	A2
block	BL	BL
cast	SC	SC
skill1	S1	S1
skill2	S2	S2
skill3	S3	S3
skill4	S4	S4
dead	DD	DD
knockback	GH	KB
sequence	xx	xx
run	RN	RN

Head HD
Torso TR
Legs LG
RightArm RA
LeftArm LA
RightHand RH
LeftHand LH
Shield SH
Special1 S1
Special2 S2
Special3 S3
Special4 S4
Special5 S5
Special6 S6
Special7 S7
Special8 S8

*/
?>
