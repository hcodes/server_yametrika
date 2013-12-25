# Серверное отслеживание посетителей с помощью Яндекс.Метрики для PHP и Node.js

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
+ Внешняя ссылка - extLink()
+ Загрузка файла - file()
+ Параметры визита - params()
+ Неотказ - notBounce()

## Настройки счётчика Метрики
**В настройках счётчика во вкладке "Фильтры" / "Фильтрация роботов" необходимо выбрать опцию "Учитывать посещения всех роботов". В противном случае, статистика собираться не будет.**

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

    <?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    $counter->hit('http://example.ru/archive.zip');
    //...
    ?>


Загрузка файла:

    <?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    $counter->file('http://example.ru/archive.zip');
    //...
    ?>

Какие программы используют посетители для чтения RSS:

    <?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    // Просмотр статистики в отчёте "Параметры визитов", ветка RSS -> User Agent
    $counter->params(Array('RSS' => Array('User Agent' => $_SERVER['HTTP_USER_AGENT'])));
    //...
    ?>

Слежка за роботами за скачкой robots.txt:
Добавляем в корневой .htaccess строку "RewriteRule ^robots.txt$ robots.php" и создаём в корне файл robots.php с содержанием:

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
