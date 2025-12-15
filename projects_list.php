<?php
require_once 'db.php';
require_once 'auth.php';

require_login();

$user_role = current_user_role();

// Check if Employee should see this page?
// "Для роли "Сотрудник": Полностью скрыть панель/раздел "Проекты" из меню."
// But if they access it directly?
// The requirement only says "Hide from menu". But usually "Hide" implies "No Access".
// However, employees might need to see projects to see tasks in context.
// Let's assume they can access but it's hidden from menu.
// Wait, if they can't create projects, they only see the list.

$error = '';
$success = '';

// Обработка создания проекта (Только админ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if ($user_role !== 'admin') {
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

// Search and Sort
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_desc';

$sql = "SELECT * FROM projects WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
}

switch ($sort) {
    case 'name_asc': $sql .= " ORDER BY name ASC"; break;
    case 'name_desc': $sql .= " ORDER BY name DESC"; break;
    case 'id_asc': $sql .= " ORDER BY id ASC"; break;
    case 'id_desc':
    default: $sql .= " ORDER BY id DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список проектов</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav">
            <?php if ($user_role === 'admin'): ?>
                <a href="projects_list.php" style="text-decoration: underline;">Проекты</a>
            <?php endif; ?>
            <a href="tasks_list.php">Задачи</a>
            <?php if ($user_role === 'admin'): ?>
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

        <?php if ($user_role === 'admin'): ?>
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

        <div style="margin-bottom: 20px; background: #f1f1f1; padding: 15px; border-radius: 4px;">
            <form method="GET" class="filter-form">
                <div class="filter-item">
                    <label>Поиск по названию</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-item">
                    <label>Сортировка</label>
                    <select name="sort">
                        <option value="id_desc" <?= $sort == 'id_desc' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="id_asc" <?= $sort == 'id_asc' ? 'selected' : '' ?>>Сначала старые</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>По алфавиту (А-Я)</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>По алфавиту (Я-А)</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Применить</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
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
    </div>
</body>
</html>
