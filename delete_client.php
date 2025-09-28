<?php
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен']);
    exit;
}

// Получение ID клиента для удаления
$clientId = $_GET['id'] ?? '';
if (empty($clientId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID клиента не указан']);
    exit;
}

// Загрузка данных
$dataFile = 'data.json';
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true);
} else {
    $data = ['clients' => []];
}

// Удаление клиента
if (isset($data['clients'][$clientId])) {
    unset($data['clients'][$clientId]);

    // Сохранение обновленных данных
    if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при сохранении данных']);
    }
} else {
    http_response_code(404);
}
?>
