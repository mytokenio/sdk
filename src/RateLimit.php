<?php

/**
 * 限流
 *
 * demo
 * if (!\RateLimit::check('sms', '18665679660')) {
 *      throw new \Exception('该手机号发送频率超过限制');
 * }
 *
 * Class RateLimit
 */

class RateLimit
{
    //限流配置 type => [period, max] 指定时间(秒)内最多多少次
    protected static $configs = [
        'test' => [10, 5],
        'sms' => [86400, 50],
        'search' => [60, 10],
        'kline'  => [60,100],
        'register' => [60,10],
        //TODO
    ];

    /**
     * 设置限流规则，全部设置或单个设置
     * @param $config
     * @param $v
     */
    public static function setConfig($config, $v = null)
    {
        if (is_null($v)) {
            self::$configs = $config;
        } else {
            self::$configs[$config] = $v;
        }
    }

    /**
     * 对指定 id 检查对应 type 的限流配置
     *
     * @param string $type
     * @param string $id
     * @param int $use
     * @return int
     */
    public static function check($type, $id, $use = 1)
    {
        if (!isset(self::$configs[$type])) {
            \Log::getLogger('ratelimit')->error('type not exist '.$type);
            return 0;
        }

        $redis = self::getRedis();
        list($period, $max) = self::$configs[$type];

        $rate = $max / $period;

        $t_key = self::keyTime($type, $id);
        $a_key = self::keyAllow($type, $id);

        if ($redis->exists($t_key)) {
            $c_time = time();

            $time_passed = $c_time - $redis->get($t_key);
            $redis->set($t_key, $c_time, $period);

            $allow = $redis->get($a_key);
            $allow += $time_passed * $rate;

            if ($allow > $max) {
                $allow = $max;
            }

            if ($allow < $use) {
                $redis->set($a_key, $allow, $period);

                \Log::getLogger('ratelimit')->warning('overlimit '.$type, [
                    'type' => $type,
                    'id' => $id,
                ]);

                return 0;
            } else {
                $redis->set($a_key, $allow - $use, $period);
                return (int)ceil($allow);
            }
        } else {
            $redis->set($t_key, time(), $period);
            $redis->set($a_key, $max - $use, $period);
            return $max;
        }
    }

    public static function purge($type, $id)
    {
        self::getRedis()->del(self::keyTime($type, $id));
        self::getRedis()->del(self::keyAllow($type, $id));
    }

    public static function keyTime($type, $id)
    {
        return 'ratelimit:' . $type . ":" . $id . ":time";
    }

    public static function keyAllow($type, $id)
    {
        return 'ratelimit:' . $type . ":" . $id . ":allow";
    }


    /**
     * @return Cache|Redis|RedisCluster
     */
    public static function getRedis()
    {
        return \Cache::getInstance();
    }

}