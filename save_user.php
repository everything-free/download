<?php
header('Content-Type: application/json');

$usersFile = 'users.json';

// Функция для загрузки пользователей
function loadUsers($file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
        return [];
    }
    $data = file_get_contents($file);
    if (empty($data)) {
        return [];
    }
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

// Функция для сохранения пользователей
function saveUsers($file, $users) {
    if (!is_writable($file) && file_exists($file)) {
        echo json_encode(['success' => false, 'message' => 'Файл недоступен для записи']);
        exit;
    }

    $result = file_put_contents($file, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Ошибка записи в файл']);
        exit;
    }
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

$users = loadUsers($usersFile);
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'user');

// Валидация
if (empty($username) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'Логин и роль не могут быть пустыми']);
    exit;
}

if (!$id && empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Пароль не может быть пустым']);
    exit;
}

// ОЧЕНЬ ВАЖНО: Четкое разделение логики создания и редактирования
if ($id) {
    // РЕДАКТИРОВАНИЕ существующего пользователя
    $userFound = false;

    // Ищем пользователя по ID
    foreach ($users as $index => $user) {
        if ($user['id'] == $id) {
            $userFound = true;

            // Проверяем, не занят ли логин другим пользователем
            foreach ($users as $otherUser) {
                if ($otherUser['username'] === $username && $otherUser['id'] != $id) {
                    echo json_encode(['success' => false, 'message' => 'Пользователь с таким логином уже существует']);
                    exit;
                }
            }

            // Обновляем данные пользователя
            $users[$index]['username'] = $username;
            $users[$index]['role'] = $role;
            if (!empty($password)) {
                $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            break;
        }
    }

    if (!$userFound) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }
} else {
    // СОЗДАНИЕ нового пользователя
    // Проверяем, нет ли уже пользователя с таким логином
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            echo json_encode(['success' => false, 'message' => 'Пользователь с таким логином уже существует']);
            exit;
        }
    }

    // Генерируем новый ID
    $maxId = 0;
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] > $maxId) {
            $maxId = $user['id'];
        }
    }

    // Создаем нового пользователя
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $newUser = [
        'id' => $maxId + 1,
        'username' => $username,
        'password' => $hashedPassword,
        'role' => $role
    ];
    $users[] = $newUser;
}

saveUsers($usersFile, $users);
echo json_encode(['success' => true]);
?>