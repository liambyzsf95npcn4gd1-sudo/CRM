<?php
require_once 'auth.php';
// Уничтожаем сессию и перенаправляем на страницу входа
session_destroy();
header('Location: login.php');
exit;
?>
