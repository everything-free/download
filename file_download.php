<?php
// file_download.php
$root_dir = __DIR__ . '/files';
$relative_path = isset($_GET['path']) ? $_GET['path'] : '';
$file_path = realpath($root_dir . '/' . $relative_path);
$base_path = realpath($root_dir);

if ($file_path === false || strpos($file_path, $base_path) !== 0 || !is_file($file_path)) {
    die("Файл не найден или недопустимый путь!");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
