<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID сотрудника.");
}

$user_id = $_GET['id'];
$currentUserRole = current_user_role();
$currentUserId = current_user_id();

// Просмотр доступен админу или самому владельцу профиля
if ($currentUserRole !== 'admin' && $currentUserId != $user_id) {
    die("Доступ запрещен.");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Сотрудник не найден.");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль сотрудника: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="projects_list.php">Проекты</a>
            <a href="tasks_list.php">Задачи</a>
            <?php if ($currentUserRole === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <div style="margin-bottom: 20px;">
            <?php if ($currentUserRole === 'admin'): ?>
                <a href="users.php" class="btn btn-warning" style="background-color: #eee; color: #333; border: 1px solid #ccc;">&larr; Назад к списку</a>
            <?php else: ?>
                <a href="tasks_list.php" class="btn btn-warning" style="background-color: #eee; color: #333; border: 1px solid #ccc;">&larr; Назад</a>
            <?php endif; ?>
        </div>

        <h1>Профиль сотрудника</h1>

        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <table style="width: auto; border: none;">
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold; width: 150px;">ФИО:</td>
                    <td style="border: none;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['patronymic']) ?></td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Логин:</td>
                    <td style="border: none;"><?= htmlspecialchars($user['login']) ?></td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Роль:</td>
                    <td style="border: none;">
                        <?= $user['role'] === 'admin' ? '<span style="color: red;">Администратор</span>' : '<span style="color: green;">Сотрудник</span>' ?>
                    </td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Email:</td>
                    <td style="border: none;"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Должность:</td>
                    <td style="border: none;"><?= htmlspecialchars($user['position'] ?? '-') ?></td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Телефон:</td>
                    <td style="border: none;"><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
