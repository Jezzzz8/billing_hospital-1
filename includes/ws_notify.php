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

        $acknowledgment = fgets($socket, 1024);
        $result = is_string($acknowledgment) ? json_decode($acknowledgment, true) : null;
        if (!is_array($result) || empty($result['accepted'])) {
            $socketState = stream_get_meta_data($socket);
            $reason = !empty($socketState['timed_out'])
                ? 'The local notification relay did not acknowledge the broadcast before timing out.'
                : 'The local notification relay rejected or did not acknowledge the broadcast.';
            throw new RuntimeException($reason);
        }

        if (!empty($result['send_errors'])) {
            error_log(sprintf(
                '[ws_broadcast] Relay queued %d socket(s) and reported %d send error(s) for %s.',
                (int)($result['queued'] ?? 0),
                (int)$result['send_errors'],
                $event
            ));
        } elseif ((int)($result['queued'] ?? 0) === 0) {
            error_log(sprintf('[ws_broadcast] Relay accepted %s, but queued it to zero socket connections.', $event));
        }
    } catch (Throwable $e) {
        error_log('[ws_broadcast] ' . $e->getMessage());
    } finally {
        fclose($socket);
    }
}
