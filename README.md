# 🥧 PiperBlog

**PiperBlog** is a modern, lightweight, and Docker-based Content Management System (CMS) designed specifically for bloggers and developers. It offers an elegant user interface, powerful management features, and is ready to run in minutes thanks to Docker.

---

## ✨ Features

PiperBlog comes packed with features for content creators and administrators:

### 📝 Content Management
* **Intuitive Editor:** Support for **Markdown** and **HTML** when writing posts.
* **Post Status:** Manage posts as *Draft*, *Published*, or *Archived*.
* **Sticky Posts:** Pin important posts to the top of the homepage (📌 Feature).
* **Categories:** Organize your content into flexible categories.
* **Tags:** Organize articles with specific keywords (Tags) for better discoverability.
* **Syntax Highlighting:** Automatic highlighting of code blocks for technical blogs.

### 🖼️ Media & Files
* **File Manager:** Integrated file manager for uploading and managing images and files.
* **Hero Images:** Set impressive cover images for your articles.
* **File Attachments:** Offer files directly within the article for download.

### 💬 Interaction & Community
* **Comment System:** Visitors can comment on articles.
* **Spam Protection:** Built-in mathematical spam protection (Captcha).
* **Moderation:** Admin tools to approve, mark as spam, or delete comments.

### ⚙️ Administration & System
* **Comprehensive Dashboard:** Statistics on posts, comments, and system status at a glance.
* **Multi-User System:** Role-based permissions (Admin, Editor, Viewer) to manage team access secure.
* **Settings:** Configure blog title, description, SMTP mail server, and more directly in the admin panel.
* **Backup System:** Create and download backups of your data (JSON, CSV, or Full ZIP).
* **Maintenance Mode:** Temporarily take the site offline for updates.

### 🎨 Design & UX
* **Search Function:** Integrated search bar to find content instantly.
* **Pagination:** Smart pagination for easy browsing of article archives.
* **Dark Mode:** Visitors can toggle between light and dark mode 🌓.
* **Responsive Design:** Optimized for desktop, tablet, and mobile.
* **Sidebar:** Dynamic sidebar with categories, tags cloud, and latest comments.
* **Back-to-Top:** Convenient navigation for long articles.

### 🌍 Internationalization (i18n)
* **Multi-language:** Full support for **German 🇩🇪**, **English 🇬🇧**, **French 🇫🇷**, and **Spanish 🇪🇸** in both the frontend and backend.

---

## 🚀 Installation

PiperBlog is optimized for **Docker**, making installation extremely simple.

### Prerequisites
* Docker & Docker Compose installed.
* Git (optional, to clone the repo).

### Step-by-Step Guide

1.  **Clone Repository**
    ```bash
    git clone [https://github.com/your-user/piperblog.git](https://github.com/your-user/piperblog.git)
    cd piperblog
    ```

2.  **Run Installer**
    Use the included installation script to set up the environment and start the containers:
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
    Upon accessing the admin area for the first time, you will be prompted to set an initial password for the administrator if one does not exist yet.

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

# 🥧 PiperBlog

**PiperBlog** ist ein modernes, leichtgewichtiges und Docker-basiertes Content-Management-System (CMS), das speziell für Blogger und Entwickler entwickelt wurde. Es bietet eine elegante Benutzeroberfläche, leistungsstarke Verwaltungsfunktionen und ist dank Docker in wenigen Minuten einsatzbereit.

---

## ✨ Features

PiperBlog kommt vollgepackt mit Funktionen für Content-Ersteller und Administratoren:

### 📝 Content Management
* **Intuitiver Editor:** Unterstützung für **Markdown** und **HTML** beim Verfassen von Beiträgen.
* **Beitragsstatus:** Verwalte Beiträge als *Entwurf*, *Veröffentlicht* oder *Archiviert*.
* **Sticky Posts:** Pinne wichtige Beiträge oben an die Startseite (📌 Feature).
* **Kategorien:** Organisiere deine Inhalte in flexiblen Kategorien.
* **Tags (Schlagwörter):** Organisiere Artikel mit spezifischen Schlagwörtern (Tags) für bessere Auffindbarkeit.
* **Syntax Highlighting:** Automatische Hervorhebung von Code-Blöcken für technische Blogs.

### 🖼️ Medien & Dateien
* **Dateimanager:** Integrierter Dateimanager zum Hochladen und Verwalten von Bildern und Dateien.
* **Hero Images:** Setze beeindruckende Titelbilder für deine Artikel.
* **Datei-Anhänge:** Biete Dateien direkt im Artikel zum Download an.

### 💬 Interaktion & Community
* **Kommentarsystem:** Besucher können Artikel kommentieren.
* **Spamschutz:** Eingebauter mathematischer Spamschutz (Captcha).
* **Moderation:** Admin-Tools zum Genehmigen, als Spam markieren oder Löschen von Kommentaren.

### ⚙️ Administration & System
* **Umfangreiches Dashboard:** Statistiken zu Posts, Kommentaren und Systemstatus auf einen Blick.
* **Multi-User-System:** Rollenbasiertes Rechtesystem (Admin, Editor, Viewer) zur sicheren Verwaltung von Team-Zugriffen.
* **Einstellungen:** Konfiguriere Blog-Titel, Beschreibung, SMTP-Mail-Server und mehr direkt im Admin-Panel.
* **Backup-System:** Erstelle und lade Backups deiner Daten (JSON, CSV oder Full ZIP) herunter.
* **Wartungsmodus:** Schalte die Seite temporär offline für Updates.

### 🎨 Design & UX
* **Suchfunktion:** Integrierte Suchleiste, um Inhalte sofort zu finden.
* **Paginierung:** Intelligente Seitennummerierung für einfaches Durchstöbern des Archivs.
* **Dark Mode:** Besucher können zwischen Hell- und Dunkelmodus wechseln 🌓.
* **Responsives Design:** Optimiert für Desktop, Tablet und Mobile.
* **Sidebar:** Dynamische Sidebar mit Kategorien, Tag-Cloud und neuesten Kommentaren.
* **Back-to-Top:** Bequeme Navigation für lange Artikel.

### 🌍 Internationalisierung (i18n)
* **Mehrsprachig:** Vollständige Unterstützung für **Deutsch 🇩🇪**, **Englisch 🇬🇧**, **Französisch 🇫🇷** und **Spanisch 🇪🇸** sowohl im Frontend als auch im Backend.

---

## 🚀 Installation

PiperBlog ist für den Betrieb mit **Docker** optimiert, was die Installation extrem einfach macht.

### Voraussetzungen
* Docker & Docker Compose installiert.
* Git (optional, zum Klonen des Repos).

### Schritt-für-Schritt Anleitung

1.  **Repository klonen**
    ```bash
    git clone [https://github.com/dein-user/piperblog.git](https://github.com/dein-user/piperblog.git)
    cd piperblog
    ```

2.  **Installer ausführen**
    Nutze das beiliegende Installations-Skript, um die Umgebung einzurichten und die Container zu starten:
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
    Beim ersten Aufruf des Admin-Bereichs wirst du aufgefordert, ein Initial-Passwort für den Administrator zu setzen, falls noch keines existiert.

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