# INTERNTRACK Backend (Laravel + MySQL)

REST API for the Student/Intern role. Auth is Laravel Sanctum (Bearer tokens), passwords hashed with bcrypt.

## Requirements

- PHP >= 8.2 (with `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`)
- Composer
- MySQL 8 (or MariaDB)

## Setup

```bash
cd backend
composer install
copy .env.example .env       # Windows (use cp on macOS/Linux)
php artisan key:generate
```

Create the database first (adjust credentials in `.env`):

```sql
CREATE DATABASE interntrack;
```

Then run migrations (creates fresh tables only — nothing existing is dropped):

```bash
php artisan migrate
php artisan serve            # http://localhost:8000
```

## Endpoints (prefix: /api)

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| POST | /auth/register | – | Sign up (student_number, password + confirmation, full_name, course_year_section, company_name) |
| POST | /auth/login | – | Login, returns Sanctum token |
| POST | /auth/logout | token | Revoke current token |
| GET | /auth/me | token | Current student profile |
| PUT | /auth/profile | token | Update own profile |
| GET | /student/dashboard | token | Aggregated dashboard summary |
| GET/POST | /student/attendance | token | List / log own time in-out |
| PUT/DELETE | /student/attendance/{id} | token | Edit / delete own log |
| GET/POST | /student/logbook | token | List / submit journal entries |
| PUT/DELETE | /student/logbook/{id} | token | Edit / delete own entry (locked once reviewed) |
| GET/POST | /student/documents | token | Requirement checklist / upload file |
| DELETE | /student/documents/{id} | token | Delete own upload (locked once approved) |
| GET | /student/evaluations | token | Read-only evaluations + score breakdown |
| GET | /announcements | token | Read-only published announcements |

CORS allows the React dev server origins listed in `FRONTEND_URL` in `.env`.
