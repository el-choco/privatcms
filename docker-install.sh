#!/usr/bin/env bash
# docker-install.sh — One-Command Setup für PiperBlog

set -euo pipefail

# 0) Docker Compose Befehl erkennen
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE="docker-compose"
else
  echo "[!] Docker Compose nicht gefunden. Bitte installieren." >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# Helper: .env Werte lesen
get_env() {
  local key="$1"; local def="${2:-}";
  if [ -f .env ]; then
    val="$(grep -E "^${key}=" .env | tail -n1 | sed -E 's/^'"${key}"'=//')" || true
    if [ -n "${val:-}" ]; then printf "%s" "$val"; return; fi
  fi
  printf "%s" "$def"
}

# 1) .env sicherstellen
if [ ! -f .env ]; then
  echo "[*] .env nicht gefunden — erstelle Standard aus .env.example"
  if [ -f .env.example ]; then cp .env.example .env; else touch .env; fi
fi

APP_URL="$(get_env APP_URL "http://localhost:3333")"
MYSQL_DATABASE="$(get_env MYSQL_DATABASE "blog")"
MYSQL_USER="$(get_env MYSQL_USER "bloguser")"
MYSQL_PASSWORD="$(get_env MYSQL_PASSWORD "blogpass")"
MYSQL_ROOT_PASSWORD="$(get_env MYSQL_ROOT_PASSWORD "changeme-root")"
ADMIN_PASSWORD="$(get_env ADMIN_PASSWORD "admin123")"

# 2) Ordnerstruktur auf dem Host vorbereiten
echo "[*] Erstelle Verzeichnisstruktur für Config und Uploads..."
mkdir -p config public/uploads/images

# 3) config/config.ini schreiben
cat > "config/config.ini" <<INI
[database]
host=db
port=3306
name=${MYSQL_DATABASE}
user=${MYSQL_USER}
password=${MYSQL_PASSWORD}
charset=utf8mb4
INI
echo "[*] config/config.ini wurde erstellt."

# 4) Container starten
echo "[*] Starte Docker-Services..."
$COMPOSE up -d --build

# 5) Warten auf DB
echo "[*] Warte auf MySQL (Timeout 60s)..."
TRIES=60
until $COMPOSE exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; do
  TRIES=$((TRIES-1))
  if [ "$TRIES" -le 0 ]; then echo "[!] Timeout: DB nicht erreichbar."; exit 1; fi
  sleep 2
  printf "."
done
printf "\n"

# 6) Schema Import (Inklusive Sticky & Download Spalten)
SCHEMA_FILE="app/db/mysql/01_schema.sql"
[ ! -f "$SCHEMA_FILE" ] && SCHEMA_FILE="app/db/01_schema.sql"

if [ -f "$SCHEMA_FILE" ]; then
  echo "[*] Importiere Datenbank-Schema aus $SCHEMA_FILE..."
  cat "$SCHEMA_FILE" | $COMPOSE exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"
else
  echo "[!] Warnung: Schema-Datei nicht gefunden. Überspringe Import."
fi

echo "[*] Setze/Update Admin-Passwort..."
HASH="$($COMPOSE exec -T web php -r 'echo password_hash(getenv("P"), PASSWORD_BCRYPT);' P="$ADMIN_PASSWORD")"
SQL="INSERT INTO users (username, password_hash, role) VALUES ('admin', '${HASH}', 'admin') ON DUPLICATE KEY UPDATE password_hash='${HASH}';"
$COMPOSE exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "$SQL" "$MYSQL_DATABASE"

# 7) RECHTE VERGEBEN & ORDNERSTRUKTUR IM CONTAINER PRÜFEN
echo "[*] Optimiere Berechtigungen im Web-Container..."

# Sicherstellen, dass Unterordner existieren
$COMPOSE exec -T web mkdir -p /var/www/html/public/uploads/images

# Besitzer auf www-data (Webserver) setzen
$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/public/uploads
$COMPOSE exec -T web chmod -R 775 /var/www/html/public/uploads

# Config-Rechte
$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/config
$COMPOSE exec -T web chmod -R 775 /var/www/html/config

echo -e "\n✅ Installation erfolgreich abgeschlossen!"
echo "-------------------------------------------------------"
echo "Frontend: ${APP_URL}"
echo "Admin:    ${APP_URL}/admin/login.php"
echo "Benutzer: admin"
echo "Passwort: ${ADMIN_PASSWORD}"
echo "-------------------------------------------------------"