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

$logs = $pdo->query("
    SELECT l.*, u.username 
    FROM activity_log l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 100
")->fetchAll();

require_once 'header.php';
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1200px;">
            <header style="margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 1.5rem; color: #1a202c;">
                    <?= htmlspecialchars($lLang['title'] ?? 'Activity Log') ?>
                </h1>
                <p style="color: #718096; margin-top: 5px;">
                    <?= htmlspecialchars($lLang['subtitle'] ?? 'Last 100 events') ?>
                </p>
            </header>

            <div class="card" style="border-top: 4px solid #805ad5;">
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
                        <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
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
                            <td style="padding: 15px;"><?= htmlspecialchars($log['details']) ?></td>
                            <td style="padding: 15px; text-align: right; color: #a0aec0; font-size: 0.85rem;">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>