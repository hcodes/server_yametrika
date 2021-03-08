<?php

require('../src/YaMetrika.php');

use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(21312094);
$counter->hit();
$counter->hit('https://mysite.org', 'My site', 'https://yandex.ru');
$counter->reachGoal('goal_name');
$counter->notBounce();
$counter->file('https://mysite.org/file.zip', 'File title');
$counter->extLink('https://yandex.ru', 'Yandex');
$counter->params(['Param1' => 1, 'Param2' => 'value']);

?>
