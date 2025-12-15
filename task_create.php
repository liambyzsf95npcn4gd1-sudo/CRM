<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

$error = '';
$success = '';

// Получение списка проектов и сотрудников для выпадающих списков
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
// Fetch employees with position
$users = $pdo->query("SELECT * FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

$default_project_id = $_GET['project_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $project_id = $_POST['project_id'];
    $assignee_id = !empty($_POST['assignee_id']) ? $_POST['assignee_id'] : null;
    $priority = $_POST['priority'];

    // Форматирование даты
    $deadline = null;
    if (!empty($_POST['deadline'])) {
        $deadline = str_replace('T', ' ', $_POST['deadline']) . ':00';
    }

    if (empty($title) || empty($project_id)) {
        $error = "Название задачи и проект обязательны.";
    } else {
        try {
            $pdo->beginTransaction();

            $created_at = date('Y-m-d H:i:s');
            // Insert Task
            // Note: updated_at is added as per plan, initialized to created_at
            $stmt = $pdo->prepare("
                INSERT INTO tasks
                (project_id, creator_id, assignee_id, title, description, priority, created_at, updated_at, deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $project_id,
                current_user_id(),
                $assignee_id,
                $title,
                $description,
                $priority,
                $created_at,
                $created_at,
                $deadline
            ]);

            $task_id = $pdo->lastInsertId();

            // Handle file uploads (multiple)
            if (isset($_FILES['files'])) {
                $uploadedFiles = upload_files($_FILES['files']);
                if (!empty($uploadedFiles)) {
                    $stmtAttach = $pdo->prepare("INSERT INTO attachments (entity_type, entity_id, file_path, created_at) VALUES (?, ?, ?, ?)");
                    foreach ($uploadedFiles as $path) {
                        $stmtAttach->execute(['task', $task_id, $path, $created_at]);
                    }
                }
            }

            $pdo->commit();

            // Перенаправление на страницу проекта
            header("Location: project_view.php?id=$project_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание задачи</title>
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

        <h1>Новая задача</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
            <form method="POST" enctype="multipart/form-data">

                <label>Проект *</label>
                <select name="project_id" required>
                    <option value="">-- Выберите проект --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $default_project_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Тема *</label>
                <input type="text" name="title" required value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">

                <label>Описание</label>
                <textarea name="description" rows="5"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>

                <label>Приоритет *</label>
                <select name="priority">
                    <option value="low">Низкий</option>
                    <option value="medium" selected>Средний</option>
                    <option value="high">Высокий</option>
                </select>

                <label>Исполнитель</label>
                <select name="assignee_id">
                    <option value="">-- Не назначен --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            <?php if(!empty($u['position'])) echo " (" . htmlspecialchars($u['position']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Дедлайн</label>
                <input type="datetime-local" name="deadline">

                <label>Файлы</label>
                <!-- Allow multiple files -->
                <input type="file" name="files[]" multiple>
                <small style="color: #666;">Разрешены: jpg, png, pdf, doc, xls, zip</small>

                <div class="mt-20">
                    <button type="submit" class="btn btn-success">Создать задачу</button>
                    <a href="projects_list.php" class="btn btn-warning" style="background-color: #ccc; color: #333;">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
