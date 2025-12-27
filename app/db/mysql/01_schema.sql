-- 1. Table Structure

-- Table: Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  role ENUM('admin','editor','viewer','member') DEFAULT 'member',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin (User: admin, Pass: admin123)
-- Hash vom User generiert (valid for admin123)
INSERT INTO users (username, password_hash, email, role)
VALUES ('admin', '$2y$10$wc/wBLIE0xr1GgogogTQp.b6siZzwaEzNoDpj0h6IBpni6g8jCA3e', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), email=VALUES(email), role=VALUES(role);

-- Table: Categories
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(128) UNIQUE NOT NULL
);

-- Table: Settings
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value TEXT
);

-- Table: Posts
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE,
  excerpt TEXT,
  content LONGTEXT,
  hero_image VARCHAR(255),
  download_file VARCHAR(255) DEFAULT NULL,
  is_sticky TINYINT(1) DEFAULT 0,
  status ENUM('draft','published','archived') DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Table: Comments
CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  parent_id INT DEFAULT NULL,
  author_name VARCHAR(128),
  author_email VARCHAR(128),
  content TEXT NOT NULL,
  status ENUM('pending','approved','spam','deleted') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id),
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Table: Files
CREATE TABLE IF NOT EXISTS files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(128),
  size INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table: Activity Log
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Tags
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Post_Tags (Relation)
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Daily Stats
CREATE TABLE IF NOT EXISTS daily_stats (
    date DATE PRIMARY KEY,
    views INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Messages (Contact Form)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    email VARCHAR(128) NOT NULL,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Pages
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    status ENUM('draft','published') DEFAULT 'draft',
    show_in_header TINYINT(1) DEFAULT 0,
    show_in_footer TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Menu Items (New)
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    link VARCHAR(255) NOT NULL,
    type ENUM('page', 'custom') DEFAULT 'custom',
    position INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed Data & Schema Updates
START TRANSACTION;

-- Default Settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
  ('blog_title', 'PrivatCMS'),
  ('blog_description', 'A simple CMS'),
  ('posts_per_page', '12'),
  ('debug_mode', '0'),
  ('error_logging', '0'),
  ('maintenance_mode', '0'),
  ('dark_mode_enabled', '0');

-- Default Categories
INSERT IGNORE INTO categories (name, slug) VALUES
  ('Allgemein','allgemein'),
  ('Docker','docker'),
  ('Portainer','portainer'),
  ('Testkategorie','testkategorie');

-- Demo Posts
INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, hero_image, status, created_at)
SELECT 1, id, 'Hallo an alle Besucher!', 'hallo-an-alle-besucher', 'Willkommen auf meinem neuen Blog...', '<p>Willkommen auf meinem neuen Blog! Dies ist ein Beispielbeitrag.</p>', NULL, 'published', NOW() - INTERVAL 14 DAY
FROM categories WHERE slug = 'allgemein' ON DUPLICATE KEY UPDATE title=VALUES(title);

INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, hero_image, status, created_at)
SELECT 1, id, 'Docker Compose Quickstart', 'docker-compose-quickstart', 'Kurzer Einstieg...', '<p>Mit Docker Compose lassen sich mehrere Services bequem starten.</p>', NULL, 'published', NOW() - INTERVAL 10 DAY
FROM categories WHERE slug = 'docker' ON DUPLICATE KEY UPDATE title=VALUES(title);

INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, hero_image, status, created_at)
SELECT 1, id, 'Portainer Tipps', 'portainer-tipps', 'Nützliche Tricks...', '<p>Portainer ist ein tolles UI für Docker.</p>', NULL, 'published', NOW() - INTERVAL 7 DAY
FROM categories WHERE slug = 'portainer' ON DUPLICATE KEY UPDATE title=VALUES(title);

INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, hero_image, status, created_at)
SELECT 1, id, 'Testeintrag', 'testeintrag', 'Dies ist ein kurzer Testeintrag.', '<p>Einfacher Testinhalt, um das Frontend zu füllen.</p>', NULL, 'published', NOW() - INTERVAL 3 DAY
FROM categories WHERE slug = 'testkategorie' ON DUPLICATE KEY UPDATE title=VALUES(title);

