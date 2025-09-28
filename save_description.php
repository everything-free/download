<?php
$data = json_decode(file_get_contents('php://input'), true);
$clientId = $data['clientId'];
$description = $data['description'];

// Загрузка существующих описаний
$descriptions = [];
if(file_exists('descriptions.json')) {
    $descriptions = json_decode(file_get_contents('descriptions.json'), true);
}

// Обновление описания
$descriptions[$clientId] = $description;

// Сохранение в файл
file_put_contents('descriptions.json', json_encode($descriptions));

header('Content-Type: application/json');
echo json_encode(['success' => true]);