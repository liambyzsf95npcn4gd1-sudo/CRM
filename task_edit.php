<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID задачи.");
}

$task_id = $_GET['id'];
$error = '';

// Получение данных задачи
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die("Задача не найдена.");
}

// Обработка обновления задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $project_id = $_POST['project_id'];
    $assignee_id = !empty($_POST['assignee_id']) ? $_POST['assignee_id'] : null;
    $priority = $_POST['priority'];

    // Форматирование даты для MySQL
    $deadline = null;
    if (!empty($_POST['deadline'])) {
        $deadline = str_replace('T', ' ', $_POST['deadline']) . ':00';
    }

    if (empty($title) || empty($project_id)) {
        $error = "Название задачи и проект обязательны.";
    } else {
        try {
            // Update timestamp
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                UPDATE tasks
                SET project_id = ?, assignee_id = ?, title = ?, description = ?, priority = ?, deadline = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $project_id,
                $assignee_id,
                $title,
                $description,
                $priority,
                $deadline,
                $now,
                $task_id
            ]);

            // Note: File uploads are not in edit form per original MVP logic,
            // usually you add files via comments or we could add "Add more files" here.
            // Requirement was "Change logic of attaching files... simultaneously add multiple comments".
            // Actually, "Загрузка нескольких файлов: Изменить логику прикрепления файлов. Сейчас 1 файл, нужно сделать возможность одновременно добавлять несколько комментариев"
            // This part of requirement text is slightly confusing: "possibility to simultaneously add multiple comments" -> probably meant "add multiple files".
            // We addressed multiple files in `task_create` and `task_view` (comments).
            // `task_edit` usually just edits metadata.

            header("Location: task_view.php?id=$task_id");
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}

// Получение списков для формы
$projects = $pdo->query("SELECT * FROM projects ORDER BY name ASC")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование задачи</title>
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

        <h1>Редактирование задачи #<?= $task['id'] ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
            <form method="POST">

                <label>Проект *</label>
                <select name="project_id" required>
                    <option value="">-- Выберите проект --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $task['project_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Тема *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($task['title']) ?>">

                <label>Описание</label>
                <textarea name="description" rows="5"><?= htmlspecialchars($task['description']) ?></textarea>

                <label>Приоритет *</label>
                <select name="priority">
                    <option value="low" <?= $task['priority'] == 'low' ? 'selected' : '' ?>>Низкий</option>
                    <option value="medium" <?= $task['priority'] == 'medium' ? 'selected' : '' ?>>Средний</option>
                    <option value="high" <?= $task['priority'] == 'high' ? 'selected' : '' ?>>Высокий</option>
                </select>

                <label>Исполнитель</label>
                <select name="assignee_id">
                    <option value="">-- Не назначен --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id'] == $task['assignee_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            <?php if(!empty($u['position'])) echo " (" . htmlspecialchars($u['position']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Дедлайн</label>
                <input type="datetime-local" name="deadline" value="<?= $task['deadline'] ? date('Y-m-d\TH:i', strtotime($task['deadline'])) : '' ?>">

                <div class="mt-20">
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    <a href="task_view.php?id=<?= $task['id'] ?>" class="btn btn-warning" style="background-color: #ccc; color: #333;">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
