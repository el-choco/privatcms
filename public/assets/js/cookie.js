document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookieBanner');
    const btn = document.getElementById('cookieAccept');
    
    // Key im LocalStorage
    const STORAGE_KEY = 'cookie_consent';
    
    // Prüfen ob schon akzeptiert
    if (!localStorage.getItem(STORAGE_KEY)) {
        // Kurze Verzögerung für nice Animation
        setTimeout(() => {
            banner.classList.add('show');
        }, 500);
    }

    if (btn) {
        btn.addEventListener('click', () => {
            // Speichern (Gültig bis Browser-Daten gelöscht werden)
            localStorage.setItem(STORAGE_KEY, 'true');
            banner.classList.remove('show');
        });
    }
});