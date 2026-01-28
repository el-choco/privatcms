# 🥧 PrivateCMS

> **The leap from blog to CMS is accomplished.**

**PrivateCMS** is a modern, lightweight, and Docker-based Content Management System (CMS) designed specifically for bloggers and developers. It offers an elegant user interface, powerful management features, and is ready to run in minutes thanks to Docker.

---

## ✨ Features

PrivateCMS comes packed with features for content creators and administrators:

### 🗣️ Forum System (New)
* **Full-Featured Boards:** Create multiple discussion boards (categories) with descriptions.
* **Threads & Posts:** Users can start new topics and reply to existing ones.
* **Thread Labels:** Organize discussions with customizable, colored prefixes (e.g., "Help", "Solved") manageable via the Admin panel.
* **Rich Editor for Users:** The powerful Markdown & HTML editor is available for forum posts, including **Image Uploads**, **Color Picker**, **Emojis**, and **Icons**.
* **Moderation Tools:** Admins can **Lock** threads (read-only) or **Pin** important topics (Sticky) to the top.
* **Navigation:** Integrated **Breadcrumbs** for easy navigation through board levels.

### 📄 CMS & Pages
* **Static Pages:** Create timeless content like "About Us", "Imprint", or "Portfolio" separate from the blog feed.
* **Dynamic Navigation:** Control which pages appear in the **Header** or **Footer** menu directly from the editor.
* **SEO URLs:** Automatic, clean URL slugs for posts and pages (e.g., `/p/about-us`).

### 📝 Content Management
* **Hybrid Pro Editor:** Advanced split-view editor supporting **Markdown** AND **HTML**. Includes a native **Color Picker**, text highlighting, and extensive formatting tools.
* **Post Status:** Manage posts as *Draft*, *Published*, or *Archived*.
* **Sticky Posts:** Pin important posts to the top of the homepage (📌 Feature).
* **Categories & Tags:** Organize your content into flexible categories and use tags for better discoverability.
* **Syntax Highlighting:** Automatic highlighting of code blocks for technical blogs.
* **Extras:** Integrated **Icon Picker** (FontAwesome), **Emoji** support, and direct **Media Upload** within the flow.

### 🖼️ Media & Files
* **File Manager:** Powerful, standalone file manager (`files.php`) to upload, view, and manage all your media assets in one place.
* **Media Integration:** Insert images directly from the editor via a modal media library.
* **Lightbox Gallery:** Integrated **Lightbox** feature automatically displays images from articles and pages in a sleek, full-screen overlay when clicked.
* **Hero Images:** Set impressive cover images for your articles.
* **File Attachments:** Offer files directly within the article for download.

### 💬 Interaction & Community
* **Comment System:** Visitors can comment on articles.
* **Threaded Replies:** Admin interface supports nested replies to specific user comments for better conversation tracking.
* **Inbox System:** Integrated contact form with a secure **Inbox** (`messages.php`) in the admin panel to read and manage incoming messages directly (no mail server needed).
* **Spam Protection:** Built-in mathematical spam protection (Captcha).
* **Moderation:** Admin tools to approve, mark as spam, or delete comments.

### ⚙️ Administration & System
* **Rich Dashboard:** A "chock-full" dashboard (`dashboard.php`) featuring visitor statistics (daily views), content metrics, and quick system status at a glance.
* **Multi-User System:** Role-based permissions (**Admin**, **Editor**, **Viewer**, and **Member**) to manage team access and forum users securely.
* **Settings:** Configure blog title, description, SMTP mail server, and more directly in the admin panel.
* **Backup System:** Create and download backups of your data (JSON, CSV, or Full ZIP).
* **Activity Log:** Tracks user actions for security and transparency (now with **Pagination** and smart navigation).
* **Maintenance Mode:** Temporarily take the site offline for updates.

### 🎨 Design & UX
* **Search Function:** Integrated search bar to find content instantly.
* **Pagination:** Smart pagination for easy browsing of article archives.
* **Dark Mode:** Visitors can toggle between light and dark mode 🌓.
* **Responsive Design:** Optimized for desktop, tablet, and mobile.
* **Sidebar:** Dynamic sidebar with categories, tags cloud, and latest comments.
* **Back-to-Top:** Convenient navigation for long articles and admin lists.

