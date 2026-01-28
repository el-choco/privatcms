#!/bin/bash

# =================================================================
#  PrivateCMS - Installateur Natif Bare Metal (Français)
# =================================================================

GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

INSTALL_DIR="/var/www/privatecms"
DB_NAME="private_cms"
DB_USER="cms_user"

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}   Lancement de l'installateur natif PrivateCMS...    ${NC}"
echo -e "${BLUE}======================================================${NC}"

# 1. Root Check
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Erreur : Veuillez exécuter en tant que root (sudo ./bare-metal-install-fr.sh)${NC}"
  exit 1
fi

# 2. File Check
if [ ! -f "01_schema.sql" ]; then
    echo -e "${RED}Erreur : '01_schema.sql' introuvable !${NC}"
    echo "Veuillez exécuter ce script dans le dossier du projet."
    exit 1
fi

# 3. Packages
echo -e "${GREEN}>>> Étape 1/6 : Installation du serveur web et de PHP...${NC}"
apt-get update -y
apt-get install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-pdo php-gd php-mbstring php-xml php-curl zip unzip

# 4. Database
echo -e "${GREEN}>>> Étape 2/6 : Configuration de la base de données${NC}"
echo -n "Veuillez entrer un mot de passe sécurisé pour l'utilisateur de la base de données : "
read -s DB_PASS
echo ""

echo "Création de la base de données et de l'utilisateur..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "Importation de la structure des tables..."
mysql ${DB_NAME} < 01_schema.sql

# 5. Copy Files
echo -e "${GREEN}>>> Étape 3/6 : Copie des fichiers vers ${INSTALL_DIR}...${NC}"
mkdir -p ${INSTALL_DIR}
rm -rf ${INSTALL_DIR}/*
cp -r . ${INSTALL_DIR}

# 6. Config
echo -e "${GREEN}>>> Étape 4/6 : Création de la configuration${NC}"
echo -n "Entrez le domaine (ex. mon-blog.com ou localhost) : "
read DOMAIN

# Automatiquement définir la langue sur Français
cat > ${INSTALL_DIR}/config/config.ini <<EOF
[app]
name = "PrivateCMS"
url = "http://${DOMAIN}"
lang = "fr"
debug = 0

[database]
host = "localhost"
dbname = "${DB_NAME}"
user = "${DB_USER}"
password = "${DB_PASS}"
charset = "utf8mb4"
EOF

# 7. Permissions
echo -e "${GREEN}>>> Étape 5/6 : Configuration des permissions...${NC}"
chown -R www-data:www-data ${INSTALL_DIR}
find ${INSTALL_DIR} -type d -exec chmod 755 {} \;
find ${INSTALL_DIR} -type f -exec chmod 644 {} \;

mkdir -p ${INSTALL_DIR}/public/uploads
chmod -R 775 ${INSTALL_DIR}/public/uploads
chown -R www-data:www-data ${INSTALL_DIR}/public/uploads

# 8. Apache
echo -e "${GREEN}>>> Étape 6/6 : Configuration d'Apache...${NC}"
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
echo -e "${GREEN}   INSTALLATION TERMINÉE ! 🚀${NC}"
echo -e "${BLUE}======================================================${NC}"
echo -e "Langue définie sur : Français (fr)"
echo -e "URL : http://${DOMAIN}"
echo -e ""
echo -e "Connexion Admin :"
echo -e "Utilisateur : admin"
echo -e "Mot de passe : admin123"
echo -e "${RED}IMPORTANT : Changez le mot de passe immédiatement !${NC}"