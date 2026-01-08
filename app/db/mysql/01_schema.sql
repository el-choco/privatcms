-- 1. Table Structure

-- Table: Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  role ENUM('admin','editor','viewer') DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin (User: admin, Pass: admin123)
-- Hash: $2y$10$JmFQxjH6U6xF3JH2RrPoGeD7m9Emo9c9GkQm6b7NVQyU1S1fOeSgW
INSERT INTO users (username, password_hash, email, role)
VALUES ('admin', '$2y$10$JmFQxjH6U6xF3JH2RrPoGeD7m9Emo9c9GkQm6b7NVQyU1S1fOeSgW', 'admin@example.com', 'admin')
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
-- Note: author_id is added via procedure below to ensure compatibility if table exists
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, -- Original user_id (required)
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
  author_name VARCHAR(128),
  author_email VARCHAR(128),
  content TEXT NOT NULL,
  status ENUM('pending','approved','spam','deleted') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id)
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

-- Table: Activity Log (New)
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

-- 2. Seed Data & Schema Updates
START TRANSACTION;

-- Default Settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
  ('blog_title', 'PiperBlog'),
  ('blog_description', 'Ein einfacher Blog'),
  ('posts_per_page', '10'),
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
-- MULTI-USER SYSTEM UPDATES
-- --------------------------------------------------------

-- Add author_id to posts if not exists
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

-- Backfill author_id with existing user_id
UPDATE posts SET author_id = user_id WHERE author_id IS NULL;

-- Add Foreign Key for author_id if not exists
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

COMMIT;