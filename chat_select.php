<?php
if (isset($_GET['client_id'])) {
    $clientId = $_GET['client_id'];
    $chatFile = "chats/{$clientId}.json"; // Путь к файлу с сообщениями

    // Проверка существует ли файл
    if (!file_exists($chatFile)) {
        // Если файла нет, создаем его с пустым массивом сообщений
        file_put_contents($chatFile, json_encode(['messages' => []]));
    }

    // Загружаем сообщения из файла
    $chatData = json_decode(file_get_contents($chatFile), true);
    echo json_encode($chatData);
}
?>
