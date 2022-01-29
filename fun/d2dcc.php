<?php


//class for reading diablo DCC files

class D2DCC {

	const DCC_MAX_DIR = 32;
	const DCC_MAX_FRAME = 256;
	const DCC_MAX_PB_ENTRY = 85000;

	private $bitreader;
	private $imagefile;
	private $imagepath;
	private $imagepaths = [];
	private $dccfilename;
	private $imageEX = false;
	private $imageOK = false;

	//bitsizes table
	private $var0dec = [0, 1, 2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 26, 28, 30, 32];
	private $nb_pix_table = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

	private $signature;
	private $version;
	public $directionsC; //dir count
	public $framesC; //frames count
	private $totalSizeCoded;

	public $directions = []; //direction array
	private $diroffsets = [];

	private $bitmap; //frame buffer bitmap
	private $pixelmap; //final bitmap

	//public $transFileIndex = 0; //index of transform file
	public $transformLevel = 2; //index for palette transform from transform table file, starts from index 2

	public $dir_selected = -1;
	public $create_image = false;
	public $create_pixelmap = false;


	public function __construct($dccfile) {
		$this->imagefile = $dccfile;

		if(!file_exists($this->imagefile)) {
			echo 'Missing gfx '.$this->imagefile.'<br />';
			return;
		}

		$this->imageOK = true;

		$pi = pathinfo($this->imagefile);
		$this->dccfilename = $pi['filename'];
	}

	public function GetDCC() {
		if(!$this->imageOK) return '';

		$imagedata = file_get_contents($this->imagefile);
		$this->bitreader = new myByteReader($imagedata);

		$this->bitreader->SetPos(0);
		$this->signature = $this->bitreader->ReadUint8();
		$this->version = $this->bitreader->ReadUint8();
		$this->directionsC = $this->bitreader->ReadUint8(); //number of directions
		$this->framesC = $this->bitreader->ReadUint32();  //number of frames per direction

		$this->bitreader->SkipBytes(4); //always 1

		$this->totalSizeCoded = $this->bitreader->ReadUint32();

		//direction offset
		for($i = 0; $i < $this->directionsC; $i++) {
			$diro = $this->bitreader->ReadUint32();
			$this->diroffsets[] = $diro;
		}
		
		//read directions data
		for($d = 0; $d < $this->directionsC; $d++) {
			// if we want only one dir, skip the others
			if($this->dir_selected != -1 && $d != $this->dir_selected) {
				continue;
			}
			$this->ReadDirection($d);
		}
	}
		