### 🌍 Internationalization (i18n)
* **Multi-language:** Full support for **German 🇩🇪**, **English 🇬🇧**, **French 🇫🇷**, and **Spanish 🇪🇸** in both the frontend and backend.

---

## 🚀 Installation

PrivateCMS is optimized for **Docker**, making installation extremely simple.

### Prerequisites
* Docker & Docker Compose installed.
* Git (optional, to clone the repo).

### Step-by-Step Guide (Docker)

1.  **Clone Repository**
    ```bash
    git clone [https://github.com/el-choco/piperblog.git](https://github.com/el-choco/piperblog.git)
    cd piperblog
    ```

2.  **Run Installer**
    Use the included installation script to set up the environment and start the containers:
    *(Note: There are 4 language versions available that automatically set the default language: `docker-install.sh` (German), `docker-install-en.sh` (English), `docker-install-es.sh` (Spanish), `docker-install-fr.sh` (French))*
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *The script automatically creates the `.env` file, builds the Docker containers, and starts them.*

3.  **Access Blog**
    Once the containers are running, your blog is accessible at:
    * **Frontend:** `http://localhost:3333` (or port according to configuration)
    * **Admin Login:** `http://localhost:3333/admin`

4.  **First Login**
    Use the default credentials to log in to the admin area:
    * **Username:** `admin`
    * **Password:** `admin123`
    * *(Please change the password immediately after your first login!)*

### 🖥️ Bare Metal / Native Installation

For servers without Docker (e.g., standard Linux VPS with Ubuntu/Debian/Apache), you can use the native "1-Click" installers.

1.  Upload the project files to your server (e.g., `/var/www/privatecms`).
2.  Run the script corresponding to your language as **root**:
    * **English:** `sudo bash bare-metal-install-en.sh`
    * **German:** `sudo bash bare-metal-install.sh`
    * **Spanish:** `sudo bash bare-metal-install-es.sh`
    * **French:** `sudo bash bare-metal-install-fr.sh`
3.  The script will automatically install Apache, PHP, MySQL, configure the database, set file permissions, and create the VirtualHost.

---

## 🛠️ Tech Stack

* **Backend:** PHP 8.2+
* **Database:** MySQL 8.0
* **Web Server:** Apache
* **Frontend:** HTML5, CSS3, Vanilla JS
* **Containerization:** Docker

---

## 📂 Structure

* `admin/` - Administration interface (Backend).
* `public/` - Visible frontend for visitors (themes, assets).
* `src/` - PHP classes and core logic.
* `config/` - Configuration files and language files (.ini).
* `docker/` - Docker-specific configurations.

---

## 📄 License

This project is released under the MIT License. See [LICENSE](LICENSE) for details.


---
---

> **Der Sprung vom Blog zum CMS ist vollbracht.**

# 🥧 PrivateCMS

**PrivateCMS** ist ein modernes, leichtgewichtiges und Docker-basiertes Content-Management-System (CMS), das speziell für Blogger und Entwickler entwickelt wurde. Es bietet eine elegante Benutzeroberfläche, leistungsstarke Verwaltungsfunktionen und ist dank Docker in wenigen Minuten einsatzbereit.

---

## ✨ Features

PrivateCMS kommt vollgepackt mit Funktionen für Content-Ersteller und Administratoren:

### 🗣️ Forum-System (Neu)
* **Umfangreiche Boards:** Erstelle Diskussionsbereiche (Kategorien) mit Beschreibungen.
* **Themen & Beiträge:** Nutzer können neue Themen erstellen und auf bestehende antworten.
* **Themen-Labels:** Organisiere Diskussionen mit anpassbaren, farbigen Präfixen (z.B. "Hilfe", "Gelöst"), verwaltbar im Admin-Bereich.
* **Mächtiger Editor:** Der Editor steht auch Nutzern im Forum zur Verfügung – inklusive **Bilder-Upload**, **Farbwähler**, **Emojis** und **Icons**.
* **Moderations-Tools:** Administratoren können Themen **sperren** (Read-Only) oder wichtige Beiträge **anpinnen** (Sticky).
* **Navigation:** Integrierte **Breadcrumbs** (Krümelpfad) für eine einfache Orientierung im Forum.

