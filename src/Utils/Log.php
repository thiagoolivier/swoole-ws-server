<?php

namespace Src\Utils;

class Log
{
    public static function info(string $message): void
    {
        $config = require __DIR__ . '/../../config/swoole.php';

        $logDir = $config['log_file'];
        $date = date('Y-m-d H:i:s');
        $log = "[{$date}] [INFO] {$message}\n";
        file_put_contents($logDir, $log, FILE_APPEND);
    }

    public static function warning(string $message): void
    {
        $config = require __DIR__ . '/../../config/swoole.php';

        $logDir = $config['log_file'];
        $date = date('Y-m-d H:i:s');
        $log = "[{$date}] [WARNING] {$message}\n";
        file_put_contents($logDir, $log, FILE_APPEND);
    }

    public static function error(string $message): void
    {
        $config = require __DIR__ . '/../../config/swoole.php';

        $logDir = $config['log_file'];
        $date = date('Y-m-d H:i:s');
        $log = "[{$date}] [ERROR] {$message}\n";
        file_put_contents($logDir, $log, FILE_APPEND);
    }
}