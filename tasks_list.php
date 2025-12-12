<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

$user_role = current_user_role();
$user_id = current_user_id();

// Построение запроса
$sql = "
    SELECT t.*, p.name as project_name, u.first_name as assignee_name, u.last_name as assignee_surname
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assignee_id = u.id
    WHERE 1=1
";
$params = [];

// Контроль доступа: сотрудник видит только свои задачи
if ($user_role === 'employee') {
    $sql .= " AND t.assignee_id = ?";
    $params[] = $user_id;
}

// Фильтры
if (isset($_GET['project_id']) && $_GET['project_id'] !== '') {
    $sql .= " AND t.project_id = ?";
    $params[] = $_GET['project_id'];
}
if (isset($_GET['assignee_id']) && $_GET['assignee_id'] !== '') {
    $sql .= " AND t.assignee_id = ?";
    $params[] = $_GET['assignee_id'];
}
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $_GET['status'];
}
if (isset($_GET['priority']) && $_GET['priority'] !== '') {
    $sql .= " AND t.priority = ?";
    $params[] = $_GET['priority'];
}
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $term = '%' . $_GET['search'] . '%';
    $params[] = $term;
    $params[] = $term;
}

// Сортировка: Дедлайн по возрастанию (сначала срочные)
$sql .= " ORDER BY t.deadline ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Получение данных для фильтров
$projects = $pdo->query("SELECT * FROM projects ORDER BY name ASC")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список задач</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="projects_list.php">Проекты</a>
            <a href="tasks_list.php" style="text-decoration: underline;">Задачи</a>
            <?php if (current_user_role() === 'admin'): ?>
                <a href="users.php">Сотрудники</a>
            <?php endif; ?>
            <span style="float: right;">
                <?= htmlspecialchars($_SESSION['name']) ?> (<?= $_SESSION['role'] ?>) | <a href="logout.php">Выход</a>
            </span>
        </div>

        <h1>Список задач</h1>

        <!-- Фильтры -->
        <div style="background: #f1f1f1; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label>Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="margin-bottom: 0;">
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label>Проект</label>
                    <select name="project_id" style="margin-bottom: 0;">
                        <option value="">Все проекты</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($_GET['project_id']) && $_GET['project_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($user_role === 'admin'): ?>
                <div style="flex: 1; min-width: 150px;">
                    <label>Исполнитель</label>
                    <select name="assignee_id" style="margin-bottom: 0;">
                        <option value="">Все сотрудники</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (isset($_GET['assignee_id']) && $_GET['assignee_id'] == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="flex: 1; min-width: 120px;">
                    <label>Статус</label>
                    <select name="status" style="margin-bottom: 0;">
                        <option value="">Все</option>
                        <option value="waiting" <?= (isset($_GET['status']) && $_GET['status'] == 'waiting') ? 'selected' : '' ?>>Ожидает</option>
                        <option value="in_progress" <?= (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : '' ?>>В работе</option>
                        <option value="review" <?= (isset($_GET['status']) && $_GET['status'] == 'review') ? 'selected' : '' ?>>На проверке</option>
                        <option value="done" <?= (isset($_GET['status']) && $_GET['status'] == 'done') ? 'selected' : '' ?>>Готово</option>
                        <option value="archive" <?= (isset($_GET['status']) && $_GET['status'] == 'archive') ? 'selected' : '' ?>>Архив</option>
                    </select>
                </div>

                <div style="flex: 1; min-width: 120px;">
                    <label>Приоритет</label>
                    <select name="priority" style="margin-bottom: 0;">
                        <option value="">Все</option>
                        <option value="high" <?= (isset($_GET['priority']) && $_GET['priority'] == 'high') ? 'selected' : '' ?>>Высокий</option>
                        <option value="medium" <?= (isset($_GET['priority']) && $_GET['priority'] == 'medium') ? 'selected' : '' ?>>Средний</option>
                        <option value="low" <?= (isset($_GET['priority']) && $_GET['priority'] == 'low') ? 'selected' : '' ?>>Низкий</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary" style="margin: 0;">Фильтр</button>
                    <a href="tasks_list.php" class="btn btn-warning" style="background-color: #ccc; color: #333; margin: 0;">Сброс</a>
                </div>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Тема</th>
                    <th>Проект</th>
                    <th>Статус</th>
                    <th>Приоритет</th>
                    <th>Исполнитель</th>
                    <th>Дедлайн</th>
                    <th width="100"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tasks) > 0): ?>
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
                            <td><?= htmlspecialchars($t['project_name']) ?></td>
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
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #777;">Задач не найдено.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
