# INTERNTRACK

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

Capstone project — Group 4  
Stack: **React (Vite)** frontend · **Laravel Sanctum** REST API · **MySQL**

## Features

- Role-based portals: **Student**, **Faculty**, **Coordinator**, **Director**, **Industry Supervisor**
- Student attendance, weekly logbook, documents, evaluations, portfolio
- Coordinator placement, document stage routing (coordinator → faculty), supervisor QR approvals
- Internship status tagging: active / completed / suspended / deferred / expelled (with reason + history)
- Completion certificate PDF when an internship is marked completed
- Profile settings saved to the database + avatar upload

## Requirements

| Tool | Version / notes |
|------|-----------------|
| PHP | **8.2+** (8.2 / 8.3 recommended) |
| Composer | 2.x |
| Node.js | 18+ (npm) |
| MySQL | 8.x (Laragon / XAMPP / local MySQL) |

## How to run (first time)

### 1. Clone

```bash
git clone https://github.com/Orb-BIT/interntrack-capstone.git
cd interntrack-capstone
```

### 2. Backend (Laravel API)

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Edit `backend/.env` and set MySQL (example):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=interntrack
DB_USERNAME=root
DB_PASSWORD=

APP_URL=http://127.0.0.1:8001
```

Create the empty MySQL database `interntrack`, then:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001
```

Leave this terminal running. API: `http://127.0.0.1:8001`

> `migrate:fresh --seed` recreates tables and loads demo accounts. Use this after a fresh clone so teammates get the same logins.

### 3. Frontend (React)

Open a **second** terminal:

```bash
cd frontend
npm install
copy .env.example .env
npm run dev
```

Open the URL Vite prints (usually `http://127.0.0.1:5173`).

Ensure `frontend/.env` matches the API:

```env
VITE_API_BASE_URL=http://127.0.0.1:8001/api/v1
```

## How to run (after `git pull`)

```bash
# Backend
cd backend
composer install
php artisan migrate
php artisan db:seed
# or full reset: php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001

# Frontend (other terminal)
cd frontend
npm install
npm run dev
```

More detail: see [`SETUP.md`](./SETUP.md) and [`DATABASE_COMMANDS.md`](./DATABASE_COMMANDS.md).

## Demo accounts

Password for **all** seeded accounts: `interntrack123`

| Role | Username | Name |
|------|----------|------|
| Student | `2300600` | Christian Hero Valinado (4ITD) |
| Student | `2300592` | Clarence Montealegre (4ITD) |
| Faculty | `FAC-1001` | — |
| Coordinator | `COR-1001` | — |
| Director | `DIR-1001` | — |

Industry Supervisor accounts are **not** seeded. Create one via:

**Student → Invite Supervisor (QR) → public register → Coordinator approve.**

## Project structure

```
interntrack-capstone/
├── backend/          # Laravel API (Sanctum)
├── frontend/         # React (Vite)
├── SETUP.md          # Detailed local setup
├── DATABASE_COMMANDS.md
└── README.md         # This file
```

## Notes

- Do **not** commit `backend/.env` or `frontend/.env` (gitignored).
- `php artisan storage:link` is required for avatar/profile photos.
- If login fails after a teammate’s DB reset, run `php artisan db:seed --class=StudentAccountsSeeder` (or `migrate:fresh --seed`).

## License

Capstone academic project — University of Cabuyao.