### 📄 CMS & Seiten
* **Statische Seiten:** Erstelle zeitlose Inhalte wie "Über Uns", "Impressum" oder "Portfolio", getrennt vom Blog-Feed.
* **Dynamische Navigation:** Steuere direkt im Editor, ob Seiten im **Header** oder **Footer** Menü erscheinen sollen.
* **SEO-URLs:** Automatische, saubere URL-Slugs für Artikel und Seiten (z.B. `/p/ueber-uns`).

### 📝 Content Management
* **Hybrid Pro Editor:** Fortschrittlicher Split-View Editor mit Unterstützung für **Markdown** UND **HTML**. Beinhaltet einen nativen **Farbwähler**, Textmarkierung und umfangreiche Formatierungstools.
* **Beitragsstatus:** Verwalte Beiträge als *Entwurf*, *Veröffentlicht* oder *Archiviert*.
* **Sticky Posts:** Pinne wichtige Beiträge oben an die Startseite (📌 Feature).
* **Kategorien & Tags:** Organisiere deine Inhalte in flexiblen Kategorien und nutze Schlagwörter für bessere Auffindbarkeit.
* **Syntax Highlighting:** Automatische Hervorhebung von Code-Blöcken für technische Blogs.
* **Extras:** Integrierter **Icon Picker** (FontAwesome), **Emoji**-Support und direkter **Medien-Upload** im Schreibfluss.

### 🖼️ Medien & Dateien
* **Dateimanager:** Eigenständiger Dateimanager (`files.php`) zum zentralen Hochladen, Ansehen und Verwalten aller Medien-Dateien.
* **Medien-Integration:** Bilder direkt aus dem Editor über eine Mediathek einfügen.
* **Lightbox Galerie:** Integrierte **Lightbox**-Funktion zeigt Bilder in Artikeln und Seiten beim Anklicken automatisch in einer eleganten Vollbild-Ansicht.
* **Hero Images:** Setze beeindruckende Titelbilder für deine Artikel.
* **Datei-Anhänge:** Biete Dateien direkt im Artikel zum Download an.

### 💬 Interaktion & Community
* **Kommentarsystem:** Besucher können Artikel kommentieren.
* **Verschachtelte Antworten:** Admin-Oberfläche unterstützt direkte Antworten auf spezifische Nutzerkommentare (Threaded Comments).
* **Posteingang:** Integriertes Kontaktformular mit eigenem **Posteingang** (`messages.php`) im Admin-Panel zum Lesen und Verwalten von Nachrichten (kein Mailserver nötig).
* **Spamschutz:** Eingebauter mathematischer Spamschutz (Captcha).
* **Moderation:** Admin-Tools zum Genehmigen, als Spam markieren oder Löschen von Kommentaren.

### ⚙️ Administration & System
* **Umfangreiches Dashboard:** Ein prall gefülltes Dashboard (`dashboard.php`) mit Besucherstatistiken (tägliche Aufrufe), Inhalts-Metriken und Systemstatus auf einen Blick.
* **Multi-User-System:** Rollenbasiertes Rechtesystem (**Admin**, **Editor**, **Viewer** und **Mitglied**) zur sicheren Verwaltung von Team-Zugriffen und Foren-Nutzern.
* **Einstellungen:** Konfiguriere Blog-Titel, Beschreibung, SMTP-Mail-Server und mehr direkt im Admin-Panel.
* **Backup-System:** Erstelle und lade Backups deiner Daten (JSON, CSV oder Full ZIP) herunter.
* **Logbuch:** Protokolliert Benutzeraktionen für Sicherheit und Transparenz (jetzt mit **Paginierung** und Navigation).
* **Wartungsmodus:** Schalte die Seite temporär offline für Updates.

