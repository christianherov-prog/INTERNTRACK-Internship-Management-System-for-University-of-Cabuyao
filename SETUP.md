# INTERNTRACK — Local Setup (after `git pull`)

Stack: React (Vite) frontend + Laravel Sanctum API + **MySQL only** (Laragon recommended). SQLite is not used.

## Prerequisites

- PHP 8.2+, Composer, Node 18+, **MySQL 8.x**
- Create databases: `interntrack` (app) and `interntrack_testing` (PHPUnit)
- PHP 8.2+ on PATH (or use your Laragon/XAMPP 8.2+ binary)

## 1. Backend

```bash
cd backend
composer install
cp .env.example .env   # skip if you already have a .env
php artisan key:generate
```

Configure `.env`:

- `DB_*` — your local MySQL database
- `APP_URL` — **must match** the origin where you serve Laravel (e.g. `http://127.0.0.1:8001`)

Then (recommended full teammate setup — creates tables **and** demo accounts):

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001
```

Or, if you already have data you want to keep:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001
```

`migrate:fresh --seed` is what groupmates should run after `git pull` so shared seeded accounts exist in their local MySQL. Student accounts live in `database/seeders/StudentAccountsSeeder.php` (registered from `DatabaseSeeder.php`) — they are **not** created only in one person’s local DB.

`storage:link` is required for profile photos (`/storage/avatars/...`). Without it, uploads may save to disk but images will 404 in the browser.

### Seeded student logins (password for all: `interntrack123`)

| Username | Name | Section |
|----------|------|---------|
| `2300600` | Christian Hero Valinado | 4ITD |
| `2300592` | Clarence Montealegre | 4ITD |

Staff: `DIR-1001`, `COR-1001`, `FAC-1001` (same password).

## 2. Frontend

```bash
cd frontend
npm install
cp .env.example .env   # skip if you already have a .env
npm run dev
```

Ensure `VITE_API_BASE_URL` in `frontend/.env` points at the same API host/port as the backend (default: `http://127.0.0.1:8001/api/v1`).

## 3. Live notifications (Reverb — optional)

To demo **WebSocket** updates (not required for basic CRUD demos):

```bash
# third terminal
cd backend
php artisan reverb:start
```

Match `REVERB_*` in `backend/.env` with `VITE_REVERB_*` in `frontend/.env` (see `frontend/.env.example`). Restart Vite after changing `VITE_*`.

**Without** `VITE_REVERB_APP_KEY`, the Topbar uses **~60s polling** only — still correct if the defense says so. Do not claim “push” unless Reverb is running.

Also see [`DEFENSE_SCRIPT.md`](DEFENSE_SCRIPT.md) and [`PROGRESS_NOTES.md`](PROGRESS_NOTES.md).

## 4. Roles & API lists

Supported portals: **Student**, **Director**, **Coordinator**, **Faculty**, and
**Supervisor** (company HTE — via QR invite + coordinator approval).

List endpoints return a standard envelope:

```json
{ "data": [ ... ], "meta": { "current_page", "last_page", "per_page", "total" } }
```

Grouped evaluation lists use `{ "data": { "pending": [], "completed": [] } }`.

Authenticated roles can also call `GET /api/v1/dashboard/summary` for live KPI
counts. `users.last_login_at` already exists and is refreshed on successful login.

## 5. Profile photos (avatars)

- Column: `users.avatar_path` (migration `2026_07_18_193000_add_avatar_path_to_users_table.php`)
- Upload endpoint: `POST /api/v1/auth/avatar` (multipart field name: `avatar`)
- Files live under `backend/storage/app/public/avatars/` and are served via the `public/storage` symlink
- Avatar URLs are built from the API request host — do **not** hardcode a teammate’s machine path

## 6. Do not commit

These are gitignored (keep them local):

- `backend/.env`, `frontend/.env`
- `backend/vendor/`, `frontend/node_modules/`
- `backend/public/storage` (symlink recreated by `php artisan storage:link`)
- Uploaded files under `backend/storage/app/public/` (placeholder `.gitignore` stays tracked)

Migrations under `backend/database/migrations/` **are** tracked — always commit new migration files.

## 7. Production / defense-demo env checklist

Before a production-like demo (not for everyday local seeding):

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `FRONTEND_URL` set to the real SPA origin (supervisor invite links)
- [ ] `SANCTUM_TOKEN_EXPIRATION` set (minutes; default 1440 = 24h)
- [ ] `INTERNTRACK_CURRENT_TERM` matches the demo academic term
- [ ] `MISD_ALLOW_DEFAULT_PASSWORD_PROVISION=false` outside local (config already defaults to false when `APP_ENV !== local` — set explicitly in staging/prod)
- [ ] Rotate `APP_KEY`, `REVERB_APP_SECRET`, DB credentials
- [ ] Sensitive uploads (journals/docs/signatures/portfolio) use the private disk + `GET /api/v1/files/download` — avatars may remain public via `storage:link`
- [ ] Confirm Reverb secrets are not left as repo defaults if the demo is exposed beyond localhost
