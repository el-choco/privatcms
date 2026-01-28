<?php
declare(strict_types=1);
session_start();

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if (empty($_SESSION['admin']) || $userRole !== 'admin') { header('Location: /admin/'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$lLang = $t['activity_log'] ?? [];

$limit = 50; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$totalLogs = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

$stmt = $pdo->prepare("
    SELECT l.*, u.username 
    FROM activity_log l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

require_once 'header.php';
?>

<style>
    .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center; }
    .page-btn { padding: 8px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; color: #4a5568; text-decoration: none; transition: 0.2s; font-weight: 500; }
    .page-btn:hover { background: #edf2f7; color: #2d3748; border-color: #cbd5e0; }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; cursor: default; }
    .page-info { color: #718096; font-size: 0.9rem; }

    #backToTop {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #3182ce;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        border: 2px solid white;
        transition: transform 0.2s, background 0.2s;
        z-index: 2147483647; 
    }
    #backToTop:hover { transform: translateY(-5px); background: #2b6cb0; }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px; padding-bottom: 60px;">
        <div style="width: 100%; max-width: 1500px;">
            <header style="margin-bottom: 30px; display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                    <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                        <?= htmlspecialchars($lLang['title'] ?? 'Activity Log') ?>
                    </h1>
                    <p style="color: #718096; margin-top: 5px;">
                        <?= number_format($totalLogs) ?> <?= htmlspecialchars($lLang['entries_total'] ?? 'entries total') ?>
                    </p>
                </div>
            </header>

            <div class="card" style="border-top: 5px solid #3182ce; padding:0; overflow:hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($lLang['col_time'] ?? 'Time') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($lLang['col_user'] ?? 'User') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($lLang['col_action'] ?? 'Action') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($lLang['col_details'] ?? 'Details') ?>
                            </th>
                            <th style="padding: 15px; text-align: right; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($lLang['col_ip'] ?? 'IP') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" style="padding: 30px; text-align: center; color: #a0aec0;">
                                    No logs found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition:background 0.15s;" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 15px; color: #718096; white-space: nowrap;">
                                    <?= date('Y-m-d H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td style="padding: 15px; font-weight: bold;">
                                    <?= htmlspecialchars($log['username'] ?? 'Unknown') ?>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="background: #f7fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 0.85rem;">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; color: #4a5568;"><?= htmlspecialchars($log['details']) ?></td>
                                <td style="padding: 15px; text-align: right; color: #a0aec0; font-size: 0.85rem;">
                                    <?= htmlspecialchars($log['ip_address']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <a href="?page=<?= $page - 1 ?>" class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                    &laquo; <?= htmlspecialchars($lLang['prev'] ?? 'Prev') ?>
                </a>
                
                <span class="page-info">
                    <?= htmlspecialchars($lLang['page'] ?? 'Page') ?> <strong><?= $page ?></strong> <?= htmlspecialchars($lLang['of'] ?? 'of') ?> <strong><?= $totalPages ?></strong>
                </span>

                <a href="?page=<?= $page + 1 ?>" class="page-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <?= htmlspecialchars($lLang['next'] ?? 'Next') ?> &raquo;
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<button id="backToTop" title="Go to top">&#9650;</button>

<script>
    const btn = document.getElementById('backToTop');
    let lastScrolledElement = window;

    function checkScroll(e) {
        let st = 0;
        let target = window;

        if (e && e.target && e.target.scrollTop !== undefined) {
            st = e.target.scrollTop;
            target = e.target;
        } else {
            st = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
        }

        if (st > 200) { 
            btn.style.display = "flex";
            lastScrolledElement = target;
        } else {
            btn.style.display = "none";
        }
    }

    window.addEventListener('scroll', checkScroll, true);
    
    btn.addEventListener('click', function() {
        if (lastScrolledElement && lastScrolledElement.scrollTo) {
            lastScrolledElement.scrollTo({ top: 0, behavior: 'smooth' });
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        document.documentElement.scrollTo({ top: 0, behavior: 'smooth' });
        document.body.scrollTo({ top: 0, behavior: 'smooth' });
        
        const possibleContainers = document.querySelectorAll('.content-area, main, .card');
        possibleContainers.forEach(el => {
            if (el.scrollTop > 0) el.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
</script>

<?php include 'footer.php'; ?>