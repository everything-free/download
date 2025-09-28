<?php
if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']);  // Извлекаем имя файла безопасно
    $filePath = 'data/' . $fileName;

    // Проверка существования файла
    if (file_exists($filePath)) {
        // Устанавливаем заголовки для скачивания
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Отправка содержимого файла
        readfile($filePath);
        exit;
    } else {
        echo 'Файл не найден.';
    }
} else {
    echo 'Не указан файл для скачивания.';
}
?>
