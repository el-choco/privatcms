<?php
declare(strict_types=1);
session_start();
/**
 * Minimal admin shell with sidebar and content area.
 * Replace this demo gate with a proper login.
 */
if (empty($_SESSION['admin'])) { $_SESSION['admin'] = true; }
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <h2 class="brand">Admin</h2>
      <nav>
        <a href="/admin/dashboard.php">Dashboard</a>
        <a href="/admin/posts.php">Posts</a>
        <a href="/admin/comments.php">Comments</a>
        <a href="/admin/files.php">Files</a>
        <a href="/admin/categories.php">Categories</a>
        <a href="/admin/settings.php">Settings</a>
      </nav>
    </aside>
    <main class="admin-content">
      <h1>Welcome</h1>
      <p>Use the sidebar to manage content.</p>
    </main>
  </div>
</body>
</html>
