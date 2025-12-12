<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID проекта.");
}

$project_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Проект не найден.");
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтверждение клонирования</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 600px; margin-top: 100px; text-align: center;">
        <h2>Клонирование проекта</h2>
        <p>Вы собираетесь клонировать проект: <strong><?= htmlspecialchars($project['name']) ?></strong>.</p>

        <div class="alert alert-danger" style="text-align: left;">
            <strong>Внимание!</strong>
            <ul>
                <li>Будет создан новый проект "Копия <?= htmlspecialchars($project['name']) ?>".</li>
                <li>Все задачи будут скопированы.</li>
                <li>Исполнители, сроки и статусы задач будут сброшены.</li>
            </ul>
        </div>

        <div style="margin-top: 30px;">
            <a href="project_clone.php?id=<?= $project_id ?>" class="btn btn-warning">Да, клонировать</a>
            <a href="project_view.php?id=<?= $project_id ?>" class="btn btn-primary" style="background-color: #ccc; color: #333; border: 1px solid #bbb;">Отмена</a>
        </div>
    </div>
</body>
</html>
