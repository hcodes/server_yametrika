<?php

function __autoload($className) {
	$classPath = dirname(__FILE__)."/classes/".str_replace("_", "/", $className).".php";
	if (file_exists($classPath)) {
		require_once $classPath;
	}
}

//http://www.php.net/manual/en/function.json-encode.php#100835
if (!function_exists("json_encode")) {
	function json_encode($data) {
		if (is_array($data) || is_object($data)) {
			$islist = is_array($data) && (empty($data) || array_keys($data) === range(0, count($data) - 1));
			
			if ($islist) {
				$json = "[".implode(",", array_map("json_encode", $data))."]";
			} else {
				$items = array();
				foreach ($data as $key => $value) {
					$items[] = json_encode("$key").":".json_encode($value);
				}
				$json = "{".implode(",", $items)."}";
			}
		} elseif (is_string($data)) {
			//escape non-printable or non-ASCII characters
			//also put the \\ character first, as suggested in comments on the 'addclashes' page
			$string = '"'.addcslashes($data, "\\\"\n\r\t/".chr(8).chr(12)).'"';
			$json = "";
			$len = strlen($string);
			
			//convert UTF-8 to hexadecimal codepoints.
			for ($i = 0; $i < $len; $i++) {
				$char = $string[$i];
				$c1 = ord($char);
				
				//single byte
				if ($c1 < 128) {
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
					continue;
				}
				
				//double byte
				$c2 = ord($string[++$i]);
				if (($c1 & 32) === 0) {
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
					continue;
				}
				
				//triple
				$c3 = ord($string[++$i]);
				if(($c1 & 16) === 0) {
					$json .= sprintf("\\u%04x", (($c1 - 224) << 12) + (($c2 - 128) << 6) + ($c3 - 128));
					continue;
				}
				
				//quadruple
				$c4 = ord($string[++$i]);
				if (($c1 & 8 ) === 0) {
					$u = (($c1 & 15) << 2) + (($c2 >> 4) & 3) - 1;
					
					$w1 = (54 << 10) + ($u << 6) + (($c2 & 15) << 2) + (($c3 >> 4) & 3);
					$w2 = (55 << 10) + (($c3 & 15) << 6) + ($c4-128);
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
				}
			}
		} else {
			//int, floats, bools, null
			$json = strtolower(var_export($data, true));
		}
		
		return $json;
	}
}
