<?php
// audio_api.php
header('Content-Type: application/json');

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $clientId = isset($_POST['client_id']) ? $_POST['client_id'] : '';

    if ($action === 'get_audio_files' && $clientId) {
        $audioDir = 'audio_' . $clientId . '/';
        $files = [];

        if (is_dir($audioDir)) {
            foreach (scandir($audioDir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $audioDir . $file;

                    // Проверяем, что файл полностью записан (ждем 2 секунды после последнего изменения)
                    if (time() - filemtime($filePath) < 2) {
                        continue; // Пропускаем файлы, которые все еще записываются
                    }

                    $fileSize = filesize($filePath);

                    // Пропускаем файлы с нулевым размером
                    if ($fileSize === 0) {
                        continue;
                    }

                    // Извлекаем информацию из имени файла
                    $fileNameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                    $parts = explode('_', $fileNameWithoutExt, 2);

                    $timestamp = isset($parts[0]) ? floatval($parts[0]) : filemtime($filePath);
                    $device = isset($parts[1]) ? $parts[1] : 'Unknown';

                    $files[] = [
                        'filename' => $filePath,
                        'timestamp' => $timestamp,
                        'device' => $device,
                        'size' => $fileSize
                    ];
                }
            }
        }

        echo json_encode($files);
        exit;
    } elseif ($action === 'clear_audio_files' && $clientId) {
        $audioDir = 'audio_' . $clientId . '/';
        if (is_dir($audioDir)) {
            foreach (scandir($audioDir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($audioDir . $file);
                }
            }
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Directory not found']);
        }
        exit;
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'get_audio_file') {
    $clientId = isset($_GET['client_id']) ? $_GET['client_id'] : '';
    $filename = isset($_GET['filename']) ? $_GET['filename'] : '';

    if ($clientId && $filename) {
        // Проверяем, что файл находится в правильной директории
        if (strpos($filename, 'audio_' . $clientId . '/') === 0 && file_exists($filename)) {
            $fileSize = filesize($filename);

            if ($fileSize > 0) {
                header('Content-Type: audio/wav');
                header('Content-Length: ' . $fileSize);
                readfile($filename);
                exit;
            }
        }
    }

    // Если файл не найден или имеет нулевой размер
    header("HTTP/1.0 404 Not Found");
    exit;
}

echo json_encode([]);