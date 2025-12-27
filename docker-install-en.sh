#!/usr/bin/env bash
set -euo pipefail

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE="docker-compose"
else
  echo "[!] Docker Compose not found. Please install." >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

get_env() {
  local key="$1"; local def="${2:-}";
  if [ -f .env ]; then
    val="$(grep -E "^${key}=" .env | tail -n1 | sed -E 's/^'"${key}"'=//')" || true
    if [ -n "${val:-}" ]; then printf "%s" "$val"; return; fi
  fi
  printf "%s" "$def"
}

if [ ! -f .env ]; then
  echo "[*] .env not found — creating default from .env.example"
  if [ -f .env.example ]; then cp .env.example .env; else touch .env; fi
fi

APP_URL="$(get_env APP_URL "http://localhost:3333")"
MYSQL_DATABASE="$(get_env MYSQL_DATABASE "blog")"
MYSQL_USER="$(get_env MYSQL_USER "bloguser")"
MYSQL_PASSWORD="$(get_env MYSQL_PASSWORD "blogpass")"
MYSQL_ROOT_PASSWORD="$(get_env MYSQL_ROOT_PASSWORD "changeme-root")"
ADMIN_PASSWORD="$(get_env ADMIN_PASSWORD "admin123")"

echo "[*] Ensuring config and upload directories exist..."
mkdir -p config uploads

cat > "config/config.ini" <<INI
[database]
host=db
port=3306
name=${MYSQL_DATABASE}
user=${MYSQL_USER}
password=${MYSQL_PASSWORD}
charset=utf8mb4
INI
echo "[*] config/config.ini created."

echo "[*] Starting Docker services..."
$COMPOSE up -d --build

echo "[*] Waiting for MySQL (Timeout 60s)..."
TRIES=60
until $COMPOSE exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; do
  TRIES=$((TRIES-1))
  if [ "$TRIES" -le 0 ]; then echo "[!] Timeout: DB unreachable."; exit 1; fi
  sleep 2
  printf "."
done
printf "\n"

SCHEMA_FILE="app/db/mysql/01_schema.sql"
[ ! -f "$SCHEMA_FILE" ] && SCHEMA_FILE="app/db/01_schema.sql"

if [ -f "$SCHEMA_FILE" ]; then
  echo "[*] Importing database schema from $SCHEMA_FILE..."
  cat "$SCHEMA_FILE" | $COMPOSE exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"
else
  echo "[!] Warning: Schema file not found. Skipping import."
fi

echo "[*] Optimizing permissions in web container..."

$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/public/uploads
$COMPOSE exec -T web chmod -R 775 /var/www/html/public/uploads

$COMPOSE exec -T web chown -R www-data:www-data /var/www/html/config
$COMPOSE exec -T web chmod -R 775 /var/www/html/config

echo "[*] Setting default application language to 'en'..."
$COMPOSE exec -T web find /var/www/html -name "*.php" -type f -exec sed -i "s/'lang'] ?? '[a-z]\{2\}'/'lang'] ?? 'en'/g" {} +
# ---------------------------------------

echo -e "\n✅ Installation completed successfully!"
echo "-------------------------------------------------------"
echo "Frontend: ${APP_URL}"
echo "Admin:    ${APP_URL}/admin/login.php"
echo "User:     admin"
echo "Password: ${ADMIN_PASSWORD}"

echo "-------------------------------------------------------"