	//read one direction data
	private function ReadDirection($dirnum) {
		
		$this->bitreader->SetPos($this->diroffsets[$dirnum]);

		//bitstream
		$dir = new DCCDirection();

		$dir->outSizeCoded = $this->bitreader->ReadUint32();

		$dir->compressionFlags = $this->bitreader->ReadBits(2);

		$dir->var0B = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->widthB = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->heightB = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->xoffB = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->yoffB = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->optbytesB = $this->var0dec[$this->bitreader->ReadBits(4)];
		$dir->codedBytesB = $this->var0dec[$this->bitreader->ReadBits(4)];

		// init direction box min & max
		$boxD = new DCCBox();
		$boxD->xmin = $boxD->ymin = 0x80000000;
		$boxD->xmax = $boxD->ymax = -0x80000000;

		for($n = 0; $n < $this->framesC; $n++) {
			$frame = new DCCFrame();
			$frame->var0 = $this->bitreader->ReadBits($dir->var0B);
			$frame->width = $this->bitreader->ReadBits($dir->widthB);
			$frame->height = $this->bitreader->ReadBits($dir->heightB);
			$frame->xoff = $this->bitreader->ReadBits($dir->xoffB);
			$frame->yoff = $this->bitreader->ReadBits($dir->yoffB);
			$frame->optbytes = $this->bitreader->ReadBits($dir->optbytesB);
			$frame->codedBytes = $this->bitreader->ReadBits($dir->codedBytesB);
			$frame->frameBU = $this->bitreader->ReadBits(1);

			//offsets are signed
			if($frame->xoff >= (1 << ($dir->xoffB - 1))) { //6 64
				$frame->xoff = $frame->xoff - (1 << $dir->xoffB);
			}
			if($frame->yoff >= (1 << ($dir->yoffB - 1))) { //6 64
				$frame->yoff = $frame->yoff - (1 << $dir->yoffB);
			}

			// frame box
			$box = new DCCBox();
			$box->xmin = $frame->xoff;
			$box->xmax = $box->xmin + $frame->width - 1;

			if ($frame->frameBU) { // bottom-up
				$box->ymin = $frame->yoff;
				$box->ymax = $box->ymin + $frame->height - 1;
			}
			else { // top-down
				$box->ymax = $frame->yoff;
				$box->ymin = $box->ymax - $frame->height + 1;
			}
			$box->w = $box->xmax - $box->xmin + 1;
			$box->h = $box->ymax - $box->ymin + 1;
			$frame->box = $box;

			// direction box
			if ($box->xmin < $boxD->xmin) {
				$boxD->xmin = $box->xmin;
			}
			if ($box->ymin < $boxD->ymin) {
				$boxD->ymin = $box->ymin;
			}
			if ($box->xmax > $boxD->xmax) {
				$boxD->xmax = $box->xmax;
			}
			if ($box->ymax > $boxD->ymax) {
				$boxD->ymax = $box->ymax;
			}

			$dir->frames[$n] = $frame;
		}
		// frames header END

		$boxD->w = $boxD->xmax - $boxD->xmin + 1;
		$boxD->h = $boxD->ymax - $boxD->ymin + 1;
		$dir->box = $boxD;

		//READ OPTIONAL DATA --- TODO
		if($dir->optbytesB != 0) {
			//read align bytes and align
			for($f = 0; $f < $this->framesC; $f++) {
				//$this->bitreader->SkipBytes($optbytes);
			}
		}
		//OPTIONAL DATA END

		if($dir->compressionFlags & 0x02) {
			$dir->equalCellsSize = $this->bitreader->ReadBits(20);
		}
		$dir->pixelMaskSize = $this->bitreader->ReadBits(20);
		if($dir->compressionFlags & 0x01) {
			$dir->encTypeSize = $this->bitreader->ReadBits(20);
			$dir->rawPixelCodesSize = $this->bitreader->ReadBits(20);
		}

		//get used pixels
		$dir->pixelValueKeys = [];
		$dir->pixelused = [];
		for($n = 0; $n < 8; $n++) {
			$pixelv = $this->bitreader->ReadBits(32);
			for($p = 0; $p < 32; $p++) {
				if($pixelv & (1 << $p)) {
					$pixeln = $n * 32 + $p;
					$dir->pixelused[] = $pixeln;
				}
			}
		}

		//set streams starting positions
		$dir->PSequalCells = $this->bitreader->GetBitPos();
		$this->bitreader->SkipBits($dir->equalCellsSize);

		$dir->PSpixelMask = $this->bitreader->GetBitPos();
		$this->bitreader->SkipBits($dir->pixelMaskSize);

		$dir->PSencType = $this->bitreader->GetBitPos();
		$this->bitreader->SkipBits($dir->encTypeSize);

		$dir->PSrawPixelCodes = $this->bitreader->GetBitPos();
		$this->bitreader->SkipBits($dir->rawPixelCodesSize);

		$dir->PSpixel_code_and_displacement = $this->bitreader->GetBitPos();

		$this->directions[$dirnum] = $dir;

		if($dirnum == $this->directionsC - 1) {
			$dir->pixel_code_and_displacement = ($this->bitreader->GetLength() * 8) - $this->bitreader->GetBitPos();
		}
		else {
			$dir->pixel_code_and_displacement = ($this->diroffsets[$dirnum + 1] * 8) - $this->bitreader->GetBitPos();
		}
	}

