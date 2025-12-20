<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$iniPath = __DIR__ . '/../config/config.ini';
$ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED) ?: [];
$langOverride = isset($_GET['lang']) ? (string)$_GET['lang'] : null;
$i18n = I18n::fromConfig($ini, $langOverride);

$dbCfg = $ini['database'] ?? [];
$pdo = null;
try { $db = new Database($dbCfg); $pdo = $db->pdo(); } catch (Throwable $e) {}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$admin = $_SESSION['admin'] ?? ['username' => 'admin'];
$adminUser = (string)($admin['username'] ?? 'admin');
$adminId = 1;
try {
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $adminUser]);
        $adminId = (int)($stmt->fetchColumn() ?: 1);
    }
} catch (Throwable $e) {}

/** Helpers */
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}
function boolIni($val): bool {
    if (is_bool($val)) return $val;
    $v = strtolower((string)$val);
    return in_array($v, ['1','true','yes','on'], true);
}
function sanitize_html(string $html): string {
    // remove scripts and event handlers
    $html = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html);
    $html = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace('/\son[a-z]+\s*=\s*\'[^\']*\'/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*"\s*javascript:[^"]*"/i', '$1="#"', $html);
    $html = preg_replace('/(href|src)\s*=\s*\'\s*javascript:[^\']*\'/i', '$1="#"', $html);
    // allowlist of tags
    $allowed = '<p><br><strong><em><u><code><pre><blockquote><a><img><h2><h3><ul><ol><li>';
    $html = strip_tags($html, $allowed);
    return $html;
}
function markup_to_html(string $txt): string {
    // escape first
    $safe = htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // basic tags
    $rep = [
        '/\[b\](.*?)\[\/b\]/si' => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/si' => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/si' => '<u>$1</u>',
        '/\[h2\](.*?)\[\/h2\]/si' => '<h2>$1</h2>',
        '/\[h3\](.*?)\[\/h3\]/si' => '<h3>$1</h3>',
        '/\[quote\](.*?)\[\/quote\]/si' => '<blockquote>$1</blockquote>',
    ];
    foreach ($rep as $re => $to) { $safe = preg_replace($re, $to, $safe); }
    // code (escaped)
    $safe = preg_replace_callback('/\[code\](.*?)\[\/code\]/si', function($m){
        return '<pre><code>'.htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</code></pre>';
    }, $safe);
    // lists
    $safe = preg_replace('/\[li\](.*?)\[\/li\]/si', '<li>$1</li>', $safe);
    $safe = preg_replace('/\[ul\](.*?)\[\/ul\]/si', '<ul>$1</ul>', $safe);
    $safe = preg_replace('/\[ol\](.*?)\[\/ol\]/si', '<ol>$1</ol>', $safe);
    // links and images
    $safe = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/si', '<a href="$1">$2</a>', $safe);
    $safe = preg_replace('/\[url\](.*?)\[\/url\]/si', '<a href="$1">$1</a>', $safe);
    $safe = preg_replace('/\[img\](.*?)\[\/img\]/si', '<img src="$1" alt="">', $safe);
    // line breaks
    $safe = nl2br($safe);
    return sanitize_html($safe);
}

/** Settings */
$softDelete = boolIni($ini['system']['soft_delete'] ?? true);
$deleteFiles = boolIni($ini['system']['delete_files'] ?? false);

