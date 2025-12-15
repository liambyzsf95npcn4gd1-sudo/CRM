<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID проекта.");
}

$project_id = $_GET['id'];

// Получение информации о проекте
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Проект не найден.");
}

// Получение списка задач проекта с информацией об исполнителях
$sql = "
    SELECT t.*, u.first_name as assignee_name, u.last_name as assignee_surname
    FROM tasks t
    LEFT JOIN users u ON t.assignee_id = u.id
    WHERE t.project_id = ?
";

$params = [$project_id];

// Если пользователь - сотрудник, показываем только его задачи
if (current_user_role() === 'employee') {
    $sql .= " AND t.assignee_id = ?";
    $params[] = current_user_id();
}

$sql .= " ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.deadline ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Проект: <?= htmlspecialchars($project['name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav">
            <?php if (current_user_role() === 'admin'): ?>
                <a href="projects_list.php">Проекты</a>
            <?php endif; ?>
            <a href="tasks_list.php">Задачи</a>
            <?php if (current_user_role() === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h1>Проект: <?= htmlspecialchars($project['name']) ?></h1>
            <?php if (current_user_role() === 'admin'): ?>
                <div>
                    <a href="project_edit.php?id=<?= $project['id'] ?>" class="btn btn-primary">Редактировать</a>
                    <a href="project_clone_confirm.php?id=<?= $project['id'] ?>" class="btn btn-warning">Клонировать</a>
                    <a href="project_delete.php?id=<?= $project['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить проект? Это действие необратимо и удалит все задачи и файлы проекта.');">Удалить проект</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-20">
            <h3>Задачи проекта</h3>
            <?php if (count($tasks) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Тема</th>
                                <th>Статус</th>
                                <th>Приоритет</th>
                                <th>Исполнитель</th>
                                <th>Дедлайн</th>
                                <th width="100"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                                <?php
                                    $priorityClass = '';
                                    if ($t['priority'] === 'high') $priorityClass = 'row-high';
                                    elseif ($t['priority'] === 'medium') $priorityClass = 'row-medium';
                                    elseif ($t['priority'] === 'low') $priorityClass = 'row-low';
                                ?>
                                <tr class="<?= $priorityClass ?>">
                                    <td><?= $t['id'] ?></td>
                                    <td>
                                        <a href="task_view.php?id=<?= $t['id'] ?>" style="color: #333; font-weight: bold;">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $statuses = [
                                            'waiting' => 'Ожидает',
                                            'in_progress' => 'В работе',
                                            'review' => 'На проверке',
                                            'done' => 'Готово',
                                            'archive' => 'Архив'
                                        ];
                                        echo $statuses[$t['status']] ?? $t['status'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorities = ['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий'];
                                        echo $priorities[$t['priority']] ?? $t['priority'];
                                        ?>
                                    </td>
                                    <td>
                                        <?= $t['assignee_id'] ? htmlspecialchars($t['assignee_name'] . ' ' . $t['assignee_surname']) : '<span style="color: #999;">Не назначен</span>' ?>
                                    </td>
                                    <td>
                                        <?= $t['deadline'] ? date('d.m.Y H:i', strtotime($t['deadline'])) : '-' ?>
                                    </td>
                                    <td>
                                        <a href="task_view.php?id=<?= $t['id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Открыть</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>В этом проекте пока нет задач.</p>
            <?php endif; ?>
        </div>

        <?php if (current_user_role() === 'admin'): ?>
            <div class="mt-20">
                <a href="task_create.php?project_id=<?= $project['id'] ?>" class="btn btn-success">Добавить задачу</a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
