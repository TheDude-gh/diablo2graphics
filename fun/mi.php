<?php

//common functions
	function expost($name, $def = null){
		return isSet($_POST[$name]) ? $_POST[$name] : $def;
	}

	function exget($name, $def = null){
		return isSet($_GET[$name]) ? $_GET[$name] : $def;
	}

	function excookie($name, $def = null){
		return isSet($_COOKIE[$name]) ? $_COOKIE[$name] : $def;
	}

	function SetDef($var, $def = null){
		if(!$var) $var = $def;
	}

//FILE
	function file_write($filename, $data){
		return file_put_contents($filename, $data);
	}

	function file_append($filename, $data){
		return file_put_contents($filename, $data, FILE_APPEND);
	}

	function comma($value){
		return number_format($value, 0, ',', '&nbsp;');
	}

	function padleft($value, $len = 2, $char = '0'){
		return str_pad($value, $len, $char, STR_PAD_LEFT);
	}

	function vd($var){
		echo '<pre class="vardump">';
		var_dump($var);
		echo '</pre>';
	}

	function pre($var){
		echo '<pre class="vardump">'.$var.'</pre>';
	}

?>
