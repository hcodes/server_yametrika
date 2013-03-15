// TODO

var YaMetrika = function (counterId, counterClass, encoding) {
    this.counterId = counterId;
    this.counterClass = counterClass || 0;
    this.encoding = encoding || 'utf-8';
};

const HOST = 'mc.yandex.ru';
const PATH = '/watch';
const PORT = 80;

YaMetrika.prototype = {
    constructor: YaMetrika,
    // Отправка хита
    hit:  function (pageUrl, pageTitle, pageRef, userParams, ut) {
        // TODO
    },
    // Достижение цели
    reachGoal: function (target, userParams) {
        // TODO
    },
    // Внешняя ссылка
    extLink: function (url, title) {
        if (url) {
            var referer = this.currentPageUrl();
            this.hitExt(url, title, referer, null, {
                ln: true,
                ut: 'noindex'
            });
        }
    },
    // Загрузка файла
    file: function (file, title) {
        if (file) {
            var currentUrl = this.currentPageUrl();
            var file = this.absoluteUrl(file, currentUrl);
            this.hitExt(file, title, currentUrl, null, {
                dl: true,
                ln: true
            });
        }
    },
    // Не отказ
    notBounce: function () {
        this.hitExt('', '', '', null, {nb: true});
    },
    // Параметры визитов
    params: function (data) {
        if (data) {
            this.hitExt('', '', '', data, {pa: true});
        }
    },
    // Общий метод для отправки хитов
    hitExt: function (pageUrl, pageTitle, pageRef, userParams, modes) {
        // TODO
    },
    // Текущий URL
    currentPageUrl: function () {
        // TODO
    },
    // Преобразование из относительного в абсолютный url
    absoluteUrl: function (url, baseUrl) {
        // TODO
    },
    // Построение переменных в запросе
    buildQueryVars: function (queryVars) {
        // TODO
    },
    postRequest: function (host, path, dataToSend) {
        // TODO
    }
};