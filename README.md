# INTERNTRACK

Internship management system for the University of Cabuyao (Group 4 Capstone).

## Project structure

| Folder | What it is | Stack |
| --- | --- | --- |
| `backend/` | REST API (Student/Intern role) | Laravel 12, PHP 8.2+, MySQL, Sanctum auth |
| `frontend/` | Student portal web app | React 18, Vite, JavaScript |
| root `*.html`, `master-style.css`, `script.js` | Original static prototype (design reference only) | HTML/CSS/JS |

## Quick start for teammates

### 1. Backend (needs PHP 8.2+, Composer, MySQL — XAMPP or Laragon works)

```bash
cd backend
composer install
copy .env.example .env      # cp on macOS/Linux
php artisan key:generate
```

Create a MySQL database named `interntrack` (set `DB_USERNAME`/`DB_PASSWORD` in `backend/.env` if yours differ), then:

```bash
php artisan migrate
php artisan serve           # API at http://localhost:8000
```

### 2. Frontend (needs Node.js 18+)

```bash
cd frontend
npm install
npm run dev                 # App at http://localhost:5173
```

Open http://localhost:5173, click "Create an account", and sign up with a student number + password. A fresh account starts with zero hours/entries — all dashboard numbers come from the database.

See `backend/README.md` for the full API endpoint list and `frontend/README.md` for frontend details.
