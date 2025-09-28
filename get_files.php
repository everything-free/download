<?php
$filesDir = 'data/';
$screenshotsDir = 'screenshots/';
$files = [];

// Функция для добавления файлов из папки в массив
function addFilesFromDir($dir, &$files, $type = 'file') {
    if (is_dir($dir)) {
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $dir . $file;
                $files[] = [
                    'name' => $file,
                    'path' => $filePath,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                    'type' => mime_content_type($filePath),
                    'category' => $type
                ];
            }
        }
    }
}

// Добавляем файлы из обеих папок
addFilesFromDir($filesDir, $files, 'file');
addFilesFromDir($screenshotsDir, $files, 'screenshot');

// Сортировка по дате изменения (новые сверху)
usort($files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

if (empty($files)) {
    echo '<div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>Файлы не найдены</p>
          </div>';
} else {
    foreach ($files as $file) {
        $icon = getFileIcon($file['type']);
        $categoryClass = $file['category'] === 'screenshot' ? 'screenshot-file' : '';
        $preview = $file['category'] === 'screenshot' && strpos($file['type'], 'image/') === 0
            ? '<div class="file-preview-container">
                  <img src="' . $file['path'] . '" class="file-preview" onclick="openModal(\'' . $file['path'] . '\')">
               </div>'
            : '';

        echo '
        <div class="file-card ' . $categoryClass . '" data-name="' . htmlspecialchars($file['name']) . '"
             data-size="' . $file['size'] . '"
             data-modified="' . $file['modified'] . '">
            ' . $preview . '
            <div class="file-header">
                <span class="file-name">' . $icon . ' ' . htmlspecialchars($file['name']) . '</span>
                <div class="file-actions">
                    <button class="file-action-btn" onclick="deleteFile(\'' . htmlspecialchars($file['name']) . '\', \'' . $file['category'] . '\')" title="Удалить">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>

            <div class="file-details">
                <span>' . date("d.m.Y H:i", $file['modified']) . '</span>
                <span>' . formatSizeUnits($file['size']) . '</span>
            </div>

            <button class="download-btn" onclick="downloadFile(\'' . htmlspecialchars($file['name']) . '\', \'' . $file['category'] . '\')">
                <i class="fas fa-download"></i>
                Скачать
            </button>
        </div>';
    }
}

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getFileIcon($mimeType) {
    $icons = [
        'text' => '📄',
        'image' => '🖼️',
        'audio' => '🎵',
        'video' => '🎥',
        'application/zip' => '📦',
        'application/pdf' => '📑',
        'default' => '📁'
    ];

    if (strpos($mimeType, 'text/') === 0) return $icons['text'];
    if (strpos($mimeType, 'image/') === 0) return $icons['image'];
    if (strpos($mimeType, 'audio/') === 0) return $icons['audio'];
    if (strpos($mimeType, 'video/') === 0) return $icons['video'];
    return $icons[$mimeType] ?? $icons['default'];
}
?>