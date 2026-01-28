#!/bin/bash

# =================================================================
#  PrivateCMS - Native Bare Metal Installer (Ubuntu/Debian)
# =================================================================

# Farben
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Konfiguration
INSTALL_DIR="/var/www/privatecms"
DB_NAME="private_cms"
DB_USER="cms_user"

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}   PrivateCMS Native Installer wird gestartet...      ${NC}"
echo -e "${BLUE}======================================================${NC}"

# 1. Root-Check
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Fehler: Bitte als Root ausführen (sudo ./install.sh)${NC}"
  exit 1
fi

# 2. Prüfen ob Dateien da sind
if [ ! -f "01_schema.sql" ]; then
    echo -e "${RED}Fehler: '01_schema.sql' nicht gefunden!${NC}"
    echo "Bitte starte das Skript im Projekt-Ordner."
    exit 1
fi

# 3. Pakete installieren
echo -e "${GREEN}>>> Schritt 1/6: Installiere Webserver & PHP...${NC}"
apt-get update -y
apt-get install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-pdo php-gd php-mbstring php-xml php-curl zip unzip

# 4. Datenbank Setup
echo -e "${GREEN}>>> Schritt 2/6: Datenbank Einrichtung${NC}"
echo -n "Bitte ein sicheres Passwort für den Datenbank-Benutzer eingeben: "
read -s DB_PASS
echo ""

echo "Erstelle Datenbank und Benutzer..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "Importiere Tabellenstruktur..."
mysql ${DB_NAME} < 01_schema.sql

# 5. Dateien kopieren
echo -e "${GREEN}>>> Schritt 3/6: Kopiere Dateien nach ${INSTALL_DIR}...${NC}"
mkdir -p ${INSTALL_DIR}
rm -rf ${INSTALL_DIR}/*
cp -r . ${INSTALL_DIR}

# 6. Config erstellen (MIT SPRACHE)
echo -e "${GREEN}>>> Schritt 4/6: Konfiguration erstellen${NC}"

# Domain abfragen
echo -n "Domain (z.B. mein-blog.de oder localhost): "
read DOMAIN

# Sprache abfragen (Neu!)
echo -n "Standard-Sprache (de, en, es, fr) [de]: "
read LANG_INPUT
# Fallback auf 'de' wenn nichts eingegeben wird
LANG_INPUT=${LANG_INPUT:-de}

cat > ${INSTALL_DIR}/config/config.ini <<EOF
[app]
name = "PrivateCMS"
url = "http://${DOMAIN}"
lang = "${LANG_INPUT}"
debug = 0

[database]
host = "localhost"
dbname = "${DB_NAME}"
user = "${DB_USER}"
password = "${DB_PASS}"
charset = "utf8mb4"
EOF

# 7. Rechte setzen
echo -e "${GREEN}>>> Schritt 5/6: Setze Berechtigungen...${NC}"
chown -R www-data:www-data ${INSTALL_DIR}
find ${INSTALL_DIR} -type d -exec chmod 755 {} \;
find ${INSTALL_DIR} -type f -exec chmod 644 {} \;

# Upload Ordner
mkdir -p ${INSTALL_DIR}/public/uploads
chmod -R 775 ${INSTALL_DIR}/public/uploads
chown -R www-data:www-data ${INSTALL_DIR}/public/uploads

# 8. Apache VHost
echo -e "${GREEN}>>> Schritt 6/6: Konfiguriere Apache...${NC}"
a2enmod rewrite

cat > /etc/apache2/sites-available/privatecms.conf <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAdmin webmaster@localhost
    DocumentRoot ${INSTALL_DIR}/public

    <Directory ${INSTALL_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory ${INSTALL_DIR}/src>
        Require all denied
    </Directory>
    <Directory ${INSTALL_DIR}/config>
        Require all denied
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/privatecms_error.log
    CustomLog \${APACHE_LOG_DIR}/privatecms_access.log combined
</VirtualHost>
EOF

a2dissite 000-default.conf
a2ensite privatecms.conf
systemctl reload apache2

echo -e "${BLUE}======================================================${NC}"
echo -e "${GREEN}   INSTALLATION ABGESCHLOSSEN! 🚀${NC}"
echo -e "${BLUE}======================================================${NC}"
echo -e "Sprache eingestellt auf: ${LANG_INPUT}"
echo -e "URL: http://${DOMAIN}"
echo -e ""
echo -e "Admin: admin / admin123"