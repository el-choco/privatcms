<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$mLang = $t_temp['messages'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_id']]);
        header("Location: messages.php?msg=deleted");
        exit;
    }
    if (isset($_POST['toggle_read'])) {
        $id = (int)$_POST['toggle_read'];
        $current = (int)$_POST['current_status'];
        $newStatus = $current === 1 ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE messages SET is_read = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        header("Location: messages.php"); 
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
    .msg-list { display: flex; flex-direction: column; gap: 15px; }
    .msg-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; transition: box-shadow 0.2s, border-color 0.2s; position: relative; }
    .msg-card.unread { border-left: 5px solid #3182ce; background: #ebf8ff; }
    .msg-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

    .msg-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border: 1px solid grey; border-radius: 8px; padding: 9px; }
    .msg-sender { font-weight: 600; font-size: 1.1rem; color: #2d3748; }
    .msg-email { font-size: 0.9rem; color: #718096; }
    .msg-date { font-size: 0.85rem; color: #a0aec0; white-space: nowrap; }

    .msg-subject { font-weight: 700; margin-bottom: 10px; color: #2b6cb0; border: 1px solid grey; border-radius: 8px; padding: 9px; }
    .msg-content { color: #4a5568; line-height: 1.6; white-space: pre-wrap; font-size: 0.95rem;border: 1px solid grey; border-radius: 8px; padding: 51px 0px 5px 9px; }

    .msg-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #edf2f7; display: flex; gap: 10px; justify-content: flex-end; }
    
    .badge-new { background: #3182ce; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; text-transform: uppercase; font-weight: bold; vertical-align: middle; margin-left: 10px; }
    
    .btn-action { background: transparent; border: 1px solid #cbd5e0; color: #4a5568; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; }
    .btn-action:hover { background: #f7fafc; border-color: #a0aec0; }
    .btn-delete { color: #e53e3e; border-color: #feb2b2; }
    .btn-delete:hover { background: #fff5f5; border-color: #e53e3e; }
    .btn-reply { background: #3182ce; border-color: #3182ce; color: white; }
    .btn-reply:hover { background: #2b6cb0; }
    :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
</style>

<div class="admin-content" style="margin: 0px 30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1 style="margin:0;"><?= htmlspecialchars($mLang['title'] ?? 'Posteingang') ?></h1>
        <div style="background:#e2e8f0; padding:5px 15px; border-radius:20px; font-weight:bold; color:#4a5568;">
            <?= count($messages) ?> <?= htmlspecialchars($mLang['count_suffix'] ?? 'Nachrichten') ?>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert success" style="background:#c6f6d5; color:#22543d; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #9ae6b4;">
            <?= htmlspecialchars($mLang['msg_deleted'] ?? 'Nachricht gelöscht.') ?>
        </div>
    <?php endif; ?>

    <div class="msg-list">
        <?php if (empty($messages)): ?>
            <div style="text-align:center; padding:60px; color:#a0aec0; border:2px dashed #e2e8f0; border-radius:12px;">
                <i class="fa-regular fa-envelope-open" style="font-size:3rem; margin-bottom:15px; display:block;"></i>
                <?= htmlspecialchars($mLang['no_messages'] ?? 'Keine Nachrichten.') ?>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg-card <?= $msg['is_read'] ? '' : 'unread' ?>">
                    <div class="msg-header">
                        <div>
                            <div class="msg-sender">
                                <?= htmlspecialchars($msg['name']) ?>
                                <?php if(!$msg['is_read']): ?>
                                    <span class="badge-new">NEU</span>
                                <?php endif; ?>
                            </div>
                            <div class="msg-email"><?= htmlspecialchars($msg['email']) ?></div>
                        </div>
                        <div class="msg-date">
                            <i class="fa-regular fa-clock"></i> <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>

                    <?php if(!empty($msg['subject'])): ?>
                        <div class="msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                    <?php endif; ?>

                    <div class="msg-content"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>

                    <div class="msg-actions">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="toggle_read" value="<?= $msg['id'] ?>">
                            <input type="hidden" name="current_status" value="<?= $msg['is_read'] ?>">
                            <button type="submit" class="btn-action">
                                <?php if($msg['is_read']): ?>
                                    <i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($mLang['mark_unread'] ?? 'Als ungelesen') ?>
                                <?php else: ?>
                                    <i class="fa-regular fa-envelope-open"></i> <?= htmlspecialchars($mLang['mark_read'] ?? 'Als gelesen') ?>
                                <?php endif; ?>
                            </button>
                        </form>
                        
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="delete_id" value="<?= $msg['id'] ?>">
                            <button type="submit" class="btn-action btn-delete" onclick="return confirm('<?= htmlspecialchars($mLang['confirm_delete'] ?? 'Wirklich löschen?') ?>')">
                                <i class="fa-solid fa-trash"></i> <?= htmlspecialchars($mLang['delete'] ?? 'Löschen') ?>
                            </button>
                        </form>
                        
                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= urlencode($msg['subject'] ?? 'Anfrage') ?>" class="btn-action btn-reply">
                            <i class="fa-solid fa-reply"></i> <?= htmlspecialchars($mLang['reply'] ?? 'Antworten') ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>