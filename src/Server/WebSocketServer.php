<?php

namespace Src\Server;

use Src\Utils\JWTUtils;
use Src\Utils\MessageValidation;
use Src\Utils\Log;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class WebSocketServer
{
    private Server $server;
    private array $config;

    public function __construct(string $host, int $port)
    {
        $this->server = new Server($host, $port);
        $this->setConfig();

        $this->server->on('start', function (Server $server) {
            echo "Server started at host {$server->host} and port {$server->port}\n";
        });

        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);

        $this->server->on('close', function (Server $server, $fd) {
            echo "FD {$fd} connection closed.\n";
        });
    }

    public function onOpen (Server $server, Request $request) 
    {
        $decoded = JWTUtils::validateToken($request);

        if (!$decoded) {
            $server->close($request->fd);
        } else {
            echo "FD {$request->fd} connection stablished.\n";
        }
    }

    public function onMessage(Server $server, $frame) {
        $validator = new MessageValidation();

        try {
            $validator->validate($frame->data);
        } catch (\Throwable $th) {
            $remote_addr = $server->connection_info($frame->fd)['remote_addr'];
            $remote_port = $server->connection_info($frame->fd)['remote_port'];
            
            Log::error(
                "{$th->getMessage()} | " .
                "Remote address: {$remote_addr} | " .
                "Port: {$remote_port}"
            );
        }

        $data = json_decode($frame->data, true);

        if ($data['type'] === 'message') {
            
        }
        
        foreach ($server->connections as $fd) {
            if ($server->isEstablished($fd) && $fd !== $frame->fd) {
                $server->push($fd, $frame->data);
            }
        }
    }

    public function start(): void
    {
        $this->server->start();
    }

    public function setConfig(): void
    {
        $config = require __DIR__ . '/../../config/swoole.php';

        if (empty($config) || !is_array($config)) {
            throw new \RuntimeException("Invalid configuration file!");
        }

        $this->config = $config;

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'daemonize' => $this->config['daemonize'],
            'log_file' => $this->config['log_file'],
            'max_conn' => $this->config['max_conn'],
            'max_request' => $this->config['max_request'],
            'heartbeat_idle_time' => $this->config['heartbeat_idle_time'],
            'heartbeat_check_interval' => $this->config['heartbeat_check_interval'],
        ]);
    }
}