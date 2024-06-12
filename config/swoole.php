<?php

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

return [
    'worker_num' => 4,
    'daemonize' => false,
    'max_conn' => 1000,
    'max_request' => 10000,
    'heartbeat_idle_time' => 300,
    'heartbeat_check_interval' => 60,
    'log_file' => __DIR__ . $_ENV['SWOOLE_LOG_PATH'],
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? '',
];