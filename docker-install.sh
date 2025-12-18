#!/usr/bin/env bash
# docker-install.sh — one-command setup for PiperBlog
# - Starts Docker services (web, db)
# - Waits for MySQL to become ready
# - Imports initial schema if not present
# - Imports sample seed data if the posts table is empty
# - Prints access URLs
#
# Requirements: Docker + Docker Compose v2 ("docker compose").
# This script is idempotent and safe to re-run.

set -euo pipefail

# Detect compose command (prefer plugin)
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE="docker-compose"
else
  echo "[!] Docker Compose not found. Install Docker Desktop or docker-compose." >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# Helper to read value from .env (simple parser)
get_env() {
  local key="$1"; local def="${2:-}";
  if [ -f .env ]; then
    val="$(grep -E "^${key}=" .env | tail -n1 | sed -E 's/^'"${key}"'=//')" || true
    if [ -n "${val:-}" ]; then printf "%s" "$val"; return; fi
  fi
  printf "%s" "$def"
}

# 1) Ensure .env exists
if [ ! -f .env ]; then
  echo "[*] .env not found — creating from .env.example"
  cp .env.example .env
fi

APP_URL="$(get_env APP_URL "http://localhost:3333")"
MYSQL_DATABASE="$(get_env MYSQL_DATABASE "blog")"
MYSQL_USER="$(get_env MYSQL_USER "bloguser")"
MYSQL_PASSWORD="$(get_env MYSQL_PASSWORD "blogpass")"
MYSQL_ROOT_PASSWORD="$(get_env MYSQL_ROOT_PASSWORD "changeme-root")"

# 2) Start containers
echo "[*] Starting services..."
$COMPOSE up -d --build

# 3) Wait for DB readiness (mysqladmin ping)
echo "[*] Waiting for MySQL to be ready..."
TRIES=60
until $COMPOSE exec -T db bash -lc 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; do
  TRIES=$((TRIES-1)) || true
  if [ "$TRIES" -le 0 ]; then
    echo "[!] MySQL did not become ready in time." >&2
    exit 1
  fi
  sleep 2
  printf "."
done
printf "\n"

# Small helper to run a query inside the db container
mysql_exec(){
  local q="$1"
  $COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -Nse '"$q"' "$MYSQL_DATABASE"'
}

# 4) Ensure schema exists; import if missing
TABLE_EXISTS=$($COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"$MYSQL_DATABASE\" AND table_name=\"posts\""' 2>/dev/null || echo 0)
if [ "${TABLE_EXISTS:-0}" -eq 0 ]; then
  echo "[*] Importing initial schema (01_schema.sql)..."
  if [ ! -f app/db/mysql/01_schema.sql ]; then
    echo "[!] Schema file app/db/mysql/01_schema.sql not found." >&2
    exit 1
  fi
  cat app/db/mysql/01_schema.sql | $COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
fi

# 5) Seed sample data if posts table empty
POSTS_COUNT=$(mysql_exec 'SELECT COUNT(*) FROM posts;' 2>/dev/null || echo 0)
if [ "${POSTS_COUNT:-0}" -eq 0 ]; then
  if [ -f app/db/mysql/02_seed.sql ]; then
    echo "[*] Importing sample data (02_seed.sql)..."
    cat app/db/mysql/02_seed.sql | $COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
  else
    echo "[i] No seed file found (app/db/mysql/02_seed.sql). Skipping sample import."
  fi
else
  echo "[*] Posts already present (${POSTS_COUNT}). Skipping seeds."
fi

# 6) Done: print info
cat <<EOF

✅ PiperBlog is up.

Frontend:   ${APP_URL}
Admin:      ${APP_URL%/}/admin/

MySQL:      host=db  database=${MYSQL_DATABASE}  user=${MYSQL_USER}

Tip: docker compose logs -f web db
EOF
