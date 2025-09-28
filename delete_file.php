<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'];
    $category = $_POST['category'];
    $filePath = ($category === 'screenshot' ? 'screenshots/' : 'data/') . $filename;

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode(['success' => true, 'message' => 'Файл успешно удален']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка при удалении файла']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Файл не найден']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Неподдерживаемый метод запроса']);
}
?>