<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID пользователя.");
}

$user_id = $_GET['id'];
$current_user_id = current_user_id();
$current_role = current_user_role();

// Check permissions
// Admin can edit anyone.
// Employee can ONLY edit themselves.
if ($current_role !== 'admin' && $user_id != $current_user_id) {
    die("У вас нет прав на редактирование этого профиля.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);

    // Only admin can change role and login
    $role = isset($_POST['role']) ? $_POST['role'] : null;
    $login = isset($_POST['login']) ? trim($_POST['login']) : null;

    // Password change
    $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : null;

    if (empty($first_name) || empty($last_name)) {
        $error = "Имя и Фамилия обязательны.";
    } else {
        try {
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?";
            $params = [$first_name, $last_name, $email, $phone, $position];

            if ($current_role === 'admin') {
                $sql .= ", role = ?, login = ?";
                $params[] = $role;
                $params[] = $login;
            }

            if ($new_password) {
                $sql .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Update session if editing self
            if ($user_id == $current_user_id) {
                $_SESSION['name'] = $first_name . ' ' . $last_name;
                if ($current_role === 'admin') {
                     $_SESSION['role'] = $role;
                }
            }

            $success = "Данные успешно обновлены.";
            // Refresh data
        } catch (PDOException $e) {
             if ($e->getCode() == 23000) {
                $error = "Пользователь с таким логином уже существует.";
            } else {
                $error = "Ошибка БД: " . $e->getMessage();
            }
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Пользователь не найден.");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование профиля</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <?php if ($current_role === 'admin'): ?>
                <a href="projects_list.php">Проекты</a>
            <?php endif; ?>
            <a href="tasks_list.php">Задачи</a>
            <?php if ($current_role === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <h1>Редактирование профиля</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert" style="background-color: #d4edda; color: #155724;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
            <form method="POST">

                <?php if ($current_role === 'admin'): ?>
                    <label>Логин *</label>
                    <input type="text" name="login" required value="<?= htmlspecialchars($user['login']) ?>">

                    <label>Роль *</label>
                    <select name="role">
                        <option value="employee" <?= $user['role'] == 'employee' ? 'selected' : '' ?>>Сотрудник</option>
                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Администратор</option>
                    </select>
                <?php else: ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Логин:</strong> <?= htmlspecialchars($user['login']) ?><br>
                        <strong>Роль:</strong> <?= $user['role'] == 'admin' ? 'Администратор' : 'Сотрудник' ?>
                    </div>
                <?php endif; ?>

                <label>Имя *</label>
                <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">

                <label>Фамилия *</label>
                <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">

                <label>Должность</label>
                <input type="text" name="position" value="<?= htmlspecialchars($user['position']) ?>">

                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">

                <label>Телефон</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">

                <hr style="margin: 20px 0;">
                <h3>Смена пароля</h3>
                <label>Новый пароль (оставьте пустым, если не хотите менять)</label>
                <input type="text" name="new_password" placeholder="Новый пароль">

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    <?php if ($current_role === 'admin'): ?>
                        <a href="users.php" class="btn btn-warning" style="background-color: #eee; color: #333; border: 1px solid #ccc;">Отмена</a>
                    <?php else: ?>
                         <a href="tasks_list.php" class="btn btn-warning" style="background-color: #eee; color: #333; border: 1px solid #ccc;">Назад</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
