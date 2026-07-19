# INTERNTRACK — Local Setup (after `git pull`)

Stack: React (Vite) frontend + Laravel Sanctum API + MySQL (Laragon recommended).

Requires **PHP 8.2+** on PATH (or invoke artisan with your Laragon/XAMPP 8.2+ binary).

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

## 3. Roles & API lists

Supported portals: **Student**, **Director**, **Coordinator**, **Faculty**, and
**Supervisor** (company HTE — via QR invite + coordinator approval).

List endpoints return a standard envelope:

```json
{ "data": [ ... ], "meta": { "current_page", "last_page", "per_page", "total" } }
```

Grouped evaluation lists use `{ "data": { "pending": [], "completed": [] } }`.

Authenticated roles can also call `GET /api/v1/dashboard/summary` for live KPI
counts. `users.last_login_at` already exists and is refreshed on successful login.

## 4. Profile photos (avatars)

- Column: `users.avatar_path` (migration `2026_07_18_193000_add_avatar_path_to_users_table.php`)
- Upload endpoint: `POST /api/v1/auth/avatar` (multipart field name: `avatar`)
- Files live under `backend/storage/app/public/avatars/` and are served via the `public/storage` symlink
- Avatar URLs are built from the API request host — do **not** hardcode a teammate’s machine path

## 5. Do not commit

These are gitignored (keep them local):

- `backend/.env`, `frontend/.env`
- `backend/vendor/`, `frontend/node_modules/`
- `backend/public/storage` (symlink recreated by `php artisan storage:link`)
- Uploaded files under `backend/storage/app/public/` (placeholder `.gitignore` stays tracked)

Migrations under `backend/database/migrations/` **are** tracked — always commit new migration files.
