<?php
// db.php
// Конфигурация подключения к базе данных.
// По умолчанию скрипт пытается подключиться к MySQL.
// Если установлена переменная окружения 'TEST_ENV' со значением 'sqlite', используется SQLite для локального тестирования.

$host = 'localhost';
$dbname = 'crm_db';
$user = 'root';
$pass = '';

try {
    // Проверка среды локального тестирования
    if (getenv('TEST_ENV') === 'sqlite') {
        $pdo = new PDO('sqlite:' . __DIR__ . '/local_test.db');
    } else {
        // Стандартное подключение MySQL для продакшена
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
        $pdo = new PDO($dsn, $user, $pass);
    }

    // Установка режима ошибок на выбрасывание исключений
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Получение данных в виде ассоциативного массива по умолчанию
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

/**
 * Upload files and return array of paths.
 * Expects $_FILES['input_name'] as $filesArray.
 */
function upload_files($filesArray, $uploadDir = 'uploads/') {
    $uploadedPaths = [];
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileList = [];
    // Handle multiple files upload structure
    if (isset($filesArray['name']) && is_array($filesArray['name'])) {
        foreach ($filesArray['name'] as $idx => $name) {
            if ($filesArray['error'][$idx] === UPLOAD_ERR_OK) {
                $fileList[] = [
                    'name' => $name,
                    'tmp_name' => $filesArray['tmp_name'][$idx],
                    'error' => $filesArray['error'][$idx]
                ];
            }
        }
    } elseif (isset($filesArray['name']) && !is_array($filesArray['name'])) {
        // Single file
        if ($filesArray['error'] === UPLOAD_ERR_OK) {
            $fileList[] = $filesArray;
        }
    }

    foreach ($fileList as $file) {
        $info = pathinfo($file['name']);
        $ext = strtolower($info['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
        if (in_array($ext, $allowed)) {
            $filename = uniqid('file_') . '.' . $ext;
            $target = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $uploadedPaths[] = $uploadDir . $filename;
            }
        }
    }
    return $uploadedPaths;
}

/**
 * Delete a file from the server disk.
 */
function delete_file_from_disk($path) {
    if ($path && file_exists(__DIR__ . '/' . $path)) {
        unlink(__DIR__ . '/' . $path);
    }
}
?>
