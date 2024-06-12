<?php

namespace Src\Utils;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Swoole\Http\Request;

class JWTUtils
{
    public static function validateToken(Request $request): bool
    {
        $config = require __DIR__ . '/../../config/swoole.php';
        $secret = $config['jwt_secret'];

        $token = $request->get['token'] ?? '';

        try {
            JWT::decode($token, new Key($secret, 'HS256'));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}