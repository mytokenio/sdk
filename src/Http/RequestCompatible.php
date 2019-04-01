<?php
/**
 * File RequestCompatible.php
 * @henter
 * Time: 2018-06-09 20:08
 *
 */

namespace Http;

/**
 * 兼容旧的 request 方法
 *
 * Trait RequestCompatible
 */
trait RequestCompatible
{

    /**
     * @return string
     */
    public function getAction()
    {
        return strtolower(\Yaf\Dispatcher::getInstance()->getRequest()->getActionName());
    }

    /**
     * @return string
     */
    public function getController()
    {
        return strtolower(\Yaf\Dispatcher::getInstance()->getRequest()->getControllerName());
    }

    public function init()
    {
        $this->initParams();
        $this->checkParams();
    }

    /*
     * 初始化路由参数到 request
     */
    public function initParams()
    {
        $params = \Yaf\Dispatcher::getInstance()->getRequest()->getParams();
        foreach ($params as $key => $value) {
            $this->request->set($key, $value);
        }
    }

    //TODO,to be removed
    public function checkParams()
    {
    }

    /**
     * 旧的获取全部参数方法， 第一个 key 为当前 path
     *      {
     *          "controller/action": "",
     *          "k": "v",
     *          ...
     *      }
     *
     * TODO 恢复正常 kv 数据
     * @return array
     */
    public function allParams()
    {
        return array_merge([ltrim($this->getPathInfo(), '/') => ''], $this->input());
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        return $this->input($key, $default);
    }

    /**
     * @param $key
     * @param $value
     */
    public function setParam($key, $value)
    {
        $this->query->set($key, $value);
    }

    public function __get($key)
    {
        return $this->getParam($key);
    }

    public function __set($key, $value)
    {
        $this->setParam($key, $value);
    }

}
