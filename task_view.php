<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID задачи.");
}

$task_id = $_GET['id'];
$user_role = current_user_role();
$user_id = current_user_id();

// Update task_views to mark as viewed (Step 4 of plan, but doing it here as I'm editing the file)
$now = date('Y-m-d H:i:s');
$stmtView = $pdo->prepare("INSERT INTO task_views (user_id, task_id, last_viewed_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_viewed_at = ?");
$stmtView->execute([$user_id, $task_id, $now, $now]);


// Обработка изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $new_status = null;
    $started_at_update = false;

    // Права доступа и переходы между статусами
    if ($user_role === 'employee') {
        // Проверка, назначена ли задача этому сотруднику
        $stmt = $pdo->prepare("SELECT assignee_id, status FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task_check = $stmt->fetch();

        if ($task_check && $task_check['assignee_id'] == $user_id) {
            if ($action === 'start' && $task_check['status'] === 'waiting') {
                $new_status = 'in_progress';
                $started_at_update = true;
            } elseif ($action === 'complete' && $task_check['status'] === 'in_progress') {
                $new_status = 'review';
            }
        }
    } elseif ($user_role === 'admin') {
        if ($action === 'done') {
            $new_status = 'done';
        } elseif ($action === 'delete') {
            header("Location: task_delete.php?id=$task_id");
            exit;
        }
    }

    if ($new_status) {
        $now = date('Y-m-d H:i:s');
        if ($started_at_update) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, started_at = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$new_status, $now, $now, $task_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$new_status, $now, $task_id]);
        }
        // Обновление страницы для отображения изменений
        header("Location: task_view.php?id=$task_id");
        exit;
    }
}

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_text'])) {
    $text = trim($_POST['comment_text']);

    // Multiple file upload
    $uploadedFiles = [];
    if (isset($_FILES['comment_files'])) {
        $uploadedFiles = upload_files($_FILES['comment_files']);
    }

    if (!empty($text) || !empty($uploadedFiles)) {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO comments (task_id, user_id, text, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$task_id, $user_id, $text, $now]);
        $comment_id = $pdo->lastInsertId();

        if (!empty($uploadedFiles)) {
            $stmtAttach = $pdo->prepare("INSERT INTO attachments (entity_type, entity_id, file_path, created_at) VALUES (?, ?, ?, ?)");
            foreach ($uploadedFiles as $path) {
                $stmtAttach->execute(['comment', $comment_id, $path, $now]);
            }
        }

        // Update task updated_at
        $stmtUpdateTask = $pdo->prepare("UPDATE tasks SET updated_at = ? WHERE id = ?");
        $stmtUpdateTask->execute([$now, $task_id]);

        header("Location: task_view.php?id=$task_id");
        exit;
    }
}

// Получение деталей задачи
$sql = "
    SELECT t.*, p.name as project_name, u.first_name as assignee_name, u.last_name as assignee_surname, c.first_name as creator_name, c.last_name as creator_surname
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assignee_id = u.id
    LEFT JOIN users c ON t.creator_id = c.id
    WHERE t.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die("Задача не найдена.");
}

// Проверка доступа: Сотрудник видит только свои задачи
if ($user_role === 'employee' && $task['assignee_id'] != $user_id) {
    die("Доступ запрещен. Вы не являетесь исполнителем этой задачи.");
}

// Получение вложений задачи
$stmtAttach = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'task' AND entity_id = ?");
$stmtAttach->execute([$task_id]);
$task_files = $stmtAttach->fetchAll();


// Получение комментариев с вложениями
$stmt = $pdo->prepare("
    SELECT c.*, u.first_name, u.last_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.task_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$task_id]);
$comments = $stmt->fetchAll();

