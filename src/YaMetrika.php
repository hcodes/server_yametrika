<?php

/*
    Author: Seleznev Denis, hcodes@yandex.ru
    Description: Server-side tracking of visitors using Yandex.Metrica
    Repo: https://github.com/hcodes/server_yametrika/
    License: MIT
*/

namespace ServerYaMetrika;

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
        $referer = $this->getServerParam('HTTP_REFERER');

        if (is_null($pageUrl)) {
            $pageUrl = $currentUrl;
        }

        if (is_null($pageRef)) {
            $pageRef = $referer;
        }

        $pageUrl = $this->absoluteUrl($pageUrl, $currentUrl);
        $pageRef = $this->absoluteUrl($pageRef, $currentUrl);

        $modes = ['ut' => $ut];

        return $this->hitExt($pageUrl, $pageTitle, $pageRef, $userParams, $modes);
    }

    // Достижение цели
    public function reachGoal($target = '', $userParams = null)
    {
        if ($target) {
            $target = 'goal://' . $this->getServerParam('HTTP_HOST') . '/' . $target;
            $referer = $this->currentPageUrl();
        } else {
            $target = $this->currentPageUrl();
            $referer = $this->getServerParam('HTTP_REFERER');
        }

        return $this->hitExt($target, null, $referer, $userParams, null);
    }

    // Внешняя ссылка
    public function extLink($url = '', $title = '')
    {
        if ($url) {
            $modes = ['ln' => true, 'ut' => 'noindex'];
            $referer = $this->currentPageUrl();

            return $this->hitExt($url, $title, $referer, null, $modes);
        }

        return false;
    }

    // Загрузка файла
    public function file($file = '', $title = '')
    {
        if ($file) {
            $currentUrl = $this->currentPageUrl();
            $modes = ['dl' => true, 'ln' => true];
            $file = $this->absoluteUrl($file, $currentUrl);

            return $this->hitExt($file, $title, $currentUrl, null, $modes);
        }

        return false;
    }

    // Не отказ
    public function notBounce()
    {
        $modes = ['nb' => true];

        return  $this->hitExt('', '', '', null, $modes);
    }

    // Параметры визитов
    public function params($data)
    {
        if ($data) {
            $modes = ['pa' => true];

            return $this->hitExt('', '', '', $data, $modes);
        }

        return false;
    }

    private getServerParam($name)
    {
        return isset($_SERVER[$name]) ? $_SERVER[$name] : '';
    }

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

        return $this->postRequest(self::HOST, $getQuery, $this->buildQueryVars($postData));
    }

    private function currentPageUrl()
    {
        $protocol = 'http://';

        if ($this->getServerParam('HTTPS')) {
            $protocol = 'https://';
        }

        $pageUrl = $protocol . $this->getServerParam('HTTP_HOST') . $this->getServerParam('REQUEST_URI');

        return $pageUrl;
    }

    private function absoluteUrl($url, $baseUrl) {
        if (!$url) {
            return '';
        }

        $parseUrl = parse_url($url);
        $base = parse_url($baseUrl);

        if (!$parseUrl || !$base) {
            return '';
        }

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

    private function buildQueryVars($queryVars)
    {
        $queryBits = [];

        foreach($queryVars as $key => $value) {
            $queryBits[] = $key . '=' . $value;
        }

        return (implode('&', $queryBits));
    }

    private function postRequest($host, $path, $dataToSend)
    {
        $dataLen = strlen($dataToSend);

        $out = 'POST ' . $path . ' HTTP/1.1\r\n';
        $out .= 'Host: ' . $host . '\r\n';

        $ip = $this->getServerParam('REMOTE_ADDR'); 
        if ($ip) {
            $out .= 'X-Forwarded-For: ' . $ip . '\r\n';
        }

        $ua = $this->getServerParam('HTTP_USER_AGENT'); 
        if ($ua) {
            $out .= 'User-Agent: ' . $ua . '\r\n';
        }
        
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
                if (fwrite($socket, $out)) {
                    while ($in = @fgets($socket, 1024)) {
                        $result .= $in;
                    }
                } else {
                    throw new Exception('unable to write');
                }

                fclose($socket);
            } else {
                throw new Exception('unable to create socket');
            }

        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}

?>
