<?php

/*
    Author: Seleznev Denis, hcodes@yandex.ru
    Description: Server-side tracking of visitors using Yandex.Metrica
    Repo: https://github.com/hcodes/server_yametrika/
    License: MIT
*/

class YaMetrika {
    const HOST = 'mc.yandex.ru';
    const PATH = '/watch/';
    const PORT = 443;

    private $counterId;
    private $counterClass;
    private $encoding;

    function __construct($counterId, $counterClass = 0, $encoding = 'utf-8')
    {
        $this->counterId = $counterId;
        $this->counterClass = $counterClass;
        $this->encoding = $encoding;
    }

    // Отправка хита
    public function hit($pageUrl = null, $pageTitle = null, $pageRef = null, $userParams = null, $ut = '')
    {
        $currentUrl = $this->currentPageUrl();
        $referer = $_SERVER['HTTP_REFERER'];

        if (is_null($pageUrl)) {
            $pageUrl = $currentUrl;
        }

        if (is_null($pageRef)) {
            $pageRef = $referer;
        }

        $pageUrl = $this->absoluteUrl($pageUrl, $currentUrl);
        $pageRef = $this->absoluteUrl($pageRef, $currentUrl);

        $modes = ['ut' => $ut];
        $this->hitExt($pageUrl, $pageTitle, $pageRef, $userParams, $modes);
    }

    // Достижение цели
    public function reachGoal($target = '', $userParams = null)
    {
        if ($target) {
            $target = 'goal://' . $_SERVER['HTTP_HOST'] . '/' . $target;
            $referer = $this->currentPageUrl();
        } else {
            $target = $this->currentPageUrl();
            $referer = $_SERVER['HTTP_REFERER'];
        }

        $this->hitExt($target, null, $referer, $userParams, null);
    }

    // Внешняя ссылка
    public function extLink($url = '', $title = '')
    {
        if ($url) {
            $modes = ['ln' => true, 'ut' => 'noindex'];
            $referer = $this->currentPageUrl();
            $this->hitExt($url, $title, $referer, null, $modes);
        }
    }

    // Загрузка файла
    public function file($file = '', $title = '')
    {
        if ($file) {
            $currentUrl = $this->currentPageUrl();
            $modes = ['dl' => true, 'ln' => true];
            $file = $this->absoluteUrl($file, $currentUrl);
            $this->hitExt($file, $title, $currentUrl, null, $modes);
        }
    }

    // Не отказ
    public function notBounce()
    {
        $modes = ['nb' => true];
        $this->hitExt('', '', '', null, $modes);
    }

    // Параметры визитов
    public function params($data)
    {
        if ($data) {
            $modes = ['pa' => true];
            $this->hitExt('', '', '', $data, $modes);
        }
    }

    // Общий метод для отправки хитов
    private function hitExt($pageUrl = '', $pageTitle = '', $pageRef = '', $userParams = null, $modes = [])
    {
        $postData = [];

        if ($this->counterClass) {
            $postData['cnt-class'] = $this->counterClass;
        }

        if ($pageUrl) {
            $postData['page-url'] = urlencode($pageUrl);
        }

        if ($pageRef) {
            $postData['page-ref'] = urlencode($pageRef);
        }

        if ($modes) {
            $modes['ar'] = true;
        } else {
            $modes = ['ar' => true];
        }

        $browser_info = [];
        if ($modes && count($modes)) {
            foreach($modes as $key => $value) {
                if ($value and $key != 'ut') {
                    if ($value === true) {
                        $value = 1;
                    }

                    $browser_info[] = $key . ':' . $value;
                }
            }
        }

        $browser_info[] = 'en:' . $this->encoding;

        if ($pageTitle) {
            $browser_info[] = 't:' . urlencode($pageTitle);
        }

        $postData['browser-info'] = implode(':', $browser_info);


        if ($userParams) {
            $up = json_encode($userParams);
            $postData['site-info'] = urlencode($up);
        }

        if ($modes['ut']) {
            $postData['ut'] = $modes['ut'];
        }

        $getQuery = self::PATH . $this->counterId . '/1?rn=' . rand(0, 1000000) . '&wmode=2';

        $this->postRequest(self::HOST, $getQuery, $this->buildQueryVars($postData));
    }

    // Текущий URL
    private function currentPageUrl()
    {
        $protocol = 'http://';

        if ($_SERVER['HTTPS']) {
            $protocol = 'https://';
        }

        $pageUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        return $pageUrl;
    }

    // Преобразование из относительного в абсолютный url
    private function absoluteUrl($url, $baseUrl) {
        if (!$url) {
            return '';
        }

        $parseUrl = parse_url($url);
        $base = parse_url($baseUrl);
        $hostUrl = $base['scheme'] . '://' . $base['host'];

        if ($parseUrl['scheme']) {
            $absUrl = $url;
        } elseif ($parseUrl['host']) {
            $absUrl = 'http://' . $url;
        } else {
            $absUrl = $hostUrl . $url;
        }

        return $absUrl;
    }

    // Построение переменных в запросе
    private function buildQueryVars($queryVars)
    {
        $queryBits = [];
        while (list($var, $value) = each($queryVars)) {
            $queryBits[] = $var . '=' . $value;
        }

        return (implode('&', $queryBits));
    }

    // Отправка POST-запроса
    private function postRequest($host, $path, $dataToSend)
    {
        $dataLen = strlen($dataToSend);

        $out  = 'POST ' . $path . ' HTTP/1.1\r\n';
        $out .= 'Host: ' . $host . '\r\n';
        $out .= 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'] . '\r\n';
        $out .= 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . '\r\n';
        $out .= 'Content-type: application/x-www-form-urlencoded\r\n';
        $out .= 'Content-length: ' . $dataLen . '\r\n';
        $out .= 'Connection: close\r\n\r\n';
        $out .= $dataToSend;

        $errno = '';
        $errstr = '';
        $result = '';

        try {
            $socket = @fsockopen('ssl://' . $host, self::PORT, $errno, $errstr, 3);
            if ($socket) {
                if (!fwrite($socket, $out)) {
                    throw new Exception('unable to write');
                } else {
                    while ($in = @fgets($socket, 1024)) {
                        $result .= $in;
                    }
                }

                fclose($socket);
            } else {
                throw new Exception('unable to create socket');
            }

        } catch (exception $e) {
            return false;
        }

        return true;
    }
}

?>
