<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_label'])) {
        $title = trim($_POST['title'] ?? '');
        $css = $_POST['css_class'] ?? 'badge-gray';
        if ($title) {
            $pdo->prepare("INSERT INTO forum_labels (title, css_class) VALUES (?, ?)")->execute([$title, $css]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM forum_labels WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    }
    header("Location: forum-labels.php");
    exit;
}

$labels = $pdo->query("SELECT * FROM forum_labels ORDER BY title ASC")->fetchAll();
$colors = [
    'badge-red' => 'Rot', 'badge-orange' => 'Orange', 'badge-green' => 'Grün',
    'badge-blue' => 'Blau', 'badge-purple' => 'Lila', 'badge-gray' => 'Grau', 'badge-dark' => 'Dunkel'
];

include 'header.php';
?>

<style>
.badge { display:inline-block; padding:4px 8px; border-radius:4px; font-size:0.75rem; font-weight:700; text-transform:uppercase; color:#fff; }
.badge-red { background:#e53e3e; } .badge-orange { background:#dd6b20; } .badge-green { background:#38a169; }
.badge-blue { background:#3182ce; } .badge-purple { background:#805ad5; } .badge-gray { background:#718096; } .badge-dark { background:#2d3748; }
.color-opt { cursor:pointer; margin-right:10px; display:inline-block; }
.color-opt input { display:none; }
.color-opt span { display:inline-block; padding:5px 10px; border-radius:4px; opacity:0.5; border:2px solid transparent; }
.color-opt input:checked + span { opacity:1; border-color:#333; transform:scale(1.1); }
</style>

<div class="content-area" style="padding: 20px; display:flex; justify-content:center;">
    <div style="width:100%; max-width:1500px;">
        <h1>Forum Labels (Präfixe)</h1>

        <div class="card" style="padding:20px; margin-bottom:30px; background:#fff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border-top: 5px solid #3182ce;">
            <h3>Neues Label erstellen</h3>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Titel</label>
                    <input type="text" name="title" required placeholder="z.B. Frage, Gelöst, Wichtig" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;">
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:10px; font-weight:bold;">Farbe wählen</label>
                    <div style="display:flex; flex-wrap:wrap;">
                        <?php foreach($colors as $cls => $name): ?>
                            <label class="color-opt">
                                <input type="radio" name="css_class" value="<?= $cls ?>" <?= $cls==='badge-gray'?'checked':'' ?>>
                                <span class="badge <?= $cls ?>"><?= $name ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" name="create_label" class="btn" style="background:#3182ce; color:#fff; padding:10px 20px; border:none; border-radius:4px; cursor:pointer;">Erstellen</button>
            </form>
        </div>

        <div class="card" style="padding:0; overflow:hidden; background:#fff; border-radius:8px; border-top: 5px solid #3182ce;">
            <table style="width:100%; border-collapse:collapse;">
                <thead style="background:#f7fafc; border-bottom:1px solid #e2e8f0;">
                    <tr>
                        <th style="padding:15px; text-align:left;">Vorschau</th>
                        <th style="padding:15px; text-align:left;">Klasse</th>
                        <th style="padding:15px; text-align:right;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($labels as $l): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:15px;">
                            <span class="badge <?= htmlspecialchars($l['css_class']) ?>">
                                <?= htmlspecialchars($l['title']) ?>
                            </span>
                        </td>
                        <td style="padding:15px; color:#718096;"><?= htmlspecialchars($l['css_class']) ?></td>
                        <td style="padding:15px; text-align:right;">
                            <form method="POST" onsubmit="return confirm('Löschen?');">
                                <input type="hidden" name="delete_id" value="<?= $l['id'] ?>">
                                <button type="submit" style="background:none; border:none; color:red; cursor:pointer;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>