	public function CreateImages() {
		foreach($this->directions as $d => &$dir) {

			// if we want only one dir, skip the others
			if($this->dir_selected != -1 && $d != $this->dir_selected) {
				continue; 
			}
			
			$this->PrepareDirectionCells($dir);

			foreach($dir->frames as $f => &$frame) {
				$this->PrepareFrameCells($frame, $dir->box);
			}

			$this->ProccessFrames($dir, $d);
			$this->MakeFrames($dir, $d);
		}
	}

	public function PrepareDirectionCells(&$dir) {
		//equivalent of creating frame buffer bitmap
		$this->bitmap = [];
		for($x = 0; $x < $dir->box->w; $x++) {
			for($y = 0; $y < $dir->box->h; $y++) {
				$this->bitmap[$x][$y] = 0;
			}
		}

		$w = $dir->box->w;
		$h = $dir->box->h;

		$tmp = $w - 1;
		$nb_cell_w = 1 + (int)($tmp / 4);

		$tmp = $h - 1;
		$nb_cell_h = 1 + (int)($tmp / 4);

		$nb_cell = $nb_cell_w * $nb_cell_h;

		$cell_w = $cell_h = [];

		if ($nb_cell_w == 1) {
			$cell_w[0] = $w;
		}
		else {
			for ($i = 0; $i < ($nb_cell_w - 1); $i++) {
				$cell_w[$i] = 4;
			}
			$cell_w[$nb_cell_w - 1] = $w - (4 * ($nb_cell_w - 1));
		}

		if ($nb_cell_h == 1) {
			$cell_h[0] = $h;
		}
		else {
			for ($i = 0; $i < ($nb_cell_h - 1); $i++) {
				$cell_h[$i] = 4;
			}
			$cell_h[$nb_cell_h - 1] = $h - (4 * ($nb_cell_h - 1));
		}

		$dir->nb_cells_w = $nb_cell_w;
		$dir->nb_cells_h = $nb_cell_h;

		$y0 = 0;
		for ($y = 0; $y < $nb_cell_h; $y++) {
			$x0 = 0;
			for ($x = 0; $x < $nb_cell_w; $x++) {
				$cell = new DCCcell();
				$cell->x0 =$x0;
				$cell->y0 =$y0;
				$cell->w = $cell_w[$x];
				$cell->h = $cell_h[$y];

				$dir->cells[] = $cell;
				$x0 += 4;
			}
			$y0 += 4;
		}

	}

	public function PrepareFrameCells(&$frame, $dbox) {
		$nb_cell_w = $nb_cell_h = $nb_cell = 0;
		$cell_w = [];
		$cell_h = [];

		// width (in # of pixels) in 1st column
		$w = 4 - (($frame->box->xmin - $dbox->xmin) % 4);

		if (($frame->box->w - $w) <= 1) { // if 2nd column is 0 or 1 pixel width
			$nb_cell_w = 1;
		}
		else {
			// so, we have minimum 2 pixels behind 1st column
			$tmp = $frame->box->w - $w - 1; // tmp is minimum 1, can't be 0
			$nb_cell_w = 2 + (int)($tmp / 4);
			if (($tmp % 4) == 0) {
				$nb_cell_w--;
			}
		}

		$h = 4 - (($frame->box->ymin - $dbox->ymin) % 4);
		if (($frame->box->h - $h) <= 1) {
			$nb_cell_h = 1;
		}
		else {
			$tmp = $frame->box->h - $h - 1;
			$nb_cell_h = 2 + (int)($tmp / 4);
			if (($tmp % 4) == 0) {
				$nb_cell_h--;
			}
		}

		$nb_cell = $nb_cell_w * $nb_cell_h;


		if ($nb_cell_w == 1) {
			$cell_w[0] = $frame->box->w;
		}
		else {
			$cell_w[0] = $w;
			for ($i = 1; $i < ($nb_cell_w - 1); $i++) {
				$cell_w[$i] = 4;
			}
			$cell_w[$nb_cell_w - 1] = $frame->box->w - $w - (4 * ($nb_cell_w - 2));
		}

		if ($nb_cell_h == 1) {
			$cell_h[0] = $frame->box->h;
		}
		else {
			$cell_h[0] = $h;
			for ($i = 1; $i < ($nb_cell_h - 1); $i++) {
				$cell_h[$i] = 4;
				}
			$cell_h[$nb_cell_h - 1] = $frame->box->h - $h - (4 * ($nb_cell_h - 2));
		}

		$frame->nb_cells_w = $nb_cell_w;
		$frame->nb_cells_h = $nb_cell_h;

		$y0 = $frame->box->ymin - $dbox->ymin;
		for ($y = 0; $y < $nb_cell_h; $y++) {
			$x0 = $frame->box->xmin - $dbox->xmin;
			for ($x = 0; $x < $nb_cell_w; $x++) {
				$cell = new DCCcell();
				$cell->x0 = $x0;
				$cell->y0 = $y0;
				$cell->w  = $cell_w[$x];
				$cell->h  = $cell_h[$y];

				$frame->cells[] = $cell;
				$x0 += $cell->w;
			}
			$y0 += $cell->h;
		}
	}

