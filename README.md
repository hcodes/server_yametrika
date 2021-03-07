
# server_yametrika
![Packagist Version](https://img.shields.io/packagist/v/hcodes/server_yametrika)
![Packagist Downloads](https://img.shields.io/packagist/dm/hcodes/server_yametrika)
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
Серверная реализация сделана по аналогии с [JavaScript-реализацией](https://yandex.ru/support/metrica/code/counter-initialize.html).
+ Загрузка страницы `hit()`
+ Достижение цели `reachGoal()`
+ Внешняя ссылка `extLink()`
+ Загрузка файла `file()`
+ Параметры визита `params()`
+ Неотказ `notBounce()`
 
**Яндекс.Метрика принимает хиты только по https-протоколу, не забудьте проверить поддержку SSL в PHP.**

## Настройки счётчика Метрики
**В настройках счётчика во вкладке «Фильтры» / «Фильтрация роботов» необходимо выбрать опцию «Учитывать посещения всех роботов». В противном случае, статистика собираться не будет.**

## Ограничения
Отчёты, которые будут недоступны в Метрике при серверной отправки:
+ Половозрастная структура
+ Пол и возраст
+ Разрешения дисплеев
+ Вебвизор, аналитика форм
+ Карта кликов

Уникальные посетители считаются по User Agent и IP-адресу.

## API
### Посещение страницы
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики

// Все параметры по умолчанию.
$counter->hit(); // Значение URL и referer берутся по умолчанию из $_SERVER

// Отправка хита с абсолютными урлами.
$counter->hit('https://mysite.org', 'Main page', 'https://ya.ru'); // page_url, title, referer

// Отправка хита с относительными урлами.
$counter->hit('/index.html', 'Main page', '/back.html');

// Отправка хита вместе с параметрами визитов.
$userParams = ['param' => 1, 'param2' => 2];
$counter->hit('https://mysite.org', 'Main page', 'https://ya.ru', $userParams);

// Отправка хита вместе с параметрами визитов и с запретом на индексацию.
$userParams = ['param' => 1, 'param2' => 2];
$counter->hit('https://mysite.org', 'Main page', 'https://ya.ru', $userParams, 'noindex');
?>
```

### Достижение цели
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.
// Внимание! Перед вызовом методов reachGoal должен вызван метод hit(...),
// чтобы была корректная привязка цели к визиту.
$counter->reachGoal('goal_name');

// С параметрами визита.
$counter->reachGoal('goal_name', ['Param1' => 1, 'Param2' => 2]);
?>
```

### Внешняя ссылка, отчёт «Внешние ссылки»
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.
$counter->extLink('https://yandex.ru');

// С названием ссылки.
$counter->extLink('https://yandex.ru', 'Яндекс');
?>
```

### Загрузка файла, отчёт «Загрузка файлов»
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.

$counter->file('https://mysite.org/archive.zip');

// С названием ссылки.
$counter->file('https://mysite.org/archive.zip', 'Архив рассылки');
?>
```

### Отправка пользовательских параметров, отчёт «Параметры визитов»
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.

$counter->params(['level1' => ['level2' => 1]]);
?>
```

### Неотказ
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.

$counter->notBounce();
?>
```

## Примеры применения
### Какие программы используют посетители для чтения RSS?
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.
// Просмотр статистики в отчёте «Параметры визитов», ветка RSS → User Agent.
$counter->params(['RSS' => ['User Agent' => $_SERVER['HTTP_USER_AGENT']]]);
?>
```

### Слежка за роботами за скачкой robots.txt
Добавляем в корневой .htaccess строку "RewriteRule ^robots.txt$ robots.php" и создаём в корне файл robots.php с содержанием:
```PHP
<?php
use ServerYaMetrika\YaMetrika;

$counter = new YaMetrika(123456); // Номер счётчика Метрики.
// Просмотр статистики в отчёте «Параметры визитов», ветка Robots.txt → User Agent.
$counter->params(['Robots.txt' => ['User Agent' => $_SERVER['HTTP_USER_AGENT']]]);

$txt = file_get_contents('robots.txt');

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
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