/** Actions */
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $error = $i18n->t('posts.error_csrf');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $title = trim((string)($_POST['title'] ?? ''));
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $excerpt = trim((string)($_POST['excerpt'] ?? ''));
                $format = (string)($_POST['format'] ?? 'markup');
                $contentRaw = (string)($_POST[$format === 'html' ? 'content_html' : 'content_markup'] ?? '');
                if ($title === '') {
                    $error = $i18n->t('posts.error_title_required');
                } else {
                    $slug = slugify($title);
                    // ensure unique slug
                    $base = $slug; $n = 1;
                    if ($pdo) {
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE slug = :s');
                        while (true) {
                            $stmt->execute([':s' => $slug]);
                            if ((int)$stmt->fetchColumn() === 0) break;
                            $slug = $base . '-' . $n++;
                        }
                        $contentHtml = $format === 'html' ? sanitize_html($contentRaw) : markup_to_html($contentRaw);
                        $stmt = $pdo->prepare('INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, status, created_at) VALUES (:uid, :cid, :title, :slug, :excerpt, :content, :status, NOW())');
                        $stmt->execute([
                            ':uid' => $adminId,
                            ':cid' => $categoryId ?: null,
                            ':title' => $title,
                            ':slug' => $slug,
                            ':excerpt' => $excerpt,
                            ':content' => $contentHtml,
                            ':status' => 'draft',
                        ]);
                        $notice = $i18n->t('posts.created');
                    }
                }
            } elseif ($action === 'publish' || $action === 'unpublish') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id && $pdo) {
                    $status = $action === 'publish' ? 'published' : 'draft';
                    $stmt = $pdo->prepare('UPDATE posts SET status = :st, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([':st' => $status, ':id' => $id]);
                    $notice = $i18n->t('posts.updated');
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id && $pdo) {
                    if ($softDelete) {
                        $stmt = $pdo->prepare('UPDATE posts SET status = :st, updated_at = NOW() WHERE id = :id');
                        $stmt->execute([':st' => 'archived', ':id' => $id]);
                        $notice = $i18n->t('posts.updated');
                    } else {
                        // delete comments first (FK)
                        $pdo->prepare('DELETE FROM comments WHERE post_id = :id')->execute([':id' => $id]);
                        // get hero_image path
                        $stmt = $pdo->prepare('SELECT hero_image FROM posts WHERE id = :id');
                        $stmt->execute([':id' => $id]);
                        $hero = (string)($stmt->fetchColumn() ?: '');
                        // delete post
                        $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $id]);
                        // optionally delete hero file (relative path)
                        if ($deleteFiles && $hero) {
                            $path = dirname(__DIR__) . '/' . ltrim($hero, '/');
                            if (is_file($path)) @unlink($path);
                        }
                        $notice = $i18n->t('posts.deleted');
                    }
                }
            }
        } catch (Throwable $e) {
            $error = $i18n->t('posts.error_db');
        }
    }
}

/** Filters */
$filter = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

