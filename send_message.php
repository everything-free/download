<?php
session_start();
$dataFile = 'data.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $clientId = $input['client_id'] ?? '';
    $message = trim($input['message'] ?? '');
    $sender = $input['sender'] ?? 'Неизвестный';  // Получаем отправителя

    if (!$clientId || !$message) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid data']));
    }

    $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['chats' => []];

    if (!isset($data['chats'][$clientId])) {
        $data['chats'][$clientId] = [];
    }

    // Определяем тип отправителя (admin или client)
    $messageType = ($sender === 'Админ') ? 'admin' : 'client';

    $messageData = [
        'text' => htmlspecialchars($message),
        'timestamp' => time(),
        'type' => $messageType,
        'sender' => htmlspecialchars($sender)  // Сохраняем имя отправителя
    ];

    $data['chats'][$clientId][] = $messageData;
    file_put_contents($dataFile, json_encode($data));

    echo json_encode(['status' => 'success']);
}
?>
