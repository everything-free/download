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

// Обработка AJAX-запросов для стримов
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!$client_id) {
        echo json_encode(['status' => 'error', 'message' => 'Клиент не выбран!']);
        exit;
    }

    $ajax_action = $_POST['ajax_action'];

    if ($ajax_action == 'toggle_stream') {
        // Включение/выключение стрима
        $status = $_POST['status'] ?? '0';
        $command = $status ? 'streamon' : 'streamoff';

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
            echo json_encode(['status' => 'error', 'message' => 'Ошибка cURL: ' . curl_error($ch)]);
        } else {
            $responseData = json_decode($response, true);
            if ($responseData && $responseData['status'] == 'ok') {
                // Также обновляем статус стрима в данных
                if (isset($data['clients'][$client_id])) {
                    $data['clients'][$client_id]['stream_enabled'] = (bool)$status;
                    file_put_contents($dataFile, json_encode($data));
                }
                echo json_encode(['status' => 'success', 'message' => 'Команда отправлена клиенту']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ошибка отправки команды']);
            }
        }
        curl_close($ch);
        exit;
    }
}

// В начале файла после session_start()
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление стримами</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #3d3d3d;
            --accent: #576aed;
            --accent-hover: #4758d4;
            --text-primary: #e6e4e1;
            --text-secondary: #888;
            --text-muted: #64748b;
            --success: #4CAF50;
            --error: #f44336;
            --warning: #f59e0b;
            --border: #121212;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Styles */
        .app-header {
            padding: 1.1rem;
            background: #1e1e1e;
            border-bottom: 1px solid #121212;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            color: var(--accent);
            background: rgba(87,106,237,0.1);
        }

        .app-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.8rem;
            color: var(--text-primary);
        }

        .app-title i {
            color: var(--accent);
        }

        /* Main Container */
        .app-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar Styles */
        .clients-panel {
            width: 250px;
            background: #121212;
            border-right: 1px solid #121212;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .search-box {
            position: relative;
            margin-bottom: 0.5rem;
        }

        .search-box input {
            width: 100%;
            padding: 0.6rem 0.75rem 0.6rem 2.5rem;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .clients-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
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
            border-color: var(--accent);
        }

        .client-card.active {
            background: #1e1e1e;
            color: white;
            border-color: var(--accent);
        }

        .client-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .client-name {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .status-online {
            background-color: var(--success);
        }

        .status-offline {
            background-color: var(--error);
        }

        .stream-badge {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            background-color: Green;
            color: white;
        }

        .client-details {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .client-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg-primary);
        }

        /* Toolbar Styles */
        .stream-toolbar {
            padding: 0.8rem 1rem;
            background: #121212;
            border-bottom: 1px solid #121212;
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

        .quality-select {
            padding: 0.5rem;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
        }

        /* Stream Content */
        .stream-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-color: #000;
            position: relative;
            overflow: hidden;
        }

        .stream-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            color: var(--text-muted);
            text-align: center;
            max-width: 400px;
        }

        .stream-placeholder i {
            font-size: 3rem;
            color: var(--text-muted);
        }

        .stream-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stream-frame {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }

        .stream-info {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background-color: rgba(0, 0, 0, 0.7);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .stream-controls {
            position: absolute;
            bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }

        .control-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .control-btn:hover {
            background-color: var(--accent);
            transform: scale(1.1);
        }

        /* Toast Styles */
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

        /* Fullscreen Mode */
        .fullscreen-mode {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: black;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fullscreen-mode .stream-frame {
            max-width: 100vw;
            max-height: 100vh;
            width: auto;
            height: auto;
        }

        .fullscreen-controls {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }

        .exit-fullscreen {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10000;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #121212;
        }

        ::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .clients-panel {
                width: 220px;
            }
        }

        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }

            .clients-panel {
                width: 100%;
                height: 250px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .stream-toolbar {
                flex-wrap: wrap;
            }
        }

    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-content">
            <a href="/" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Назад
            </a>

        </div>
    </header>

    <div class="app-container">
        <aside class="clients-panel">
            <div class="panel-header">
                <h2 class="panel-title">Подключенные клиенты</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Поиск клиентов..." id="clientSearch">
                </div>
            </div>
            <div class="clients-list" id="clientsList">
                <?php foreach($data['clients'] as $id => $client):
                    $isOnline = (time() - $client['last_seen']) < 300;
                    $isActive = $id === $client_id;
                    $hasStream = isset($client['stream_enabled']) && $client['stream_enabled'];
                ?>
                <div class="client-card <?= $isActive ? 'active' : '' ?>" data-client-id="<?= htmlspecialchars($id) ?>">
                    <div class="client-header">
                        <div class="client-name">
                            <span class="status-indicator <?= $isOnline ? 'status-online' : 'status-offline' ?>"></span>
                            <?= htmlspecialchars($id) ?>
                        </div>
                        <?php if ($hasStream): ?>
                        <div class="stream-badge">
                            <i class="fas fa-video"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="client-details">
                        <div class="client-detail">
                            <i class="fas fa-network-wired"></i>
                            <span><?= $client['ip'] ?></span>
                        </div>
                        <div class="client-detail">
                            <i class="fas fa-clock"></i>
                            <span><?= date('d.m.Y H:i', $client['last_seen']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="main-content">
            <?php if ($client_id && isset($data['clients'][$client_id])):
                $client = $data['clients'][$client_id];
                $stream_enabled = isset($client['stream_enabled']) ? $client['stream_enabled'] : false;
                $isOnline = (time() - $client['last_seen']) < 300;
            ?>
            <div class="stream-toolbar">
                <button class="toolbar-btn" id="toggleStreamBtn">
                    <i class="fas fa-video"></i>
                    <?= $stream_enabled ? 'Выключить стрим' : 'Включить стрим' ?>
                </button>
                <button class="toolbar-btn" id="refreshStreamBtn">
                    <i class="fas fa-sync"></i>
                    Обновить
                </button>
                <select class="quality-select" id="qualitySelector">
                    <option value="low">Низкое качество</option>
                    <option value="medium" selected>Среднее качество</option>
                    <option value="high">Высокое качество</option>
                </select>
            </div>

            <div class="stream-content">
                <?php if ($stream_enabled && $isOnline): ?>
                <div class="stream-wrapper" id="streamWrapper">
                    <img class="stream-frame" id="streamFrame"
                         src="streams/<?= urlencode($client_id) ?>.jpg?t=<?= time() ?>"
                         alt="Live stream">
                    <div class="stream-info">
                        <div>Клиент: <?= htmlspecialchars($client_id) ?></div>
                        <div>IP: <?= $client['ip'] ?></div>
                        <div>Последняя активность: <?= date('d.m.Y H:i', $client['last_seen']) ?></div>
                    </div>
                    <div class="stream-controls">
                        <button class="control-btn" id="screenshotBtn" title="Сделать снимок">
                            <i class="fas fa-camera"></i>
                        </button>
                        <button class="control-btn" id="fullscreenBtn" title="Полный экран">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="stream-placeholder">
                    <i class="fas fa-video-slash"></i>
                    <h3><?= $isOnline ? 'Стрим отключен' : 'Клиент offline' ?></h3>
                    <p><?= $isOnline ? 'Нажмите "Включить стрим" для активации' : 'Клиент неактивен более 5 минут' ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="stream-content">
                <div class="stream-placeholder">
                    <i class="fas fa-desktop"></i>
                    <h3>Выберите клиента для управления стримом</h3>
                    <p>Клиенты отображаются в левой панели</p>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="toast-container"></div>

    <script>
        let streamInterval = null;
        let currentClientId = '<?= $client_id ?>';
        let streamEnabled = <?= $stream_enabled ? 'true' : 'false' ?>;
        let currentQuality = 'medium';
        let isFullscreen = false;

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Поиск клиентов
            const clientSearch = document.getElementById('clientSearch');
            if (clientSearch) {
                clientSearch.addEventListener('input', filterClients);
            }

            // Обработчики кнопок
            const toggleStreamBtn = document.getElementById('toggleStreamBtn');
            if (toggleStreamBtn) {
                toggleStreamBtn.addEventListener('click', toggleStream);
            }

            const refreshStreamBtn = document.getElementById('refreshStreamBtn');
            if (refreshStreamBtn) {
                refreshStreamBtn.addEventListener('click', refreshStream);
            }

            const qualitySelector = document.getElementById('qualitySelector');
            if (qualitySelector) {
                qualitySelector.addEventListener('change', changeQuality);
            }

            const screenshotBtn = document.getElementById('screenshotBtn');
            if (screenshotBtn) {
                screenshotBtn.addEventListener('click', takeScreenshot);
            }

            const fullscreenBtn = document.getElementById('fullscreenBtn');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', toggleFullscreen);
            }

            // Запускаем обновление стрима, если он активен
            if (streamEnabled) {
                startStream();
            }
        });

        function filterClients() {
            const searchTerm = this.value.toLowerCase();
            const clientCards = document.querySelectorAll('.client-card');

            clientCards.forEach(card => {
                const clientId = card.getAttribute('data-client-id').toLowerCase();
                if (clientId.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function selectClient(clientId) {
            window.location.href = '?client_id=' + encodeURIComponent(clientId);
        }

        function toggleStream() {
    const newStatus = !streamEnabled;
    const command = newStatus ? 'streamon' : 'streamoff';

    const formData = new FormData();
    formData.append('action', 'set_command');
    formData.append('client_id', currentClientId);
    formData.append('command', command);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showToast('success', newStatus ? 'Команда streamon отправлена' : 'Команда streamoff отправлена');

            // Также обновляем статус стрима в данных
            const statusFormData = new FormData();
            statusFormData.append('action', 'set_stream_status');
            statusFormData.append('client_id', currentClientId);
            statusFormData.append('status', newStatus ? '1' : '0');

            fetch('api.php', {
                method: 'POST',
                body: statusFormData
            })
            .then(response => response.json())
            .then(statusData => {
                if (statusData.status === 'ok') {
                    // Обновляем страницу через секунду
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('error', 'Ошибка обновления статуса стрима');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Ошибка сети при обновлении статуса');
            });
        } else {
            showToast('error', data.message || 'Ошибка отправки команды');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Ошибка сети');
    });
}

        function changeQuality() {
            const selector = document.getElementById('qualitySelector');
            currentQuality = selector.value;
            if (streamEnabled) {
                refreshStream();
            }
        }

        function takeScreenshot() {
            if (!streamEnabled) {
                showToast('info', 'Стрим не активен');
                return;
            }

            // Создаем временную ссылку для скачивания скриншота
            const streamFrame = document.getElementById('streamFrame');
            if (streamFrame) {
                const link = document.createElement('a');
                link.href = streamFrame.src;
                link.download = `screenshot_${currentClientId}_${Date.now()}.jpg`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showToast('success', 'Снимок сохранен');
            }
        }

        function toggleFullscreen() {
            if (isFullscreen) {
                exitFullscreen();
            } else {
                enterFullscreen();
            }
        }

        function enterFullscreen() {
            const streamWrapper = document.getElementById('streamWrapper');
            if (streamWrapper) {
                const fullscreenContainer = document.createElement('div');
                fullscreenContainer.className = 'fullscreen-mode';
                fullscreenContainer.id = 'fullscreenContainer';

                const clone = streamWrapper.cloneNode(true);
                fullscreenContainer.appendChild(clone);

                const exitBtn = document.createElement('button');
                exitBtn.className = 'exit-fullscreen';
                exitBtn.innerHTML = '<i class="fas fa-times"></i>';
                exitBtn.addEventListener('click', exitFullscreen);
                fullscreenContainer.appendChild(exitBtn);

                // Обновляем кнопку полноэкранного режима в полноэкранном контейнере
                const fullscreenBtn = clone.querySelector('#fullscreenBtn');
                if (fullscreenBtn) {
                    fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    fullscreenBtn.setAttribute('title', 'Выйти из полноэкранного режима');
                    fullscreenBtn.addEventListener('click', exitFullscreen);
                }

                document.body.appendChild(fullscreenContainer);
                isFullscreen = true;

                // Обновляем изображение в полноэкранном режиме
                const fullscreenImg = fullscreenContainer.querySelector('.stream-frame');
                if (fullscreenImg) {
                    fullscreenImg.src = `streams/${currentClientId}.jpg?t=${Date.now()}`;
                }

                // Обновляем основную кнопку полноэкранного режима
                if (fullscreenBtn) {
                    fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    fullscreenBtn.setAttribute('title', 'Выйти из полноэкранного режима');
                }
            }
        }

        function exitFullscreen() {
            const fullscreenContainer = document.getElementById('fullscreenContainer');
            if (fullscreenContainer) {
                document.body.removeChild(fullscreenContainer);
                isFullscreen = false;

                // Восстанавливаем основную кнопку полноэкранного режима
                const fullscreenBtn = document.getElementById('fullscreenBtn');
                if (fullscreenBtn) {
                    fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                    fullscreenBtn.setAttribute('title', 'Полный экран');
                }
            }
        }

        function refreshStream() {
            if (streamEnabled) {
                updateStream();
                showToast('success', 'Стрим обновлен');
            } else {
                showToast('info', 'Стрим не активен');
            }
        }

        function startStream() {
            // Частое обновление для плавного стрима
            streamInterval = setInterval(updateStream, 200);
        }

        function updateStream() {
            const streamFrame = document.getElementById('streamFrame');
            if (streamFrame) {
                // Создаем новое изображение для предзагрузки
                const newImage = new Image();
                newImage.onload = function() {
                    // Когда изображение загружено, заменяем текущее
                    streamFrame.src = this.src;

                    // Также обновляем изображение в полноэкранном режиме, если активно
                    if (isFullscreen) {
                        const fullscreenImg = document.querySelector('#fullscreenContainer .stream-frame');
                        if (fullscreenImg) {
                            fullscreenImg.src = this.src;
                        }
                    }
                };
                newImage.onerror = function() {
                    console.error('Ошибка загрузки стрима');
                };
                // Загружаем новое изображение
                newImage.src = `streams/${currentClientId}.jpg?t=${Date.now()}&quality=${currentQuality}`;
            }
        }

        // Функция для показа уведомлений
        function showToast(type, message) {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';

            toast.innerHTML = `
                <i class="fas ${icon}"></i>
                <div class="toast-content">${message}</div>

            `;

            toastContainer.appendChild(toast);

            // Удаляем toast после 3 секунд
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        // Показываем существующие уведомления при загрузке страницы
        <?php if ($toast): ?>
        showToast('<?= $toast['type'] ?>', '<?= $toast['message'] ?>');
        <?php endif; ?>

        // Делегирование событий для кликов по карточкам клиентов
        document.getElementById('clientsList').addEventListener('click', function(e) {
            const clientCard = e.target.closest('.client-card');
            if (clientCard) {
                const clientId = clientCard.getAttribute('data-client-id');
                selectClient(clientId);
            }
        });
    </script>
</body>
</html>
