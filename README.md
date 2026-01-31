> ## [Deutsche ReadMe!](https://github.com/el-choco/privatcms?tab=readme-ov-file#-privatcms-1) 
>
> ## [Lisez-moi en français!](https://github.com/el-choco/privatcms?tab=readme-ov-file#-privatcms-3)
>
> ## [ReadMe en español!](https://github.com/el-choco/privatcms?tab=readme-ov-file#-privatcms-2)

---

# 🥧 PrivatCMS

> **The leap from blog to CMS is accomplished.**

**PrivatCMS** is a modern, lightweight, and Docker-based Content Management System (CMS) designed specifically for bloggers and developers. It offers an elegant user interface, powerful management features, and is ready to run in minutes thanks to Docker.

---

## ✨ Features

PrivatCMS comes packed with features for content creators and administrators:

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

PrivatCMS is optimized for **Docker**, making installation extremely simple.

### Prerequisites
* Docker & Docker Compose installed.
* Git (optional, to clone the repo).

### Step-by-Step Guide (Docker)

1.  **Clone Repository**
    ```bash
    git clone https://github.com/el-choco/privatcms.git
    cd privatcms
    ```
2.  **Please edit the docker-compose.yml file to meet your needs (ports, volumes, etc.).**

3.  **Run Installer**
    Use the included installation script to set up the environment and start the containers:
    *(Note: There are 4 language versions available that automatically set the default language: `docker-install.sh` (German), `docker-install-en.sh` (English), `docker-install-es.sh` (Spanish), `docker-install-fr.sh` (French))*
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *The script automatically creates the `.env` file, builds the Docker containers, and starts them.*

4.  **Access Blog**
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

1.  Upload the project files to your server (e.g., `/var/www/privatcms`).
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

# 🥧 PrivatCMS

**PrivatCMS** ist ein modernes, leichtgewichtiges und Docker-basiertes Content-Management-System (CMS), das speziell für Blogger und Entwickler entwickelt wurde. Es bietet eine elegante Benutzeroberfläche, leistungsstarke Verwaltungsfunktionen und ist dank Docker in wenigen Minuten einsatzbereit.

---

## ✨ Features

PrivatCMS kommt vollgepackt mit Funktionen für Content-Ersteller und Administratoren:

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

PrivatCMS ist für den Betrieb mit **Docker** optimiert, was die Installation extrem einfach macht.

### Voraussetzungen
* Docker & Docker Compose installiert.
* Git (optional, zum Klonen des Repos).

### Schritt-für-Schritt Anleitung (Docker)

1.  **Repository klonen**
    ```bash
    git clone https://github.com/el-choco/privatcms.git
    cd privatcms
    ```
2.  **Bitte bearbeitet die docker-compose.yml so das sie euren Bedürfnissen entspricht ( Ports, Volumes etc. ).**

3.  **Installer ausführen**
    Nutze das beiliegende Installations-Skript, um die Umgebung einzurichten und die Container zu starten:
    *(Hinweis: Es stehen 4 Sprachvarianten zur Verfügung, die automatisch die Standardsprache einstellen: `docker-install.sh` (Deutsch), `docker-install-en.sh` (Englisch), `docker-install-es.sh` (Spanisch), `docker-install-fr.sh` (Französisch))*
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *Das Skript erstellt automatisch die `.env` Datei, baut die Docker-Container und startet sie.*

4.  **Blog aufrufen**
    Sobald die Container laufen, ist dein Blog erreichbar unter:
    * **Frontend:** `http://localhost:3333` (oder Port gemäß Konfiguration)
    * **Admin-Login:** `http://localhost:3333/admin`

5.  **Erste Anmeldung**
    Nutze die Standard-Zugangsdaten für den Admin-Bereich:
    * **Benutzer:** `admin`
    * **Passwort:** `admin123`
    * *(Bitte ändere das Passwort sofort nach dem ersten Login!)*

### 🖥️ Bare Metal / Native Installation

Für Server ohne Docker (z.B. Standard Linux VPS mit Ubuntu/Debian/Apache) stehen native "1-Klick"-Installer bereit.

1.  Lade die Projektdateien auf deinen Server (z.B. nach `/var/www/privatcms`).
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

---
---

# 🥧 PrivatCMS

**PrivatCMS** es un sistema de gestión de contenidos (CMS) moderno, ligero y basado en Docker, desarrollado específicamente para blogueros y desarrolladores. Ofrece una interfaz de usuario elegante, potentes funciones de administración y está listo para usar en pocos minutos gracias a Docker.

---

## ✨ Características

PrivatCMS viene repleto de funciones para creadores de contenido y administradores:

