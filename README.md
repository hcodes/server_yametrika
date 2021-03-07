
# server_yametrika
![Packagist License](https://img.shields.io/packagist/l/hcodes/server_yametrika)

**Серверное отслеживание посетителей с помощью Яндекс.Метрики для PHP.**

В некоторых случаях требуется отслеживать действия на стороне сервера без JavaScript.

Например:
+ Слежка за поисковыми роботами
+ Редиректы
+ Загрузка файлов
+ Страницы с ошибками (403, 404, 500)
+ RSS
+ Время выполнения скриптов
+ Время запросов к базам данных
+ Треккинг AJAX-запросов
+ и пр.

## Возможности
Серверная реализация сделана по аналогии с [JavaScript-реализацией](http://help.yandex.ru/metrika/?id=1113052).
+ Загрузка страницы - hit()
+ Достижение цели - reachGoal()
+ Внешняя ссылка - extLink()
+ Загрузка файла - file()
+ Параметры визита - params()
+ Неотказ - notBounce()
 
**Яндекс.Метрика принимает хиты только по https-протоколу, не забудьте проверить [поддержку SSL](https://github.com/hcodes/server_yametrika/blob/master/yametrika.php#L283) в PHP.**

## Настройки счётчика Метрики
**В настройках счётчика во вкладке «Фильтры» / «Фильтрация роботов» необходимо выбрать опцию «Учитывать посещения всех роботов». В противном случае, статистика собираться не будет.**

## Ограничения
Отчёты, которые будут недоступны в Метрике при серверной отправки:
+ Половозрастная структура
+ Пол и возраст
+ Разрешения дисплеев
+ Версия Flash и Silverlight
+ Вебвизор, аналитика форм
+ Карта кликов

Уникальные посетители считаются по User Agent и IP-адресу.


## Как использовать
Посещение страницы:
  ```PHP
<?php
//...
include('yametrika.php');

$counter = new YaMetrika(123456); // Номер счётчика Метрики
$counter->hit('http://example.ru/archive.zip');
//...
?>
  ```

Достижение цели:
  ```PHP
<?php
//...
include('yametrika.php');

$counter = new YaMetrika(123456); // Номер счётчика Метрики
$counter->hit() // Вызов метода необходим для корректной привязки цели к визиту
$counter->reachGoal('submit');
//...
?>
  ```

Загрузка файла:
  ```PHP
<?php
//...
include('yametrika.php');

$counter = new YaMetrika(123456); // Номер счётчика Метрики
$counter->file('http://example.ru/archive.zip');
//...
?>
  ```

Какие программы используют посетители для чтения RSS:
  ```PHP
<?php
//...
include('yametrika.php');

$counter = new YaMetrika(123456); // Номер счётчика Метрики
// Просмотр статистики в отчёте "Параметры визитов", ветка RSS -> User Agent
$counter->params(Array('RSS' => Array('User Agent' => $_SERVER['HTTP_USER_AGENT'])));
//...
?>
  ```

Слежка за роботами за скачкой robots.txt:
Добавляем в корневой .htaccess строку "RewriteRule ^robots.txt$ robots.php" и создаём в корне файл robots.php с содержанием:
  ```PHP
<?php
require('yametrika.php');

$counter = new YaMetrika(123456); // Номер счётчика Метрики
// Просмотр статистики в отчёте "Параметры визитов", ветка Robots.txt -> User Agent
$counter->params(Array('Robots.txt' => Array('User Agent' => $_SERVER['HTTP_USER_AGENT'])));

$txt = file_get_contents('robots.txt');

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header('Content-Type: text/plain');
print $txt;
?>
  ```

## Полезные ссылки
+ [Лёгкая Я.Метрика](https://github.com/hcodes/lyam/)
+ [Версия для Node.js](https://github.com/hcodes/server_yametrika_nodejs/)
+ [Помощь Яндекс.Метрики](https://yandex.ru/support/metrica/)


## Лицензия
MIT License
