<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/websocket-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

echo json_encode(['success' => true, 'websocket_port' => WEBSOCKET_PORT]);
