<?php
session_start();
$dataFile = 'data.json';

$clientId = $_GET['client_id'] ?? '';

if (!$clientId) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Client ID required']));
}

$data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['chats' => []];

$messages = $data['chats'][$clientId] ?? [];

echo json_encode(['status' => 'success', 'messages' => $messages]);
?>
