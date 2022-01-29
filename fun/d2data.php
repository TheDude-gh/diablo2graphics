<?php

class D2Data {

	public $MONSTATS;
	public $MONSTATS2;
	public $MONMODE;
	public $COMPCODE;
	public $COMPOSIT;
	public $PLRTYPE;

	public $STRINGS = [];

	private $stringfile = D2STRINGPATH.'strings.txt'; //converted string file


	public function __construct() {
		$this->MONSTATS  = $this->ReadMonStats();
		$this->MONSTATS2 = $this->ReadMonStats2();
		$this->MONMODE   = $this->ReadMonMode();
		$this->COMPCODE  = $this->ReadCompCode();
		$this->COMPOSIT  = $this->ReadComposit();
		$this->PLRTYPE   = $this->ReadPlrType();


		if(!file_exists($this->stringfile)) {
			$this->ReadTblFile(D2STRINGPATH.'string.tbl');
			$this->ReadTblFile(D2STRINGPATH.'expansionstring.tbl');
			$this->ReadTblFile(D2STRINGPATH.'patchstring.tbl');
			$this->MakeStringTxtFile();
		}
		else {
			$this->LoadStringTxtFile();
		}
	}

	public function ReadMonStats() {
		$txtfile = 'MonStats';
		$select = ['Id', 'hcIdx', 'TransLvl', 'NameStr', 'Code'];
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadMonStats2() {
		$txtfile = 'monstats2';
		$select = ['Id', 'BaseW', 'HDv', 'TRv', 'LGv', 'Rav', 'Lav', 'RHv', 'LHv', 'SHv', 'S1v', 'S2v', 'S3v', 'S4v', 'S5v', 'S6v', 'S7v', 'S8v',
			'HD', 'TR', 'LG', 'RA', 'LA', 'RH', 'LH', 'SH', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8',
			'Utrans', 'Utrans(N)', 'Utrans(H)'];
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadMonMode() {
		$txtfile = 'MonMode';
		$select = ['name', 'code'];
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadCompCode() {
		$txtfile = 'CompCode';
		$select = ['component', 'code'];
		$compcodes = $this->ReadTxtFile($txtfile, $select);
		$res = [];
		foreach($compcodes as $compcode) {
			$res[$compcode['code']] = $compcode['component'];
		}
		return $res;
	}

	public function ReadComposit() {
		$txtfile = 'Composit';
		$select = ['Name', 'Token'];
		$composit = $this->ReadTxtFile($txtfile, $select);
		$res = [];
		foreach($composit as $c) {
			$res[$c['Token']] = $c['Name'];
		}
		return $res;
	}

	public function ReadPlrType() {
		$txtfile = 'PlrType';
		$select = ['Name', 'Token'];
		return $this->ReadTxtFile($txtfile, $select);
	}

	//reading of txt files
	public function ReadTxtFile($txtfile, $select) {
		$path = D2TXTPATH;
		$ext = '.txt';

		if(!file_exists($path.$txtfile.$ext)) {
			echo 'File ['.$path.$txtfile.$ext.'] not found<br />';
			return;
		}

		$filedata = file($path.$txtfile.$ext);
		$txtdata = [];
		$header = [];

		//in some files skip Expansion index, because it does not count
		$skipExp = ($txtfile == 'UniqueItems' || $txtfile == 'SetItems') ? true : false;
		$indexExp = 'Expansion';

		foreach($filedata as $k => $data) {
			$data = explode(TAB, $data);
			if($k == 0) {
				//get indexes of data we want to gather them
				foreach($data as $k => $title) {
					$title = trim($title);
					if(!in_array($title, $select)) {
						continue;
					}
					$header[$title] = $k;
				}
				continue;
			}

			//skipping Expansion text for some files, otherwise it breaks indexing from various item props
			if($skipExp && $data[0] == $indexExp) {
				continue;
			}

			$row = [];
			foreach($select as $title) {
				$row[$title] = trim($data[$header[$title]]);
			}
			$txtdata[] = $row;
		}
		$filedata = null;
		return $txtdata;
	}

	//dbg function
	public function ShowTxt($txtdata) {
		foreach($txtdata as $n => $d) {
			echo "$n ** ";
			foreach($d as $k => $v) {
				echo "$k: $v, ";
			}
			echo '<br />';
		}
	}

	//get string from string array
	public function GetString($index) {
		if(array_key_exists($index, $this->STRINGS)) {
			return $this->STRINGS[$index];
		}
		else {
			return '???';
		}
	}

	//dbg function
	public function ShowStrings() {
		echo '<table>';
		$n = 1;
		$empty = 0;
		foreach($this->STRINGS as $strid => $strname) {
			echo "<tr><td>$n</td><td>$strid</td><td>[$strname]</td></tr>";
			if(trim($strname) == '') $empty++;
			$n++;
		}
		echo '</table>';
		echo $empty.'<br /><br />';
	}

	//read tbl file and save to string array and file
	public function ReadTblFile($tblfile) {

		if(!file_exists($tblfile)) {
			echo 'File ['.$tblfile.'] not found<br />';
			return;
		}

		$data = file_get_contents($tblfile);

		$bit = new myByteReader($data);

		$bit->SkipBytes(2);

		$numstrID = $bit->ReadUint16();
		$numstr = $bit->ReadUint16();

		//$bit->SkipBytes(3);
		//$first = $bit->ReadUint32(); //first string pos
		//$bit->SkipBytes(4);
		//$last = $bit->ReadUint32(); //last string pos

		$bit->SkipBytes(15); //we dont need the data, so we just skip it 3+4+4+4

		/*for($i = 0; $i < $numstrID; $i++) {
			$id = $bit->ReadUint16();
		}*/
		$bit->SkipBytes($numstrID * 2); //we dont need the data, so we just skip it 2*$numstrID


		/*for($i = 0; $i < $numstr; $i++) {
			$bit->SkipBytes(1);
			$id = $bit->ReadUint16();
			$bit->SkipBytes(4);
			$ofsname = $bit->ReadUint32();
			$ofsstr = $bit->ReadUint32();
			$strlen = $bit->ReadUint16();
		}*/
		$bit->SkipBytes($numstr * 17); //we dont need the data, so we just skip it (1+2+4+4+4+2)*$numstr

		$even = 0;
		for($i = 0; $i < $numstrID; $i++) {
			$strid = trim($bit->ReadString());
			$strname = $bit->ReadString();
			$this->STRINGS[$strid] = $strname;
		}
	}

	//make string file from string array, so we dont have to read tbl files everytime
	//also skip string with EOL in them, because it's not needed for our purposes and it's mostly NPC chat anyway
	public function MakeStringTxtFile() {
		$pairs = '';
		foreach($this->STRINGS as $strid => $strname) {
			if(strpos($strname, EOL) !== false) continue; //skip string with EOL
			$pairs .= $strid.TAB.str_replace(EOL, '\\n', $strname).EOL;
		}
		file_write($this->stringfile, $pairs);
	}

	//load string file
	public function LoadStringTxtFile() {
		$data = file($this->stringfile);
		foreach($data as $str) {
			$str = str_replace(EOL, '', $str);
			list($strid, $strname) = explode(TAB, $str);
			$this->STRINGS[$strid] = $strname;
		}
	}

	//character cell parts. Instead of building it from dcc files names, it is hardcoded here
	public $CHAR_PARTS = [
		'ai' => [
			'HD' => ['bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['axf', 'clw', 'hxb', 'ktr', 'lbb', 'lbw', 'lit', 'lxb', 'sbb', 'sbw', 'skr'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'axf', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'clw', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'ktr', 'lax', 'lit', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'skr', 'spr', 'ssd', 'sst', 'tri', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'am' => [
			'HD' => ['bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['am1', 'am2', 'hxb', 'lbb', 'lbw', 'lxb', 'sbb', 'sbw'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['am3', 'axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'ba' => [
			'HD' => ['ba1', 'ba3', 'ba5', 'bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['axe', 'bsd', 'bwn', 'clb', 'clm', 'crs', 'dgr', 'dir', 'fla', 'flc', 'glv', 'gpl', 'gps', 'gsd', 'hax', 'jav', 'lbb', 'lbw', 'lsd', 'mac', 'opl', 'ops', 'pil', 'sbb', 'sbw', 'scm', 'ssd', 'whm', 'wnd', 'ywn'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'chars' => [
		],
		'dz' => [
			'HD' => ['bhm', 'cap', 'crn', 'dr1', 'dr3', 'dr4', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['hxb', 'lbb', 'lbw', 'lwb', 'lxb', 'sbb', 'sbw'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'vpl', 'vps', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'ne' => [
			'HD' => ['bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['hxb', 'lbb', 'lbw', 'lxb', 'sbb', 'sbw'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'S3' => ['ne1', 'ne2', 'ne3'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'pa' => [
			'HD' => ['bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['hxb', 'lbb', 'lbw', 'lwb', 'lxb', 'sbb', 'sbw'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'fls', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'opl', 'ops', 'pax', 'pik', 'pil', 'pot', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'vpl', 'vps', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'hsh', 'kit', 'lrg', 'pa1', 'pa3', 'pa5', 'spk', 'tor', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
		'so' => [
			'HD' => ['bhm', 'cap', 'crn', 'fhl', 'ghm', 'hlm', 'lit', 'msk', 'skp'],
			'LA' => ['hvy', 'lit', 'med'],
			'LG' => ['hvy', 'lit', 'med'],
			'LH' => ['lbb', 'lbw', 'sbb', 'sbw'],
			'RA' => ['hvy', 'lit', 'med'],
			'RH' => ['axe', 'brn', 'bsd', 'bst', 'btx', 'bwn', 'clb', 'clm', 'crs', 'cst', 'dgr', 'dir', 'fla', 'flc', 'gix', 'glv', 'gpl', 'gps', 'gsd', 'hal', 'hax', 'hxb', 'jav', 'lax', 'lsd', 'lst', 'lxb', 'mac', 'mau', 'ob1', 'ob3', 'ob4', 'opl', 'ops', 'pax', 'pik', 'pil', 'scm', 'scy', 'spr', 'ssd', 'sst', 'tri', 'whm', 'wnd', 'ywn'],
			'S1' => ['hvy', 'lit', 'med'],
			'S2' => ['hvy', 'lit', 'med'],
			'SH' => ['bsh', 'buc', 'kit', 'lrg', 'spk', 'tow'],
			'TR' => ['hvy', 'lit', 'med'],
		],
	];
}
?>