/** Load categories */
$categories = [];
try {
    if ($pdo) {
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {}

/** Load posts */
$posts = [];
$total = 0;
try {
    if ($pdo) {
        $where = [];
        $params = [];
        if ($filter && in_array($filter, ['draft','published','archived'], true)) {
            $where[] = 'p.status = :st';
            $params[':st'] = $filter;
        }
        if ($search !== '') {
            $where[] = 'p.title LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $countSql = "SELECT COUNT(*) FROM posts p $sqlWhere";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT p.id, p.title, p.slug, p.status, p.created_at, p.updated_at, c.name AS category
                FROM posts p
                LEFT JOIN categories c ON c.id = p.category_id
                $sqlWhere
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {}

function statusLabel(I18n $i18n, string $st): string {
    return $st === 'published' ? $i18n->t('posts.status_published') :
           ($st === 'archived' ? $i18n->t('posts.status_archived') : $i18n->t('posts.status_draft'));
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($i18n->t('posts.manage_title')) ?> – PiperBlog Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/admin/assets/styles/admin.css" rel="stylesheet">
  <style>
    .editor-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 10px}
    .editor-toolbar .btn{background:#f8fafc;color:#111827;border:1px solid #e5e7eb}
    .editor-tabs{display:flex;gap:8px;margin:0 0 8px}
    .editor-tabs .badge{cursor:pointer}
    .hidden{display:none}
    textarea.input{min-height:220px}
  </style>
</head>
<body>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <h2 class="brand">Admin</h2>
      <nav>
        <a href="/admin/dashboard.php"><?= htmlspecialchars($i18n->t('nav.dashboard')) ?></a>
        <a href="/admin/posts.php"><?= htmlspecialchars($i18n->t('nav.posts')) ?></a>
        <a href="/admin/comments.php"><?= htmlspecialchars($i18n->t('nav.comments')) ?></a>
        <a href="/admin/files.php"><?= htmlspecialchars($i18n->t('nav.files')) ?></a>
        <a href="/admin/categories.php"><?= htmlspecialchars($i18n->t('nav.categories')) ?></a>
        <a href="/admin/settings.php"><?= htmlspecialchars($i18n->t('nav.settings')) ?></a>
        <a href="/admin/logout.php"><?= htmlspecialchars($i18n->t('nav.logout')) ?></a>
      </nav>
    </aside>
    <main class="admin-content">
      <div class="topbar">
        <h1 style="margin:0"><?= htmlspecialchars($i18n->t('posts.manage_title')) ?></h1>
        <div class="lang-switch">
          <?php $cur = $i18n->locale(); ?>
          <a href="?lang=de" class="<?= $cur==='de'?'active':'' ?>">DE</a>
          <a href="?lang=en" class="<?= $cur==='en'?'active':'' ?>">EN</a>
        </div>
      </div>

      <p class="muted"><?= htmlspecialchars($i18n->t('dashboard.logged_in_as', ['{user}' => (string)$adminUser])) ?></p>

      <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <section class="panel">
        <h3><?= htmlspecialchars($i18n->t('posts.button_new')) ?></h3>
        <form method="post" class="form" autocomplete="off" action="/admin/posts.php">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <label for="title"><?= htmlspecialchars($i18n->t('posts.label_title')) ?></label>
          <input class="input" type="text" id="title" name="title" placeholder="<?= htmlspecialchars($i18n->t('posts.label_title')) ?>" required>

          <label for="category_id"><?= htmlspecialchars($i18n->t('posts.label_category')) ?></label>
          <select class="input" id="category_id" name="category_id">
            <option value=""><?= htmlspecialchars($i18n->t('posts.filter_all')) ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <label for="excerpt"><?= htmlspecialchars($i18n->t('posts.label_excerpt')) ?></label>
          <textarea class="input" id="excerpt" name="excerpt" placeholder="<?= htmlspecialchars($i18n->t('posts.label_excerpt')) ?>"></textarea>

          <div class="editor-tabs">
            <span class="badge" id="tabMarkup"><?= htmlspecialchars($i18n->t('posts.tab_markup')) ?></span>
            <span class="badge" id="tabHtml"><?= htmlspecialchars($i18n->t('posts.tab_html')) ?></span>
          </div>
          <input type="hidden" name="format" id="format" value="markup">

          <div id="toolbarMarkup" class="editor-toolbar">
            <button type="button" class="btn" onclick="wrapTag('content_markup','[b]','[/b]')"><?= htmlspecialchars($i18n->t('posts.toolbar.bold')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[i]','[/i]')"><?= htmlspecialchars($i18n->t('posts.toolbar.italic')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[u]','[/u]')"><?= htmlspecialchars($i18n->t('posts.toolbar.underline')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[h2]','[/h2]')">H2</button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[h3]','[/h3]')">H3</button>
            <button type="button" class="btn" onclick="insertLink('content_markup','[url=','[/url]')"><?= htmlspecialchars($i18n->t('posts.toolbar.link')) ?></button>
            <button type="button" class="btn" onclick="insertImage('content_markup','[img]','[/img]')"><?= htmlspecialchars($i18n->t('posts.toolbar.image')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[code]','[/code]')"><?= htmlspecialchars($i18n->t('posts.toolbar.code')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[quote]','[/quote]')"><?= htmlspecialchars($i18n->t('posts.toolbar.quote')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[ul]\n[li]','[/li]\n[/ul]')">UL</button>
            <button type="button" class="btn" onclick="wrapTag('content_markup','[ol]\n[li]','[/li]\n[/ol]')">OL</button>
          </div>
          <textarea class="input" id="content_markup" name="content_markup" placeholder="<?= htmlspecialchars($i18n->t('posts.label_content')) ?>"></textarea>

          <div id="toolbarHtml" class="editor-toolbar hidden">
            <button type="button" class="btn" onclick="wrapTag('content_html','<strong>','</strong>')"><?= htmlspecialchars($i18n->t('posts.toolbar.bold')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<em>','</em>')"><?= htmlspecialchars($i18n->t('posts.toolbar.italic')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<u>','</u>')"><?= htmlspecialchars($i18n->t('posts.toolbar.underline')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<h2>','</h2>')">H2</button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<h3>','</h3>')">H3</button>
            <button type="button" class="btn" onclick="insertLink('content_html','<a href=\"','\">','</a>')"><?= htmlspecialchars($i18n->t('posts.toolbar.link')) ?></button>
            <button type="button" class="btn" onclick="insertImage('content_html','<img src=\"','\" alt=\"\">')"><?= htmlspecialchars($i18n->t('posts.toolbar.image')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<pre><code>','</code></pre>')"><?= htmlspecialchars($i18n->t('posts.toolbar.code')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<blockquote>','</blockquote>')"><?= htmlspecialchars($i18n->t('posts.toolbar.quote')) ?></button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<ul>\n<li>','</li>\n</ul>')">UL</button>
            <button type="button" class="btn" onclick="wrapTag('content_html','<ol>\n<li>','</li>\n</ol>')">OL</button>
          </div>
          <textarea class="input hidden" id="content_html" name="content_html" placeholder="<?= htmlspecialchars($i18n->t('posts.label_content')) ?>"></textarea>

          <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('posts.button_new')) ?></button>
        </form>
      </section>

      <section class="panel" style="margin-top:16px">
        <h3><?= htmlspecialchars($i18n->t('posts.title')) ?></h3>

        <form method="get" class="form form-2col" autocomplete="off" action="/admin/posts.php">
          <div>
            <label for="q"><?= htmlspecialchars($i18n->t('posts.search_placeholder')) ?></label>
            <input class="input" type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars($i18n->t('posts.search_placeholder')) ?>">
          </div>
          <div>
            <label for="status"><?= htmlspecialchars($i18n->t('posts.label_status')) ?></label>
            <select class="input" id="status" name="status">
              <option value=""><?= htmlspecialchars($i18n->t('posts.filter_all')) ?></option>
              <option value="draft" <?= $filter==='draft'?'selected':'' ?>><?= htmlspecialchars($i18n->t('posts.status_draft')) ?></option>
              <option value="published" <?= $filter==='published'?'selected':'' ?>><?= htmlspecialchars($i18n->t('posts.status_published')) ?></option>
              <option value="archived" <?= $filter==='archived'?'selected':'' ?>><?= htmlspecialchars($i18n->t('posts.status_archived')) ?></option>
            </select>
          </div>
          <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('common.search')) ?></button>
        </form>

        <ul class="list">
          <?php if (!$posts): ?>
            <li><?= htmlspecialchars($i18n->t('posts.no_posts')) ?></li>
          <?php else: foreach ($posts as $p): ?>
            <li>
              <div>
                <strong><?= htmlspecialchars((string)$p['title']) ?></strong>
                <div class="muted"><?= htmlspecialchars((string)($p['category'] ?? '–')) ?> • <?= htmlspecialchars(statusLabel($i18n, (string)$p['status'])) ?> • <?= htmlspecialchars((string)$p['slug']) ?></div>
              </div>
              <div>
                <form method="post" style="display:inline" action="/admin/posts.php">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <?php if ((string)$p['status'] !== 'published'): ?>
                    <input type="hidden" name="action" value="publish">
                    <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('posts.button_publish')) ?></button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="unpublish">
                    <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('posts.button_unpublish')) ?></button>
                  <?php endif; ?>
                </form>
                <form method="post" style="display:inline" action="/admin/posts.php" onsubmit="return confirm('<?= htmlspecialchars($i18n->t('posts.confirm_delete')) ?>')">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn" type="submit"><?= htmlspecialchars($i18n->t('posts.button_delete')) ?></button>
                </form>
              </div>
            </li>
          <?php endforeach; endif; ?>
        </ul>

        <?php
        $pages = max(1, (int)ceil($total / $perPage));
        if ($pages > 1):
        ?>
        <div class="muted">
          <?php for ($i=1; $i<=$pages; $i++): ?>
            <?php
            $qs = http_build_query(['q' => $search, 'status' => $filter, 'page' => $i, 'lang' => $i18n->locale()]);
            ?>
            <a href="/admin/posts.php?<?= htmlspecialchars($qs) ?>" class="badge <?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script>
  function wrapTag(id, before, after){
    var ta = document.getElementById(id); if(!ta) return;
    var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
    var v = ta.value; var sel = v.substring(s,e) || '';
    var rep = before + sel + after;
    ta.value = v.substring(0,s) + rep + v.substring(e);
    ta.focus(); ta.selectionStart = s + before.length; ta.selectionEnd = s + before.length + sel.length;
  }
  function insertLink(id, before, middle, after){
    var url = prompt('URL:','https://'); if(!url) return;
    if(middle){ wrapTag(id, before + url + middle, after); } else { wrapTag(id, before + url + ']', '[/url]'); }
  }
  function insertImage(id, before, after){
    var url = prompt('Image URL:','https://'); if(!url) return; wrapTag(id, before + url + (after||'') , after ? '' : '[/img]');
  }
  function setMode(mode){
    document.getElementById('format').value = mode;
    document.getElementById('content_markup').classList.toggle('hidden', mode!=='markup');
    document.getElementById('toolbarMarkup').classList.toggle('hidden', mode!=='markup');
    document.getElementById('content_html').classList.toggle('hidden', mode!=='html');
    document.getElementById('toolbarHtml').classList.toggle('hidden', mode!=='html');
    document.getElementById('tabMarkup').classList.toggle('active', mode==='markup');
    document.getElementById('tabHtml').classList.toggle('active', mode==='html');
  }
  document.getElementById('tabMarkup').addEventListener('click', function(){ setMode('markup'); });
  document.getElementById('tabHtml').addEventListener('click', function(){ setMode('html'); });
  setMode('markup');
  </script>
</body>
</html>
