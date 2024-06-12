<?php

require __DIR__ . '/../vendor/autoload.php';

use Src\Server\WebSocketServer;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$server = new WebSocketServer(
    $_ENV['WS_HOST'], 
    $_ENV['WS_PORT']
);

$server->start();