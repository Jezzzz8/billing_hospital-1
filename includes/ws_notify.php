<?php

require_once __DIR__ . '/websocket-config.php';

function ws_broadcast(
    string $event,
    array $payload = [],
    array $roles = [],
    ?int $departmentId = null,
    array $userIds = []
): void
{
    $senderUserId = (int)($_SESSION['user']['user_id'] ?? 0);
    if ($senderUserId > 0) {
        $payload['sender_user_id'] = $senderUserId;
    }

    $socketAddress = 'tcp://127.0.0.1:' . WEBSOCKET_BROADCAST_PORT;
    $socket = @stream_socket_client($socketAddress, $errno, $error, 1);
    if ($socket === false) {
        error_log(sprintf('[ws_broadcast] Could not connect to the local notification relay (%d): %s', $errno, $error));
        return;
    }

    stream_set_timeout($socket, 2);

    try {
        $message = json_encode([
            'action'        => 'broadcast',
            'event'         => $event,
            'payload'       => $payload,
            'roles'         => $roles,
            'department_id' => $departmentId,
            'user_ids'      => array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0))),
        ], JSON_THROW_ON_ERROR) . "\n";
        if (strlen($message) > 65536) {
            throw new RuntimeException('The WebSocket broadcast exceeds the supported message size.');
        }

        $offset = 0;
        $length = strlen($message);
        while ($offset < $length) {
            $written = fwrite($socket, substr($message, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write to the local notification relay.');
            }
            $offset += $written;
        }
    } catch (Throwable $e) {
        error_log('[ws_broadcast] ' . $e->getMessage());
    } finally {
        fclose($socket);
    }
}