### 🗣️ Sistema de Foros (Nuevo)
* **Tableros extensos:** Crea áreas de discusión (categorías) con descripciones.
* **Temas y Publicaciones:** Los usuarios pueden crear nuevos temas y responder a los existentes.
* **Editor Potente:** El editor también está disponible para los usuarios en el foro, incluyendo **subida de imágenes**, **selector de color**, **emojis** e **iconos**.
* **Herramientas de Moderación:** Los administradores pueden **bloquear** temas (solo lectura) o **fijar** publicaciones importantes (Sticky).
* **Navegación:** **Migas de pan** (Breadcrumbs) integradas para una fácil orientación en el foro.

### 📄 CMS y Páginas
* **Páginas estáticas:** Crea contenido atemporal como "Sobre nosotros", "Aviso legal" o "Portafolio", separado del feed del blog.
* **Navegación Dinámica:** Controla directamente en el editor si las páginas deben aparecer en el menú del **Encabezado** o del **Pie de página**.
* **URLs SEO:** Slugs de URL automáticos y limpios para artículos y páginas (por ejemplo, `/p/sobre-nosotros`).

### 📝 Gestión de Contenidos
* **Editor Híbrido Pro:** Editor avanzado de vista dividida con soporte para **Markdown** Y **HTML**. Incluye un **selector de color** nativo, resaltado de texto y amplias herramientas de formato.
* **Estado de la publicación:** Gestiona entradas como *Borrador*, *Publicado* o *Archivado*.
* **Sticky Posts:** Fija publicaciones importantes en la parte superior de la página de inicio (📌 Función).
* **Categorías y Etiquetas:** Organiza tu contenido en categorías flexibles y utiliza etiquetas para facilitar la búsqueda.
* **Resaltado de sintaxis:** Resaltado automático de bloques de código para blogs técnicos.
* **Extras:** **Selector de iconos** integrado (FontAwesome), soporte para **Emojis** y **subida de medios** directa en el flujo de escritura.

### 🖼️ Medios y Archivos
* **Gestor de archivos:** Gestor de archivos independiente (`files.php`) para subir, ver y gestionar centralmente todos los archivos multimedia.
* **Integración de medios:** Inserta imágenes directamente desde el editor a través de una biblioteca multimedia.
* **Galería Lightbox:** La función **Lightbox** integrada muestra las imágenes en artículos y páginas automáticamente en una elegante vista de pantalla completa al hacer clic.
* **Imágenes Hero:** Establece impresionantes imágenes de portada para tus artículos.
* **Archivos adjuntos:** Ofrece archivos para descargar directamente en el artículo.

### 💬 Interacción y Comunidad
* **Sistema de comentarios:** Los visitantes pueden comentar los artículos.
* **Bandeja de entrada:** Formulario de contacto integrado con su propia **bandeja de entrada** (`messages.php`) en el panel de administración para leer y gestionar mensajes (no se necesita servidor de correo).
* **Protección contra spam:** Protección antispam matemática integrada (Captcha).
* **Moderación:** Herramientas de administración para aprobar, marcar como spam o eliminar comentarios.

### ⚙️ Administración y Sistema
* **Panel de control completo:** Un panel de control (`dashboard.php`) repleto de estadísticas de visitantes (vistas diarias), métricas de contenido y estado del sistema de un vistazo.
* **Sistema Multi-usuario:** Sistema de derechos basado en roles (**Admin**, **Editor**, **Viewer** y **Miembro**) para la gestión segura de accesos del equipo y usuarios del foro.
* **Configuración:** Configura el título del blog, la descripción, el servidor de correo SMTP y más directamente en el panel de administración.
* **Sistema de copias de seguridad:** Crea y descarga copias de seguridad de tus datos (JSON, CSV o ZIP completo).
* **Registro (Log):** Registra las acciones de los usuarios para mayor seguridad y transparencia.
* **Modo de mantenimiento:** Pon el sitio temporalmente fuera de línea para actualizaciones.

### 🎨 Diseño y UX
* **Función de búsqueda:** Barra de búsqueda integrada para encontrar contenido al instante.
* **Paginación:** Numeración inteligente de páginas para navegar fácilmente por el archivo.
* **Modo Oscuro:** Los visitantes pueden cambiar entre modo claro y oscuro 🌓.
* **Diseño Responsivo:** Optimizado para escritorio, tableta y móvil.
* **Barra lateral:** Barra lateral dinámica con categorías, nube de etiquetas y comentarios recientes.
* **Volver arriba:** Navegación cómoda para artículos largos.

### 🌍 Internacionalización (i18n)
* **Multilingüe:** Soporte completo para **Alemán 🇩🇪**, **Inglés 🇬🇧**, **Francés 🇫🇷** y **Español 🇪🇸** tanto en el frontend como en el backend.