	public function ProccessFrames(&$dir, $d) {
		$buff_w  = $dir->nb_cells_w;
		$buff_h  = $dir->nb_cells_h;
		$nb_cell = $buff_w * $buff_h;

		$pb_idx = -1; // current entry of pixel_buffer

		$cell_buffer = [];

		$echo = '';

		for ($f = 0; $f < $this->framesC; $f++) {
			$frame = $dir->frames[$f];

			$cell_w  = $frame->nb_cells_w;
			$cell_h  = $frame->nb_cells_h;
			$cell0_x = (int)(($frame->box->xmin - $dir->box->xmin) / 4);
			$cell0_y = (int)(($frame->box->ymin - $dir->box->ymin) / 4);

			// for each cells of this frame
			for ($y = 0; $y < $cell_h; $y++) {
				$curr_cell_y = $cell0_y + $y;
				for ($x = 0; $x < $cell_w; $x++) {
					$curr_cell_x = $cell0_x + $x;
					$curr_cell = $curr_cell_x + ($curr_cell_y * $buff_w);

					if ($curr_cell >= $nb_cell) {
						echo "CELL overflow ($curr_cell >= $nb_cell)<br />";
						return 1;
					}

					// check if this cell need a new entry in pixel_buffer
					$next_cell = false;
					$isEqual = 0;

					$entry = new PixelEntry();

					if (array_key_exists($curr_cell, $cell_buffer)) {
						if ($dir->equalCellsSize) {
							$this->bitreader->SetBitPos($dir->PSequalCells + $dir->PequalCells);
							$isEqual = $this->bitreader->ReadBits(1);
							$dir->PequalCells += 1;
						}

						if ($isEqual == 0) {
							$this->bitreader->SetBitPos($dir->PSpixelMask + $dir->PpixelMask);
							$pixel_mask = $this->bitreader->ReadBits(4);
							$dir->PpixelMask += 4;
						}
						else {
							$next_cell = true;
						}
					}
					else {
						$pixel_mask = 0x0F;
					}

					if ($next_cell == false) {
						// decode the pixels
						// read_pixel[] is a stack, where we push the pixel code
						$read_pixel = [0, 0, 0, 0];
						$last_pixel = 0;
						$nb_pix = $this->nb_pix_table[$pixel_mask];
						if ($nb_pix && $dir->encTypeSize) {
							$this->bitreader->SetBitPos($dir->PSencType + $dir->PencType);
							$encoding_type = $this->bitreader->ReadBits(1);
							$dir->PencType += 1;
						}
						else {
							$encoding_type = 0;
						}

						$decoded_pix = 0;
						for ($i = 0; $i < $nb_pix; $i++) {
							if ($encoding_type) {
								$this->bitreader->SetBitPos($dir->PSrawPixelCodes + $dir->PrawPixelCodes);
								$read_pixel[$i] = $this->bitreader->ReadBits(8);
								$dir->PrawPixelCodes += 8;
							}
							else {
								$read_pixel[$i] = $last_pixel;
								$this->bitreader->SetBitPos($dir->PSpixel_code_and_displacement + $dir->Ppixel_code_and_displacement);
								$pix_displ = $this->bitreader->ReadBits(4);
								$dir->Ppixel_code_and_displacement += 4;
								$read_pixel[$i] += $pix_displ;

								while ($pix_displ == 15) {
									$pix_displ = $this->bitreader->ReadBits(4);
									$dir->Ppixel_code_and_displacement += 4;
									$read_pixel[$i] += $pix_displ;
								}
							}

							if ($read_pixel[$i] == $last_pixel) {
								$read_pixel[$i] = 0; // discard this pixel
								break; // stop the decoding of pixels
							}
							else {
								$last_pixel = $read_pixel[$i];
								$decoded_pix++;
							}
						}

						// we have the 4 pixels code for the new entry in pixel_buffer
						$old_entry = array_key_exists($curr_cell, $cell_buffer) ? $cell_buffer[$curr_cell] : new PixelEntry();
						$pb_idx++;
						if ($pb_idx >= D2DCC::DCC_MAX_PB_ENTRY) {
							echo 'Cell buffer overflow<br />';
						}

						$dir->pixelBuf[$pb_idx] = new PixelEntry();

						$curr_idx  = $decoded_pix - 1;
						for ($i = 0; $i < 4; $i++) {
							if ($pixel_mask & (1 << $i)) {
								if ($curr_idx >= 0) { // if stack is not empty, pop it
									$dir->pixelBuf[$pb_idx]->val[$i] = $read_pixel[$curr_idx--] & 0xff;
								}
								else { // else pop a 0
									$dir->pixelBuf[$pb_idx]->val[$i] = 0;
								}
							}
							else {
								$dir->pixelBuf[$pb_idx]->val[$i] = $old_entry->val[$i];
							}
						}

						$dir->pixelBuf[$pb_idx]->frame = $f;
						$dir->pixelBuf[$pb_idx]->frame_cell_index = $x + ($y * $cell_w);
						$cell_buffer[$curr_cell] = $dir->pixelBuf[$pb_idx];
					}
				}
			}
		}

		// replace pixel codes in pixel_buffer by their true values for palette
		for ($i = 0; $i <= $pb_idx; $i++) {
			for ($x = 0; $x < 4; $x++) {
				$y = $dir->pixelBuf[$i]->val[$x];
				$dir->pixelBuf[$i]->val[$x] = $dir->pixelused[$y];
			}
		}

		// end
	}

