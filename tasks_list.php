<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

$user_role = current_user_role();
$user_id = current_user_id();

// Default logic for showing completed/archive
$show_completed = isset($_GET['show_completed']) ? (bool)$_GET['show_completed'] : false;

// Построение запроса
// Modified to join task_views to check unseen changes
$sql = "
    SELECT t.*, p.name as project_name, u.first_name as assignee_name, u.last_name as assignee_surname, u.position as assignee_position,
           tv.last_viewed_at
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assignee_id = u.id
    LEFT JOIN task_views tv ON t.id = tv.task_id AND tv.user_id = ?
    WHERE 1=1
";
$params = [$user_id];

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

// Status filter or Default hiding
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $_GET['status'];
} elseif (!$show_completed) {
    // Hide Done and Archive by default
    $sql .= " AND t.status NOT IN ('done', 'archive')";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav">
            <?php if ($user_role === 'admin'): ?>
                <a href="projects_list.php" class="desktop-only">Проекты</a>
            <?php endif; ?>
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
            <form method="GET" class="filter-form">
                <div class="filter-item">
                    <label>Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>

                <div class="filter-item">
                    <label>Проект</label>
                    <select name="project_id">
                        <option value="">Все проекты</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($_GET['project_id']) && $_GET['project_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($user_role === 'admin'): ?>
                <div class="filter-item">
                    <label>Исполнитель</label>
                    <select name="assignee_id">
                        <option value="">Все сотрудники</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (isset($_GET['assignee_id']) && $_GET['assignee_id'] == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-item">
                    <label>Статус</label>
                    <select name="status">
                        <option value="">Все (активные)</option>
                        <option value="waiting" <?= (isset($_GET['status']) && $_GET['status'] == 'waiting') ? 'selected' : '' ?>>Ожидает</option>
                        <option value="in_progress" <?= (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : '' ?>>В работе</option>
                        <option value="review" <?= (isset($_GET['status']) && $_GET['status'] == 'review') ? 'selected' : '' ?>>На проверке</option>
                        <option value="done" <?= (isset($_GET['status']) && $_GET['status'] == 'done') ? 'selected' : '' ?>>Готово</option>
                        <option value="archive" <?= (isset($_GET['status']) && $_GET['status'] == 'archive') ? 'selected' : '' ?>>Архив</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label>Приоритет</label>
                    <select name="priority">
                        <option value="">Все</option>
                        <option value="high" <?= (isset($_GET['priority']) && $_GET['priority'] == 'high') ? 'selected' : '' ?>>Высокий</option>
                        <option value="medium" <?= (isset($_GET['priority']) && $_GET['priority'] == 'medium') ? 'selected' : '' ?>>Средний</option>
                        <option value="low" <?= (isset($_GET['priority']) && $_GET['priority'] == 'low') ? 'selected' : '' ?>>Низкий</option>
                    </select>
                </div>

                <div class="filter-item" style="display: flex; align-items: center; padding-top: 25px;">
                     <input type="checkbox" name="show_completed" id="show_completed" value="1" <?= $show_completed ? 'checked' : '' ?>>
                     <label for="show_completed" style="margin-left: 5px; font-weight: normal; margin-bottom: 0;">Показать завершенные</label>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Фильтр</button>
                    <a href="tasks_list.php" class="btn btn-warning" style="background-color: #ccc; color: #333;">Сброс</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
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

                                // Unseen logic: Orange marker
                                // If task updated_at > last_viewed_at OR last_viewed_at IS NULL (never viewed)
                                $isUnseen = false;
                                if ($t['updated_at']) {
                                    if (!$t['last_viewed_at'] || strtotime($t['updated_at']) > strtotime($t['last_viewed_at'])) {
                                        $isUnseen = true;
                                    }
                                } elseif (!$t['last_viewed_at']) {
                                    // If no updated_at (old tasks) but never viewed? Assuming new tasks have updated_at = created_at
                                    // Let's assume created_at
                                    if (strtotime($t['created_at']) > strtotime($t['last_viewed_at'] ?? '0')) {
                                         $isUnseen = true;
                                    }
                                }

                                $markerStyle = $isUnseen ? 'border-left: 5px solid orange;' : '';
                            ?>
                            <tr class="<?= $priorityClass ?>" style="<?= $markerStyle ?>">
                                <td><?= $t['id'] ?></td>
                                <td>
                                    <a href="task_view.php?id=<?= $t['id'] ?>" style="color: #333; font-weight: bold;">
                                        <?php if($isUnseen): ?><span style="color: orange;">●</span><?php endif; ?>
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
    </div>
</body>
</html>
