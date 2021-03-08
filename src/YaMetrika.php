<?php

/**
 * Серверное отслеживание посетителей с помощью Яндекс.Метрики.
 *
 * @author   Denis Seleznev <hcodes@yandex.ru>
 * @license  MIT
 * @link     https://github.com/hcodes/server_yametrika/
 */

namespace ServerYaMetrika;

class YaMetrika
{
    public const HOST = 'mc.yandex.ru';
    public const PATH = '/watch/';
    public const PORT = 443;

    private $counterId;
    private $counterClass;
    private $encoding;

    /**
     * Конструктор.
     *
     * @param number $counterId    Идентификатор счётчика
     * @param number $counterClass Тип счётчика
     * @param string $encoding     Кодировка страницы
     */
    public function __construct($counterId, $counterClass = 0, $encoding = 'utf-8')
    {
        $this->counterId = $counterId;
        $this->counterClass = $counterClass;
        $this->encoding = $encoding;
    }

    /**
     * Отправляет посещение страницы.
     *
     * @param string|null $pageUrl     URL страницы
     * @param string|null $pageTitle   Заголовок страницы
     * @param string|null $pageReferer Реферер
     * @param array|null  $userParams  Параметры визита
     * @param string|null $ut          Запрет индексация
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function hit(
        $pageUrl = null,
        $pageTitle = null,
        $pageReferer = null,
        $userParams = null,
        $ut = null
    ) {
        $currentUrl = $this->currentPageUrl();
        $referer = $this->getServerParam('HTTP_REFERER');

        if (is_null($pageUrl)) {
            $pageUrl = $currentUrl;
        }

        if (is_null($pageReferer)) {
            $pageReferer = $referer;
        }

        $pageUrl = $this->absoluteUrl($pageUrl, $currentUrl);
        $pageReferer = $this->absoluteUrl($pageReferer, $currentUrl);

        $modes = [];

        if (!is_null($ut)) {
            $modes['ut'] = $ut;
        }

        return $this->hitExt(
            $pageUrl,
            $pageTitle,
            $pageReferer,
            $userParams,
            $modes
        );
    }

    /**
     * Отправляет достижение цели.
     *
     * @param string|null $target     Название цели
     * @param array|null  $userParams Параметры визита
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function reachGoal($target = null, $userParams = null)
    {
        if (is_null($target)) {
            $target = $this->currentPageUrl();
            $referer = $this->getServerParam('HTTP_REFERER');
        } else {
            $target = 'goal://' . $this->getServerParam('HTTP_HOST') . '/' . $target;
            $referer = $this->currentPageUrl();
        }

        return $this->hitExt($target, null, $referer, $userParams, null);
    }

    /**
     * Отправляет внешнюю ссылку.
     *
     * @param string $url   Внешняя ссылка
     * @param string $title Заголовок ссылки
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function extLink($url, $title = null)
    {
        if ($url) {
            $modes = ['ln' => true, 'ut' => 'noindex'];
            $referer = $this->currentPageUrl();

            return $this->hitExt($url, $title, $referer, null, $modes);
        }

        return false;
    }

    /**
     * Отправляет загрузку файла.
     *
     * @param string $file  Ссылка на файл
     * @param string $title Заголовок для файла
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function file($file, $title = null)
    {
        if ($file) {
            $currentUrl = $this->currentPageUrl();
            $modes = ['dl' => true, 'ln' => true];
            $file = $this->absoluteUrl($file, $currentUrl);

            return $this->hitExt($file, $title, $currentUrl, null, $modes);
        }

        return false;
    }

    /**
     * Отправляет неотказ.
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function notBounce()
    {
        $modes = ['nb' => true];

        return $this->hitExt('', '', '', null, $modes);
    }

    /**
     * Отправляет параметры визитов.
     *
     * @param array $data Параметры визитов
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    public function params($data)
    {
        if ($data) {
            $modes = ['pa' => true];

            return $this->hitExt('', '', '', $data, $modes);
        }

        return false;
    }

    /**
     * Возвращает значение параметра в $_SERVER.
     *
     * @param string $name Имя параметра
     *
     * @return string
     */
    private function getServerParam($name)
    {
        return isset($_SERVER[$name]) ? $_SERVER[$name] : '';
    }

    /**
     * Общий метод отправки данных.
     *
     * @param string|null $pageUrl     Адрес страницы
     * @param string|null $pageTitle   Заголовок страницы
     * @param string|null $pageReferer Реферер страницы
     * @param array|null  $userParams  Параметры визита
     * @param array|null  $modes       Режимы
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    private function hitExt(
        $pageUrl = null,
        $pageTitle = null,
        $pageReferer = null,
        $userParams = null,
        $modes = null
    ) {
        $postData = [];

        if ($this->counterClass) {
            $postData['cnt-class'] = $this->counterClass;
        }

        if ($pageUrl) {
            $postData['page-url'] = urlencode($pageUrl);
        }

        if ($pageReferer) {
            $postData['page-ref'] = urlencode($pageReferer);
        }

        if (!$modes) {
            $modes = [];
        }

        if ($modes) {
            $modes['ar'] = true;
        } else {
            $modes = ['ar' => true];
        }

        $browser_info = [];
        if ($modes && count($modes)) {
            foreach ($modes as $key => $value) {
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

        if (isset($modes['ut'])) {
            $postData['ut'] = $modes['ut'];
        }

        $rnd = rand(0, 1000000);
        $getQuery = self::PATH . $this->counterId . '/1?rn=' . $rnd . '&wmode=2';

        return $this->postRequest(
            self::HOST,
            $getQuery,
            http_build_query($postData)
        );
    }

    /**
     * Возвращает адрес текущей страницы.
     *
     * @return string
     */
    private function currentPageUrl()
    {
        $protocol = 'http://';

        if ($this->getServerParam('HTTPS')) {
            $protocol = 'https://';
        }

        $host = $this->getServerParam('HTTP_HOST');
        $uri = $this->getServerParam('REQUEST_URI');
        $pageUrl = $protocol . $host . $uri;

        return $pageUrl;
    }

    /**
     * Возвращает абсолютный адрес страницы.
     *
     * @param string $url     Адрес страницы
     * @param string $baseUrl Базовый адрес страницы
     *
     * @return string
     */
    private function absoluteUrl($url, $baseUrl)
    {
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

    /**
     * Отправляет POST-запрос в Яндекс.Метрику.
     *
     * @param string $host Хост Яндекс.Метрики
     * @param string $path Путь
     * @param string $data Данные
     *
     * @return bool Успешность отправки данных в Яндекс.Метрику
     */
    private function postRequest($host, $path, $data)
    {
        $out = "POST " . $path . " HTTP/1.1\n";
        $out .= "Host: " . $host . "\n";

        $ip = $this->getServerParam('REMOTE_ADDR');
        if ($ip) {
            $out .= "X-Forwarded-For: " . $ip . "\n";
        }

        $ua = $this->getServerParam('HTTP_USER_AGENT');
        if ($ua) {
            $out .= "User-Agent: " . $ua . "\n";
        }

        $out .= "Content-type: application/x-www-form-urlencoded\n";
        $out .= "Content-length: " . strlen($data) . "\n";
        $out .= "Connection: close\n\n";
        $out .= $data;

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
