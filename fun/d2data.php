<?php

//class for getting diablo TXT data

class D2Data {

	//needed data arrays
	public $MONSTATS;
	public $MONSTATS2;
	public $MONMODE;
	public $COMPCODE;

	//converted strings from all tbl files
	public $STRINGS = array();

	//file with converted strings
	private $stringfile = D2STRINGPATH.'strings.txt'; //converted string file


	public function __construct() {
		$this->MONSTATS = $this->ReadMonStats();
		$this->MONSTATS2 = $this->ReadMonStats2();
		$this->MONMODE = $this->ReadMonMode();
    $this->COMPCODE = $this->ReadCompCode();


		//read string file, if not found, proccess tbl files
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
		$select = array('Id', 'hcIdx', 'TransLvl', 'NameStr', 'Code');
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadMonStats2() {
		$txtfile = 'monstats2';
		$select = array('Id', 'BaseW', 'HDv', 'TRv', 'LGv', 'Rav', 'Lav', 'RHv', 'LHv', 'SHv', 'S1v', 'S2v', 'S3v', 'S4v', 'S5v', 'S6v', 'S7v', 'S8v',
    	'HD', 'TR', 'LG', 'RA', 'LA', 'RH', 'LH', 'SH', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8',
			'Utrans', 'Utrans(N)', 'Utrans(H)');
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadMonMode() {
		$txtfile = 'MonMode';
		$select = array('name', 'code');
		return $this->ReadTxtFile($txtfile, $select);
	}

	public function ReadCompCode() {
		$txtfile = 'CompCode';
		$select = array('component', 'code');
		$compcodes = $this->ReadTxtFile($txtfile, $select);
		$res = array();
		foreach($compcodes as $compcode) {
			$res[$compcode['code']] = $compcode['component'];
		}
		return $res;
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
		$txtdata = array();
		$header = array();

		//in some files skip Expansion index, because it does not count
		$skipExp = ($txtfile == 'UniqueItems' || $txtfile == 'SetItems') ? true : false;
		$indexExp = 'Expansion';

		foreach($filedata as $k => $data) {
			$data = explode(TAB, $data);
			if($k == 0) {
				//get indexes of data we want to gather them
				foreach($data as $k => $title) {
					$title = trim($title);
					if(!in_array($title, $select)) continue;
					$header[$title] = $k;
				}
				continue;
			}

			if($skipExp && $data[0] == $indexExp) continue; //skipping Expansion text for some files, otherwise it breaks indexing from various item props

			$row = array();
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

}
?>