	// decompression of the direction frames
	public function MakeFrames(&$dir, $d) {
		$echo = '';
		// initialised the last_w & last_h of the buffer cells
		for ($c = 0; $c < $dir->nb_cells_w * $dir->nb_cells_h; $c++) {
			$dir->cells[$c]->last_w = -1;
			$dir->cells[$c]->last_h = -1;
		}

		// create the temp frame bitmap (size = current direction box)
		$this->pixelmap = [];

		$ipb = 0; //pixel buffer index

		// for all frames
		for ($f = 0; $f < $this->framesC; $f++) {
			//clear final buffer for each frame
			for ($x = 0; $x < $dir->box->w; $x++) {
				for ($y = 0; $y < $dir->box->h; $y++) {
					$this->pixelmap[$x][$y] = 0;
				}
			}

			$frame = $dir->frames[$f];
			$nb_cell = $frame->nb_cells_w * $frame->nb_cells_h;

			// for all cells of this frame
			for ($c = 0; $c < $nb_cell; $c++) {
				// frame cell
				$cell = $frame->cells[$c];

				// buffer cell
				$cell_x = (int)($cell->x0 / 4);
				$cell_y = (int)($cell->y0 / 4);
				$cell_idx  = $cell_x + ($cell_y * $dir->nb_cells_w);
				$buff_cell = $dir->cells[$cell_idx];

				$entry = array_key_exists($ipb, $dir->pixelBuf) ? $dir->pixelBuf[$ipb] : new PixelEntry();

				// equal cell checks
				if ($entry->frame != $f || $entry->frame_cell_index != $c) {
					// this buffer cell have an equalcell bit set to 1
					// so either copy the frame cell or clear it

					if (($cell->w != $buff_cell->last_w) || ($cell->h != $buff_cell->last_h)) {
						// different sizes
						$this->ClearCellToColor($cell, 0); // set all pixels of the frame cell to 0
					}
					else {
						// same sizes
						// copy the old frame cell into its new position
						$this->BufferPixelMapCopy($buff_cell->last_x0, $buff_cell->last_y0, $cell->x0, $cell->y0, $cell->w, $cell->h);

						// copy it again, into the final frame bitmap
						$this->PixelMapCopy($cell->x0, $cell->y0, $cell->w, $cell->h);
					}
				}
				else {
					// fill the frame cell with pixels
					if ($entry->val[0] == $entry->val[1]) {
						// fill FRAME cell to color val[0]
						$this->ClearCellToColor($cell, $entry->val[0]);
					}
					else {
						if ($entry->val[1] == $entry->val[2]) {
							$nb_bit = 1;
						}
						else {
							$nb_bit = 2;
						}

						// fill FRAME cell with pixels
						for ($y = $cell->y0; $y < $cell->y0 + $cell->h; $y++) {
							for ($x = $cell->x0; $x < $cell->x0 + $cell->w; $x++) {
								$this->bitreader->SetBitPos($dir->PSpixel_code_and_displacement + $dir->Ppixel_code_and_displacement);
								$pix = $this->bitreader->ReadBits($nb_bit);
								$dir->Ppixel_code_and_displacement += $nb_bit;

								$this->bitmap[$x][$y] = $entry->val[$pix];
							}
						}
					}

					// copy the frame cell into the frame bitmap
					$this->PixelMapCopy($cell->x0, $cell->y0, $cell->w, $cell->h);

					// next pixelbuffer entry
					$ipb++;
				}

				// for the buffer cell that was used by this frame cell,
				// save the width & size of the current frame cell
				// (needed for further tests about equalcell)
				$dir->cells[$cell_idx]->last_w  = $cell->w;
				$dir->cells[$cell_idx]->last_h  = $cell->h;

				// and save its origin, for further copy when equalcell
				$dir->cells[$cell_idx]->last_x0  = $cell->x0;
				$dir->cells[$cell_idx]->last_y0  = $cell->y0;
			}

			//probably here
			if($this->create_image) {
				$this->imagepath = D2IMGPATH.$this->dccfilename.'_d'.padleft($d+1).'f'.padleft($f+1).'.png';
				$this->imagepaths[$f] = $this->imagepath;
				$this->GetFrameImage($dir);
			}
			if($this->create_pixelmap) {
				$bm = new DCCBitmap();
				$bm->x0 = 0;
				$bm->y0 = 0;
				$bm->xoff = $frame->xoff;
				$bm->yoff = $frame->yoff;
				$bm->w = $dir->box->w;
				$bm->h = $dir->box->h;
				$bm->boxd = $dir->box;
				$bm->boxf = $frame->box;
				$bm->bitmap = $this->pixelmap;
				$dir->pixelmap[$f] = $bm;
			}
		}
		// end
	}

