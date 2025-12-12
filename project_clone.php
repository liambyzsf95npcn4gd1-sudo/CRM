<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID проекта.");
}

$old_project_id = $_GET['id'];

// 1. Получение данных исходного проекта
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$old_project_id]);
$old_project = $stmt->fetch();

if (!$old_project) {
    die("Проект не найден.");
}

try {
    $pdo->beginTransaction();

    // 2. Создание нового проекта
    $new_name = "Копия " . $old_project['name'];
    $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
    $stmt->execute([$new_name]);
    $new_project_id = $pdo->lastInsertId();

    // 3. Получение всех задач исходного проекта
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ?");
    $stmt->execute([$old_project_id]);
    $tasks = $stmt->fetchAll();

    // 4. Копирование задач
    // Правила копирования:
    // assignee_id (Исполнитель) -> ставим NULL
    // status (Статус) -> сбрасываем в 'waiting'
    // deadline (Срок) -> ставим NULL
    // started_at (Дата начала) -> ставим NULL
    // file_path (Файл) -> ставим NULL (файлы не копируются)
    // created_at (Дата создания) -> Текущая дата
    // title, description, priority -> Копируем как есть
    // Комментарии НЕ копируются

    // Администратор, выполняющий клонирование, становится создателем новых задач
    $creator_id = current_user_id();

    $sql_insert = "
        INSERT INTO tasks
        (project_id, creator_id, assignee_id, title, description, status, priority, created_at, deadline, started_at, file_path)
        VALUES (?, ?, NULL, ?, ?, 'waiting', ?, ?, NULL, NULL, NULL)
    ";
    $stmt_insert = $pdo->prepare($sql_insert);

    // Совместимость дат для SQLite/MySQL
    $now = date('Y-m-d H:i:s');

    foreach ($tasks as $t) {
        $stmt_insert->execute([
            $new_project_id,
            $creator_id,
            $t['title'],
            $t['description'],
            $t['priority'],
            $now
        ]);
    }

    $pdo->commit();

    // Перенаправление на страницу просмотра нового проекта
    header("Location: project_view.php?id=$new_project_id");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Ошибка клонирования: " . $e->getMessage());
}
?>
