<?php
header('Content-Type: application/json');

// Настройки
$uploadDir = 'uploads/audio/';
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/x-m4a'];

// Создаем директорию, если не существует
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Проверяем, что файл был отправлен
if (!isset($_FILES['audioFile'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был отправлен']);
    exit;
}

$file = $_FILES['audioFile'];

// Проверяем ошибки загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Размер файла превышает разрешенный',
        UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает указанный в форме',
        UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку файла'
    ];

    $error = $errorMessages[$file['error']] ?? 'Неизвестная ошибка загрузки';
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// Проверяем размер файла
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'Размер файла превышает допустимый лимит 10MB']);
    exit;
}

// Проверяем тип файла
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Разрешены только аудиофайлы']);
    exit;
}

// Генерируем уникальное имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = uniqid() . '_' . time() . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Перемещаем файл в целевую директорию
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // Формируем URL для доступа к файлу
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);

    // Убедимся, что путь заканчивается на /
    if (substr($baseUrl, -1) !== '/') {
        $baseUrl .= '/';
    }

    $fileUrl = $baseUrl . $filePath;

    echo json_encode(['success' => true, 'fileUrl' => $fileUrl, 'fileName' => $fileName]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
}
?>