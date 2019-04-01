<?php

/**
 * File Log.php
 * @henter
 * Time: 2018-02-02 12:27
 *
 */

class Log
{
    public static $log_file;

    public static function getUniqueId()
    {
        return LoggerHandler::getUniqueId();
    }

    public static function setRates($rates)
    {
        LoggerHandler::setRates($rates);
    }

    public static function setLogFile($filepath)
    {
        static::$log_file = $filepath;
    }

    /**
     * @param string $type
     * @return Logger
     */
    public static function getLogger($type = '')
    {
        return Logger::getInstance($type ?: 'default', static::$log_file);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @param string $type The log type
     * @return Boolean Whether the record has been processed
     * @static
     */
    public static function info($message, $context = [], $type = '')
    {
        return static::getLogger($type)->info($message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @param string $type The log type
     * @return Boolean Whether the record has been processed
     * @static
     */
    public static function warn($message, $context = [], $type = '')
    {
        return static::getLogger($type)->warning($message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @param string $type The log type
     * @return Boolean Whether the record has been processed
     * @static
     */
    public static function warning($message, $context = [], $type = '')
    {
        return static::getLogger($type)->warning($message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @param string $type The log type
     * @return Boolean Whether the record has been processed
     * @static
     */
    public static function error($message, $context = [], $type = '')
    {
        return static::getLogger($type)->error($message, $context);
    }


}