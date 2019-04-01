<?php
/**
 * File Internal.php
 * @henter
 * Time: 2018-05-18 20:12
 *
 */

use GuzzleHttp\Client;

/**
 * 请求 Internal API
 */
class Internal
{
    const SUCCESS               = 0;
    const SUCCESS_MSG           = 'success';

    const ERROR_UNKNOWN        = 999;
    const ERROR_UNKNOWN_MSG    = 'unknown error';
    const ERROR_UNKNOWN_B_MSG  = '未知错误，请尝试重新提交';

    const SERVER_ERROR          = 990;
    const SERVER_ERROR_MSG      = 'server error';

    const PARAMS_ERROR          = 800001;
    const PARAMS_ERROR_MSG      = 'param error';

    const FORBIDDEN             = 800002;
    const FORBIDDEN_MSG         = 'no role';

    /**
     * 批量请求
     *   reqs
     *       [uri, params, method],
     *       [uri, params, method],
     *       [uri, params, method],
     *       ...
     *
     * 返回每个请求的响应数据，顺序跟 reqs 一致
     *   return
     *      array, array, array, ...
     *
     * @param array $reqs
     * @return array
     */
    public static function multi(...$reqs)
    {
        $client = self::getClient();
        // key => request
        $promises = [];
        $requests = [];

        //req: [uri, params]
        $hashs = [];
        foreach ($reqs as $req) {
            $hash = md5(json_encode($req));
            $hashs[] = $hash;

            if (count($req) == 2) {
                list($uri, $params) = $req;
                $method = 'GET';
            } elseif (count($req) > 2) {
                list($uri, $params, $method) = $req;
            } else {
                continue;
            }

            $options = self::getOptions($method, $params);
            if ($method == 'JSON') {
                $method = 'POST';
            }

            $promises[$hash] = $client->getAsync($uri, $options);

            //日志跟之前一致
            $requests[] = [
                'url' => $uri,
                'data' => $params,
                'method' => $method,
                'key' => $hash,
            ];
        }

        $start = \Util::getmicrotime();

        $responses = [];

        $return = [];
        $results = \GuzzleHttp\Promise\settle($promises)->wait();

        foreach ($hashs as $hash) {
            $result = $results[$hash];
            if ($result['state'] == \GuzzleHttp\Promise\PromiseInterface::FULFILLED) {
                $body = $result['value']->getBody();
                $responses[$hash] = $body;

                $return[] = \json_decode($body, true)['data'] ?? [];
            } else {
                \Log::getLogger('http_multi_request')->error($result['reason']->getMessage());
                $return[] = [];
            }
        }

        $cost = \Util::getmicrotime() - $start;

        \Log::getLogger('http_multi_request')
            ->info('multi requests', [
                'n' => count($requests),
                'cost' => $cost,
                'requests' => $requests,
                'responses' => $responses
            ]);

        return $return;
    }

    /**
     * @param $uri
     * @param array $params
     * @return mixed
     */
    public static function get($uri, $params = [])
    {
        return self::request('GET', $uri, $params);
    }

    /**
     * @param $uri
     * @param array $params
     * @param string $key
     * @return mixed
     */
    public static function getList($uri, $params = [], $key = 'list')
    {
        $data = self::request('GET', $uri, $params);
        return $data[$key] ?? [];
    }

    /**
     * @param $uri
     * @param array $params
     * @return mixed
     */
    public static function post($uri, $params = [])
    {
        return self::request('POST', $uri, $params);
    }

    /**
     * TODO, retry request
     *
     * @param $method
     * @param $uri
     * @param array $params
     * @param bool $raw 是否返回原始数据 默认返回 data 字段
     * @return mixed
     * @throws Exception
     */
    public static function request($method, $uri, $params = [], $raw = false)
    {
        $method = strtoupper($method);
        $options = self::getOptions($method, $params);

        $start = \Util::getmicrotime();
        try {
            if ($method == 'JSON') {
                $method = 'POST';
            }
            $response = self::getClient()->request($method, $uri, $options);
        } catch (\Exception $e) {
            \Log::getLogger('exception')->error($e->getMessage(), ['e' => $e]);
            return false;
        }

        $cost = \Util::getmicrotime() - $start;

        //log参数跟之前兼容
        $context = [
            'uri' => $uri,
            'cost' => $cost,
            'request' => [
                'url' => $uri,
                'data' => $params,
                'method' => $method,
            ],
            'status' => $response->getStatusCode(),
            'response' => $response->getBody()
        ];

        if ($response->getStatusCode() != 200) {
            \Log::getLogger('http_request')->error('request failed: '.$uri, $context);
            return false;
        }

        if (php_sapi_name() != 'cli') {
            \Log::getLogger('http_request')->info('request: '.$uri, $context);
        }

        $ret = \json_decode($response->getBody(), true);

        //返回原始数据
        if ($raw) {
            return $ret;
        }

        if ($ret['code'] != self::SUCCESS) {
            throw new \LogicException($ret['message'], $ret['code']);
        }
        return $ret['data'];
    }

    /**
     * @param $method
     * @param array $params
     * @return array
     */
    protected static function getOptions($method, $params = [])
    {
        //$params['preRequestId'] = \Log::getUniqueId();
        $options = [
            'headers' => [
                'preRequestId' => \Log::getUniqueId()
            ]
        ];

        if ($language = strtolower(getenv('LC_ALL'))) {
            $params['language'] = $language;
        }

        if ($method == 'POST') {
            $options['form_params'] = $params;
        } elseif ($method == 'GET') {
            $options['query'] = $params;
        } elseif ($method == 'JSON') {
            $options['json'] = $params;
        }
        return $options;
    }

    /**
     * @return Client
     */
    protected static function getClient()
    {
        return new Client([
            'base_uri' => self::getInternalApiServer(),
            'timeout'  => 10.0,
            'connect_timeout' => 3,
        ]);
    }

    /**
     * @return string
     */
    protected static function getInternalApiServer()
    {
        return \SDK::config('internalapi')->hostname;
    }

}