---

## 🚀 Instalación

PrivatCMS está optimizado para funcionar con **Docker**, lo que hace que la instalación sea extremadamente sencilla.

### Requisitos
* Docker y Docker Compose instalados.
* Git (opcional, para clonar el repositorio).

### Instrucciones paso a paso

1.  **Clonar repositorio**
    ```bash
    git clone [https://github.com/el-choco/piperblog.git](https://github.com/el-choco/piperblog.git)
    cd piperblog
    ```

2.  **Ejecutar instalador**
    Utiliza el script de instalación incluido para configurar el entorno e iniciar los contenedores:
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *El script crea automáticamente el archivo `.env`, construye los contenedores Docker y los inicia.*

3.  **Acceder al blog**
    Una vez que los contenedores estén funcionando, tu blog estará accesible en:
    * **Frontend:** `http://localhost:3333` (o el puerto según configuración)
    * **Login Admin:** `http://localhost:3333/admin`

4.  **Primer inicio de sesión**
    Utiliza las credenciales estándar para el área de administración:
    * **Usuario:** `admin`
    * **Contraseña:** `admin123`
    * *(¡Por favor, cambia la contraseña inmediatamente después del primer inicio de sesión!)*

---

## 🛠️ Stack Tecnológico

* **Backend:** PHP 8.2+
* **Base de datos:** MySQL 8.0
* **Servidor web:** Apache
* **Frontend:** HTML5, CSS3, Vanilla JS
* **Contenedorización:** Docker

---

## 📂 Estructura

* `admin/` - Interfaz de administración (Backend).
* `public/` - El frontend visible para los visitantes (Temas, Assets).
* `src/` - Clases PHP y lógica central.
* `config/` - Archivos de configuración y archivos de idioma (.ini).
* `docker/` - Configuraciones específicas de Docker.

---

## 📄 Licencia

Este proyecto se publica bajo la licencia MIT. Ver [LICENSE](LICENSE) para más detalles.

---
---

# 🥧 PrivatCMS

**PrivatCMS** est un système de gestion de contenu (CMS) moderne, léger et basé sur Docker, conçu spécifiquement pour les blogueurs et les développeurs. Il offre une interface utilisateur élégante, des fonctionnalités d'administration puissantes et est prêt à l'emploi en quelques minutes grâce à Docker.

---

## ✨ Fonctionnalités

PrivatCMS regorge de fonctionnalités pour les créateurs de contenu et les administrateurs :

### 🗣️ Système de Forum (Nouveau)
* **Tableaux complets :** Créez des zones de discussion (catégories) avec des descriptions.
* **Sujets & Messages :** Les utilisateurs peuvent créer de nouveaux sujets et répondre aux existants.
* **Éditeur Puissant :** L'éditeur est également disponible pour les utilisateurs du forum – incluant le **téléchargement d'images**, le **sélecteur de couleurs**, les **émojis** et les **icônes**.
* **Outils de modération :** Les administrateurs peuvent **verrouiller** des sujets (lecture seule) ou **épingler** des messages importants (Sticky).
* **Navigation :** **Fil d'Ariane** (Breadcrumbs) intégré pour une orientation facile dans le forum.

### 📄 CMS & Pages
* **Pages statiques :** Créez du contenu intemporel comme "À propos", "Mentions légales" ou "Portfolio", séparé du flux du blog.
* **Navigation dynamique :** Contrôlez directement dans l'éditeur si les pages doivent apparaître dans le menu de l'**en-tête** ou du **pied de page**.
* **URL SEO :** Slugs d'URL automatiques et propres pour les articles et les pages (par ex. `/p/a-propos`).

### 📝 Gestion de contenu
* **Éditeur Hybride Pro :** Éditeur avancé en vue fractionnée avec support pour **Markdown** ET **HTML**. Comprend un **sélecteur de couleurs** natif, le surlignage de texte et des outils de formatage complets.
* **Statut de l'article :** Gérez les articles comme *Brouillon*, *Publié* ou *Archivé*.
* **Articles épinglés :** Épinglez les articles importants en haut de la page d'accueil (📌 Fonctionnalité).
* **Catégories & Tags :** Organisez votre contenu dans des catégories flexibles et utilisez des mots-clés pour une meilleure visibilité.
* **Coloration syntaxique :** Mise en évidence automatique des blocs de code pour les blogs techniques.
* **Extras :** **Sélecteur d'icônes** intégré (FontAwesome), support **Emoji** et **téléchargement de médias** direct dans le flux d'écriture.

