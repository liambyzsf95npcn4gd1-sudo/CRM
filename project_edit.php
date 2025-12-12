<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID проекта.");
}

$project_id = $_GET['id'];
$error = '';

// Получение данных проекта
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Проект не найден.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);

    if (empty($name)) {
        $error = "Название проекта не может быть пустым.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE projects SET name = ? WHERE id = ?");
            $stmt->execute([$name, $project_id]);

            // Перенаправление на страницу просмотра проекта
            header("Location: project_view.php?id=$project_id");
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование проекта</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="projects_list.php">Проекты</a>
            <a href="tasks_list.php">Задачи</a>
            <a href="users.php">Сотрудники</a>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <h1>Редактирование проекта</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
            <form method="POST">
                <label>Название проекта</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($project['name']) ?>">

                <div class="mt-20">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="project_view.php?id=<?= $project_id ?>" class="btn btn-warning" style="background-color: #ccc; color: #333;">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
