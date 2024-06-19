<?php

namespace Src\Server;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Utils\Log;
use Src\Utils\MessageValidator;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class WebSocketServer
{
    private Server $server;
    private array $config;
    private MessageValidator $validator;

    public function __construct(string $host, int $port)
    {
        $this->server = new Server($host, $port);
        $this->setConfig();
        $this->validator = new MessageValidator();

        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
    }

    public function onStart(Server $server)
    {
        echo "Server started at {$server->host}:{$server->port}\n";

        Log::info("Server started.", [
            'host' => $server->host,
            'port' => $server->port
        ]);
    }

    public function onOpen (Server $server, Request $request) 
    {
        Log::info("Connection established.", [
            'ip' => $request->server['remote_addr'],
            'port' => $request->server['remote_port']
        ]);
    }

    public function onMessage(Server $server, $frame) {
        $senderData = $server->connection_info($frame->fd);

        try {
            $validatedData = $this->validator->validate($frame->data);
            $data = json_decode($validatedData, true);

            Log::info("Message received.", [
                'type' => $data['type'],
                'ip' => $senderData['remote_ip'],
                'port' => $senderData['remote_port']
            ]);

            if ($data['type'] === 'auth') {
                $validated = $this->validateToken($data['content']);

                if (!isset($validated)) {
                    $server->push($frame->fd, json_encode(['error' => 'Invalid token']));
                    $server->disconnect($frame->fd, 4001, 'Invalid token!');

                    Log::info("Invalid token.", [
                        'ip' => $senderData['remote_ip'],
                        'port' => $senderData['remote_port']
                    ]);
                } else {
                    $server->push($frame->fd, json_encode(['message' => 'Authenticated']));
                }
            }

            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd) && $fd !== $frame->fd) {
                    $server->push($fd, $validatedData);
                }
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'ip' => $senderData['remote_ip'],
                'port' => $senderData['remote_port'],
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $server->push($frame->fd, json_encode(['error' => $e->getMessage()]));
            return;
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $senderData = $server->connection_info($fd);
        Log::info("Connection closed.", [
            'ip' => $senderData['remote_ip'],
            'port' => $senderData['remote_port']
        ]);
    }

    public function start(): void
    {
        $this->server->start();
    }

    private function setConfig(): void
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

    private function validateToken(string $jwt): \stdClass
    {
        $secret = $this->config['jwt_secret'];

        return JWT::decode($jwt, new Key($secret, 'HS256'));
    }
}