<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin lead') {
    header('HTTP/1.1 403 Forbidden');
    exit('Доступ запрещен');
}

$usersFile = 'users.json';

function loadUsers($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

$users = loadUsers($usersFile);

// Добавляем ID для каждого пользователя
$usersWithId = [];
foreach ($users as $index => $user) {
    $user['id'] = $index + 1;
    $usersWithId[] = $user;
}

header('Content-Type: application/json');
echo json_encode($usersWithId);
?>