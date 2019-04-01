<?php
/**
 * File SDK.php
 * @henter
 * Time: 2018-06-05 15:26
 *
 */

/**
 * 项目内的 bootstrap 类需继承此 SDK 类
 * 会自动执行 _init 开头的方法
 *
 * Class SDK
 */
class SDK extends \Yaf\Bootstrap_Abstract
{
    //TODO, 后续处理
    const LOG_SERVERS = ['172.31.7.134:12333', '172.31.14.163:12333', '172.31.8.106:12333'];

    /**
     * 完整配置，所有组件均从这里读取配置
     *
     * 目前仅支持 yaf 环境，后续优化 TODO
     * @var \Yaf\Config_Abstract
     */
    private static $config;

    /**
     * @var \Http\Request
     */
    protected static $request;

    /**
     * @return \Http\Request
     */
    public static function getRequest()
    {
        return self::$request;
    }

    /**
     * 初始化 SDK
     */
    public function _initSDK(Yaf\Dispatcher $dispatcher)
    {
        self::$config = $this->getConfig();
        //$request 有可能已被其它 _init 方法赋值 （自定义 request 对象）
        self::$request = self::$request ?: \Http\Request::capture();

        $dispatcher->disableView();
        $dispatcher->registerPlugin(new \Http\Plugin());
    }

    /**
     * 项目内需重写此方法，用于获取 yaf config
     * @return \Yaf\Config\Simple
     */
    public function getConfig()
    {
        $iniConf = Yaf\Application::app()->getConfig()->toArray();

        if ($host = getenv('REDIS_HOST')) {
            $iniConf['redis']['config']['host'] = $host;
        }
        if ($seeds = getenv('REDIS_SEEDS')) {
            $iniConf['redis']['cluster']['seeds'] = explode(',', $seeds);
        }

        if ($host = getenv('DB_HOST')) {
            $iniConf['database']['master']['config']['host'] = $host;
            $iniConf['database']['master']['config']['db'] = getenv('DB_NAME');
            $iniConf['database']['master']['config']['username'] = getenv('DB_USER');
            $iniConf['database']['master']['config']['password'] = getenv('DB_PASSWORD');
            $iniConf['database']['slave'] = $iniConf['database']['master'];
        }

        if ($host = getenv('KAFKA_HOST')) {
            $iniConf['kafka'] = $host;
        }

        return new Yaf\Config\Simple($iniConf, true);
    }

    /**
     * @param null $key
     * @param null $default
     * @return \Yaf\Config\Simple|mixed
     */
    public static function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return self::$config;
        }

        return self::$config->{$key} ?? $default;
    }

    private static function _initLog()
    {
        //log servers [host:port, ...]
        if (!empty(self::$config->log->servers)) {
            $servers = self::$config->log->servers->toArray();
        } else {
            $servers = self::LOG_SERVERS;
        }

        if (php_sapi_name() == 'cli') {
            $extra = [
                'command' => implode(' ', $_SERVER['argv']),
                'mem' => memory_get_usage(true),
            ];
        } elseif (isset($_REQUEST['udid'])) {
            //for app request
            $extra = [
                'h' => $_SERVER['HTTP_HOST'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'v' => $_REQUEST['v'] ?? '',
                'd' => $_REQUEST['udid'] ?? '',
                't' => $_REQUEST['mytoken'] ?? $_REQUEST['mytoken_sid'] ?? '',
                'os' => $_REQUEST['device_os'] ?? '',
                'm' => $_REQUEST['device_model'] ?? '',
                'p' => $_REQUEST['platform'] ?? '',
                'l' => $_REQUEST['language'] ?? '',
                'real_ip' => \Util::getClientIp(),
            ];
        } else {
            //pc or other ?
            $extra = [
                'h' => $_SERVER['HTTP_HOST'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'v' => $_REQUEST['v'] ?? '',
                't' => $_REQUEST['mytoken'] ?? $_REQUEST['mytoken_sid'] ?? '',
                'p' => $_REQUEST['platform'] ?? '',
                'l' => $_REQUEST['language'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'real_ip' => \Util::getClientIp(),
            ];
        }

        if (\Yaf\ENVIRON != 'product') {
            \Log::setLogFile(self::$config->log->path);
        }

        LoggerHandler::setServers($servers);
        LoggerHandler::setEnv(\YAF\ENVIRON);
        LoggerHandler::setExtra($extra);
        LoggerHandler::setUniqueId($_REQUEST['preRequestId'] ?? self::$request->header('preRequestId'));
    }

    /**
     * 初始化数据库连接配置
     * 基于 laravel illuminate, 详细参考 https://laravel.com/docs/5.6/database
     *
     * @param \Yaf\Config\Simple $config
     */
    private static function _initDb()
    {
        $db = self::config('db');
        $database = self::config('database');

        //不需要 db 的项目，如 openapi
        if (!$db && !$database) {
            return;
        }

        $capsule = new \Illuminate\Database\Capsule\Manager;

        if ($db) {
            foreach ($db->toArray() as $name => $conn) {
                $conn['sticky'] = true;
                $capsule->addConnection($conn, $name);
            }
        } else {
            //兼容旧的配置
            $master = $database->master->config ?? $database->config;
            $slave = $database->slave->config ?? $database->config;

            $newConfig = [
                'driver' => 'mysql',
                'read' => [
                    'host' => $slave->host,
                ],
                'write' => [
                    'host' => $master->host,
                ],
                'sticky' => true,
                'username' => $master->username,
                'password' => $master->password,
                'database' => $master->db,
                'port' => $master->port,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
            ];
            $capsule->addConnection($newConfig);
        }

        $dispatcher = new \Illuminate\Events\Dispatcher();

        //update fetch mode, fuck laravel team, see https://laravel.com/docs/5.4/upgrade
        $dispatcher->listen(\Illuminate\Database\Events\StatementPrepared::class, function ($event) {
            $event->statement->setFetchMode(PDO::FETCH_ASSOC);
        });

        $dispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) {
            \Model::$lastQuery = $event->sql;

            // > 1s
            if ($event->time > 1000) {
                \Log::getLogger('slow_query')->info($event->sql, [
                    'conn' => $event->connection->getName(),
                    'sql' => $event->sql,
                    'params' => $event->bindings,
                    'hash' => md5($event->sql),
                    'cost' => $event->time/1000, //ms to s
                ]);
            }
        });

        $capsule->setEventDispatcher($dispatcher);
        $capsule->setAsGlobal();
        //$capsule->bootEloquent();
        class_alias('\Illuminate\Database\Capsule\Manager', 'DB');
    }
}
