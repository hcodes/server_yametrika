<?php

/*
    Author: Seleznev Denis, hcodes@yandex.ru
    Description: Серверная отправка хитов с помощью PHP в Яндекс.Метрику
    Version: 1.0.2
    License: MIT, GNU PL

    Примеры использования:
    ======================

    $counter = new YaMetrika(123456); // номер счётчика Метрики
    $counter->hit(); // Значение URL и referer берутся по умолчанию из $_SERVER

    // Отправка хита
    $counter->hit('http://example.ru', 'Main page', 'http://ya.ru');
    $counter->hit('/index.html', 'Main page', '/back.html');

    // Отправка хита вместе с пользовательскими параметрами
    $counter->hit('http://example.ru', 'Main page', 'http://ya.ru', $myParams);

    // Отправка хита вместе с параметрами визитов и с запретом на индексацию
    $counter->hit('http://example.ru', 'Main page', 'http://ya.ru', $myParams, 'noindex');

    // Достижение цели
    $counter->reachGoal('back');

    // Внешняя ссылка - отчёт "Внешние ссылки"
    $counter->extLink('http://yandex.ru');

    // Загрузка файла - отчёт "Загрузка файлов"
    $counter->file('http://example.ru/file.zip');
    $counter->file('/file.zip');

    // Отправка пользовательских параметров - отчёт "Параметры визитов"
    $counter->params(array('level1' => array('level2' => 1)));

    // Не отказ
    $counter->notBounce();
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
    public function hit($pageUrl = null, $pageTitle = null, $pageRef = null, $userParams = '', $ut = '')
    {
        $currentUrl = $this->currentPageUrl();
        $referer = $_SERVER['HTTP_REFERER'];

        if (is_null($pageUrl))
        {
            $pageUrl = $currentUrl;
        }

        if (is_null($pageRef))
        {
            $pageRef = $referer;
        }

        $pageUrl = $this->absoluteUrl($pageUrl, $currentUrl);
        $pageRef = $this->absoluteUrl($pageRef, $currentUrl);

        $modes = array('ut' => $ut);
        $this->hitExt($pageUrl, $pageTitle, $pageRef, $userParams, $modes);
    }

    // Достижение цели
    public function reachGoal($target = '', $userParams = null)
    {
        if ($target)
        {
            $target = 'goal://'.$_SERVER['HTTP_HOST'].'/'.$target;
            $referer = $this->currentPageUrl();
        }
        else
        {
            $target = $this->currentPageUrl();
            $referer = $_SERVER['HTTP_REFERER'];
        }

        $this->hitExt($target, null, $referer, $userParams, null);
    }

    // Внешняя ссылка
    public function extLink($url = '', $title = '')
    {
        if ($url)
        {
            $modes = array('ln' => true, 'ut' => 'noindex');
            $referer = $this->currentPageUrl();
            $this->hitExt($url, $title, $referer, null, $modes);
        }
    }

    // Загрузка файла
    public function file($file = '', $title = '')
    {
        if ($file)
        {
            $currentUrl = $this->currentPageUrl();
            $modes = array('dl' => true, 'ln' => true);
            $file = $this->absoluteUrl($file, $currentUrl);
            $this->hitExt($file, $title, $currentUrl, null, $modes);
        }
    }

    // Не отказ
    public function notBounce()
    {
        $modes = array('nb' => true);
        $this->hitExt('', '', '', null, $modes);
    }

    // Параметры визитов
    public function params($data)
    {
        if ($data)
        {
            $modes = array('pa' => true);
            $this->hitExt('', '', '', $data, $modes);
        }
    }

    // Общий метод для отправки хитов
    private function hitExt($pageUrl = '', $pageTitle = '', $pageRef = '', $userParams = null, $modes = array())
    {
        $postData = array();

        if ($this->counterClass)
        {
            $postData['cnt-class'] = $this->counterClass;
        }

        if ($pageUrl)
        {
            $postData['page-url'] = urlencode($pageUrl);
        }

        if ($pageRef)
        {
            $postData['page-ref'] = urlencode($pageRef);
        }

        if ($modes)
        {
            $modes['ar'] = true;
        }
        else
        {
            $modes = array('ar' => true);
        }

        $browser_info = array();
        if ($modes and count($modes))
        {
            foreach($modes as $key => $value)
            {
                if ($value and $key != 'ut')
                {
                    if ($value === true)
                    {
                        $value = 1;
                    }

                    $browser_info[] = $key.':'.$value;
                }
            }
        }

        $browser_info[] = 'en:'.$this->encoding;

        if ($pageTitle)
        {
            $browser_info[] = 't:'.urlencode($pageTitle);
        }

        $postData['browser-info'] = implode(':', $browser_info);


        if ($userParams)
        {
            $up = json_encode($userParams);
            $postData['site-info'] = urlencode($up);
        }

        if ($modes['ut'])
        {
            $postData['ut'] = $modes['ut'];
        }

        $getQuery = self::PATH.$this->counterId.'/1?rn='.rand(0, 100000).'&wmode=2';

        $this->postRequest(self::HOST, $getQuery, $this->buildQueryVars($postData));
    }

    // Текущий URL
    private function currentPageUrl()
    {
        $protocol = 'http://';

        if ($_SERVER['HTTPS'])
        {
            $protocol = 'https://';
        }

        $pageUrl = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

        return $pageUrl;
    }

    // Преобразование из относительного в абсолютный url
    private function absoluteUrl($url, $baseUrl) {
        if (!$url) {
            return '';
        }

        $parseUrl = parse_url($url);
        $base = parse_url($baseUrl);
        $hostUrl = $base['scheme'].'://'.$base['host'];

        if ($parseUrl['scheme'])
        {
            $absUrl = $url;
        }
        elseif ($parseUrl['host'])
        {
            $absUrl = 'http://'.$url;
        }
        else
        {
            $absUrl = $hostUrl . $url;
        }

        return $absUrl;
    }

    // Построение переменных в запросе
    private function buildQueryVars($queryVars)
    {
        $queryBits = array();
        while (list($var, $value) = each($queryVars))
        {
            $queryBits[] = $var.'='.$value;
        }

        return (implode('&', $queryBits));
    }

    // Отправка POST-запроса
    private function postRequest($host, $path, $dataToSend)
    {
        $dataLen = strlen($dataToSend);

        $out  = "POST $path HTTP/1.1\r\n";
        $out .= "Host: $host\r\n";
        $out .= "X-Real-IP: ".$_SERVER['REMOTE_ADDR']."\r\n";
        $out .= "User-Agent: ".$_SERVER['HTTP_USER_AGENT']."\r\n";
        $out .= "Content-type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-length: $dataLen\r\n";
        $out .= "Connection: close\r\n\r\n";
        $out .= $dataToSend;

        $errno = '';
        $errstr = '';
        $result = '';

        try
        {
            $socket = @fsockopen('ssl://'.$host, self::PORT, $errno, $errstr, 3);             
            if ($socket)
            {
                if (!fwrite($socket, $out))
                {
                    throw new Exception("unable to write");
                }
                else
                {
                    while ($in = @fgets($socket, 1024))
                    {
                        $result .= $in;
                    }
                }

                fclose($socket);
            }
            else
            {
                throw new Exception("unable to create socket");
            }

        }
        catch (exception $e)
        {
            return false;
        }

        return true;
    }
}

// http://docs.php.net/manual/en/function.json-encode.php
if (!function_exists('json_encode'))
{
    function json_encode( $data ) {
        if( is_array($data) || is_object($data) ) {
            $islist = is_array($data) && ( empty($data) || array_keys($data) === range(0,count($data)-1) );

            if( $islist ) {
                $json = '[' . implode(',', array_map('json_encode', $data) ) . ']';
            } else {
                $items = array();
                foreach( $data as $key => $value ) {
                    $items[] = json_encode("$key") . ':' . json_encode($value);
                }
                $json = '{' . implode(',', $items) . '}';
            }
        } elseif( is_string($data) ) {
            # Escape non-printable or Non-ASCII characters.
            # I also put the \\ character first, as suggested in comments on the 'addclashes' page.
            $string = '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
            $json    = '';
            $len    = strlen($string);
            # Convert UTF-8 to Hexadecimal Codepoints.
            for( $i = 0; $i < $len; $i++ ) {

                $char = $string[$i];
                $c1 = ord($char);

                # Single byte;
                if( $c1 < 128 ) {
                    $json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
                    continue;
                }

                # Double byte
                $c2 = ord($string[++$i]);
                if ( ($c1 & 32) === 0 ) {
                    $json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
                    continue;
                }

                # Triple
                $c3 = ord($string[++$i]);
                if( ($c1 & 16) === 0 ) {
                    $json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128));
                    continue;
                }

                # Quadruple
                $c4 = ord($string[++$i]);
                if( ($c1 & 8 ) === 0 ) {
                    $u = (($c1 & 15) << 2) + (($c2 >> 4) & 3) - 1;

                    $w1 = (54 << 10) + ($u << 6) + (($c2 & 15) << 2) + (($c3 >> 4) & 3);
                    $w2 = (55 << 10) + (($c3 & 15) << 6) + ($c4 - 128);
                    $json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
                }
            }
        } else {
            # int, floats, bools, null
            $json = strtolower(var_export( $data, true ));
        }
        return $json;
    }
}

?>