### 🎨 Design & UX
* **Suchfunktion:** Integrierte Suchleiste, um Inhalte sofort zu finden.
* **Paginierung:** Intelligente Seitennummerierung für einfaches Durchstöbern des Archivs.
* **Dark Mode:** Besucher können zwischen Hell- und Dunkelmodus wechseln 🌓.
* **Responsive Design:** Optimiert für Desktop, Tablet und Mobile.
* **Sidebar:** Dynamische Sidebar mit Kategorien, Tag-Cloud und neuesten Kommentaren.
* **Back-to-Top:** Bequeme Navigation für lange Artikel und Listen.

### 🌍 Internationalisierung (i18n)
* **Mehrsprachig:** Vollständige Unterstützung für **Deutsch 🇩🇪**, **Englisch 🇬🇧**, **Französisch 🇫🇷** und **Spanisch 🇪🇸** sowohl im Frontend als auch im Backend.

---

## 🚀 Installation

PrivateCMS ist für den Betrieb mit **Docker** optimiert, was die Installation extrem einfach macht.

### Voraussetzungen
* Docker & Docker Compose installiert.
* Git (optional, zum Klonen des Repos).

### Schritt-für-Schritt Anleitung (Docker)

1.  **Repository klonen**
    ```bash
    git clone [https://github.com/el-choco/piperblog.git](https://github.com/el-choco/piperblog.git)
    cd piperblog
    ```

2.  **Installer ausführen**
    Nutze das beiliegende Installations-Skript, um die Umgebung einzurichten und die Container zu starten:
    *(Hinweis: Es stehen 4 Sprachvarianten zur Verfügung, die automatisch die Standardsprache einstellen: `docker-install.sh` (Deutsch), `docker-install-en.sh` (Englisch), `docker-install-es.sh` (Spanisch), `docker-install-fr.sh` (Französisch))*
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *Das Skript erstellt automatisch die `.env` Datei, baut die Docker-Container und startet sie.*

3.  **Blog aufrufen**
    Sobald die Container laufen, ist dein Blog erreichbar unter:
    * **Frontend:** `http://localhost:3333` (oder Port gemäß Konfiguration)
    * **Admin-Login:** `http://localhost:3333/admin`

4.  **Erste Anmeldung**
    Nutze die Standard-Zugangsdaten für den Admin-Bereich:
    * **Benutzer:** `admin`
    * **Passwort:** `admin123`
    * *(Bitte ändere das Passwort sofort nach dem ersten Login!)*

### 🖥️ Bare Metal / Native Installation

Für Server ohne Docker (z.B. Standard Linux VPS mit Ubuntu/Debian/Apache) stehen native "1-Klick"-Installer bereit.

1.  Lade die Projektdateien auf deinen Server (z.B. nach `/var/www/privatecms`).
2.  Führe das passende Skript für deine Sprache als **root** aus:
    * **Deutsch:** `sudo bash bare-metal-install.sh`
    * **Englisch:** `sudo bash bare-metal-install-en.sh`
    * **Spanisch:** `sudo bash bare-metal-install-es.sh`
    * **Französisch:** `sudo bash bare-metal-install-fr.sh`
3.  Das Skript installiert automatisch Apache, PHP, MySQL, richtet die Datenbank ein, setzt Berechtigungen und erstellt den VirtualHost.

---

## 🛠️ Technologie-Stack

* **Backend:** PHP 8.2+
* **Datenbank:** MySQL 8.0
* **Webserver:** Apache
* **Frontend:** HTML5, CSS3, Vanilla JS
* **Containerisierung:** Docker

---

## 📂 Struktur

* `admin/` - Verwaltungsoberfläche (Backend).
* `public/` - Das für Besucher sichtbare Frontend (Themes, Assets).
* `src/` - PHP-Klassen und Kernlogik.
* `config/` - Konfigurationsdateien und Sprachdateien (.ini).
* `docker/` - Docker-spezifische Konfigurationen.

---

## 📄 Lizenz

Dieses Projekt ist unter der MIT Lizenz veröffentlicht. Siehe [LICENSE](LICENSE) für Details.