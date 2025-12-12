CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    patronymic VARCHAR(100),
    role ENUM('admin', 'employee') NOT NULL,
    position VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    creator_id INT NOT NULL,
    assignee_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('waiting', 'in_progress', 'review', 'done', 'archive') DEFAULT 'waiting',
    priority ENUM('low', 'medium', 'high') NOT NULL,
    created_at DATETIME NOT NULL,
    deadline DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Вставка дефолтного администратора (admin / 12345)
INSERT INTO users (login, password, first_name, last_name, role)
VALUES ('admin', '$2y$10$wGTkPtLxwTCi8h3xr5IPU.mTkcoQivqQ/ezVGjA8dG2Ar7IsX8id2', 'Admin', 'User', 'admin');
