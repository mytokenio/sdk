<?php
namespace Http;

use Yaf\Request_Abstract as Request;
use Yaf\Response_Abstract as Response;
use Yaf\Plugin_Abstract;

/**
 * 默认插件，为yaf请求生命周期服务
 *
 * Class Plugin
 * @package Http
 */
class Plugin extends Plugin_Abstract {

    public function routerStartup(Request $request, Response $response)
    {
    }

    public function routerShutdown(Request $request, Response $response)
    {
    }

    public function dispatchLoopStartup(Request $request, Response $response)
    {
    }

    public function preDispatch(Request $request, Response $response)
    {
    }

    public function postDispatch(Request $request, Response $response)
    {

    }

    public function dispatchLoopShutdown(Request $request, Response $response)
    {
        //hack for controller action response
        \Http\Controller::sendControllerResponse($response);
    }
}
