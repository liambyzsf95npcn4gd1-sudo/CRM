<?php
// auth.php
// Отвечает за логику аутентификации и управление сессиями.

// Запускаем сессию, если она еще не запущена
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверяет, авторизован ли пользователь.
 * Если нет, перенаправляет на страницу входа (login.php).
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Проверяет, имеет ли текущий пользователь роль 'admin'.
 * Если нет, прерывает выполнение и выводит сообщение об ошибке (403).
 */
function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("Доступ запрещен. Требуются права администратора.");
    }
}

/**
 * Возвращает ID текущего авторизованного пользователя.
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Возвращает роль текущего авторизованного пользователя.
 */
function current_user_role() {
    return $_SESSION['role'] ?? null;
}
?>
