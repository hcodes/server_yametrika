/////////////////////////////////////////////
//// Примеры использования YaMetrika
////////////////////////////////////////////

var YaMetrika = require('yametrika');
    
var counter = new YaMetrika({id: 123456, site: 'http://example.ru'}); // номер счётчика Метрики

// Отправка хита
counter.hit('http://example.ru', 'Main page', 'http://ya.ru');
counter.hit('/index.html', 'Main page', '/back.html'); // Для работы с относительными урлами необходимо указать site при создании объекта YaMetrika

// Отправка хита вместе с пользовательскими параметрами
counter.hit('http://example.ru', 'Main page', 'http://ya.ru', myParams);

// Отправка хита вместе с параметрами визитов и с запретом на индексацию
counter.hit('http://example.ru', 'Main page', 'http://ya.ru', myParams, 'noindex');

// Достижение цели
counter.reachGoal('back');

// Внешняя ссылка - отчёт "Внешние ссылки"
counter.extLink('http://yandex.ru');

// Загрузка файла - отчёт "Загрузка файлов"
counter.file('http://example.ru/file.zip');
counter.file('/file.zip'); // Для работы с относительными урлами необходимо указать site при создании объекта YaMetrika

// Отправка пользовательских параметров - отчёт "Параметры визитов"
counter.params({level1: {level2: 1});
// или
counter.params('level1', 'level2', 1);

// Не отказ
counter.notBounce();