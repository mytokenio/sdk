<?php
namespace Http;

use Illuminate\Contracts\Support\Arrayable;

trait ControllerTrait
{
    /**
     * 错误码文件，将被解析注释
     * @var string
     */
    public static $errorCodeClass = ErrorCode::class;
    public static $errorCodeParamsError = ErrorCode::PARAMS_ERROR;
    public static $errorCodeMethodError = ErrorCode::REQUEST_METHOD_ERROR;

    public $headers = [
        'Content-Type' => 'application/json;charset=utf-8',
        'Server' => 'apache/1.8.0',
    ];

    /**
     * @param array ...$keys
     * @return self
     */
    public function requirePost(...$keys)
    {
        $this->requireMethod('POST');
        return $this->requireParams(...$keys);
    }

    /**
     * @param array ...$keys
     * @return self
     */
    public function requireGet(...$keys)
    {
        $this->requireMethod('GET');
        return $this->requireParams(...$keys);
    }

    /**
     * 检查必须参数，任何一个为 null 则报错
     *
     * @param array ...$keys
     * @return self
     */
    public function requireParams(...$keys)
    {
        foreach ($keys as $key) {
            if (is_null($this->get($key))) {
                $this->throwError(static::$errorCodeParamsError);
            }
        }
        return $this;
    }

    /**
     * 检查参数, 全部为 null 时才报错
     *
     * @param array ...$keys
     * @return self
     */
    public function requireParamsAny(...$keys)
    {
        foreach ($keys as $key) {
            if (!is_null($this->get($key))) {
                return $this;
            }
        }
        $this->throwError(static::$errorCodeParamsError);
    }

    /**
     * @param $method
     * @return self
     */
    public function requireMethod($method)
    {
        if ($this->getMethod() != strtoupper($method)) {
            $this->throwError(static::$errorCodeMethodError);
        }
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return \SDK::getRequest();
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * add header
     *
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function header(string $name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * 获取参数，会依次从 $_GET $_POST 或 json 里取
     *
     * @param $key
     * @param $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->getRequest()->input($key, $default);
    }

    /**
     * 获取参数，没有则报错
     *
     * @param $key
     * @param $method
     * @return mixed
     */
    public function forceParam($key, $method = null)
    {
        if ($method) {
            $this->requireMethod($method);
        }

        $v = $this->get($key);
        if (is_null($v)) {
            $this->throwError(static::$errorCodeParamsError);
        }
        return $v;
    }

    /**
     * 检查app版本 =
     * @param string $version
     * @param string $os
     * @return bool
     */
    public function versionIs($version, $os = null)
    {
        return $this->versionCompare($version, $os, '=');
    }

    /**
     * 检查app版本 <
     * @param string $version
     * @param string $os
     * @return bool
     */
    public function versionLt($version, $os = null)
    {
        return $this->versionCompare($version, $os, '<');
    }

    /**
     * 检查app版本 <=
     * @param string $version
     * @param string $os
     * @return bool
     */
    public function versionLte($version, $os = null)
    {
        return $this->versionCompare($version, $os, '<=');
    }

    /**
     * 检查app版本 >
     *
     * @param string $version
     * @param string $os
     * @return bool
     */
    public function versionGt($version, $os = null)
    {
        return $this->versionCompare($version, $os, '>');
    }

    /**
     * 检查app版本 >=
     *
     * @param string $version
     * @param string $os
     * @return bool
     */
    public function versionGte($version, $os = null)
    {
        return $this->versionCompare($version, $os, '>=');
    }

    /**
     * @param $version
     * @param null $os
     * @param $op
     * @return bool
     */
    private function versionCompare($version, $os = null, $op)
    {
        if (!$this->getV()) {
            return false;
        }

        if ($os && $os != $this->getOs()) {
            return false;
        }
        return version_compare($this->getV(), $version, $op);
    }

    /**
     * 获取版本号
     *
     * @return string
     */
    public function getV()
    {
        return (string)$this->get('v');
    }

    /**
     * @return string
     */
    public function getOs()
    {
        return $this->get('device_os');
    }

    /**
     * @return bool
     */
    public function isInApp()
    {
        try {
            $this->requireParams('v', 'platform', 'udid', 'device_model', 'device_os');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 业务逻辑异常（通过 code）
     *
     * @param int $code
     * @param string $msg
     */
    public function throwError(int $code, string $msg = '')
    {
        throw new \LogicException($msg, $code);
    }

    /**
     * @param array|Arrayable|\Symfony\Component\HttpFoundation\Response $resp
     */
    public function handleResponse($resp = null)
    {
        //默认未返回数据的情况，如 调用 renderSuccessJson 这类用法
        if (is_null($resp)) {
            return;
        }

        if ($resp instanceof \Symfony\Component\HttpFoundation\Response) {
            //do nothing
        } elseif ($resp instanceof Arrayable) {
            $resp = $this->response($resp->toArray());
        } else {
            $resp = $this->response($resp);
        }

        $resp->sendHeaders();
        $this->getResponse()->setBody($resp->getContent());
    }

    /**
     * @param $data
     * @param string $msg
     * @return JsonResponse
     */
    public function response($data, $msg = ErrorCode::SUCCESS_MSG)
    {
        return new JsonResponse(
            [
                'code' => ErrorCode::SUCCESS,
                'message' => _($msg),
                'data' => $data,
                'timestamp' => time(),
            ],
            200,
            $this->headers
        );
    }

    /**
     * @param int $code
     * @param string $msg
     * @return JsonResponse
     */
    public function error($code = ErrorCode::ERROR_UNKNOWN, $msg = '')
    {
        return new JsonResponse(
            [
                'code' => $code,
                'message' => _($msg ?: static::$errorCodeClass::getMsg($code)),
                'data' => null,
                'timestamp' => time(),
            ],
            200,
            $this->headers
        );
    }
}