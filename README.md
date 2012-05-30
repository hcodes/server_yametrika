Серверное отслеживание посетителей с помощью Яндекс.Метрики
===========================================================
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

Возможности
============
Серверная реализация сделана по аналогии с <a target="_blank" href="http://help.yandex.ru/metrika/?id=1113052">JavaScript-реализацией</a>.
<ul>
    <li>Загрузка страницы - <a target="_blank" href="">hit()</a></li>
    <li>Внешняя ссылка - <a target="_blank" href="">extLink()</a></li>
    <li>Загрузка файла - <a target="_blank" href="">file()</a></li>
    <li>Параметры визита - <a target="_blank" href="">params()</a></li>
    <li>Неотказ - <a target="_blank" href="">notBounce()</a></li>
</ul>

<h1>Ограничения</h1>
<p class="ul">Отчёты, которые будут недоступны в Метрике при серверной отправки:</p>
<ul class="p">
    <li>Половозрастная структура</li>
    <li>Пол и возраст</li>
    <li>Разрешения дисплеев</li>
    <li>Версия Flash и Silverlight</li>
    <li>Вебвизор, аналитика форм</li>
    <li>Карта кликов</li>
</ul>

<p>Уникальные посетители считаются по User Agent и IP-адресу.</p>

<h1>Как использовать</h1>

<p>Посещение страницы:</p>
<pre>
<code class="php">
&lt;?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    $counter->hit('http://example.ru/archive.zip');
    //...
?&gt;
</code>
</pre>
<br />
<p>Загрузка файла:</p>
<pre>
<code class="php">
&lt;?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    $counter->file('http://example.ru/archive.zip');
    //...
?&gt;
</code>
</pre>
<br />
<p>Какие программы используют посетители для чтения RSS:</p>
<pre>
<code class="php">
&lt;?php
    //...
    include('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    // Просмотр статистики в отчёте "Параметры визитов", ветка RSS -&gt; User Agent
    $counter->params(Array('RSS' => Array('User Agent' => $_SERVER['HTTP_USER_AGENT'])));
    //...
?&gt;
</code>
</pre>

<br />
<p>Слежка за роботами за скачкой robots.txt:</p>
<p>Добавляем в корневой .htaccess строку "RewriteRule ^robots.txt$ robots.php" и создаём в корне файл robots.php с содержанием:</p>
<pre>
<code class="php">
&lt;?php
    require('yametrika.php');

    $counter = new YaMetrika(123456); // Номер счётчика Метрики
    // Просмотр статистики в отчёте "Параметры визитов", ветка Robots.txt -&gt; User Agent
    $counter->params(Array('Robots.txt' => Array('User Agent' => $_SERVER['HTTP_USER_AGENT'])));

    $txt = file_get_contents('robots.txt');

    header('Cache-Control: no-cache');
    header('Pragma: no-cache');
    header('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
    header('Content-Type: text/plain');
    print $txt;
?&gt;
</code>
</pre>
<p>.</p>