### 🖼️ Médias & Fichiers
* **Gestionnaire de fichiers :** Gestionnaire de fichiers autonome (`files.php`) pour télécharger, visualiser et gérer tous les fichiers médias de manière centralisée.
* **Intégration média :** Insérez des images directement depuis l'éditeur via une médiathèque.
* **Galerie Lightbox :** La fonction **Lightbox** intégrée affiche automatiquement les images dans les articles et les pages en plein écran élégant lors du clic.
* **Images Hero :** Définissez des images de titre impressionnantes pour vos articles.
* **Pièces jointes :** Proposez des fichiers à télécharger directement dans l'article.

### 💬 Interaction & Communauté
* **Système de commentaires :** Les visiteurs peuvent commenter les articles.
* **Boîte de réception :** Formulaire de contact intégré avec sa propre **boîte de réception** (`messages.php`) dans le panneau d'administration pour lire et gérer les messages (pas de serveur mail nécessaire).
* **Protection anti-spam :** Protection anti-spam mathématique intégrée (Captcha).
* **Modération :** Outils d'administration pour approuver, marquer comme spam ou supprimer des commentaires.

### ⚙️ Administration & Système
* **Tableau de bord complet :** Un tableau de bord riche (`dashboard.php`) avec des statistiques de visiteurs (vues quotidiennes), des métriques de contenu et l'état du système en un coup d'œil.
* **Système multi-utilisateurs :** Système de droits basé sur des rôles (**Admin**, **Éditeur**, **Observateur** et **Membre**) pour une gestion sécurisée des accès de l'équipe et des utilisateurs du forum.
* **Paramètres :** Configurez le titre du blog, la description, le serveur de messagerie SMTP et plus encore directement dans le panneau d'administration.
* **Système de sauvegarde :** Créez et téléchargez des sauvegardes de vos données (JSON, CSV ou ZIP complet).
* **Journal (Log) :** Enregistre les actions des utilisateurs pour la sécurité et la transparence.
* **Mode maintenance :** Mettez le site temporairement hors ligne pour les mises à jour.

### 🎨 Design & UX
* **Fonction de recherche :** Barre de recherche intégrée pour trouver du contenu instantanément.
* **Pagination :** Numérotation intelligente des pages pour parcourir facilement les archives.
* **Mode Sombre :** Les visiteurs peuvent basculer entre le mode clair et sombre 🌓.
* **Design Responsif :** Optimisé pour ordinateur, tablette et mobile.
* **Barre latérale :** Barre latérale dynamique avec catégories, nuage de tags et commentaires récents.
* **Retour en haut :** Navigation pratique pour les longs articles.

### 🌍 Internationalisation (i18n)
* **Multilingue :** Support complet pour l'**Allemand 🇩🇪**, l'**Anglais 🇬🇧**, le **Français 🇫🇷** et l'**Espagnol 🇪🇸** tant sur le frontend que sur le backend.

---

## 🚀 Installation

PrivatCMS est optimisé pour fonctionner avec **Docker**, ce qui rend l'installation extrêmement simple.

### Prérequis
* Docker & Docker Compose installés.
* Git (optionnel, pour cloner le dépôt).

### Instructions étape par étape

1.  **Cloner le dépôt**
    ```bash
    git clone [https://github.com/el-choco/piperblog.git](https://github.com/el-choco/piperblog.git)
    cd piperblog
    ```

2.  **Exécuter l'installateur**
    Utilisez le script d'installation inclus pour configurer l'environnement et démarrer les conteneurs :
    ```bash
    chmod +x docker-install.sh
    ./docker-install.sh
    ```
    *Le script crée automatiquement le fichier `.env`, construit les conteneurs Docker et les démarre.*

3.  **Accéder au blog**
    Dès que les conteneurs fonctionnent, votre blog est accessible sous :
    * **Frontend :** `http://localhost:3333` (ou le port selon la configuration)
    * **Login Admin :** `http://localhost:3333/admin`

4.  **Première connexion**
    Utilisez les identifiants par défaut pour la zone d'administration :
    * **Utilisateur :** `admin`
    * **Mot de passe :** `admin123`
    * *(Veuillez changer le mot de passe immédiatement après la première connexion !)*

---

## 🛠️ Stack Technologique

* **Backend :** PHP 8.2+
* **Base de données :** MySQL 8.0
* **Serveur web :** Apache
* **Frontend :** HTML5, CSS3, Vanilla JS
* **Conteneurisation :** Docker

---

## 📂 Structure

* `admin/` - Interface d'administration (Backend).
* `public/` - Le frontend visible pour les visiteurs (Thèmes, Assets).
* `src/` - Classes PHP et logique centrale.
* `config/` - Fichiers de configuration et fichiers de langue (.ini).
* `docker/` - Configurations spécifiques à Docker.

---

## 📄 Licence

Ce projet est publié sous la licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.
