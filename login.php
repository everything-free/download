<?php
session_start();

$usersFile = 'users.json';
$error = '';

// Функция для загрузки пользователей
function loadUsers($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Функция для сохранения пользователей
function saveUsers($file, $users) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = loadUsers($usersFile);
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($users)) {
        // Режим регистрации первого пользователя (автоматически становится lead)
        if (!empty($username) && !empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userData = [
                'username' => $username,
                'password' => $hashedPassword,
                'role' => 'admin lead'
            ];
            $users[] = $userData;
            saveUsers($usersFile, $users);
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'admin lead';
            header("Location: index.php");
            exit;
        } else {
            $error = "Логин и пароль не могут быть пустыми!";
        }
    } else {
        // Режим авторизации
        $userFound = false;
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $user['role'];
                header("Location: index.php");
                exit;
            }
        }
        $error = "Неверный логин или пароль!";
    }
}

$isRegistration = !file_exists($usersFile) || empty(loadUsers($usersFile));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isRegistration ? 'Регистрация' : 'Авторизация' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #121212;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: #1a1a1a;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .header {
            background: #333;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #121212;
        }

        .header h2 {
            font-weight: 600;
            font-size: 24px;
        }

        .form-container {
            padding: 24px;
        }

        .notice {
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            background: #1A1A1A;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.5;
        }

        .role-info {
            margin-top: 15px;
            padding: 12px;
            background: #121212;
            border-radius: 6px;
            font-size: 13px;
        }

        .role-info h3 {
            margin-bottom: 8px;
            color: #a0a0a0;
            font-size: 14px;
        }

        .role-list {
            list-style-type: none;
        }

        .role-list li {
            padding: 4px 0;
            display: flex;
            align-items: center;
        }

        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 8px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-lead {
            background: #555;
            color: #ffcc00;
        }

        .badge-admin {
            background: #555;
            color: #ff6666;
        }

        .badge-user {
            background: #555;
            color: #66ccff;
        }

        .badge-master {
            background: #555;
            color: #8d38f5;
        }

        .badge-mluser {
            background: #555;
            color: #5cfa4d;
        }


        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display:
             block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #ccc;
        }

        .input-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #121212;
            border-radius: 6px;
            background: #121212;
            color: #e0e0e0;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #888;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            background: #121212;
            color: #e0e0e0;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn:hover {
            background: #666;
        }

        .error {
            color: #ff5555;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #3a2e2e;
            border-radius: 6px;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><?= $isRegistration ? 'Регистрация' : 'Авторизация' ?></h2>
        </div>

        <div class="form-container">
            <?php if ($isRegistration): ?>
                <div class="notice">
                    <p>Добро пожаловать! Вы первый пользователь системы и получите роль <strong>Lead</strong>.</p>
                </div>
            <?php else: ?>

            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" placeholder="Введите ваш логин" required>
                </div>

                <div class="input-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" placeholder="Введите ваш пароль" required>
                </div>

                <button type="submit" class="btn">
                    <?= $isRegistration ? 'Зарегистрироваться' : 'Войти' ?>
                </button>
            </form>

            <div class="role-info">
                <h3>Роли в системе:</h3>
                <ul class="role-list">
                    <li><span class="role-badge badge-lead">ADMIN LEAD</span> Полный доступ к системе</li>
                    <li><span class="role-badge badge-admin">ADMIN</span> Права администратора</li>
                    <li><span class="role-badge badge-user">Master</span> Расширеные права пользователя</li>
                    <li><span class="role-badge badge-user">Worker</span> Стандартные права пользователя</li>
                    <li><span class="role-badge badge-user">Ml. Worker</span> Минимальные права пользователя</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>