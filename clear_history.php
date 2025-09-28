<?php
$historyFile = 'output_history.json';

header('Content-Type: application/json');

try {
    if(file_exists($historyFile)) {
        if(!unlink($historyFile)) {
            throw new Exception('Не удалось удалить файл истории');
        }
    }

    // Создаем пустой файл
    file_put_contents($historyFile, json_encode([]));

    echo json_encode(['status' => 'success']);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>