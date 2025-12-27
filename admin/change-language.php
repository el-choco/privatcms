<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
    }
    if (!empty($_POST['timezone'])) {
        $_SESSION['timezone'] = $_POST['timezone'];
    }
    if (!empty($_POST['date_fmt'])) {
        $_SESSION['date_fmt'] = $_POST['date_fmt'];
    }
    $msg = 'success';
}

require_once 'header.php';

$timezones = [
    'Europe/Berlin' => 'Berlin (UTC+1/+2)',
    'Europe/London' => 'London (UTC+0/+1)',
    'Europe/Paris' => 'Paris (UTC+1/+2)',
    'Europe/Madrid' => 'Madrid (UTC+1/+2)',
    'America/New_York' => 'New York (UTC-5/-4)',
    'Asia/Tokyo' => 'Tokyo (UTC+9)',
    'UTC' => 'UTC'
];

$dateFormats = [
    'd.m.Y' => $t['language_settings']['fmt_dmy'] ?? '31.12.2025',
    'Y-m-d' => $t['language_settings']['fmt_ymd'] ?? '2025-12-31',
    'm/d/Y' => $t['language_settings']['fmt_mdy'] ?? '12/31/2025'
];

$currentTz = $_SESSION['timezone'] ?? date_default_timezone_get();
$currentFmt = $_SESSION['date_fmt'] ?? 'd.m.Y';
$currentLangSelect = $_SESSION['lang'] ?? 'de';

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];
?>

<header class="top-header" style="padding-left: 30px;">
    <h1><?= htmlspecialchars($t['language_settings']['title'] ?? 'Settings') ?></h1>
</header>

<style>
    .custom-select-wrapper { position: relative; user-select: none; width: 100%; }
    .custom-select-trigger {
        position: relative; display: flex; align-items: center; justify-content: space-between;
        padding: 12px 15px; font-size: 1.1rem; font-weight: 500; color: #2d3748;
        border: 2px solid #e2e8f0; border-radius: 6px; background: #fff; cursor: pointer; transition: all 0.2s;
    }
    .custom-select-trigger:hover { border-color: #cbd5e0; }
    .custom-options {
        position: absolute; display: none; top: 100%; left: 0; right: 0;
        background: #fff; border: 2px solid #e2e8f0; border-top: 0;
        border-radius: 0 0 6px 6px; z-index: 10; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        max-height: 200px; overflow-y: auto;
    }
    .custom-option {
        padding: 12px 15px; cursor: pointer; display: flex; align-items: center; transition: 0.2s; font-size: 1.1rem;
    }
    .custom-option:hover { background: #f7fafc; color: #3182ce; }
    .custom-option.selected { background: #ebf8ff; color: #2b6cb0; font-weight: bold; }
    .custom-option img, .custom-select-trigger img { width: 24px; height: auto; margin-right: 12px; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .arrow { border: solid #718096; border-width: 0 2px 2px 0; display: inline-block; padding: 3px; transform: rotate(45deg); margin-left: 10px; transition: 0.2s; }
    
    .open .custom-options { display: block; }
    .open .custom-select-trigger { border-color: #3182ce; border-bottom-color: transparent; border-radius: 6px 6px 0 0; }
    .open .arrow { transform: rotate(-135deg); border-color: #3182ce; }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; align-items: flex-start; padding-top: 40px; height: 100%;">
        <div class="card" style="width: 100%; max-width: 1500px; padding: 50px; border-top: 6px solid #3182ce; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center;">
            
            <?php if ($msg === 'success'): ?>
                <div class="alert-success" style="text-align: left; margin-bottom: 30px;">
                    <?= htmlspecialchars($t['language_settings']['success'] ?? 'Saved') ?>
                </div>
            <?php endif; ?>

            <h2 style="margin-top: 0; color: #2d3748;"><?= htmlspecialchars($t['language_settings']['title'] ?? 'Settings') ?></h2>
            <p style="color: #718096; margin-bottom: 40px; font-size: 1.1rem;">
                <?= htmlspecialchars($t['language_settings']['description'] ?? '') ?>
            </p>

            <form method="post" style="text-align: left;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" style="font-size: 1.1rem;"><?= htmlspecialchars($t['language_settings']['label_lang'] ?? 'Language') ?></label>
                        
                        <input type="hidden" name="lang" id="langInput" value="<?= htmlspecialchars($currentLangSelect) ?>">
                        
                        <div class="custom-select-wrapper" id="langSelect">
                            <div class="custom-select-trigger">
                                <span>
                                    <img src="<?= $languages[$currentLangSelect]['flag'] ?>" alt="flag"> 
                                    <?= htmlspecialchars($languages[$currentLangSelect]['label']) ?>
                                </span>
                                <span class="arrow"></span>
                            </div>
                            <div class="custom-options">
                                <?php foreach ($languages as $code => $data): ?>
                                    <div class="custom-option <?= $code === $currentLangSelect ? 'selected' : '' ?>" 
                                         data-value="<?= $code ?>" 
                                         data-img="<?= $data['flag'] ?>">
                                        <img src="<?= $data['flag'] ?>" alt="<?= $code ?>">
                                        <?= htmlspecialchars($data['label']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 1rem;"><?= htmlspecialchars($t['language_settings']['label_timezone'] ?? 'Timezone') ?></label>
                        <select name="timezone" class="form-control" style="padding: 12px; border: 2px solid #e2e8f0;">
                            <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>>
                                    🕐 <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 1rem;"><?= htmlspecialchars($t['language_settings']['label_datefmt'] ?? 'Date Format') ?></label>
                        <select name="date_fmt" class="form-control" style="padding: 12px; border: 2px solid #e2e8f0;">
                            <?php foreach ($dateFormats as $fmt => $label): ?>
                                <option value="<?= $fmt ?>" <?= $currentFmt === $fmt ? 'selected' : '' ?>>
                                    📅 <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 40px; text-align: center;">
                    <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem; width: 100%;">
                        <?= htmlspecialchars($t['language_settings']['btn_save'] ?? 'Save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('.custom-select-trigger').addEventListener('click', function() {
    this.closest('.custom-select-wrapper').classList.toggle('open');
});

document.querySelectorAll('.custom-option').forEach(function(option) {
    option.addEventListener('click', function() {
        const val = this.getAttribute('data-value');
        const imgSrc = this.getAttribute('data-img');
        const text = this.innerText;

        this.closest('.custom-select-wrapper').classList.remove('open');
        
        const trigger = this.closest('.custom-select-wrapper').querySelector('.custom-select-trigger span');
        trigger.innerHTML = `<img src="${imgSrc}"> ${text}`;
        
        document.getElementById('langInput').value = val;
        
        document.querySelectorAll('.custom-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
    });
});

window.addEventListener('click', function(e) {
    const select = document.querySelector('.custom-select-wrapper');
    if (!select.contains(e.target)) {
        select.classList.remove('open');
    }
});
</script>

<?php include 'footer.php'; ?>