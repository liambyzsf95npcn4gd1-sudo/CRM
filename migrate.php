<?php
require_once 'db.php';

echo "Starting migration...\n";

try {
    // 1. Update Users Table (Add email, position, phone)
    $columnsToAdd = [
        'email' => 'VARCHAR(100)',
        'position' => 'VARCHAR(100)',
        'phone' => 'VARCHAR(50)'
    ];

    foreach ($columnsToAdd as $col => $type) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
            echo "Added '$col' to users.\n";
        } catch (PDOException $e) {
            // Check if error is because column exists (Code 42S21 for MySQL usually, or generic)
            // Just echo warning
            echo "Column '$col' might already exist or error: " . $e->getMessage() . "\n";
        }
    }

    // 2. Create attachments table
    $sql = "CREATE TABLE IF NOT EXISTS attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('task', 'comment') NOT NULL,
        entity_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($sql);
    echo "Created 'attachments' table.\n";

    // 3. Create task_views table
    $sql = "CREATE TABLE IF NOT EXISTS task_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_id INT NOT NULL,
        last_viewed_at DATETIME NOT NULL,
        UNIQUE KEY unique_view (user_id, task_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($sql);
    echo "Created 'task_views' table.\n";

    // 4. Add updated_at to tasks
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN updated_at DATETIME DEFAULT NULL");
        // Initialize updated_at with created_at
        $pdo->exec("UPDATE tasks SET updated_at = created_at WHERE updated_at IS NULL");
        echo "Added 'updated_at' to tasks.\n";
    } catch (PDOException $e) {
        echo "Column 'updated_at' might already exist or error: " . $e->getMessage() . "\n";
    }

    // 5. Migrate existing files
    try {
        // Only run if tasks.file_path exists (might have been dropped in previous run)
        // A robust way to check column existence in raw PDO is hard without SHOW COLUMNS
        // We wrap in try-catch
        $stmt = $pdo->query("SELECT id, file_path, created_at FROM tasks WHERE file_path IS NOT NULL AND file_path != ''");
        if ($stmt) {
            $tasks = $stmt->fetchAll();
            foreach ($tasks as $task) {
                $stmtInsert = $pdo->prepare("INSERT INTO attachments (entity_type, entity_id, file_path, created_at) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute(['task', $task['id'], $task['file_path'], $task['created_at']]);
            }
            echo "Migrated " . count($tasks) . " task attachments.\n";

            // Drop column only after successful migration
            $pdo->exec("ALTER TABLE tasks DROP COLUMN file_path");
            echo "Dropped 'file_path' from tasks.\n";
        }
    } catch (Exception $e) {
        echo "Task migration skipped or failed (column missing?): " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->query("SELECT id, task_id, file_path, created_at FROM comments WHERE file_path IS NOT NULL AND file_path != ''");
        if ($stmt) {
            $comments = $stmt->fetchAll();
            foreach ($comments as $comment) {
                $stmtInsert = $pdo->prepare("INSERT INTO attachments (entity_type, entity_id, file_path, created_at) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute(['comment', $comment['id'], $comment['file_path'], $comment['created_at']]);
            }
            echo "Migrated " . count($comments) . " comment attachments.\n";

            $pdo->exec("ALTER TABLE comments DROP COLUMN file_path");
            echo "Dropped 'file_path' from comments.\n";
        }
    } catch (Exception $e) {
        echo "Comment migration skipped or failed: " . $e->getMessage() . "\n";
    }

    echo "Migration complete.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
