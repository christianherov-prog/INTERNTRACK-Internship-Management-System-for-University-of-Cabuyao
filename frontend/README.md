# INTERNTRACK Frontend (React + Vite)

Student/Intern portal. The UI is a 1:1 port of the HTML prototype pages (same markup and `master-style.css` from the project root — do not restyle).

## Requirements

- Node.js >= 18

## Setup

```bash
cd frontend
npm install
npm run dev        # http://localhost:5173
```

The API base URL is read from `.env`:

```
VITE_API_BASE_URL=http://localhost:8000/api
```

Make sure the Laravel backend is running (`php artisan serve` in `../backend`) before logging in.

## Pages

- `/login` — sign in / sign up (student number + password)
- `/` — Dashboard (all numbers from `/api/student/dashboard`)
- `/attendance` — time in/out CRUD
- `/logbook` — journal entry CRUD
- `/documents` — requirement checklist + upload
- `/evaluations` — read-only scores
- `/settings` — profile update

The Records page from the prototype was intentionally removed (coordinator/PALD scope).
