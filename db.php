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
?>
