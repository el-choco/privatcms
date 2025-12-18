# PiperBlog – Greenfield

A modern, self-hosted blog built with PHP 8.2, Docker, and MySQL. The frontend uses a 4-column Flexbox grid with responsive fallbacks. Includes an admin shell (sidebar, placeholders), install script with safe chown/chmod, and a clean directory layout.

## Quick start (Docker)

1) Copy env file:
```bash
cp .env.example .env
```

2) Start services:
```bash
docker compose up -d
```

3) Initialize schema:
```bash
docker compose exec db mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < app/db/mysql/01_schema.sql
```

4) Access:
- Frontend: http://localhost:3333
- Admin: http://localhost:3333/admin/ (temporary open shell)

## Structure

- `public/` Frontend (article grid, article page, assets)
- `admin/` Admin shell (sidebar, dashboard placeholder)
- `src/App/` Core (database, router, csrf)
- `app/db/mysql/` Schema SQL
- `config/` App config
- `uploads/` User uploads
- `scripts/` Install helpers
- `.env` / `uploads.ini` Runtime and PHP upload limits

## Permissions

Installer sets safe permissions:
- Directories: 0775
- Files: 0664
- Owner/Group: web user (www-data by default)

Never use 0777.

## Next steps

- Auth (login/logout, password hash verify)
- Posts CRUD with inline editor (slug/excerpt, hero-image upload)
- Comments moderation (approve/spam/delete) and frontend display
- Files manager (upload, delete, thumbnails)
- Categories CRUD
- Settings (tabs: General, Language, Theme, Email, Database, System, Debug & Logs)
- Pagination & search on frontend
