<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

$error = '';
$success = '';

// Проверка наличия директории для загрузки файлов
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Получение списка проектов и сотрудников для выпадающих списков
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

$default_project_id = $_GET['project_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $project_id = $_POST['project_id'];
    $assignee_id = !empty($_POST['assignee_id']) ? $_POST['assignee_id'] : null;
    $priority = $_POST['priority'];

    // Форматирование даты для MySQL (YYYY-MM-DD HH:MM:SS)
    // datetime-local возвращает YYYY-MM-DDTHH:MM
    $deadline = null;
    if (!empty($_POST['deadline'])) {
        $deadline = str_replace('T', ' ', $_POST['deadline']) . ':00';
    }

    // Загрузка файла
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['file']['name']);
        $ext = strtolower($fileInfo['extension']);

        // Список разрешенных расширений (Белый список)
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];

        if (in_array($ext, $allowed)) {
            // Генерация уникального имени файла
            $filename = uniqid('task_') . '.' . $ext;
            $target = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                $file_path = 'uploads/' . $filename;
            } else {
                $error = "Ошибка загрузки файла.";
            }
        } else {
            $error = "Недопустимый формат файла. Разрешены: " . implode(', ', $allowed);
        }
    }

    if (empty($title) || empty($project_id)) {
        $error = (!empty($error) ? $error . " " : "") . "Название задачи и проект обязательны.";
    } elseif (empty($error)) {
        try {
            $created_at = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO tasks
                (project_id, creator_id, assignee_id, title, description, priority, created_at, deadline, file_path)
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
                $deadline,
                $file_path
            ]);

            // Перенаправление на страницу проекта
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
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Дедлайн</label>
                <input type="datetime-local" name="deadline">

                <label>Файл</label>
                <input type="file" name="file">
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
