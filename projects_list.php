<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

$error = '';
$success = '';

// Обработка создания проекта (Только админ)
// Если пользователь - админ, он может создать новый проект.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (current_user_role() !== 'admin') {
        $error = "Только администратор может создавать проекты.";
    } else {
        $name = trim($_POST['name']);
        if (empty($name)) {
            $error = "Название проекта не может быть пустым.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
                $stmt->execute([$name]);
                $success = "Проект создан.";
            } catch (PDOException $e) {
                $error = "Ошибка БД: " . $e->getMessage();
            }
        }
    }
}

// Получение списка всех проектов
$stmt = $pdo->query("SELECT * FROM projects ORDER BY id DESC");
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список проектов</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="projects_list.php" style="text-decoration: underline;">Проекты</a>
            <a href="tasks_list.php">Задачи</a>
            <?php if (current_user_role() === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <h1>Проекты</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert" style="background-color: #d4edda; color: #155724;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (current_user_role() === 'admin'): ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 30px;">
            <h3>Создать новый проект</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <label>Название проекта</label>
                <input type="text" name="name" required placeholder="Например: Разработка CRM">
                <button type="submit" class="btn btn-success">Создать</button>
            </form>
        </div>
        <?php endif; ?>

        <h3>Все проекты</h3>
        <table>
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Название</th>
                    <th width="150">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($projects) > 0): ?>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td>
                                <a href="project_view.php?id=<?= $p['id'] ?>" style="font-weight: bold; font-size: 16px;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Открыть</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #777;">Нет проектов.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
