<?php

namespace Src\Server;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Utils\Log;
use Src\Utils\MessageValidator;
use Src\Utils\TokenCache;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class WebSocketServer
{
    private Server $server;
    private array $config;
    private MessageValidator $validator;
    private TokenCache $tokenCache;
    private RoomManager $roomManager;
    private array $fdToUserIdMap = [];

    public function __construct(string $host, int $port)
    {
        $this->server = new Server($host, $port);
        $this->setConfig();
        $this->validator = new MessageValidator();
        $this->tokenCache = new TokenCache(300);
        $this->roomManager = new RoomManager();

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
        $userId = $request->header['user-id'] ?? null;
        $appId = $request->header['app-id'] ?? null;
        $token = $request->header['token'] ?? null;

        if (!isset($userId)) {
            $server->disconnect($request->fd, 4000, 'Invalid user id!');

            Log::info("Invalid user id.", [
                'ip' => $request->server['remote_addr'],
                'port' => $request->server['remote_port']
            ]);

            return;
        }

        if (!isset($appId) || $appId !== $this->config['app_id']) {
            $server->disconnect($request->fd, 4000, 'Invalid app id!');

            Log::info("Invalid app id.", [
                'ip' => $request->server['remote_addr'],
                'port' => $request->server['remote_port']
            ]);

            return;
        }

        if (empty($token)) {
            $server->disconnect($request->fd, 4001, 'Token not provided!');

            Log::info("Token not provided.", [
                'ip' => $request->server['remote_addr'],
                'port' => $request->server['remote_port']
            ]);

            return;
        }

        try {
            $this->validateToken($token);
            $this->fdToUserIdMap[$request->fd] = $userId;
        } catch (\Throwable $e) {
            $server->disconnect($request->fd, 4002, 'Invalid token!');

            Log::info("Invalid token.", [
                'ip' => $request->server['remote_addr'],
                'port' => $request->server['remote_port']
            ]);

            return;
        }

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

            $this->validateToken($data['token']);

            Log::info("Message received.", [
                'type' => $data['type'],
                'ip' => $senderData['remote_ip'],
                'port' => $senderData['remote_port']
            ]);

            $roomId = $data['metadata']['roomId'];
            $userId = $this->fdToUserIdMap[$frame->fd];

            switch ($data['type']) {
                case 'join_room':
                    $this->roomManager->joinRoom($roomId, $userId);
                    
                    $server->push(
                        $frame->fd, 
                        json_encode([
                            'type' => 'joined_room', 
                            'room_id' => $roomId
                        ])
                    );
                    
                    Log::info("User joined room.", [
                        'room_id' => $roomId,
                        'user_id' => $userId
                    ]);

                    break;
                case 'leave_room':
                    $this->roomManager->leaveRoom($roomId, $userId);
                    
                    $server->push(
                        $frame->fd,
                        json_encode([
                            'type' => 'left_room',
                            'room_id' => $roomId
                        ])
                    );

                    Log::info("User left room.", [
                        'room_id' => $roomId,
                        'user_id' => $userId
                    ]);

                    break;
                case "message":
                case "image":                   
                case "document":
                case "notification":
                    if (!$this->roomManager->isUserInRoom($roomId, $userId)) {
                        throw new \RuntimeException("User not in room!");
                    }

                    $this->sendMessage(
                        $roomId,
                        $userId,
                        $data['content']
                    );
                    break;
                default:
                    throw new \RuntimeException("Invalid message type!");
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'ip' => $senderData['remote_ip'],
                'port' => $senderData['remote_port'],
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $server->push($frame->fd, json_encode(['error' => $e->getMessage()]));
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $userId = $this->fdToUserIdMap[$fd] ?? null;
        if ($userId) {
            unset($this->fdToUserIdMap[$fd]);
        }

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
        $cachedToken = $this->tokenCache->get($jwt);
        
        if ($cachedToken !== null) {
            return $cachedToken;
        }

        $secret = $this->config['jwt_secret'];
        $decodedToken = JWT::decode($jwt, new Key($secret, 'HS256'));
        
        $this->tokenCache->set($jwt, $decodedToken);

        return $decodedToken;
    }

    private function sendMessage(string $roomId, string $senderUserId, string $message): void
    {
        $participants = $this->roomManager->getRoomParticipants($roomId);
        
        if (empty($participants)) return;

        foreach ($participants as $userId => $active) {
            if ($userId == $senderUserId) continue;

            $fd = array_search($userId, $this->fdToUserIdMap);
            
            if ($fd && $this->server->isEstablished($fd)) {
                $this->server->push($fd, json_encode([
                    'user_id' => $senderUserId,
                    'type' => 'message', 
                    'content' => $message
                ]));
            }
        }
    }
}