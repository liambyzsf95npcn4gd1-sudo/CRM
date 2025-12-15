<?php
require_once 'db.php';
require_once 'auth.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Неверный ID пользователя.");
}

$user_id = $_GET['id'];

if ($user_id == current_user_id()) {
    die("Нельзя удалить самого себя.");
}

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
if (!$stmt->fetch()) {
    die("Пользователь не найден.");
}

// Delete user. Cascading delete should handle tasks/comments assignment?
// Schema:
// tasks.assignee_id ON DELETE SET NULL
// tasks.creator_id ON DELETE CASCADE
// comments.user_id ON DELETE CASCADE
// task_views.user_id ON DELETE CASCADE
// So if we delete user:
// 1. Tasks created by them are DELETED (Cascade). This triggers cascading delete of comments/attachments for those tasks.
// 2. Tasks assigned to them become Unassigned (Set Null).
// 3. Comments made by them are DELETED (Cascade).

// However, deleting tasks created by them might be too aggressive (deleting project work).
// Usually in CRM, you don't delete history. But requirement says: "Add possibility to delete employee".
// If "Cascading Delete" is a strict requirement for "Project" (delete project -> delete all), for Users it might be different.
// The Schema has `FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE`.
// This means if I delete a user, all tasks they created vanish. This might be intentional for "Cleanup".

// But we also need to clean up files for tasks that are about to be deleted!
// If tasks are deleted via Cascade, we won't get a chance to delete their files via PHP unless we do it BEFORE.

// So:
// 1. Find all tasks created by this user.
// 2. Delete files for those tasks.
// 3. Find all comments by this user.
// 4. Delete files for those comments.
// 5. Delete User.

// 1. Tasks created by user
$stmt = $pdo->prepare("SELECT id FROM tasks WHERE creator_id = ?");
$stmt->execute([$user_id]);
$createdTaskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($createdTaskIds)) {
    // Reuse deletion logic?
    // We can't easily reuse the "task_delete.php" logic without HTTP calls or function extraction.
    // I already extracted `delete_file_from_disk`.

    // Attachments for these tasks
    $inTasks = str_repeat('?,', count($createdTaskIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'task' AND entity_id IN ($inTasks)");
    $stmt->execute($createdTaskIds);
    $taskFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Comments on these tasks (by anyone) will be deleted too.
    $stmt = $pdo->prepare("SELECT id FROM comments WHERE task_id IN ($inTasks)");
    $stmt->execute($createdTaskIds);
    $taskCommentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($taskCommentIds)) {
        $inTaskComments = str_repeat('?,', count($taskCommentIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inTaskComments)");
        $stmt->execute($taskCommentIds);
        $taskCommentFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($taskCommentFiles as $f) delete_file_from_disk($f);

        // Delete attachments records for these comments
        $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inTaskComments)");
        $stmt->execute($taskCommentIds);
    }

    foreach ($taskFiles as $f) delete_file_from_disk($f);

    // Delete attachments records for these tasks
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'task' AND entity_id IN ($inTasks)");
    $stmt->execute($createdTaskIds);
}

// 2. Comments by user (on other tasks)
// Their comments will be deleted. We need to delete attachments for THEIR comments.
$stmt = $pdo->prepare("SELECT id FROM comments WHERE user_id = ?");
$stmt->execute([$user_id]);
$userCommentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($userCommentIds)) {
    $inUserComments = str_repeat('?,', count($userCommentIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT file_path FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inUserComments)");
    $stmt->execute($userCommentIds);
    $userCommentFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($userCommentFiles as $f) delete_file_from_disk($f);

    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'comment' AND entity_id IN ($inUserComments)");
    $stmt->execute($userCommentIds);
}

// Finally delete user
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$user_id]);

header("Location: users.php?msg=deleted");
exit;
