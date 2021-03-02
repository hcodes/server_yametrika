<?php

require_once('yametrika.php');

$counter = new YaMetrika(123456);
$counter->hit();
$counter->reachGoal('example');
$counter->notBounce();
$counter->extLink('https://example.com');
$counter->file('https://example.com/file.zip');

?>
