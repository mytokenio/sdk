<?php
/**
 * File Cache.php
 * @henter
 * Time: 2018-03-01 21:03
 *
 */


/**
 * Class Cache
 * with auto-encode-decode array
 *
 * @method static get($key)
 */
class Cache
{
    private static $start_at = 0;

    private static $instance;

    /**
     * @return \RedisCluster|\Redis|self
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $redis;

    private $isCluster = false;

    public function __construct()
    {
        $conf = \SDK::config('redis');

        //seeds config for redis cluster
        if (isset($conf->cluster->seeds) && $conf->cluster->seeds->toArray()) {
            list($host, $port) = explode(':', current($conf->cluster->seeds->toArray()));

//            $this->redis = new \RedisCluster(null, $conf->cluster->seeds->toArray(), 3, 3, true);
//            $this->isCluster = true;

            $redis = new \Redis();
            $ok = $redis->connect($host, $port, 3);
            if (!$ok) {
                \Log::error('redis connect error' . $host . ' file: ' . __FILE__ . 'line: ' . __LINE__);
            }

            $this->redis = $redis;


        } else {
            $redis = new \Redis();
            $ok = $redis->connect($conf->config->host, $conf->config->port, 3);
            if (!$ok) {
                \Log::error('redis connect error' . $conf->config->host . ' file: ' . __FILE__ . 'line: ' . __LINE__);
            }

            if ($conf->config->isauth) {
                $res = $redis->auth($conf->config->auth);
                if (false == $res) {
                    \Log::error('redis auth error : ' . __FILE__ . 'line: ' . __LINE__);
                }
            }

            if ($conf->config->index) {
                $res = $redis->select($conf->config->index);
                if (false == $res) {
                    \Log::error('redis select index ' . $conf->config->index . ' file: ' . __FILE__ . 'line: ' . __LINE__);
                }
            }
            $this->redis = $redis;
        }
    }

    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * @param $method
     * @param $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return self::getInstance()->__call($method, $params);
    }

    /**
     * @param $method
     * @param $params
     * @return mixed
     */
    public function __call($method, $params)
    {
        self::$start_at = \Util::getMicroTime();

        $key = current($params);
        if (strtolower($method) == 'hmset') {
            return $this->hMset($key, $params[1]);
        }

        $raw_methods = array_map(function ($name) {
            return strtolower($name);
        }, ['zRangeByScore', 'zRevRangeByScore', 'hMGet']);

        foreach ($params as $i => &$arg) {
            if (in_array(strtolower($method), $raw_methods)) {
                continue;
            }

            if (is_array($arg)) {
                $arg = \json_encode($arg);
            }
        }

        $data = call_user_func_array([$this->redis, $method], $params);
        $this->log($method, $params, $data);
        return $this->_unpack($method, $data);
    }

    /**
     * hMset简单处理, 避免复杂数据写入时出错
     *
     * @param $key
     * @param array $hash
     * @return bool
     */
    public function hMSet($key, $hash)
    {
        self::$start_at = \Util::getMicroTime();
        foreach ($hash as $k => &$v) {
            if (is_array($v)) {
                $v = \json_encode($v);
            }
        }
        $ret = $this->redis->hMset($key, $hash);
        $this->log(__METHOD__, [$key, $hash]);
        return $ret;
    }

    /**
     * @param $name
     * @param $data
     * @return array|mixed
     */
    private function _unpack($name, $data)
    {
        $list_names = array_map(function ($name) {
            return strtolower($name);
        }, ['zRange', 'zRevRangeByScore', 'zRangeByScore', 'sMembers', 'hGetAll', 'lRange']);

        if (in_array(strtolower($name), $list_names)) {
            return $this->unpackList($data);
        } else {
            return $this->unpack($data);
        }
    }

    /**
     * @param $method
     * @param $params
     * @param null $ret
     * @return bool
     */
    private function log($method, $params, $ret = null)
    {
        $cost = \Util::getMicroTime() - static::$start_at;
        $msg = $method . ' ' . \json_encode($params);
        if ($cost > 0.1) {
            \Log::getLogger('redis_perf')->info($msg, [
                'cost' => $cost,
                'params' => $params,
                'ret' => $ret,
            ]);
        } else {
            \Log::getLogger('redis_request')->info($msg, [
                'cost' => $cost,
                'params' => $params,
                'ret' => $ret,
            ]);
        }
        return true;
    }

    /**
     * @param $k
     * @param $v
     * @param $options
     * @return bool
     */
    public function set($k, $v, $options = null)
    {
        if (is_array($v)) {
            $v = \json_encode($v);
        }
        if ($options === null) {
            return $this->redis->set($k, $v);
        }
        return $this->redis->set($k, $v, $options);
    }

    /**
     * @param $keys
     * @return array
     */
    public function mget($keys)
    {
        $data = [];
        foreach ($keys as $key) {
            if ($value = $this->get($key)) {
                $data[] = $value;
            }
        }
        return $data;
    }

    /**
     * @param $hash
     * @return bool
     */
    public function mset($hash)
    {
        self::$start_at = \Util::getMicroTime();
        foreach ($hash as $key => $value) {
            $this->set($key, $value);
        }
        $this->log(__METHOD__, $hash);
        return true;
    }

    /**
     * @param $key
     * @return int
     */
    public function delete($key)
    {
        return $this->redis->del($key);
    }

    /**
     * @param $ret
     * @return array
     */
    private function unpackList($ret)
    {
        $list = [];
        if (is_array($ret)) {
            foreach ($ret as $i => $val) {
                $list[$i] = $this->unpack($val);
            }
        }
        return $list;
    }

    /**
     * @param $val
     * @return mixed
     */
    private function unpack($val)
    {
        if (is_string($val)) {
            $data = \json_decode($val, true);
            if (\json_last_error() == JSON_ERROR_NONE) {
                return $data;
            }
        }
        return $val;
    }
}
