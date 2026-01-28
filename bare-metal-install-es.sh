#!/bin/bash

# =================================================================
#  PrivateCMS - Instalador Nativo Bare Metal (Español)
# =================================================================

GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

INSTALL_DIR="/var/www/privatecms"
DB_NAME="private_cms"
DB_USER="cms_user"

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}   Iniciando Instalador Nativo de PrivateCMS...       ${NC}"
echo -e "${BLUE}======================================================${NC}"

# 1. Root Check
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Error: Por favor, ejecutar como root (sudo ./bare-metal-install-es.sh)${NC}"
  exit 1
fi

# 2. File Check
if [ ! -f "01_schema.sql" ]; then
    echo -e "${RED}Error: ¡No se encuentra '01_schema.sql'!${NC}"
    echo "Por favor, ejecute este script dentro de la carpeta del proyecto."
    exit 1
fi

# 3. Packages
echo -e "${GREEN}>>> Paso 1/6: Instalando servidor web y PHP...${NC}"
apt-get update -y
apt-get install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-pdo php-gd php-mbstring php-xml php-curl zip unzip

# 4. Database
echo -e "${GREEN}>>> Paso 2/6: Configuración de la base de datos${NC}"
echo -n "Por favor, introduzca una contraseña segura para el usuario de la base de datos: "
read -s DB_PASS
echo ""

echo "Creando base de datos y usuario..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "Importando estructura de tablas..."
mysql ${DB_NAME} < 01_schema.sql

# 5. Copy Files
echo -e "${GREEN}>>> Paso 3/6: Copiando archivos a ${INSTALL_DIR}...${NC}"
mkdir -p ${INSTALL_DIR}
rm -rf ${INSTALL_DIR}/*
cp -r . ${INSTALL_DIR}

# 6. Config
echo -e "${GREEN}>>> Paso 4/6: Creando configuración${NC}"
echo -n "Introduzca el dominio (ej. mi-blog.com o localhost): "
read DOMAIN

# Automáticamente establecer idioma a Español
cat > ${INSTALL_DIR}/config/config.ini <<EOF
[app]
name = "PrivateCMS"
url = "http://${DOMAIN}"
lang = "es"
debug = 0

[database]
host = "localhost"
dbname = "${DB_NAME}"
user = "${DB_USER}"
password = "${DB_PASS}"
charset = "utf8mb4"
EOF

# 7. Permissions
echo -e "${GREEN}>>> Paso 5/6: Estableciendo permisos...${NC}"
chown -R www-data:www-data ${INSTALL_DIR}
find ${INSTALL_DIR} -type d -exec chmod 755 {} \;
find ${INSTALL_DIR} -type f -exec chmod 644 {} \;

mkdir -p ${INSTALL_DIR}/public/uploads
chmod -R 775 ${INSTALL_DIR}/public/uploads
chown -R www-data:www-data ${INSTALL_DIR}/public/uploads

# 8. Apache
echo -e "${GREEN}>>> Paso 6/6: Configurando Apache...${NC}"
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
echo -e "${GREEN}   ¡INSTALACIÓN COMPLETADA! 🚀${NC}"
echo -e "${BLUE}======================================================${NC}"
echo -e "Idioma establecido a: Español (es)"
echo -e "URL: http://${DOMAIN}"
echo -e ""
echo -e "Admin Login:"
echo -e "Usuario: admin"
echo -e "Contraseña: admin123"
echo -e "${RED}IMPORTANTE: ¡Cambie la contraseña inmediatamente!${NC}"