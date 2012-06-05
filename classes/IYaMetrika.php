<?php

interface IYaMetrika {
	
	//http://help.yandex.ru/metrika/?id=1113052

	public function extLink($url);
	
	public function file($url);
	
	public function hit($url = null, $title = null, $referer = null, $params = null);
	
	public function notBounce();
	
	public function reachGoal($target, $params = null);

	public function params(array $params);
}