<?php
// upload_endpoint.php

// Определяем папку для сохранения файлов
$uploadDir = __DIR__ . '/data/';

// Если папка не существует, пытаемся её создать
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        exit("Ошибка: не удалось создать папку для загрузки.");
    }
}

// Проверяем, был ли передан файл через POST
if (isset($_FILES['upload_file'])) {
    $file = $_FILES['upload_file'];

    // Проверяем наличие ошибок загрузки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit("Ошибка загрузки файла: " . $file['error']);
    }

    // Получаем оригинальное имя файла
    $fileName = basename($file['name']);

    // Формируем путь для сохранения
    $destination = $uploadDir . $fileName;

    // Пытаемся переместить временный файл в указанное место
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        echo "Файл успешно загружен в /data/" . $fileName;
    } else {
        http_response_code(500);
        exit("Ошибка: не удалось сохранить файл.");
    }
} else {
    http_response_code(400);
    exit("Ошибка: файл не передан.");
}
?>
