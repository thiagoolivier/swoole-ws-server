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
            echo "Server running at host {$server->host} and port {$server->port}\n";
        });

        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
    }

    public function onOpen (Server $server, Request $request) 
    {
        try {
            $decoded = JWTUtils::validateToken($request);
            
            if (! $decoded) {
                $server->close($request->fd);
                throw new \Exception("[JWT-UNAUTHORIZED] Connection: IP {$request->server['remote_addr']}");
            } else {
                echo "FD {$request->fd} connection established.\n";
                Log::info("[CONN STABLISHED] IP: {$request->server['remote_addr']}");
            }
        } catch (\Exception $e) {
            Log::error("[CONN FAILED] " . $e->getMessage());
            $server->close($request->fd);
        }
    }

    public function onMessage(Server $server, $frame) {
        $validator = new MessageValidation();
        $senderData = $server->connection_info($frame->fd);

        try {
            $validatedData = $validator->validate($frame->data);
            $data = json_decode($validatedData, true);
            
            Log::info(
                "[MESSAGE IN] type: {$data['type']} " . 
                "ip: {$senderData['remote_ip']} | " .
                "port: {$senderData['remote_port']}"
            );

            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd) && $fd !== $frame->fd) {
                    $server->push($fd, $validatedData);
                }
            }
        } catch (\Throwable $e) {            
            Log::error(
                "{$e->getMessage()} | " .
                "ip: {$senderData['remote_ip']} | " .
                "port: {$senderData['remote_port']}"
            );

            $server->push($frame->fd, json_encode(['error' => $e->getMessage()]));
            return;
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $senderData = $server->connection_info($fd);
        Log::info("[CONN CLOSED] ip: {$senderData['remote_ip']} | port: {$senderData['remote_port']}");
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