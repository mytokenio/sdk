<?php

namespace Http;

abstract class Action extends \Yaf\Action_Abstract
{
    use ControllerTrait;

    const DEFAULT_OFFSET = 0;
    const DEFAULT_PAGE_LIMIT = 20;
    const DEFAULT_PAGE_NUM = 1;

    public function init()
    {
    }

    /**
     * 可以返回 data 数组，或 response 对象
     *
     * @return array|\Symfony\Component\HttpFoundation\Response|mixed
     */
    abstract public function exec();

    final public function execute()
    {
        try {
            $this->init();
            $this->handleResponse($this->exec());

        } catch (\DataEngine\Exception $e) {

            $this->handleResponse($this->error($e->getCode(), $e->getMessage()));

        } catch (\LogicException $e) {

            $this->handleResponse($this->error($e->getCode(), $e->getMessage()));

        } catch (\Throwable $e) {

            \Log::getLogger('exception')->error($e->getMessage(), ['e' => $e]);
            //$this->handleResponse($this->error($e->getCode(), $e->getMessage()));
            $this->handleResponse($this->error(ErrorCode::SERVER_ERROR));

        }
    }

    protected function initQueryOptions()
    {
        $options = [
            'limit' => self::DEFAULT_PAGE_LIMIT,
            'offset' => self::DEFAULT_OFFSET,
        ];

        if (null != $this->get('page_limit') && intval($this->get('page_limit')) > 0) {
            $options['limit'] = intval($this->get('page_limit'));
        }

        if (null != $this->get('page_num') && intval($this->get('page_num')) > 1) {
            $options['offset'] = (intval($this->get('page_num')) - 1) * $options['limit'];
        }

        if (null != $this->get('need_pagination')) {
            $options['need_pagination'] = true;
            if (null != $this->get('page_num') && intval($this->get('page_num')) > 0) {
                $options['page_num'] = intval($this->get('page_num'));
            } else {
                $options['page_num'] = self::DEFAULT_PAGE_NUM;
            }
        }

        return $options;
    }

    protected function initQueryOptionsForNextId()
    {
        $options = [
            'limit' => self::DEFAULT_PAGE_LIMIT,
            'next_id' => self::DEFAULT_OFFSET,
        ];

        if (null != $this->get('limit') && intval($this->get('limit')) > 0) {
            $options['limit'] = intval($this->get('limit'));
        }

        if (null != $this->get('next_id') && intval($this->get('next_id')) > 0) {
            $options['next_id'] = intval($this->get('next_id'));
        }

        return $options;
    }

    /**
     * 旧的 response 方法
     * @param $data
     * @deprecated
     */
    public function renderSuccessJson($data)
    {
        $this->handleResponse($this->response($data['data']));
    }

    /**
     * @param $data
     * @deprecated
     */
    public function renderErrorJson($data)
    {
        $this->handleResponse($this->error($data['code'], $data['message']));
    }

    /**
     * @param $data
     * @deprecated
     */
    public function renderJson($data)
    {
        $this->handleResponse($this->response($data));
    }

}
