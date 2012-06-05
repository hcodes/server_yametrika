<?php

header("Content-Type: text/plain; charset=UTF-8");

require_once "main.php";

try {
	$counter = new YaMetrika(14950387);
	$counter->reachGoal("GOAL-1");
} catch (YaMetrikaException $e) {
	//do nothing
}
