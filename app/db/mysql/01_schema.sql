-- 1. Table-Struktur

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  role ENUM('admin','editor','viewer') DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user (password: admin123)
INSERT INTO users (username, password_hash, email, role)
VALUES ('admin', '$2y$10$JmFQxjH6U6xF3JH2RrPoGeD7m9Emo9c9GkQm6b7NVQyU1S1fOeSgW', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), email=VALUES(email), role=VALUES(role);

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(128) UNIQUE NOT NULL
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value TEXT
);

-- Posts 
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

CREATE TABLE IF NOT EXISTS files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(128),
  size INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- -----------------------------------------------------------------
-- 2. Seed data
-- -----------------------------------------------------------------
START TRANSACTION;

-- Standard-Settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
  ('blog_title', 'PiperBlog'),
  ('blog_description', 'Ein einfacher Blog'),
  ('posts_per_page', '10'),
  ('debug_mode', '0'),
  ('error_logging', '0'),
  ('maintenance_mode', '0');

-- Categories
INSERT IGNORE INTO categories (name, slug) VALUES
  ('Allgemein','allgemein'),
  ('Docker','docker'),
  ('Portainer','portainer'),
  ('Testkategorie','testkategorie');

-- Example-Posts
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

-- Example-Comments
INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'el-choco', 'demo@example.com', 'Willkommen! Viel Erfolg mit dem Blog.', 'approved', NOW() - INTERVAL 13 DAY
FROM posts WHERE slug='hallo-an-alle-besucher';

INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'Heino', 'heino@example.com', 'Guter Einstieg in Docker Compose.', 'approved', NOW() - INTERVAL 9 DAY
FROM posts WHERE slug='docker-compose-quickstart';

INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
SELECT id, 'Gast', 'gast@example.com', 'Könntest du ein Beispiel-Compose posten?', 'pending', NOW() - INTERVAL 8 DAY
FROM posts WHERE slug='docker-compose-quickstart';

COMMIT;