-- Demo Comments
INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'el-choco', 'demo@example.com', 'Willkommen! Viel Erfolg mit dem Blog.', 'approved', NOW() - INTERVAL 13 DAY
FROM posts WHERE slug='hallo-an-alle-besucher';

INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'Heino', 'heino@example.com', 'Guter Einstieg in Docker Compose.', 'approved', NOW() - INTERVAL 9 DAY
FROM posts WHERE slug='docker-compose-quickstart';

INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'Gast', 'gast@example.com', 'Könntest du ein Beispiel-Compose posten?', 'pending', NOW() - INTERVAL 8 DAY
FROM posts WHERE slug='docker-compose-quickstart';

-- --------------------------------------------------------
-- MULTI-USER SYSTEM UPDATES & STATS
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "posts";
SET @columnname = "author_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE posts ADD COLUMN author_id INT NULL AFTER user_id;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

UPDATE posts SET author_id = user_id WHERE author_id IS NULL;

SET @constraintname = "fk_posts_author";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      (constraint_name = @constraintname)
      AND (table_name = @tablename)
      AND (constraint_schema = @dbname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE posts ADD CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL;"
));
PREPARE addConstraint FROM @preparedStatement;
EXECUTE addConstraint;
DEALLOCATE PREPARE addConstraint;

SET @columnname2 = "views";
SET @preparedStatement2 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname2)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE posts ADD COLUMN views INT DEFAULT 0;"
));
PREPARE alterIfNotExists2 FROM @preparedStatement2;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- --------------------------------------------------------
-- PAGES SYSTEM UPDATES
-- --------------------------------------------------------

SET @tablename = "pages";
SET @columnname = "show_in_header";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE pages ADD COLUMN show_in_header TINYINT(1) DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "show_in_footer";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE pages ADD COLUMN show_in_footer TINYINT(1) DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- MENU SYSTEM UPDATES
-- --------------------------------------------------------

SET @tablename = "menu_items";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE (table_name = @tablename) AND (table_schema = @dbname)) > 0,
  "SELECT 1",
  "CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    link VARCHAR(255) NOT NULL,
    type ENUM('page', 'custom') DEFAULT 'custom',
    position INT DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
));
PREPARE createIfNotExists FROM @preparedStatement;
EXECUTE createIfNotExists;
DEALLOCATE PREPARE createIfNotExists;

-- --------------------------------------------------------
-- COMMENTS SYSTEM UPDATES
-- --------------------------------------------------------

SET @tablename = "comments";
SET @columnname = "parent_id";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE comments ADD COLUMN parent_id INT DEFAULT NULL AFTER post_id;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @constraintname = "fk_comments_parent";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE (constraint_name = @constraintname) AND (table_name = @tablename) AND (constraint_schema = @dbname)) > 0,
  "SELECT 1",
  "ALTER TABLE comments ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE;"
));
PREPARE addConstraint FROM @preparedStatement;
EXECUTE addConstraint;
DEALLOCATE PREPARE addConstraint;

-- --------------------------------------------------------
-- FORUM SYSTEM (NEW)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS forum_boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    parent_id INT DEFAULT NULL,
    is_category TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    is_locked TINYINT(1) DEFAULT 0,
    is_sticky TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    views INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES forum_boards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(50) NOT NULL,
    css_class VARCHAR(50) NOT NULL DEFAULT 'badge-gray'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO forum_labels (title, css_class) VALUES
('Help', 'badge-red'), ('Info', 'badge-blue'), ('Request', 'badge-green'), ('Bug', 'badge-orange');

ALTER TABLE forum_threads ADD COLUMN label_id INT DEFAULT NULL;

-- --------------------------------------------------------
-- USER AVATAR UPDATE (SAFE ADD)
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "avatar";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- FORUM THREADS SORT ORDER UPDATE (SAFE ADD)
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "forum_threads";
SET @columnname = "sort_order";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE forum_threads ADD COLUMN sort_order INT DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- MENU ICONS UPDATE (SAFE ADD)
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "menu_items";
SET @columnname = "icon";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE menu_items ADD COLUMN icon VARCHAR(100) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- CATEGORIES COLOR UPDATE (SAFE ADD)
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "categories";
SET @columnname = "color";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE categories ADD COLUMN color VARCHAR(7) DEFAULT '#3182ce';"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- WIKI SYSTEM (NEW)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `wiki_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `wiki_pages` (`slug`, `title`, `content`, `sort_order`) VALUES 
('home', 'Willkommen im Wiki', 'Das ist die Startseite deines neuen Wikis. Du kannst Seiten bearbeiten oder neue erstellen.', 0);

