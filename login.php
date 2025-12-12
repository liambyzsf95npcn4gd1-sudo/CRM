<?php
require_once 'db.php';
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];

    if (empty($login) || empty($password)) {
        $error = "Пожалуйста, заполните все поля.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Перенаправление на список задач
            header('Location: tasks_list.php');
            exit;
        } else {
            $error = "Неверный логин или пароль.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 100px;">
        <h2 style="text-align: center;">Вход в CRM</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Логин</label>
            <input type="text" name="login" required>

            <label>Пароль</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Войти</button>
        </form>
    </div>
</body>
</html>
