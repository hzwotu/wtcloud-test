<?php

namespace Wotu;

use GuzzleHttp\Client;
use http\Header;
use Zipkin\Propagation\Map;

class BaseService
{

    /**
     * @param $method
     * @param $url
     * @param array $data
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * 发送服务请求
     */
    public static function sendRequest($method, $url, $data = [], $needToken = false)
    {
        $headers = self::getHeader();
        $zipKin = ZipKin::getInstance('http://tracing-analysis-dc-hz.aliyuncs.com/adapt_cn6b5ghmxw@2d20a8f3746a69e_cn6b5ghmxw@53df7ad2afe8301','sdk-test');
        $zipKin->startAction('uri路由','请求参数');
//执行业务代码A
        $zipKin->addChild('A执行的sql/redis/http等语句','记录数据的tag名');
        $zipKin->finishChild();
        $zipKin->endAction();
        $tracing = $zipKin->getTracing();
        $injector = $tracing->getPropagation()->getInjector(new Map());
        $childSpan = $zipKin->getChildSpan();
        $injector($childSpan->getContext(), $headers);
        $headers['Content-Type'] = 'application/json';
        $headers['accept'] = 'application/json, text/plain, */*';
        //获取请求头中header
        $headers = self::getHeader();
        if (!empty($headers['AUTHORIZATION']) && $needToken) {
            $headers['Authorization'] = $headers['AUTHORIZATION'];
        } else {
            $headers['Authorization'] = 'php-sdk';
            $headers['userCode'] = 'php-sdk';
        }
        $httpClient = new Client();
        $option['json'] = $data;
        $request = new \GuzzleHttp\Psr7\Request($method, $url, $headers);
        $response = $httpClient->send($request, $option);

        $resultData = json_decode($response->getBody()->getContents(), true);
//        var_dump($result);
        $childSpan->finish();
        if (empty($resultData['messageCode'])) {
            $errorMessage = $resultData['message'] ?? '请求失败';
            throw new \ErrorException($errorMessage);
        } elseif ($resultData['messageCode'] == 9997) {
            return [];
        } elseif ($resultData['messageCode'] != 200) {
            $errorMessage = $resultData['message'] ?? '请求失败';
            throw new \ErrorException($errorMessage);
        }
//        $zipKin->endAction();
        return $resultData['data'] ?? 'success';
    }


    public static function getHeader()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if ('HTTP_' == substr($key, 0, 5)) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
            if (isset($_SERVER['HTTP_AUTHORIZATION_PLATFORM'])) {
                $headers['AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION_PLATFORM'];
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['AUTHORIZATION'] = $_SERVER['PHP_AUTH_DIGEST'];
            } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
                $headers['AUTHORIZATION'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
            }
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['CONTENT-LENGTH'] = $_SERVER['CONTENT_LENGTH'];
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
            }
        }
        return $headers;
    }

    public static function sendDirectRequest($method, $url, $data = [], $needToken = false, $header = [])
    {
        return Http::get($url, $data, $header);
    }


    public static function sendNormalRequest($method, $url, $data = [], $needToken = false, $header = [])
    {
        $headers = self::getHeader();
        if (!empty($headers['AUTHORIZATION']) && $needToken) {
            $header[] = 'Authorization:' . $headers['AUTHORIZATION'];
        } else {
            $header[] = 'Authorization:php-sdk';
            $header[] = 'userCode:php-sdk';
        }
        $header[] = "Content-Type:application/json";
        if ('get' == strtolower($method)) {
            $result = Http::get($url, $data, $header);
        } else {
            $result = Http::send($url, $method, [], json_encode($data), $header);
        }
        $resultData = json_decode($result, true);
//        var_dump($result);
        if (empty($resultData['messageCode'])) {
            $errorMessage = $resultData['message'] ?? '请求失败';
            throw new \ErrorException($errorMessage);
        } elseif ($resultData['messageCode'] == 9997) {
            return [];
        } elseif ($resultData['messageCode'] != 200) {
            $errorMessage = $resultData['message'] ?? '请求失败';
            throw new \ErrorException($errorMessage);
        }

        return $resultData['data'] ?? 'success';
    }


}
