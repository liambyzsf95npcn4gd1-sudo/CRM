<?php
require_once 'db.php';
require_once 'auth.php';

// Доступ только для администраторов
require_admin();

$error = '';
$success = '';

// Обработка создания нового пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    $phone = trim($_POST['phone']);

    // Проверка на заполнение обязательных полей
    if (empty($login) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "Все обязательные поля должны быть заполнены.";
    } else {
        try {
            // Хеширование пароля перед сохранением
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (login, password, first_name, last_name, role, email, position, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$login, $passHash, $first_name, $last_name, $role, $email, $position, $phone]);
            $success = "Пользователь успешно создан.";
        } catch (PDOException $e) {
            // Обработка ошибки дубликата логина
            if ($e->getCode() == 23000) {
                $error = "Пользователь с таким логином уже существует.";
            } else {
                $error = "Ошибка БД: " . $e->getMessage();
            }
        }
    }
}

// Получение списка всех пользователей
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="projects_list.php">Проекты</a>
            <a href="tasks_list.php">Задачи</a>
            <a href="users.php" style="text-decoration: underline;">Сотрудники</a>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <h1>Сотрудники</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert" style="background-color: #d4edda; color: #155724;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 30px;">
            <h3>Добавить сотрудника</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <label>Логин *</label>
                <input type="text" name="login" required>

                <label>Пароль *</label>
                <input type="text" name="password" required>

                <label>Имя *</label>
                <input type="text" name="first_name" required>

                <label>Фамилия *</label>
                <input type="text" name="last_name" required>

                <label>Роль *</label>
                <select name="role">
                    <option value="employee">Сотрудник</option>
                    <option value="admin">Администратор</option>
                </select>

                <label>Email</label>
                <input type="text" name="email">

                <label>Должность</label>
                <input type="text" name="position">

                <label>Телефон</label>
                <input type="text" name="phone">

                <button type="submit" class="btn btn-success">Создать</button>
            </form>
        </div>

        <h3>Список пользователей</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>ФИО</th>
                    <th>Роль</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['login']) ?></td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td>
                            <?php if ($u['role'] == 'admin'): ?>
                                <span style="color: red; font-weight: bold;">Админ</span>
                            <?php else: ?>
                                <span style="color: green;">Сотрудник</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="user_view.php?id=<?= $u['id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Просмотр</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
