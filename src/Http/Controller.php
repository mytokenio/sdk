<?php
namespace Http;

abstract class Controller extends \Yaf\Controller_Abstract
{
    /**
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public static $response;

    use ControllerTrait {
        response as originResponse;
        error as originError;
    }

    public static function sendControllerResponse(\Yaf\Response_Abstract $response)
    {
        if (!$response->getBody() && self::$response) {
            self::$response->sendHeaders();
            $response->setBody(self::$response->getContent());
        }
    }

    /**
     * @param $data
     * @param string $msg
     * @return JsonResponse
     */
    public function response($data, $msg = ErrorCode::SUCCESS_MSG)
    {
        return static::$response = $this->originResponse($data, $msg);
    }

    /**
     * @param int $code
     * @param string $msg
     * @return JsonResponse
     */
    public function error($code = ErrorCode::ERROR_UNKNOWN, $msg = '')
    {
        //temp hack for controller error code
        if (class_exists('DataEngine\ErrorCode')) {
            static::$errorCodeClass = \DataEngine\ErrorCode::class;
        }
        return static::$response = $this->originError($code, $msg);
    }
}