#!/usr/bin/env bash
# docker-install.sh — one-command setup for PiperBlog
# - Starts Docker services (web, db) with build
# - Waits for MySQL to become ready
# - ALWAYS imports 01_schema.sql (idempotent: safe to re-run)
# - Prints access URLs and verifies admin user
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

# 2) Start/rebuild containers
echo "[*] Starting services (build + up)..."
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

# 4) Import schema ALWAYS (idempotent) — creates tables, admin user, seeds
SCHEMA_FILE="app/db/mysql/01_schema.sql"
if [ ! -f "$SCHEMA_FILE" ]; then
  echo "[!] Schema file $SCHEMA_FILE not found." >&2
  exit 1
fi

echo "[*] Importing schema: $SCHEMA_FILE (idempotent) ..."
cat "$SCHEMA_FILE" | $COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

# 5) Verify admin user exists
ADMIN_EXISTS=$($COMPOSE exec -T db bash -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -Nse "SELECT COUNT(*) FROM users WHERE username=\"admin\";" "$MYSQL_DATABASE"' 2>/dev/null || echo 0)
if [ "${ADMIN_EXISTS:-0}" -gt 0 ]; then
  echo "[*] Admin user present. Login should work (admin / admin123)."
else
  echo "[!] Admin user not found after import. Please check database connection and schema file." >&2
fi

# 6) Done: print info
cat <<EOF

✅ PiperBlog is up.

Frontend:   ${APP_URL}
Admin:      ${APP_URL%/}/admin/login.php

MySQL:      host=db  database=${MYSQL_DATABASE}  user=${MYSQL_USER}

Tip: docker compose logs -f web db
EOF
