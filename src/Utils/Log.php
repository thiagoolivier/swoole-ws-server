<?php

namespace Src\Utils;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Log
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            $config = require __DIR__ . '/../../config/swoole.php';

            $logDir = $config['log_file'] ?? __DIR__ . '/../../logs/app.log';

            $logger = new Logger('app');

            $handler = new StreamHandler($logDir, Logger::DEBUG);
            $formatter = new LineFormatter(null, null, true, true);
            $handler->setFormatter($formatter);

            $logger->pushHandler($handler);

            self::$logger = $logger;
        }

        return self::$logger;
    }

    public static function info(string $message, array $context = []): void
    {
        self::getLogger()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getLogger()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getLogger()->error($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::getLogger()->debug($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::getLogger()->critical($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::getLogger()->alert($message, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::getLogger()->emergency($message, $context);
    }
}