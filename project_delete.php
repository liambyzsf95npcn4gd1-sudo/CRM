<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID проекта.");
}

$project_id = $_GET['id'];

// Get all tasks
$stmt = $pdo->prepare("SELECT id FROM tasks WHERE project_id = ?");
$stmt->execute([$project_id]);
$taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($taskIds)) {
    // 1. Collect all files to delete
    // Task attachments
    $inTasks = str_repeat('?,', count($taskIds) - 1) . '?';

    $stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'task' AND entity_id IN ($inTasks)");
    $stmt->execute($taskIds);
    $taskFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Comment attachments
    // Find all comments for these tasks
    $stmt = $pdo->prepare("SELECT id FROM comments WHERE task_id IN ($inTasks)");
    $stmt->execute($taskIds);
    $commentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $commentFiles = [];
    if (!empty($commentIds)) {
        $inComments = str_repeat('?,', count($commentIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inComments)");
        $stmt->execute($commentIds);
        $commentFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Delete files
    $allFiles = array_merge($taskFiles, $commentFiles);
    foreach ($allFiles as $path) {
        delete_file_from_disk($path);
    }

    // 2. Cleanup DB attachments
    // Attachments for tasks
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'task' AND entity_id IN ($inTasks)");
    $stmt->execute($taskIds);

    // Attachments for comments
    if (!empty($commentIds)) {
        $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inComments)");
        $stmt->execute($commentIds);
    }
}

// 3. Delete Project
// Cascading delete handles tasks, comments, task_views.
$stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
$stmt->execute([$project_id]);

header("Location: projects_list.php");
exit;
