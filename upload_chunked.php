<?php
// Увеличиваем лимиты для больших файлов
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
ini_set('max_execution_time', 600);
ini_set('max_input_time', 600);
ini_set('memory_limit', '256M');
ini_set('max_input_vars', 3000);

// Для диагностики
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Абсолютный путь к папке загрузки
$uploadDir = __DIR__ . '/update/';
$chunksDir = __DIR__ . '/chunks/';

// Создаем папки с проверкой ошибок
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $error = error_get_last();
        echo json_encode([
            'success' => false,
            'message' => 'Не удалось создать папку: ' . ($error['message'] ?? 'Unknown error')
        ]);
        exit;
    }
}

if (!file_exists($chunksDir)) {
    if (!mkdir($chunksDir, 0755, true) && !is_dir($chunksDir)) {
        $error = error_get_last();
        echo json_encode([
            'success' => false,
            'message' => 'Не удалось создать папку для частей: ' . ($error['message'] ?? 'Unknown error')
        ]);
        exit;
    }
}

// Очистка старых chunk-файлов (старше 1 дня)
$files = glob($chunksDir . '*');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 86400) {
        unlink($file);
    }
}

// Получаем параметры загрузки
$chunkIndex = isset($_POST['chunkIndex']) ? (int)$_POST['chunkIndex'] : 0;
$totalChunks = isset($_POST['totalChunks']) ? (int)$_POST['totalChunks'] : 1;
$fileName = isset($_POST['fileName']) ? $_POST['fileName'] : '';
$fileId = isset($_POST['fileId']) ? $_POST['fileId'] : '';

// Генерируем fileId если не передан
if (empty($fileId)) {
    $fileId = uniqid();
}

// Проверяем отправку файла
if (!isset($_FILES['file'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Файл не был отправлен'
    ]);
    exit;
}

// Обработка файла
$file = $_FILES['file'];

// Проверяем ошибки загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Размер файла превышает разрешенный лимит на сервере',
        UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает указанный в форме лимит',
        UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку файла'
    ];

    $message = $errorMessages[$file['error']] ?? 'Неизвестная ошибка загрузки: ' . $file['error'];

    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Проверяем расширение файла
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
if (strtolower($extension) !== 'exe') {
    echo json_encode(['success' => false, 'message' => 'Разрешены только .exe файлы']);
    exit;
}

// Сохраняем chunk
$chunkName = $chunksDir . $fileId . '_' . $chunkIndex;
if (!move_uploaded_file($file['tmp_name'], $chunkName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Не удалось сохранить часть файла'
    ]);
    exit;
}

// Если это последний chunk, объединяем все части
if ($chunkIndex === $totalChunks - 1) {
    // Генерируем уникальное имя файла
    $finalFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $filePath = $uploadDir . $finalFileName;

    // Открываем конечный файл для записи
    $finalFile = fopen($filePath, 'wb');
    if (!$finalFile) {
        echo json_encode([
            'success' => false,
            'message' => 'Не удалось создать конечный файл'
        ]);
        exit;
    }

    // Объединяем все chunks
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkFile = $chunksDir . $fileId . '_' . $i;

        if (!file_exists($chunkFile)) {
            fclose($finalFile);
            unlink($filePath);
            echo json_encode([
                'success' => false,
                'message' => 'Отсутствует часть файла: ' . $i
            ]);
            exit;
        }

        $chunkContent = file_get_contents($chunkFile);
        fwrite($finalFile, $chunkContent);
        unlink($chunkFile); // Удаляем chunk после использования
    }

    fclose($finalFile);

    // Устанавливаем права на файл
    chmod($filePath, 0644);

    // Возвращаем успешный ответ
    $fileUrl = '/update/' . $finalFileName;
    echo json_encode([
        'success' => true,
        'fileUrl' => $fileUrl,
        'message' => 'Файл успешно загружен',
        'fileSize' => filesize($filePath),
        'completed' => true
    ]);
} else {
    // Возвращаем ответ о успешной загрузке chunk
    echo json_encode([
        'success' => true,
        'message' => 'Часть файла успешно загружена',
        'chunkIndex' => $chunkIndex,
        'fileId' => $fileId,
        'completed' => false
    ]);
}
?>