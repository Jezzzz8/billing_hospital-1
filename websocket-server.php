<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/websocket-config.php';

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\ConnectionInterface as SocketConnectionInterface;
use React\Socket\SocketServer;

/*
|--------------------------------------------------------------------------
| WebSocket Notification Server
|--------------------------------------------------------------------------
| WebSocket address: ws://0.0.0.0:<WEBSOCKET_PORT>
| Broadcast relay:   127.0.0.1:<WEBSOCKET_BROADCAST_PORT>
|
| Browser clients may subscribe to portal refresh events. PHP-originated
| broadcasts arrive through the loopback-only relay.
|--------------------------------------------------------------------------
*/

class BillingNotifier implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    protected SplObjectStorage $patientPortalClients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        $this->patientPortalClients = new SplObjectStorage();

        echo "[INIT] BillingNotifier initialized\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        if (isset($conn->httpRequest)) {
            parse_str($conn->httpRequest->getUri()->getQuery(), $query);
            if (($query['channel'] ?? '') === 'patient-portal') {
                $this->patientPortalClients->attach($conn, [
                    'patient_id' => 0,
                    'admission_ids' => [],
                    'statement_ids' => [],
                ]);
            }
        }

        echo sprintf(
            "[%s] [WS OPEN] ID=%s | Total clients=%d\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $this->clients->count()
        );
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!is_array($data)) {
            return;
        }

        if ($this->patientPortalClients->contains($from)) {
            if (($data['action'] ?? '') === 'subscribe_patient_portal') {
                $this->patientPortalClients[$from] = [
                    'patient_id' => max(0, (int)($data['patient_id'] ?? 0)),
                    'admission_ids' => $this->normalizeSubscriptionIds($data['admission_ids'] ?? []),
                    'statement_ids' => $this->normalizeSubscriptionIds($data['statement_ids'] ?? []),
                ];
            }
            return;
        }

        // Broadcasts are accepted only from the loopback TCP relay below.
    }

    public function broadcast(array $data): void
    {
        $eventName = trim((string)($data['event'] ?? ''));
        if ($eventName === '' || strlen($eventName) > 100) {
            return;
        }

        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $frame = json_encode([
            'type'       => 'event',
            'event'      => $eventName,
            'payload'    => $payload,
            'roles'      => is_array($data['roles'] ?? null) ? array_values($data['roles']) : [],
            'user_ids'   => is_array($data['user_ids'] ?? null) ? array_values($data['user_ids']) : [],
            'department' => isset($data['department_id']) ? (int)$data['department_id'] : null,
            'timestamp'  => time(),
        ]);

        if ($frame === false) {
            echo "[WS ERROR] Failed to encode broadcast JSON\n";
            return;
        }

        $sent = 0;
        $failed = 0;
        $failedClients = [];

        foreach ($this->clients as $client) {
            try {
                if ($this->patientPortalClients->contains($client)) {
                    if ($this->patientPortalSubscriptionMatches(
                        $this->patientPortalClients[$client],
                        is_array($data['payload'] ?? null) ? $data['payload'] : []
                    )) {
                        $client->send('{"type":"portal.refresh"}');
                        $sent++;
                    }
                    continue;
                }

                $client->send($frame);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $failedClients[] = $client;
            }
        }

        foreach ($failedClients as $client) {
            if ($this->clients->contains($client)) {
                $this->clients->detach($client);
            }
            if ($this->patientPortalClients->contains($client)) {
                $this->patientPortalClients->detach($client);
            }
            $client->close();
        }

        echo sprintf(
            "[WS BROADCAST] Event=%s | Sent=%d | Failed=%d\n",
            $eventName,
            $sent,
            $failed
        );

    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
        if ($this->patientPortalClients->contains($conn)) {
            $this->patientPortalClients->detach($conn);
        }

        echo sprintf(
            "[%s] [WS CLOSE] ID=%s | Remaining clients=%d\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $this->clients->count()
        );
    }

    public function onError(
        ConnectionInterface $conn,
        \Exception $e
    ): void {
        echo sprintf(
            "[%s] [WS ERROR] ID=%s | %s\n",
            date('Y-m-d H:i:s'),
            $conn->resourceId,
            $e->getMessage()
        );

        $conn->close();
    }

    private function normalizeSubscriptionIds($ids): array
    {
        if (!is_array($ids)) return [];

        $normalized = [];
        foreach (array_slice($ids, 0, 50) as $id) {
            $id = (int)$id;
            if ($id > 0) $normalized[$id] = $id;
        }
        return array_values($normalized);
    }

    private function patientPortalSubscriptionMatches(array $subscription, array $payload): bool
    {
        $patientId = (int)($payload['patient_id'] ?? 0);
        if ($patientId > 0 && $patientId === (int)$subscription['patient_id']) {
            return true;
        }

        $admissionId = (int)($payload['admission_id'] ?? 0);
        if ($admissionId > 0 && in_array($admissionId, $subscription['admission_ids'], true)) {
            return true;
        }

        $statementId = (int)($payload['statement_id'] ?? 0);
        return $statementId > 0 && in_array($statementId, $subscription['statement_ids'], true);
    }
}

/*
|--------------------------------------------------------------------------
| WebSocket Server
|--------------------------------------------------------------------------
*/

$notifier = new BillingNotifier();
$webSocketApp = new WsServer($notifier);

$webSocketHttp = new HttpServer(
    $webSocketApp
);

$server = IoServer::factory(
    $webSocketHttp,
    WEBSOCKET_PORT,
    '0.0.0.0'
);

/*
|--------------------------------------------------------------------------
| Loopback-only PHP broadcast relay
|--------------------------------------------------------------------------
*/

$broadcastRelay = new SocketServer('127.0.0.1:' . WEBSOCKET_BROADCAST_PORT);
$broadcastRelay->on(
    'connection',
    static function (SocketConnectionInterface $connection) use ($notifier): void {
        $buffer = '';
        $connection->on(
            'data',
            static function (string $chunk) use ($connection, &$buffer, $notifier): void {
                $buffer .= $chunk;
                if (strlen($buffer) > 65536) {
                    $connection->end();
                    return;
                }

                $lineEnd = strpos($buffer, "\n");
                if ($lineEnd === false) {
                    return;
                }

                $request = json_decode(substr($buffer, 0, $lineEnd), true);
                if (is_array($request) && ($request['action'] ?? '') === 'broadcast') {
                    $notifier->broadcast($request);
                }
                $connection->end();
            }
        );
    }
);
$broadcastRelay->on('error', static function (\Throwable $error): void {
    error_log('[websocket_broadcast_relay] ' . $error->getMessage());
});

/*
|--------------------------------------------------------------------------
| Start Servers
|--------------------------------------------------------------------------
*/

echo "============================================\n";
echo " Billing Notification WebSocket Server\n";
echo "============================================\n";
echo "WebSocket:  ws://0.0.0.0:" . WEBSOCKET_PORT . "\n";
echo "PHP relay:  127.0.0.1:" . WEBSOCKET_BROADCAST_PORT . "\n";
echo "Started:    " . date('Y-m-d H:i:s') . "\n";
echo "Waiting for connections...\n";
echo "============================================\n";

$server->run();