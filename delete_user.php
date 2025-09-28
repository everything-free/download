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

function saveUsers($file, $users) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$users = loadUsers($usersFile);
$id = $_POST['id'] ?? null;

if ($id && isset($users[$id - 1])) {
    // Нельзя удалить самого себя
    if ($users[$id - 1]['username'] === $_SESSION['username']) {
        echo json_encode(['success' => false, 'message' => 'Нельзя удалить собственный аккаунт']);
        exit;
    }

    array_splice($users, $id - 1, 1);
    saveUsers($usersFile, $users);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
}
?>