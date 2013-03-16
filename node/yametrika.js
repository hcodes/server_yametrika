/*
    http://webfilin.ru/notes/server_yametrika/
    
    Author: Seleznev Denis, hcodes@yandex.ru
    Description: Серверная отправка хитов с помощью Node.js в Яндекс.Метрику
    Version: 0.1
    License: MIT
*/

var querystring = require('querystring');
var http = require('http');

var YaMetrika = function (data) {
    this._id = data.id;
    this._type = data.type || 0;
    this._encoding = data.encoding || 'utf-8';
    this._host = data.host;
};

const HOST = 'mc.yandex.ru';
const PATH = '/watch';
const PORT = 80;

YaMetrika.prototype = {
    constructor: YaMetrika,
    // Отправка хита
    hit:  function (pageUrl, pageTitle, pageRef, userParams, ut) {
        if (!pageUrl) {
            pageUrl = this.currentPageUrl();
        }
        
        if (pageRef !== null) {
            pageRef = this.getReferer();
        }
        
        pageUrl = this.absoluteUrl(pageUrl, currentUrl);
        pageRef = this.absoluteUrl(pageRef, currentUrl);

        this.hitExt(pageUrl, pageTitle, pageRef, userParams, {ut: ut});
    },
    // Достижение цели
    reachGoal: function (target, userParams) {
        var referer;
        if (target) {
            target = 'goal://' + this.getHost() + '/' + target;
            referer = this.currentPageUrl();
        } else {
            target = this.currentPageUrl();
            referer = this.getReferer();
        }
        
        this.hitExt(target, null, referer, userParams, null);
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
        var postData = [];

        if (this._type) {
            postData['cnt-class'] = this._type;
        }
        
        if (pageUrl) {
            postData['page-url'] = encodeURI(pageUrl);
        }
        
        if (pageRef) {
            postData['page-ref'] = encodeURI(pageRef);
        }         
        
        if (modes) {
            modes['ar'] = true;
        } else  {
            modes = {ar: true};
        }
        
        browserInfo = [];
        for(var key in modes) {
            if (!modes.hasOwnProperty(key)) {
                continue;
            }
            
            if (key != 'ut') {
                browserInfo.push(key + ':' + (modes[key] === true ? 1 : modes[key]));
            }
        }
        
        browserInfo.push('en:' + this._encoding);

        if (pageTitle) {
            browserInfo.push('t:' + encodeURI(pageTitle));
        }
        
        postData['browser-info'] = browserInfo.join(':');
        
        if (userParams) {
            postData['site-info'] = encodeURI(JSON.stringify(userParams));
        }

        if (modes['ut']) {
            postData['ut'] = modes['ut'];
        }

        var getQuery = PATH + this._id + '/1?rn=' + (Math.floor(Math.random() * 1E6)) + '&wmode=2';

        this.postRequest(HOST, getQuery, querystring.stringify(postData));
    },
    getHost: function () {
        // TODO
        
        return this._host;
    },
    getReferer: function () {
        // TODO
        
        return '';
    },    
    // Текущий URL
    currentPageUrl: function () {
        // TODO
        
        return '';
    },
    // Преобразование из относительного в абсолютный url
    absoluteUrl: function (url, baseUrl) {
        // TODO
        
        return '';
    },
    postRequest: function (host, path, dataToSend) {
        var req = http.request({
            host: HOST,
            port: PORT,
            method: 'POST',
            path: path,
            headers: {
                'X-Real-IP': $_SERVER['REMOTE_ADDR'], // TODO
                'User-Agent': $_SERVER['HTTP_USER_AGENT'] // TODO
            },
        }, function () {});
        
        req.write(dataToSend, this._encoding);
        req.end();
    }
};

exports.yametrika = YaMetrika;