	public function PixelMapCopy($x0, $y0, $w, $h) {
		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$this->pixelmap[$x0 + $x][$y0 + $y] = $this->bitmap[$x0 + $x][$y0 + $y];
			}
		}
	}

	public function BufferPixelMapCopy($xsrc, $ysrc, $xdest, $ydest, $w, $h) {
		$buf = [];
		//save source to buffer
		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$buf[$xdest + $x][$ydest + $y] = $this->bitmap[$xsrc + $x][$ysrc + $y];
			}
		}

		//copy source buffer to destination
		for ($x = $xdest; $x < $w + $xdest; $x++) {
			for ($y = $ydest; $y < $h + $ydest; $y++) {
				$this->bitmap[$x][$y] = $buf[$x][$y];
			}
		}
	}

	public function ClearCellToColor($cell, $colorindex = 0) {
		for ($y = $cell->y0; $y < $cell->y0 + $cell->h; $y++) {
			for ($x = $cell->x0; $x < $cell->x0 + $cell->w; $x++) {
				$this->bitmap[$x][$y] = $colorindex;
			}
		}
	}

	public function GetFramesBitmaps($d) {
		if($d >= $this->directionsC) return [];
		return $this->directions[$d]->pixelmap;
	}

	public function GetFrameImage($dir) {
		$im = imagecreatetruecolor($dir->box->w, $dir->box->h);
		$remap = 1;

		global $D2PALETTE;

		/*
		//make background color transparent
		$bgc = $D2PALETTE->GetColor(0);

		$bg = imagecolorallocate($im, $bgc[0], $bgc[1], $bgc[2]);
		imagecolortransparent($im, $bg);
		imagefill($im, 0, 0, $bg);
		*/

		for ($x = 0; $x < $dir->box->w; $x++) {
			for ($y = 0; $y < $dir->box->h; $y++) {

				$colorindex = $this->pixelmap[$x][$y];

				if($remap) {
					$colorindex = $D2PALETTE->GetRemapIndex($this->transformLevel, $colorindex);
				}

				$colorRGB = $D2PALETTE->GetColor($colorindex);
				$color = imagecolorallocate($im, $colorRGB[0], $colorRGB[1], $colorRGB[2]);
				imagesetpixel($im, $x, $y, $color);
			}
		}

		imagepng($im, $this->imagepath);
		imagedestroy($im);

		return $this->imagepath;
	}

	public function GetImagePath($f) {
		return $this->imagepaths[$f];
	}

	public function ShowImages() {
		foreach($this->imagepaths as $img) {
			echo '<img src="'.$img.'" style="height:150px; image-rendering: pixelated;" /> ';
		}
	}

}



