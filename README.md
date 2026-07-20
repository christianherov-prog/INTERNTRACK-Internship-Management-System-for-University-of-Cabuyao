# INTERNTRACK

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

Capstone project — Group 4  
Stack: **React (Vite)** frontend · **Laravel Sanctum** REST API · **MySQL**

Repository: [Orb-BIT/interntrack-capstone](https://github.com/Orb-BIT/interntrack-capstone) · active branch: **`develop`**

---

## 🚀 Recent Updates

- **Post-completion absorption tracking** — supervisors and coordinators record hire outcomes; directors view absorption analytics
- **Shared absorption UI** — `RoleAbsorption` component with load-error handling for coordinator, student, and director views
- **Internship status workflow** — active / completed / suspended / deferred / expelled with reason and status history timeline
- **Document stage routing** — coordinator → faculty review path for internship documents
- **Completion certificates** — PDF generation when an internship is marked completed
- **Supervisor invitation workflow** — student QR invite → public register → coordinator approval
- **Profile & settings** — profile fields persisted to the database; avatar upload; toast on successful save; notification preferences stored server-side
- **Academic year / term config** — `INTERNTRACK_CURRENT_TERM` / `VITE_INTERNTRACK_CURRENT_TERM` (current: **AY 2025-2026, Sem 2**)
- **Student demo accounts** — seeded students `2300600` and `2300592` via `StudentAccountsSeeder`
- **API hardening** — rate limiting on login, supervisor registration, change-password, and avatar upload
- **Performance** — database index on `internships.absorption_status`; eager-load company on coordinator monitoring (N+1 fix)
- **Feature tests** — Auth, absorption flow, and internship status coverage
- **Docs & setup** — README / SETUP / DATABASE_COMMANDS aligned with MySQL + `migrate:fresh --seed`

---

## 🛠 Recently Fixed

- Login failures caused by **UTF-8 BOM** on PHP sources after config cache clear
- Academic term / semester **drift** (footer and seeder/internship defaults aligned to Sem 2)
- Portfolio and records missing **active** internships
- Avatar / profile photo serving after `storage:link`
- Logout and Sanctum token invalidation behavior
- Coordinator monitoring **N+1** queries when loading company data
- Scratch / local noise files stopped from being tracked; `.gitignore` encoding fixed
- Progress Report DOCX cleanup (keep Updated report; ignore future `.docx` adds)

---

## Current Features

### Authentication
- Username/password login via Laravel Sanctum
- Role-based session and protected API routes
- Change password (authenticated)
- Rate-limited sensitive endpoints

### Dashboard
- Role-specific dashboards (Student, Faculty, Coordinator, Director, Industry Supervisor)
- Term badge / internship context for students

### Internship Management
- Placement and internship records
- Status tagging with reason and history
- Absorption status after completion

### Document Management
- Student document uploads
- Coordinator → faculty stage routing and review

### Attendance
- Student attendance logging and review

### Journals
- Weekly logbook / journal entries

### Supervisor Management
- QR-based supervisor invitation
- Public supervisor registration
- Coordinator approval of pending supervisors

### Notifications
- In-app notifications
- Server-persisted notification preferences

### Reports & Analytics
- Director absorption analytics
- Role monitoring views (e.g. coordinator monitoring)

### Portfolio Builder
- Student portfolio generation (active internships included)

### Certificates
- Completion certificate PDF for completed internships

### User Settings
- Profile edit (saved to DB)
- Avatar upload
- Password change
- Notification preferences

### Role-Based Access Control
- Five roles with portal and API guards
- Unauthorized admin routes return 403 for restricted roles (e.g. industry supervisor)

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | React 18, Vite, React Router |
| Backend | Laravel 11 (PHP 8.2+), Sanctum API tokens |
| Database | MySQL 8.x |
| Auth | Laravel Sanctum |
| PDF / docs | Server-side certificate PDF generation; DOCX templates where applicable |
| Tooling | Composer, npm, PHPUnit feature tests, GitHub (`develop`) |

---

## 📈 Project Status

INTERNTRACK is under **active capstone development**. Core modules (auth, internships, documents, attendance, journals, supervisors, absorption, certificates, settings, and RBAC) are **implemented and verified** through the latest system audit (July 2026).

Remaining work is mainly **minor refinements**, usability polish, documentation upkeep, and research-related deliverables (e.g. progress report / survey materials outside the app).

---

## 🔮 Future Improvements

- Stronger password validation UX (confirm field / clearer toasts)
- Optional Two-Factor Authentication (planned; not implemented)
- Broader API/UI error handling consistency
- Further shared-component and controller cleanup
- Removal of unused UI helpers (e.g. unused StatCard)
- Replace remaining `window.confirm` prompts with in-app dialogs
- Documentation and research packaging updates

---

## Features (quick list)

- Role-based portals: **Student**, **Faculty**, **Coordinator**, **Director**, **Industry Supervisor**
- Student attendance, weekly logbook, documents, evaluations, portfolio
- Coordinator placement, document stage routing (coordinator → faculty), supervisor QR approvals
- Internship status tagging: active / completed / suspended / deferred / expelled (with reason + history)
- Completion certificate PDF when an internship is marked completed
- Post-completion absorption tracking: supervisor/coordinator hire outcomes + director analytics
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
git checkout develop
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
INTERNTRACK_CURRENT_TERM="AY 2025-2026, Sem 2"
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

Ensure `frontend/.env` matches the API and term:

```env
VITE_API_BASE_URL=http://127.0.0.1:8001/api/v1
VITE_INTERNTRACK_CURRENT_TERM=AY 2025-2026, Sem 2
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
├── CHANGELOG.md      # Dated release notes
└── README.md         # This file
```

## Notes

- Do **not** commit `backend/.env` or `frontend/.env` (gitignored).
- `php artisan storage:link` is required for avatar/profile photos.
- If login fails after a teammate’s DB reset, run `php artisan db:seed --class=StudentAccountsSeeder` (or `migrate:fresh --seed`).
- Prefer the **`develop`** branch for ongoing capstone work.

## License

Capstone academic project — University of Cabuyao.
