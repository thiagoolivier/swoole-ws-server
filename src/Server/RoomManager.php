<?php

namespace Src\Server;

class RoomManager
{
    private array $rooms = [];

    public function createRoom(string $roomId): void
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
    }

    public function joinRoom(string $roomId, string $userId): void
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
        $this->rooms[$roomId][$userId] = true;
    }

    public function leaveRoom(string $roomId, string $userId): void
    {
        if (isset($this->rooms[$roomId][$userId])) {
            unset($this->rooms[$roomId][$userId]);
        }

        if (empty($this->rooms[$roomId])) {
            unset($this->rooms[$roomId]);
        }
    }

    public function isUserInRoom(string $roomId, string $userId): bool
    {
        return isset($this->rooms[$roomId][$userId]);
    }

    public function getRoomParticipants(string $roomId): ?array
    {
        return $this->rooms[$roomId] ?? null;
    }
}