-- --------------------------------------------------------
-- RBAC SYSTEM (NEW)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(100) NOT NULL,
  description TEXT,
  color VARCHAR(7) DEFAULT '#718096',
  is_system TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permission_role (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Roles
INSERT IGNORE INTO roles (id, name, label, color, is_system) VALUES
(1, 'admin', 'Administrator', '#e53e3e', 1),
(2, 'editor', 'Editor', '#3182ce', 1),
(3, 'viewer', 'Viewer', '#805ad5', 1),
(4, 'member', 'Member', '#38a169', 1);

-- Seed Permissions (Standard English)
INSERT IGNORE INTO permissions (slug, description) VALUES
('forum_access', 'Access the forum'),
('forum_manage_boards', 'Manage boards & categories'),
('forum_moderate', 'Moderate forum (lock, sticky, delete)'),
('admin_login', 'Access Admin Panel'),
('posts_manage_own', 'Create/Edit Own Posts'),
('posts_manage_all', 'Manage All Posts'),
('file_upload', 'Upload Files'),
('posts_publish', 'Publish/Stick Posts'),
('categories_manage', 'Manage Categories'),
('comments_manage', 'Manage Comments'),
('users_manage', 'Manage Users'),
('settings_manage', 'Manage Settings'),
('roles_manage', 'Manage Roles & Permissions'),
('wiki_view', 'View Wiki'),
('wiki_create', 'Create Wiki Pages'),
('wiki_edit', 'Edit Wiki Pages'),
('wiki_delete', 'Delete Wiki Pages'),
('wiki_manage', 'Manage Wiki (Sort, Drag & Drop)');

-- Assign Permissions
-- Admin (All)
INSERT IGNORE INTO permission_role (role_id, permission_id) SELECT 1, id FROM permissions;
-- Editor (Forum, Posts, Files, Wiki Edit)
INSERT IGNORE INTO permission_role (role_id, permission_id) SELECT 2, id FROM permissions WHERE slug IN ('forum_access', 'forum_moderate', 'admin_login', 'posts_manage_own', 'posts_manage_all', 'file_upload', 'posts_publish', 'categories_manage', 'comments_manage', 'wiki_view', 'wiki_create', 'wiki_edit');
-- Viewer (View Only)
INSERT IGNORE INTO permission_role (role_id, permission_id) SELECT 3, id FROM permissions WHERE slug IN ('forum_access', 'admin_login', 'wiki_view');
-- Member (Forum, Wiki View)
INSERT IGNORE INTO permission_role (role_id, permission_id) SELECT 4, id FROM permissions WHERE slug IN ('forum_access', 'wiki_view');

-- Update Users Table (Safe Add Columns)
SET @dbname = DATABASE();
SET @tablename = "users";

-- Add role_id
SET @columnname = "role_id";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER email;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add Constraint fk_users_role
SET @constraintname = "fk_users_role";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE (constraint_name = @constraintname) AND (table_name = @tablename) AND (constraint_schema = @dbname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;"
));
PREPARE addConstraint FROM @preparedStatement;
EXECUTE addConstraint;
DEALLOCATE PREPARE addConstraint;

-- Add bio
SET @columnname = "bio";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add location
SET @columnname = "location";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN location VARCHAR(100) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add twitter_link
SET @columnname = "twitter_link";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN twitter_link VARCHAR(255) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add signature
SET @columnname = "signature";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN signature TEXT DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Migrate existing roles to role_id
UPDATE users SET role_id = 1 WHERE role = 'admin' AND role_id IS NULL;
UPDATE users SET role_id = 2 WHERE role = 'editor' AND role_id IS NULL;
UPDATE users SET role_id = 3 WHERE role = 'viewer' AND role_id IS NULL;
UPDATE users SET role_id = 4 WHERE role = 'member' AND role_id IS NULL;
UPDATE users SET role_id = 4 WHERE role_id IS NULL;

COMMIT;