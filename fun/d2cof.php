<?php

//class to read diablo COF files

class D2COF {
	private $bitreader;
	public $coffile;
	public $coffilename;
	private $fileOK = false;

	public $layers = 0;
	public $framesC = 0;
	public $directionsC = 0;
	public $wclass = []; //weapon class for each layer
	public $monmode_list = [];

	/*
	BODY PARTS
	00 HD  head
	01 TR  torso
	02 LG  legs
	03 RA  right arm
	04 LA  left arm
	05 RH  right hand
	06 LH  left hand
	07 SH  shield
	08 S1  special 1
	09 S2  special 2
	10 S3  special 3
	11 S4  special 4
	12 S5  special 5
	13 S6  special 6
	14 S7  special 7
	15 S8  special 8

	ACTION/MOVEMENT modes
	01  DT  Death
	02  NU  Neutral
	03  WL  Walk
	04  RN  Run
	05  GH  Get Hit
	06  TN  Town Neutral
	07  TW  Town Walk
	08  A1  Attack1
	09  A2  Attack2
	10  BL  Block
	11  SC  Cast
	12  TH  Throw
	13  KK  Kick
	14  S1  Skill1
	15  S2  Skill2
	16  S3  Skill3
	17  S4  Skill4
	18  DD  Dead
	19  GH  Sequence
	20  GH Knock back
	*/

	public $composit = ['HD', 'TR', 'LG', 'RA', 'LA', 'RH', 'LH', 'SH', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8'];

	public function __construct($coffile) {
		$this->coffile = $coffile;

		$pi = pathinfo($coffile);
		$this->coffilename = $pi['filename'];

		if(!file_exists($this->coffile)) {
			echo 'Missing gfx '.$this->coffile.'<br />';
			return;
		}

		$this->fileOK = true;
	}

	public function GetCOF() {
		if(!$this->fileOK) return '';

		$imagedata = file_get_contents($this->coffile);
		$this->bitreader = new myByteReader($imagedata);

		$this->bitreader->SetPos(0);
		$this->layers = $this->bitreader->ReadUint8();
		$this->framesC = $this->bitreader->ReadUint8();
		$this->directionsC = $this->bitreader->ReadUint8();

		$this->bitreader->SkipBytes(5);
		$this->bitreader->SkipBytes(16);
		$this->bitreader->SkipBytes(4);

		for($i = 0; $i < $this->layers; $i++) {
			$num = $this->bitreader->ReadUint8(); //index in composit array
			$this->bitreader->SkipBytes(4);
			$this->wclass[$this->composit[$num]] = $this->bitreader->ReadString(); //weapon class for this layer
		}

		$this->bitreader->SkipBytes($this->framesC);

		for($d = 0; $d < $this->directionsC; $d++) {
			for($f = 0; $f < $this->framesC; $f++) {
				for($l = 0; $l < $this->layers; $l++) {
					$c = $this->bitreader->ReadUint8();
					$this->monmode_list[$d][$f][$l] = $this->composit[$c];
				}
			}
		}

		//free bitreader, not needed anymore
		$this->bitreader = null;

	}

	public function ShowCOF() {
		$out = sprintf('COF %s'.EOL, $this->coffile);
		$out .= sprintf('Directions %3d'.EOL, $this->directionsC);
		$out .= sprintf('Frames / D %3d'.EOL, $this->framesC);
		$out .= sprintf('Layers     %3d'.EOL.EOL, $this->layers);
		for($d = 0; $d < $this->directionsC; $d++) {
			for($f = 0; $f < $this->framesC; $f++) {
				$out .= sprintf('D %3d F %3d : %s'.EOL, $d, $f, implode($this->monmode_list[$d][$f], ' '));
			}
			$out .= EOL;
		}
		spr($out);
	}

}




?>
