<?php
/**
 * File Log.php
 * @henter
 * Time: 2018-02-02 12:27
 *
 */

use Monolog\Logger as MonoLogger;

class Logger
{
    /**
     * @var MonoLogger
     */
    private $logger;

    /**
     * name => self
     * @var array
     */
    private static $instances = [];

    /**
     * @param string $name
     * @param string $file
     * @return self
     */
    public static function getInstance($name = 'default', $file = '')
    {
        if (!empty(self::$instances[$name])) {
            return self::$instances[$name];
        } else {
            return self::$instances[$name] = new self($name, $file);
        }
    }

    /**
     * Logger constructor.
     * @param string $name
     * @param string $file
     */
    public function __construct($name, $file = '')
    {
        $handler = new LoggerHandler();
        $handler->setType($name);

        $this->logger = (new MonoLogger($name))->pushHandler($handler);

        if ($file) {
            $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($file));
        }
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function info($message, $context = [])
    {
        return $this->logger->info($message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function warning($message, $context = [])
    {
        return $this->logger->warning($message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function error($message, $context = [])
    {
        return $this->logger->error($message, $context);
    }
}

