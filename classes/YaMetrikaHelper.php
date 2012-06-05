<?php

class YaMetrikaHelper {
	
	public static function getEnv($name) {
		$value = getenv($name);

		if (!$value && isset($_SERVER[$name])) {
			$value = $_SERVER[$name];
		}
		
		return $value;
	}

	public static function getStringLength($s) {
		if (function_exists("mb_strlen")) {
			$enc = mb_detect_encoding($s, "UTF-8, ASCII", true);
			if ($enc) {
				return mb_strlen($s, $enc);
			}
		} else if (function_exists("iconv_strlen")) {
			return iconv_strlen($s, $enc);
		} else {
			return strlen($s);
		}
		
		return 0;
	}

}