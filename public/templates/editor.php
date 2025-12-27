<?php
$eContent = $editorContent ?? '';
$eName = $editorName ?? 'content';
$ePlaceholder = $editorPlaceholder ?? '...';
$eUploadUrl = $editorUploadUrl ?? '/upload-handler.php'; 
?>
<style>
    .editor-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-top: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); display: flex !important; flex-direction: column !important; }
    .editor-toolbar { background: #f8fafc; border-bottom: 1px solid #cbd5e0; padding: 10px; font-family: sans-serif; display: block !important; }
    .editor-row { display: flex !important; align-items: center !important; gap: 6px !important; margin-bottom: 8px !important; flex-wrap: wrap !important; flex-direction: row !important; }
    .editor-row:last-child { margin-bottom: 0 !important; }
    .editor-label { font-weight: 800; color: #1877f2; width: 90px; font-size: 11px; text-transform: uppercase; flex-shrink: 0; display: inline-block !important; }
    .editor-label.green { color: #42b72a; }
    .ed-btn { background: #fff !important; border: 1px solid #ddd !important; border-radius: 6px !important; font-size: 13px !important; cursor: pointer !important; color: #444 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 40px !important; height: 32px !important; transition: all 0.2s; font-weight: 500 !important; box-sizing: border-box !important; }
    .ed-btn:hover { background: #f0f2f5 !important; border-color: #bbb !important; color: #000 !important; }
    .ed-sep { width: 1px !important; height: 20px !important; background: #eee !important; margin: 0 5px !important; display: inline-block !important; }
    .editor-split { display: flex !important; min-height: 300px !important; flex: 1 !important; flex-direction: row !important; }
    .editor-area { flex: 1 !important; border-right: 1px solid #ddd !important; position: relative !important; display: flex !important; flex-direction: column !important; }
    .editor-area textarea { width: 100% !important; height: 100% !important; border: none !important; padding: 15px !important; resize: vertical !important; outline: none !important; font-family: monospace !important; font-size: 14px !important; box-sizing: border-box !important; display: block !important; flex: 1 !important; background: transparent !important; color: inherit !important; min-height: 300px !important; }
    .preview-area { flex: 1 !important; padding: 15px !important; background: #fff !important; overflow-y: auto !important; max-height: 600px !important; display: block !important; }
    .preview-area pre { background: #23241f; color: #f8f8f2; padding: 1em; border-radius: 6px; overflow-x: auto; margin: 10px 0; }
    .preview-area code { font-family: 'Fira Code', Consolas, monospace; font-size: 14px; }
    .preview-area :not(pre) > code { background-color: #23241f; color: #f8f8f2; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; border: 1px solid #3e3d32; }
    .preview-area blockquote { border-left: 4px solid #1877f2; background: #f8fafc; margin: 1em 0; padding: 10px 20px; color: #555; font-style: italic; border-radius: 0 4px 4px 0; }
    .preview-area img { max-width: 100%; height: auto; }
    .preview-area table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    .preview-area th, .preview-area td { border: 1px solid #ddd; padding: 8px; }
    .preview-area th { background-color: #f2f2f2; font-weight: bold; text-align: left; }
    .preview-area tr:nth-child(even) { background-color: #f9f9f9; }
    #mediaModal, #iconModal, #smileyModal { display: none; position: fixed !important; z-index: 10001 !important; left: 0 !important; top: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0,0,0,0.7) !important; align-items: center !important; justify-content: center !important; }
    .modal-content { background: #fff !important; padding: 20px !important; border-radius: 12px !important; width: 80% !important; max-width: 900px !important; max-height: 80vh !important; overflow-y: auto !important; display: flex !important; flex-direction: column !important; border: 1px solid #ddd !important; color: #333 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important; }
    .media-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important; gap: 15px !important; margin-top: 15px !important; }
    .icon-grid, .smiley-grid-container { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)) !important; gap: 8px !important; padding-top: 10px !important; }
    .media-item { cursor: pointer; border: 1px solid #eee; border-radius: 6px; overflow: hidden; transition: 0.2s; text-align: center; background: #f8fafc; padding: 5px; }
    .media-item:hover { border-color: #3182ce; transform: scale(1.05); }
    .media-item img { width: 100%; height: 100px; object-fit: cover; border-radius: 4px; }
    .icon-item, .smiley-box { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; padding: 10px !important; border: 1px solid #eee !important; border-radius: 6px !important; cursor: pointer !important; transition: all 0.2s !important; aspect-ratio: 1 !important; background: #fff !important; }
    .icon-item:hover, .smiley-box:hover { background: #e7f3ff !important; border-color: #1877f2 !important; color: #1877f2 !important; transform: scale(1.1) !important; }
    .icon-item i { font-size: 24px !important; margin-bottom: 5px !important; }
    .smiley-box { font-size: 28px !important; border-color: transparent !important; }
    .icon-name { font-size: 10px !important; color: #666 !important; text-align: center !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; width: 100% !important; display: block !important; }
    [data-theme="dark"] .editor-container { background: #2d3748; border-color: #4a5568; }
    [data-theme="dark"] .editor-toolbar { background: #1a202c; border-bottom-color: #4a5568; }
    [data-theme="dark"] .ed-btn { background: #2d3748 !important; border-color: #4a5568 !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .ed-btn:hover { background: #4a5568 !important; color: #fff !important; }
    [data-theme="dark"] .ed-sep { background: #4a5568 !important; }
    [data-theme="dark"] .editor-area textarea { color: #e2e8f0 !important; }
    [data-theme="dark"] .editor-area { border-right-color: #4a5568 !important; }
    [data-theme="dark"] .preview-area { background: #2d3748 !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .preview-area :not(pre) > code { background-color: #4a5568; border-color: #4a5568; }
    [data-theme="dark"] .preview-area blockquote { background: #1a202c; color: #cbd5e0; border-left-color: #4299e1; }
    [data-theme="dark"] .modal-content { background: #2d3748 !important; border-color: #4a5568 !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .media-item { background: #1a202c !important; border-color: #4a5568 !important; }
    [data-theme="dark"] .icon-item { border-color: #4a5568; }
    [data-theme="dark"] .icon-item:hover { background: #1a202c; }
    [data-theme="dark"] .icon-name { color: #a0aec0; }
    [data-theme="dark"] .smiley-box:hover { background: #1a202c; border-color: #4a5568; }
    [data-theme="dark"] .preview-area th { background-color: #4a5568; border-color: #4a5568; }
    [data-theme="dark"] .preview-area td { border-color: #4a5568; }
    [data-theme="dark"] .preview-area tr:nth-child(even) { background-color: #2d3748; }
</style>

<div class="editor-container">
    <div class="editor-toolbar">
        <div class="editor-row">
            <span class="editor-label"><?= htmlspecialchars($peLang['label_markdown'] ?? 'MARKDOWN:') ?></span>
            <button type="button" class="ed-btn" onclick="insertTag('**', '**')" title="<?= htmlspecialchars($peLang['tooltip_bold'] ?? 'Bold') ?>"><b>B</b></button>
            <button type="button" class="ed-btn" onclick="insertTag('*', '*')" title="<?= htmlspecialchars($peLang['tooltip_italic'] ?? 'Italic') ?>"><i>I</i></button>
            <button type="button" class="ed-btn" onclick="insertTag('~~', '~~')" title="<?= htmlspecialchars($peLang['tooltip_strike'] ?? 'Strike') ?>"><s>S</s></button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('# ', '')">H1</button>
            <button type="button" class="ed-btn" onclick="insertTag('## ', '')">H2</button>
            <button type="button" class="ed-btn" onclick="insertTag('### ', '')">H3</button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertLink()" title="<?= htmlspecialchars($peLang['tooltip_link'] ?? 'Link') ?>"><i class="fa-solid fa-link"></i></button>
            <button type="button" class="ed-btn" onclick="openMediaModal()" title="<?= htmlspecialchars($peLang['tooltip_image'] ?? 'Image') ?>"><i class="fa-solid fa-image"></i></button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('`', '`')" style="font-family:monospace;">`code`</button>
            <button type="button" class="ed-btn" onclick="insertTag('```\n', '\n```')" title="<?= htmlspecialchars($peLang['tooltip_code_block'] ?? 'Code Block') ?>">...</button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="openSmileyModal()" title="<?= htmlspecialchars($peLang['tooltip_emojis'] ?? 'Emojis') ?>">😀</button>
            <button type="button" class="ed-btn" onclick="openIconModal()" title="<?= htmlspecialchars($peLang['modal_icons'] ?? 'Icons') ?>"><i class="fa-solid fa-icons"></i></button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('* ', '')" title="<?= htmlspecialchars($peLang['btn_list_bullet'] ?? '• List') ?>"><i class="fa-solid fa-list-ul"></i></button>
            <button type="button" class="ed-btn" onclick="insertTag('1. ', '')" title="<?= htmlspecialchars($peLang['btn_list_number'] ?? '1. List') ?>"><i class="fa-solid fa-list-ol"></i></button>
            <button type="button" class="ed-btn" onclick="insertTable()" title="Table"><i class="fa-solid fa-table"></i></button>
            <button type="button" class="ed-btn" onclick="insertTag('> ', '')">💬</button>
            <button type="button" class="ed-btn" onclick="insertTag('\n---\n', '')">---</button>
        </div>
        <div class="editor-row">
            <span class="editor-label green"><?= htmlspecialchars($peLang['label_html'] ?? 'HTML:') ?></span>
            <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:center\'>', '</div>')" title="<?= htmlspecialchars($peLang['btn_center'] ?? 'Center') ?>"><i class="fa-solid fa-align-center"></i></button>
            <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:right\'>', '</div>')" title="<?= htmlspecialchars($peLang['btn_right'] ?? 'Right') ?>"><i class="fa-solid fa-align-right"></i></button>
            <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:left\'>', '</div>')" title="<?= htmlspecialchars($peLang['btn_left'] ?? 'Left') ?>"><i class="fa-solid fa-align-left"></i></button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="document.getElementById('html-color-picker').click()" title="<?= htmlspecialchars($peLang['btn_color'] ?? 'Color') ?>"><i class="fa-solid fa-palette"></i></button>
            <input type="color" id="html-color-picker" style="display:none">
            <button type="button" class="ed-btn" onclick="insertTag('<mark>', '</mark>')"><?= htmlspecialchars($peLang['btn_mark'] ?? 'Mark') ?></button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('<small>', '</small>')">Small</button>
            <button type="button" class="ed-btn" onclick="insertTag('<big>', '</big>')">Large</button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('<u>', '</u>')"><u>U</u></button>
            <button type="button" class="ed-btn" onclick="insertTag('<sup>', '</sup>')">x²</button>
            <button type="button" class="ed-btn" onclick="insertTag('<sub>', '</sub>')">H₂O</button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('<details><summary>Spoiler</summary>', '</details>')">👁 Spoiler</button>
            <button type="button" class="ed-btn" onclick="insertTag('<br>', '')" style="background:#e7f3ff; border-color:#1877f2; color:#1877f2; font-weight:bold;">&lt;br&gt;</button>
            <div class="ed-sep"></div>
            <button type="button" class="ed-btn" onclick="insertTag('<div>\n\n', '\n\n</div>')" title="HTML Render Block" style="font-family:monospace; font-weight:bold; color:#d53f8c; border-color:#d53f8c;">&lt;/&gt;</button>
        </div>
    </div>
    <div class="editor-split">
        <div class="editor-area">
            <textarea name="<?= htmlspecialchars($eName) ?>" id="markdown-input" class="wiki-editor" placeholder="<?= htmlspecialchars($ePlaceholder) ?>" spellcheck="false"><?= htmlspecialchars($eContent) ?></textarea>
        </div>
        <div id="preview-box" class="preview-area"></div>
    </div>
</div>

<div id="mediaModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3><?= htmlspecialchars($peLang['modal_media'] ?? 'Media') ?></h3>
            <div style="display:flex; gap:10px;">
                <input type="file" id="uploadInput" style="display:none" onchange="uploadFile(this)">
                <button class="ed-btn" onclick="document.getElementById('uploadInput').click()"><?= htmlspecialchars($peLang['btn_upload'] ?? 'Upload') ?></button>
                <button class="ed-btn" onclick="document.getElementById('mediaModal').style.display='none'"><?= htmlspecialchars($peLang['btn_close'] ?? 'Close') ?></button>
            </div>
        </div>
        <div id="uploadStatus" style="font-size:12px; margin-bottom:10px; display:none;"></div>
        <div class="media-grid" id="mediaGrid">
            <?php if(isset($allFiles) && is_array($allFiles)): foreach($allFiles as $f): ?>
                <div class="media-item" onclick="selectImage('<?= htmlspecialchars($f) ?>')">
                    <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                        <img src="/uploads/<?= htmlspecialchars($f) ?>">
                    <?php else: ?>
                        <div style="font-size:3rem; line-height:100px; height:100px; color:#666;">📄</div>
                        <div style="font-size:10px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($f) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div id="iconModal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0;"><?= htmlspecialchars($peLang['modal_icons'] ?? 'Icons') ?></h3>
            <button class="ed-btn" onclick="document.getElementById('iconModal').style.display='none'"><?= htmlspecialchars($peLang['btn_close'] ?? 'Close') ?></button>
        </div>
        <input type="text" id="iconSearch" class="form-input" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" placeholder="<?= htmlspecialchars($peLang['placeholder_icons'] ?? 'Search...') ?>" onkeyup="filterIcons()">
        <div id="iconGrid" class="icon-grid"></div>
    </div>
</div>

<div id="smileyModal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0;"><?= htmlspecialchars($peLang['tooltip_emojis'] ?? 'Emojis') ?></h3>
            <button class="ed-btn" onclick="document.getElementById('smileyModal').style.display='none'"><?= htmlspecialchars($peLang['btn_close'] ?? 'Close') ?></button>
        </div>
        <div id="smileyGrid" class="smiley-grid-container"></div>
    </div>
</div>

<script>
<?php include __DIR__ . '/data_emojis.php'; ?>
<?php include __DIR__ . '/data_icons.php'; ?>

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('markdown-input');
    const preview = document.getElementById('preview-box');
    if (input && preview) {
        const updatePreview = () => {
            if (typeof marked !== 'undefined') {
                preview.innerHTML = marked.parse(input.value);
                if (window.hljs) hljs.highlightAll();
            }
        };
        input.addEventListener('input', updatePreview);
        setTimeout(updatePreview, 100);

        let activeWindow = null;
        input.addEventListener('mouseenter', () => { activeWindow = input; });
        preview.addEventListener('mouseenter', () => { activeWindow = preview; });

        const syncScroll = (src, dest) => {
            if (activeWindow !== src) return;
            const percentage = src.scrollTop / (src.scrollHeight - src.clientHeight);
            dest.scrollTop = percentage * (dest.scrollHeight - dest.clientHeight);
        };

        input.addEventListener('scroll', () => syncScroll(input, preview));
        preview.addEventListener('scroll', () => syncScroll(preview, input));
    }
    const cp = document.getElementById('html-color-picker');
    if (cp) cp.addEventListener('change', e => insertTag(`<span style="color:${e.target.value}">`, '</span>'));
    window.insertTag = function(open, close = '') {
        if (!input) return;
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const scrollTop = input.scrollTop;
        const text = input.value.substring(start, end);
        let middle = text; 
        let suffix = '';
        if (middle.endsWith(' ')) { middle = middle.trimEnd(); suffix = ' '; }
        const replacement = open + middle + close + suffix;
        input.value = input.value.substring(0, start) + replacement + input.value.substring(end);
        const newPos = start + open.length + middle.length + (middle.length === 0 ? 0 : close.length);
        input.focus({ preventScroll: true });
        input.setSelectionRange(newPos, newPos);
        input.scrollTop = scrollTop;
        input.dispatchEvent(new Event('input'));
    };
    window.insertLink = function() {
        if (!input) return;
        const url = prompt("<?= htmlspecialchars($peLang['enter_url'] ?? 'URL:') ?>", "https://");
        if (url) insertTag(`[${input.value.substring(input.selectionStart, input.selectionEnd) || 'Link'}](${url})`, '');
    };
    window.insertTable = function() {
        if (!input) return;
        const table = "\n| Header 1 | Header 2 | Header 3 |\n| :--- | :---: | ---: |\n| Cell 1 | Cell 2 | Cell 3 |\n| Cell 4 | Cell 5 | Cell 6 |\n";
        insertTag(table, '');
    };
    const smGrid = document.getElementById('smileyGrid');
    if(smGrid && typeof smileys !== 'undefined') {
        smileys.forEach(s => {
            const sp = document.createElement('div');
            sp.className = 'smiley-box';
            sp.innerText = s;
            sp.onclick = () => { insertTag(s, ''); document.getElementById('smileyModal').style.display='none'; };
            smGrid.appendChild(sp);
        });
    }
    const iconGrid = document.getElementById('iconGrid');
    if (iconGrid && typeof icons !== 'undefined') {
        window.renderIcons = (filter = "") => {
            iconGrid.innerHTML = "";
            const lower = filter.toLowerCase();
            icons.forEach(cls => {
                if (cls.toLowerCase().includes(lower)) {
                    const d = document.createElement('div');
                    d.className = 'icon-item';
                    d.innerHTML = `<i class="${cls}"></i><div class="icon-name">${cls.replace(/fa-(solid|brands|regular) fa-/,'')}</div>`;
                    d.onclick = () => { insertTag(`<i class="${cls}"></i>`, ''); document.getElementById('iconModal').style.display='none'; };
                    iconGrid.appendChild(d);
                }
            });
        };
    }
});
function openSmileyModal() { document.getElementById('smileyModal').style.display='flex'; }
function openIconModal() { document.getElementById('iconModal').style.display='flex'; if(window.renderIcons) window.renderIcons(); document.getElementById('iconSearch').focus(); }
function openMediaModal() { document.getElementById('mediaModal').style.display='flex'; }
function closeIconModal() { document.getElementById('iconModal').style.display='none'; }
function filterIcons() { if(window.renderIcons) window.renderIcons(document.getElementById('iconSearch').value); }
function selectImage(f) { insertTag(`![Image](/uploads/${f})`, ''); document.getElementById('mediaModal').style.display='none'; }
function uploadFile(el) {
    if(el.files.length===0) return;
    const fd = new FormData(); fd.append('file', el.files[0]);
    const st = document.getElementById('uploadStatus'); 
    st.style.display='block'; st.innerText='Uploading...';
    fetch('<?= htmlspecialchars($eUploadUrl) ?>', { method:'POST', body:fd })
    .then(r=>r.json()).then(d=>{
        if(d.status==='ok') {
            st.innerText='Uploaded!';
            const grid = document.getElementById('mediaGrid');
            const div = document.createElement('div'); div.className='media-item';
            div.onclick=()=>selectImage(d.filename);
            if(d.filename.match(/\.(jpg|jpeg|png|gif|webp)$/i)) div.innerHTML=`<img src="${d.url}">`;
            else div.innerHTML=`<div style="font-size:3rem;line-height:100px;height:100px;color:#666;">📄</div><div style="font-size:10px">${d.filename}</div>`;
            grid.insertBefore(div, grid.firstChild);
            setTimeout(()=>{st.style.display='none'}, 1500);
        } else { st.innerText='Error: '+d.error; }
    });
    el.value='';
}
</script>