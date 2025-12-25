#!/usr/bin/env bash
# docker-install.sh — one-command setup for PiperBlog

set -euo pipefail

# 0) Detect compose command
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE="docker-compose"
else
  echo "[!] Docker Compose nicht gefunden." >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# Helper to read .env
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
  cp .env.example .env || touch .env
fi

APP_URL="$(get_env APP_URL "http://localhost:3333")"
MYSQL_DATABASE="$(get_env MYSQL_DATABASE "blog")"
MYSQL_USER="$(get_env MYSQL_USER "bloguser")"
MYSQL_PASSWORD="$(get_env MYSQL_PASSWORD "blogpass")"
MYSQL_ROOT_PASSWORD="$(get_env MYSQL_ROOT_PASSWORD "changeme-root")"
ADMIN_PASSWORD="$(get_env ADMIN_PASSWORD "admin123")"

# 2) Ordnerstruktur auf dem Host vorbereiten
echo "[*] Erstelle Verzeichnisstruktur..."
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
echo "[*] Warte auf MySQL..."
TRIES=60
until $COMPOSE exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; do
  TRIES=$((TRIES-1))
  if [ "$TRIES" -le 0 ]; then echo "[!] Timeout"; exit 1; fi
  sleep 2
  printf "."
done
printf "\n"

# 6) Schema Import & Admin User
SCHEMA_FILE="app/db/mysql/01_schema.sql"
if [ -f "$SCHEMA_FILE" ]; then
  echo "[*] Importiere Datenbank-Schema..."
  cat "$SCHEMA_FILE" | $COMPOSE exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"
fi

echo "[*] Setze Admin-Passwort..."
HASH="$($COMPOSE exec -T web php -r 'echo password_hash(getenv("P"), PASSWORD_BCRYPT);' P="$ADMIN_PASSWORD")"
SQL="INSERT INTO users (username, password_hash, role) VALUES ('admin', '${HASH}', 'admin') ON DUPLICATE KEY UPDATE password_hash='${HASH}';"
$COMPOSE exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "$SQL" "$MYSQL_DATABASE"

# 7) RECHTE VERGEBEN & ORDNERSTRUKTUR IM ROOT PRÜFEN
echo "[*] Optimiere Berechtigungen für das Root-Upload-Verzeichnis..."

$COMPOSE exec -T web mkdir -p /var/www/html/public/uploads/images

$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/public/uploads
$COMPOSE exec -T web chmod -R 775 /var/www/html/public/uploads

$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/config
$COMPOSE exec -T web chmod -R 775 /var/www/html/config

echo -e "\n✅ Installation abgeschlossen!"
echo "Frontend: ${APP_URL}"
echo "Admin:    ${APP_URL}/admin/login.php (User: admin / Pass: ${ADMIN_PASSWORD})"