class DCCDirection {
	public $outSizeCoded;
	public $compressionFlags;
	public $var0B;
	public $widthB;
	public $heightB;
	public $xoffB;
	public $yoffB;
	public $optbytesB;
	public $codedBytesB;

	public $frames = [];

	public $equalCellsSize = 0;
	public $pixelMaskSize = 0;
	public $encTypeSize = 0;
	public $rawPixelCodesSize = 0;
	public $pixel_code_and_displacement = 0;

	//bit start positions of the data
	public $PSequalCells = 0;
	public $PSpixelMask = 0;
	public $PSencType = 0;
	public $PSrawPixelCodes = 0;
	public $PSpixel_code_and_displacement = 0;

	//bit move positions of the data
	public $PequalCells = 0;
	public $PpixelMask = 0;
	public $PencType = 0;
	public $PrawPixelCodes = 0;
	public $Ppixel_code_and_displacement = 0;

	public $pixelValueKeys = [];
	public $pixelused = [];

	public $box;
	public $nb_cells_w;
	public $nb_cells_h;
	public $cells = [];
	public $pixelBuf = [];
	public $pixelmap = []; //final pixel bitmaps for this diretion for each frame
}

class DCCFrame {
	public $var0;
	public $width;
	public $height;
	public $xoff;
	public $yoff;
	public $optbytes;
	public $codedBytes;
	public $frameBU;

	public $box;
	public $nb_cells_w;
	public $nb_cells_h;
	public $cells = [];

}

class DCCBox {
	public $xmin;
	public $xmax;
	public $ymin;
	public $ymax;
	public $w;
	public $h;
}

class DCCcell {
	public $x0;
	public $y0;
	public $w;
	public $h;
	public $last_w = 0;
	public $last_h = 0;
	public $last_x0 = 0;
	public $last_y0 = 0;
}

class PixelEntry {
	public $val = [0, 0, 0, 0];
	public $frame = 0;
	public $frame_cell_index = 0;
}

class DCCBitmap {
	public $w = 0;
	public $h = 0;
	public $x0 = 0; //origin point upper left x,y
	public $y0 = 0; //
	public $xoff = 0;
	public $yoff = 0;
	public $boxf;
	public $boxd;
	public $bitmap = []; //pixel bitmap

	public function CreateBitmap() {
		for ($x = 0; $x < $this->w; $x++) {
			for ($y = 0; $y < $this->h; $y++) {
				$this->bitmap[$x][$y] = 0;
			}
		}
	}
}

function spr($spr) {
	echo '<pre class="mono">'.$spr.'</pre>';
}

?>
