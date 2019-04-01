<?php
/**
 * File LoggerHandler.php
 * @henter
 * Time: 2018-02-02 15:31
 *
 */

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger as MonoLogger;

class LoggerHandler extends AbstractProcessingHandler
{
    /**
     * UDP servers
     *
     * @var array $servers
     */
    protected static $servers;

    /**
     * socket handle
     *
     * @var resource $handle
     */
    protected static $handle;

    /**
     * @var string 唯一ID
     */
    protected static $unique_id;

    /**
     * @var int 用户ID
     */
    protected static $uid;

    /**
     * @var string
     */
    protected static $env = 'dev';

    /**
     * @var array 附加信息
     */
    protected static $extra;

    /**
     * @var string 日志类型
     */
    protected $type = 'default';

    /**
     * 采样比例  值 0 到 100
     * type => rate
     * @var array
     */
    protected static $rates = [
        'default' => 100,
    ];

    public function __construct($level = MonoLogger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        static::$unique_id = static::$unique_id ?: static::genUniqueId();
    }

    public static function getUniqueId()
    {
        if (!static::$unique_id) {
            static::$unique_id = static::genUniqueId();
        }
        return static::$unique_id;
    }

    public static function setUniqueId($unique_id)
    {
        static::$unique_id = $unique_id;
    }

    public static function setUid($uid)
    {
        static::$uid = $uid;
    }

    public static function setEnv($env)
    {
        static::$env = $env;
    }

    public static function setServers(array $servers)
    {
        static::$servers = $servers;
    }

    public static function setExtra(array $extra)
    {
        static::$extra = $extra;
    }

    public static function setRates(array $rates)
    {
        static::$rates = $rates;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $level = strtolower($record['level_name']);

        if (!$this->checkRate($level)) {
            return false;
        }

        $data = $this->getLogData(
            $level,
            $record['message'],
            $record['context']
        );

        return $this->toUdp($data);
    }

    /**
     * @param $level
     * @return bool
     */
    protected function checkRate($level)
    {
        $ok = true;
        if ($level == 'info' || $level == 'debug') {
            $rate = (int)(static::$rates[$this->type] ?? 100);
            $ok = (bool)($rate >= 100 || rand(1, 10000) <= $rate * 100) ? true : false;
        }
        return $ok;
    }

    /**
     * @return string
     */
    private static function genUniqueId()
    {
        return md5(microtime(true).'/'.rand(0,255).'/'.uniqid());
    }

    public function close()
    {
        @fclose(static::$handle);
    }

    /**
     * 发送日志到远程udp服务器(logstash or rsyslog, etc.)
     *
     * @param string $data
     * @return bool
     */
    public function toUdp(string $data)
    {
        if (!static::$handle) {
            $server = 'udp://'.static::$servers[array_rand(static::$servers)];
            static::$handle = stream_socket_client($server, $errno, $errstr);
        }

        if (static::$handle) {
            @fwrite(static::$handle, $data, strlen($data));
            return true;
        }
        return false;
    }

    /**
     * 获取待存储日志数据
     *
     * @param $level
     * @param $message
     * @param $context
     * @return string
     */
    private function getLogData($level, $message, $context) {
        $index = 'log-'.static::$env.'-'.$this->type.'-'.date('Y-m-d');

        //context 只允许一维 hash，默认将数组或对象转换为 json 字符串
        foreach ($context as $k => &$v) {
            if (is_array($v)) {
                $v = \json_encode($v);
            } elseif (is_object($v)) {
                if (method_exists($v, '__toString')) {
                    $v = (string)$v;
                } else {
                    $v = \json_encode($v);
                }
            }

            if (strlen($v) > 5000) {
                $v = substr($v, 0, 5000);
            }
        }

        $data  = \json_encode([
            '@index'    => $index,
            '@type'     => $this->type,
            'datetime' => gmdate('Y-m-d H:i:s'),
            'level'     => $level,
            'unique_id' => self::$unique_id,
            'uid'   => (int)static::$uid,
            'info'      => [
                'host' => gethostname(),
                'extra' => static::$extra,
                'message' => substr($message, 0, 1000),
                'context' => $context
            ],
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        return $data;
    }

}