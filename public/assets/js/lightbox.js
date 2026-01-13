document.addEventListener('DOMContentLoaded', () => {
    // Selektor: Wählt Bilder im Artikel-Inhalt, Seiten-Inhalt und alle Bilder direkt in einem Article-Tag (für Hero-Images)
    // Wir schließen explizit Bilder in Links aus (a img), falls diese woanders hinführen sollen (außer man will auch diese in der Lightbox)
    const selectors = [
        '.article-content img', 
        '.page-content img', 
        'article img',          // Fängt oft das Hero-Image
        '.post-hero img'        // Speziell für Index-Thumbnails (falls gewünscht)
    ];
    
    const images = document.querySelectorAll(selectors.join(', '));
    if (images.length === 0) return;

    // Modal HTML erstellen (nur einmal)
    const modal = document.createElement('div');
    modal.className = 'lightbox-modal';
    modal.id = 'lightboxModal';
    
    const closeBtn = document.createElement('span');
    closeBtn.className = 'lightbox-close';
    closeBtn.innerHTML = '&times;';
    
    // Accessibility: Text aus Dataset oder Fallback
    const closeText = document.body.dataset.lbClose || 'Close';
    closeBtn.setAttribute('aria-label', closeText);
    closeBtn.title = closeText;
    
    const modalImg = document.createElement('img');
    modalImg.className = 'lightbox-content';
    
    const captionText = document.createElement('div');
    captionText.className = 'lightbox-caption';
    
    modal.appendChild(closeBtn);
    modal.appendChild(modalImg);
    modal.appendChild(captionText);
    document.body.appendChild(modal);

    // Klick-Event für jedes gefundene Bild
    images.forEach(img => {
        // Sicherheitscheck: Ignoriere sehr kleine Icons (z.B. UI-Elemente), aber erst beim Klick prüfen oder per CSS-Klasse ausschließen
        if (img.classList.contains('no-lightbox')) return;

        img.classList.add('content-image'); // Fügt Cursor: zoom-in hinzu (via CSS)
        
        img.addEventListener('click', function(e) {
            // WICHTIG: Verhindert, dass Links ausgeführt werden (falls das Bild verlinkt ist)
            e.preventDefault(); 
            e.stopPropagation();

            modal.classList.add('show');
            modalImg.src = this.src;
            // Nutze Alt-Text oder Title als Bildunterschrift
            captionText.innerText = this.alt || this.title || ''; 
            document.body.style.overflow = 'hidden'; // Scrollen im Hintergrund verhindern
        });
    });

    // Schließen-Logik
    const closeModal = () => {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        // Bildquelle leeren, um Flackern beim nächsten Öffnen zu vermeiden
        setTimeout(() => { modalImg.src = ''; }, 300); 
    };

    closeBtn.addEventListener('click', closeModal);
    
    // Schließen bei Klick auf den Hintergrund
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Schließen mit ESC-Taste
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});