<?php

/*
	http://webfilin.ru/notes/server_yametrika/
	
	Author: Seleznev Denis, hcodes@yandex.ru
	Description: Серверная отправка хитов с помощью PHP в Яндекс.Метрику
	Version: 1.0
	License: MIT, GNU PL


	Примеры использования:
	======================
	
	$counter = new YaMetrika(123456); // номер счётчика Метрики
	$counter->hit(); // Значение URL и referer берутся по умолчанию из $_SERVER
	
	// Отправка хита
	$counter->hit("http://example.ru", "Main page", "http://ya.ru");

	// Отправка хита вместе с пользовательскими параметрами
	$counter->hit("http://example.ru", "Main page", "http://ya.ru", $myParams);

	// Отправка хита вместе с параметрами визитов и с запретом на индексацию
	$counter->hit("http://example.ru", "Main page", "http://ya.ru", $myParams, "noindex");

	// Достижение цели
	$counter->reachGoal("back");
	
	// Внешняя ссылка - отчёт "Внешние ссылки"
	$counter->extLink("http://yandex.ru");
	
	// Загрузка файла - отчёт "Загрузка файлов"
	$counter->file("http://example.ru/file.zip");

	// Отправка пользовательских параметров - отчёт "Параметры визитов"
	$counter->params(array("level1" => array("level2" => 1)));

	// Не отказ
	$counter->notBounce();
*/

class YaMetrika implements IYaMetrika {

	const YM_TRACKER_HOST = "mc.yandex.ru";
	const YM_TRACKER_PORT = 80;
	const YM_TRACKER_PATH = "/watch/";

	private $counterId;
	private $counterClass;

	public function __construct($counterId, $counterClass = 0) {
		$this->counterId = $counterId;
		$this->counterClass = $counterClass;
	}

	//hit
	public function hit($url = null, $title = null, $referer = null, $params = null, $ut = "") {
		if (is_null($url)) {
			$url = $this->getCurrentUrl();
		}
		
		if (is_null($referer)) {
			$referer = YaMetrikaHelper::getEnv("HTTP_REFERER");
		}

		$modes = array("ut" => $ut, "ar" => true);
		
		return $this->hitExt($url, $title, $referer, $params, $modes);
	}
	
	//reach goal
	public function reachGoal($target, $params = null) {
		$target = "goal://".YaMetrikaHelper::getEnv("HTTP_HOST")."/".$target;
		$referer = $this->getCurrentUrl();
		
		$modes = array("ar" => true);

		return $this->hitExt($target, null, $referer, $params, $modes);
	}
	
	//external link
	public function extLink($url, $title = null) {
		$modes = array("ln" => true, "ut" => "noindex");
		$referer = $this->getCurrentUrl();

		return $this->hitExt($url, $title, $referer, null, $modes);
	}
	
	//file loading
	public function file($file, $title = null) {
		$modes = array("dl" => true, "ln" => true);
		$referer = $this->getCurrentUrl();
		
		return $this->hitExt($file, $title, $referer, null, $modes);
	}
	
	//not bounce
	public function notBounce() {
		$modes = array("nb" => true);
		
		return $this->hitExt(null, null, null, null, $modes);
	}
	
	//params
	public function params(array $data) {
		$modes = array("pa" => true);
		
		return $this->hitExt(null, null, null, $data, $modes);
	}

	//common method for tracking
	private function hitExt($url = null, $title = null, $referer = null, $params = null, $modes = array()) {
		$data = array();
		
		$browser = array();
		
		if ($this->counterClass) {
			$data["cnt-class"] = $this->counterClass;
		}
		
		if (!is_null($url)) {
			$url = $this->normalizeUrl($url);
			
			$data["page-url"] = urlencode($url);
		}
		
		if (!is_null($referer)) {
			$data["page-ref"] = urlencode($referer);
		}
		
		if (!empty($modes)) {
			foreach ($modes as $key => $value) {
				if (!empty($value) && $key != "ut") {
					if ($value === true) {
						$value = 1;
					}
					
					array_push($browser, $key.":".$value);
				}
			}
		}

		//title should be placed at the end of browser's params
		if (!is_null($title)) {
			array_push($browser, "t:".urlencode($title));
		}
		
		$data["browser-info"] = join(":", $browser);

		if (!is_null($params)) {
			$data["site-info"] = urlencode(json_encode($params));
		}

		if (isset($modes["ut"])) {
			$data["ut"] = $modes["ut"];
		}

		$query = self::YM_TRACKER_PATH.$this->counterId."/1/?rn=".rand(0, 100000)."&wmode=1";
		
		return $this->postRequest(self::YM_TRACKER_HOST, $query, self::YM_TRACKER_PORT, $this->buildQueryVars($data));
	}

	//normalize url
	private function normalizeUrl($url) {
		//test url starting with protocol
		if (strripos($url, "http://") === 0 || strripos($url, "https://") === 0) {
			return $url;
		}

		return IRI::absolutize(new IRI($this->getCurrentUrl()), $url);
	}
	
	//get current url
	private function getCurrentUrl() {
		return $this->getCurrentHostPath().YaMetrikaHelper::getEnv("REQUEST_URI");
	}

	//get current hostpath
	private function getCurrentHostPath() {
		$protocol = YaMetrikaHelper::getEnv("SERVER_PROTOCOL");

		//clean protocol
		if (($pos = strpos($protocol, "/")) !== false) {
			$protocol = strtolower(substr($protocol, 0, $pos));
		}
		
		return $protocol."://".YaMetrikaHelper::getEnv("HTTP_HOST");
	}

	//build GET params for query
	private function buildQueryVars($queryVars) { 
		$queryBits = array();
		
		while (list($var, $value) = each($queryVars)) { 
			array_push($queryBits, $var."=".$value); 
		}
		
		return join("&", $queryBits); 
	} 

	//sending query
	private function postRequest($host, $path, $port, $data) { 
		$headers = array();
		
		$dataLength = YaMetrikaHelper::getStringLength($data);
		
		array_push($headers, "POST $path HTTP/1.1");
		array_push($headers, "Host: $host");
		array_push($headers, "X-Real-IP: ".YaMetrikaHelper::getEnv("REMOTE_ADDR"));
		array_push($headers, "User-Agent: ".YaMetrikaHelper::getEnv("HTTP_USER_AGENT"));
		array_push($headers, "Content-Type: application/x-www-form-urlencoded");
		array_push($headers, "Content-Length: $dataLength");
		array_push($headers, "Connection: close");
		
		$out = join("\r\n", $headers)."\r\n\r\n".$data;

		$socket = fsockopen(self::YM_TRACKER_HOST, self::YM_TRACKER_PORT);
		if ($socket) {
			if (!fwrite($socket, $out)) {
				throw new YaMetrikaException("Unable write to socket");
			} else {
				$result = "";
				
				while ($in = fgets($socket, 1024)) {
					$result .= $in;
				}
				
				return $result;
			}
		} else {
			throw new YaMetrikaException("Can't open socket");
		}
		
		return false;
	}
}
