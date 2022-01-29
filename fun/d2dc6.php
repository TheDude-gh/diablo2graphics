<?php

//class for reading dc6 files and converting them to png, also with transform colours
class D2Palette {

	private $paletteFile = '';
	private $remapFile = '';
	private $colors = [];
	private $remapcolors = [];
	private $tableOK = 0;

	public function __construct($palettefile = '') {
		if($palettefile != '') {
			$this->paletteFile = $palettefile;
		}
		if(!file_exists($this->paletteFile)) {
			echo 'Palette file '.$this->paletteFile.' does not exists';
			return;
		}
		$this->D2Palette();
	}

	public function D2Palette(){
		$palette = file_get_contents($this->paletteFile);
		$this->bitreader = new myByteReader($palette);

		for ($i = 0; $i < 256; $i++){
			$blue = $this->bitreader->ReadUint8();
			$green = $this->bitreader->ReadUint8();
			$red = $this->bitreader->ReadUint8();
			$this->colors[$i] = [$red, $green, $blue];
		}
	}

	public function RemapPalette($palette_shift_file) {

		$this->remapFile = $palette_shift_file;
		if(!file_exists($this->remapFile)) {
			echo 'Palette shift file does not exists ('.$this->remapFile.')<br />';
		}

		$palette = file_get_contents($this->remapFile);
		$this->bitreader = new myByteReader($palette);

		$filesize = filesize($this->remapFile);

		$rtables = $filesize / 256; //remap tables count

		$this->remapcolors = [];
		//we have dcc remap tables each 256 bytes
		for ($r = 0; $r < $rtables; $r++) {
			for ($i = 0; $i < 256; $i++){
				$newindex = $this->bitreader->ReadUint8();
				$this->remapcolors[$r][$i] = $newindex;
			}
		}
	}

	//invtrans (base item), invtransform (magic item), index from coloring
	public function GetRemapIndex($table, $index) {
		if($this->tableOK == -1) return $index;
		elseif(!array_key_exists($table, $this->remapcolors)) {
			$this->tableOK = -1;
			echo 'Remap table at '.$table.' does not exists<br />';
			return $index;
		}
		return $this->remapcolors[$table][$index];
	}

	public function ShowColors($colors) {
		echo '<table><tr>';
		foreach($colors as $k => $c) {
			if($k % 16 == 0) echo '</tr><tr>';
			echo '<td style="width:20px; background: rgb('.$c[0].', '.$c[1].', '.$c[2].');">&nbsp;</td>'.EOL;
		}
		echo '</table>';
	}

	public function GetRemapTableSize() {
		return count($this->remapcolors);
	}

	//get color by index
	public function GetColor($code){
		return array_key_exists($code, $this->colors) ? $this->colors[$code] : 0;
	}

	public function PaletteImage($remap = -1) {
		$size = 16; //16^2 colors
		$dim = 10;

		$imagepath = str_ireplace('.dat', '.png', $this->paletteFile);
		if($remap >= 0) {
			$pi = pathinfo($this->remapFile);
			$rmpname = $pi['filename'];
			$imagepath = str_replace('.png', '_'.$rmpname.'_'.$remap.'.png', $imagepath);
		}

		$im = imagecreatetruecolor($size * $dim, $size * $dim);
		foreach($this->colors as $k => $c) {
			$x = ($k % $size) * $dim;
			$y = (int)($k / $size) * $dim;

			if($remap >= 0) {
				$c = $this->colors[$this->GetRemapIndex($remap, $k)];
			}

			$color = imagecolorallocate($im, $c[0], $c[1], $c[2]);
			imagefilledrectangle($im, $x, $y, $x + $dim - 1, $y + $dim - 1, $color);
		}

		//imagetruecolortopalette($im, false, 255);
		echo "Saved $imagepath<br />";
		imagepng($im, $imagepath);
		imagedestroy($im);
	}

}

?>