// Get comment attachments efficiently
$comment_ids = array_column($comments, 'id');
$comment_files = [];
if (!empty($comment_ids)) {
    $in  = str_repeat('?,', count($comment_ids) - 1) . '?';
    $stmtCA = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($in)");
    $stmtCA->execute($comment_ids);
    $all_c_files = $stmtCA->fetchAll();

    foreach ($all_c_files as $f) {
        $comment_files[$f['entity_id']][] = $f;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задача #<?= $task['id'] ?></title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav">
            <?php if ($user_role === 'admin'): ?>
                <a href="projects_list.php">Проекты</a>
            <?php endif; ?>
            <a href="tasks_list.php">Задачи</a>
            <?php if ($user_role === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <div style="margin-bottom: 20px;">
            <a href="projects_list.php" class="btn btn-warning" style="background-color: #eee; color: #333; border: 1px solid #ccc;">&larr; Назад</a>
            <?php if ($user_role === 'admin'): ?>
                <a href="task_edit.php?id=<?= $task['id'] ?>" class="btn btn-primary" style="float: right;">Редактировать</a>
            <?php endif; ?>
        </div>

        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 4px; background: #fff;">
            <h1><?= htmlspecialchars($task['title']) ?> <span style="font-size: 16px; color: #777;">(#<?= $task['id'] ?>)</span></h1>

            <table style="width: 100%; border: none; margin-bottom: 20px; max-width: 600px;">
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold; width: 120px;">Проект:</td>
                    <td style="border: none;"><?= htmlspecialchars($task['project_name']) ?></td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Статус:</td>
                    <td style="border: none;">
                        <?php
                        $statuses = ['waiting'=>'Ожидает', 'in_progress'=>'В работе', 'review'=>'На проверке', 'done'=>'Готово', 'archive'=>'Архив'];
                        echo $statuses[$task['status']] ?? $task['status'];
                        ?>
                    </td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Приоритет:</td>
                    <td style="border: none;">
                        <?php
                        $priorities = ['low'=>'Низкий', 'medium'=>'Средний', 'high'=>'Высокий'];
                        echo $priorities[$task['priority']] ?? $task['priority'];
                        ?>
                    </td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Исполнитель:</td>
                    <td style="border: none;">
                        <?= $task['assignee_id'] ? htmlspecialchars($task['assignee_name'] . ' ' . $task['assignee_surname']) : 'Не назначен' ?>
                    </td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Создатель:</td>
                    <td style="border: none;">
                        <?= htmlspecialchars($task['creator_name'] . ' ' . $task['creator_surname']) ?>
                    </td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold;">Дедлайн:</td>
                    <td style="border: none;">
                        <?= $task['deadline'] ? date('d.m.Y H:i', strtotime($task['deadline'])) : '-' ?>
                    </td>
                </tr>
                <?php if (!empty($task_files)): ?>
                <tr style="border: none;">
                    <td style="border: none; padding-left: 0; font-weight: bold; vertical-align: top;">Файлы:</td>
                    <td style="border: none;">
                        <?php foreach($task_files as $tf): ?>
                            <div><a href="<?= htmlspecialchars($tf['file_path']) ?>" target="_blank" style="color: #007bff; font-weight: bold;">Скачать файл</a></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <div style="background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin-bottom: 20px;">
                <strong>Описание:</strong><br>
                <div style="margin-top: 10px; white-space: pre-wrap;"><?= htmlspecialchars($task['description']) ?></div>
            </div>

            <!-- Кнопки действий -->
            <div style="margin-bottom: 30px;">
                <?php if ($user_role === 'employee' && $task['assignee_id'] == $user_id): ?>
                    <?php if ($task['status'] === 'waiting'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="start">
                            <button type="submit" class="btn btn-success">Начать</button>
                        </form>
                    <?php elseif ($task['status'] === 'in_progress'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success">Выполнить</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($user_role === 'admin'): ?>
                    <?php if ($task['status'] !== 'done' && $task['status'] !== 'archive'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="done">
                            <button type="submit" class="btn btn-success">Завершить</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить задачу?');">Удалить</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Комментарии -->
            <h3>Комментарии</h3>
            <div style="margin-bottom: 20px;">
                <?php if (count($comments) > 0): ?>
                    <?php foreach ($comments as $c): ?>
                        <div style="border-bottom: 1px solid #eee; padding: 10px 0;">
                            <div style="font-size: 12px; color: #777;">
                                <strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                | <?= date('d.m.Y H:i', strtotime($c['created_at'])) ?>
                            </div>
                            <div style="margin-top: 5px;"><?= nl2br(htmlspecialchars($c['text'])) ?></div>
                            <?php if (isset($comment_files[$c['id']])): ?>
                                <div style="margin-top: 5px;">
                                    <?php foreach ($comment_files[$c['id']] as $cf): ?>
                                        <div><a href="<?= htmlspecialchars($cf['file_path']) ?>" target="_blank" style="color: #007bff; font-size: 12px;">Скачать: <?= basename($cf['file_path']) ?></a></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #777;">Комментариев нет.</p>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <label>Добавить комментарий</label>
                <textarea name="comment_text" rows="3"></textarea>

                <label style="margin-top: 10px;">Прикрепить файлы</label>
                <input type="file" name="comment_files[]" multiple>
                <small style="color: #666;">Разрешены: jpg, png, pdf, doc, xls, zip</small>

                <div style="margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">Отправить</button>
                </div>
            </form>

        </div>
    </div>
</body>
</html>
