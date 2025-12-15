<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID задачи.");
}

$task_id = $_GET['id'];

// Get project ID to redirect back
$stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die("Задача не найдена.");
}

$project_id = $task['project_id'];

// 1. Delete files from disk (attachments)
// Get all attachments for this task and its comments
// Attachments for task
$stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'task' AND entity_id = ?");
$stmt->execute([$task_id]);
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Attachments for comments of this task
$stmt = $pdo->prepare("
    SELECT a.file_path
    FROM attachments a
    JOIN comments c ON a.entity_id = c.id
    WHERE a.entity_type = 'comment' AND c.task_id = ?
");
$stmt->execute([$task_id]);
$commentFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$allFiles = array_merge($files, $commentFiles);

foreach ($allFiles as $path) {
    delete_file_from_disk($path);
}

// 2. Delete database records
// Cascading delete should handle comments and attachments if foreign keys were set up perfectly.
// However, our attachments table uses polymorphic ID, not a true foreign key to tasks/comments (unless we added triggers).
// So we must manually delete attachments.
// Comments are ON DELETE CASCADE with tasks, so they will be deleted automatically.
// But we should delete attachments linked to those comments first (or concurrently).

// Delete attachments for task
$stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'task' AND entity_id = ?");
$stmt->execute([$task_id]);

// Delete attachments for comments (need to find comment IDs first)
$stmt = $pdo->prepare("SELECT id FROM comments WHERE task_id = ?");
$stmt->execute([$task_id]);
$commentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($commentIds)) {
    $in = str_repeat('?,', count($commentIds) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($in)");
    $stmt->execute($commentIds);
}

// Finally delete task (Cascades to comments, and task_views)
$stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);

header("Location: project_view.php?id=$project_id");
exit;
