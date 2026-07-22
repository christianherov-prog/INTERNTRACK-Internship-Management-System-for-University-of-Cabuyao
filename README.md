# INTERNTRACK

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

Capstone project — Group 4  
Stack: **React (Vite)** frontend · **Laravel Sanctum** REST API · **MySQL**

Repository: [Orb-BIT/interntrack-capstone](https://github.com/Orb-BIT/interntrack-capstone) · active branch: **`develop`**

---

## New / Updated Functionalities

- **In-app messaging** — shared `MessagesInbox` for **Student**, **Faculty Supervisor**, **Industry Supervisor**, and **Coordinator** (role-scoped to shared internships). **Director has no messaging portal** (by design). Supports attachments (images/docs on the public disk), per-user archive/unarchive, unsend, clear-own-view, peer avatars, and smooth client-side conversation navigation (no full-page reload; race-safe thread fetches). Industry Supervisor ↔ Faculty messaging works when both are on the same internship. API: `/api/v1/messages/*` (send throttled at 30/min).
- **Faculty attendance monitoring** — Faculty Supervisors can view assigned students’ attendance/logged hours (read-only) at `/faculty/attendance`. Validation remains with Industry Supervisors.
- **Announcements** — Coordinators post with audience targeting (students, faculty, supervisors, coordinators, or all), categories **General** / **Policy Update**, and optional file attachment. Student and Faculty dashboards show Policy Update badges and attachments.
- **500-hour OJT requirement** — CCS uniform target hours. Config: `INTERNTRACK_TARGET_HOURS` / `VITE_INTERNTRACK_TARGET_HOURS` (default **500**). Progress = logged hours ÷ target (no hardcoded 20h/week pace line on the student weekly chart).
- **Report filters** — Coordinator reports accept `program` and `industry` query filters (UI + backend on student-summary, compliance, performance).
- **3-year placement trends** — Director Reports: `GET /api/v1/director/reports/placement-trends` aggregates placements by company × academic year (SQL `GROUP BY`).
- **Archive inactive students** — Coordinators (`/coordinator/records`) and Faculty (`/faculty/assigned-students`) Active/Archived tabs; toggles `users.is_active` (reversible). Archived students are excluded from default lists and cannot log in.
- **Survey demo placements** — `SurveyPlacementSeeder` links students `2300600` / `2300592` to FAC-1001, industry supervisor, COR-1001, and an active MOA company (also called from `DatabaseSeeder`).
- **Notification preference enforcement** — `Notification::notify()` skips types the recipient opted out of in Settings.
- **Coordinator monitoring** — live lists/counts include `active`, `ongoing`, `placed`, and `for_evaluation`.
- **Login rate-limit copy** — throttled login returns a login-specific JSON message (not the messaging 429 text).
- **MISD / EMIS** — intentionally **mocked** for defense (`MockMisdController` + `MisdIntegrationService`, `MISD_USE_MOCK=true`). Not a live EMIS client. Mock routes register only when `APP_ENV=local`.

### Setup notes (new / updated)

| Variable | Where | Purpose |
|----------|--------|---------|
| `INTERNTRACK_TARGET_HOURS=500` | `backend/.env` | OJT required hours |
| `VITE_INTERNTRACK_TARGET_HOURS=500` | `frontend/.env` | FE fallback display |
| `INTERNTRACK_CURRENT_TERM` | `backend/.env` | Academic term label |
| `VITE_INTERNTRACK_CURRENT_TERM` | `frontend/.env` | Must match backend term |
| `MISD_USE_MOCK=true` | `backend/.env` | Use local mock MISD (default) |
| `INTERNTRACK_UPLOAD_MAX_MB=10` | `backend/.env` | Max upload size (messages, announcements, etc.) |
| `CACHE_STORE=file` or `database` | `backend/.env` | Required for rate limits under `artisan serve` (not `array`) |

`php artisan storage:link` is required for **avatars**, **message/announcement attachments**, and other files on the **public** disk.

Notifications are stored synchronously — no queue worker required for messaging or preference enforcement.

---

## 🚀 Recent Updates

- **Post-completion absorption tracking** — supervisors and coordinators record hire outcomes; directors view absorption analytics (`DirectorAbsorption`); coordinator/supervisor share `RoleAbsorption`
- **Internship status workflow** — active / completed / suspended / deferred / expelled with reason and status history
- **Document stage routing** — coordinator → faculty review path
- **Completion certificates** — PDF when an internship is marked completed
- **Supervisor invitation workflow** — student QR invite → public register → coordinator approval
- **Profile & settings** — DB-backed profile fields; avatar upload; notification preferences
- **Academic year / term config** — current default **AY 2025-2026, Sem 2**
- **API hardening** — rate limiting on login, supervisor registration, change-password, avatar upload, and messaging
- **Feature tests** — Auth, absorption, internship status, messaging (incl. archive/unsend/clear), notification preferences
- **Dead-code cleanup** — unused API stubs/routes, unused frontend barrel/exports, ExampleTest scaffolding, unused npm `bootstrap` / `@types/react*`, unused `laravel/sail` (local commits on `develop`)

---

## 🛠 Recently Fixed

- Login failures caused by **UTF-8 BOM** on PHP sources after config cache clear
- Academic term / semester **drift** (footer and seeder/internship defaults aligned to Sem 2)
- Portfolio and records missing **active** internships
- Avatar / profile photo serving after `storage:link`
- Logout and Sanctum token invalidation behavior
- Coordinator monitoring excluding **`active`** internships (now includes live statuses)
- Incorrect **429** copy on login when messaging throttle text leaked globally
- Scratch / local noise files ignored via `.gitignore` (`*.docx`, `*.pdf`, local scratch paths)

---

## Current Features

### Authentication
- Username/password login via Laravel Sanctum
- Role-based session and protected API routes
- Change password (authenticated)
- Rate-limited sensitive endpoints
- Inactive (`is_active=false`) accounts cannot log in

### Portals / roles
Five portals: **Student**, **Faculty Supervisor**, **Industry Supervisor** (`supervisor`), **Internship Coordinator**, **PALD Director** (`director`).  
There is **no** Academic Personnel or IT Expert role in the app (those appear only as external research audiences). The DB enum also includes unused `admin` (no portal).

### Dashboard
- Role-specific dashboards for all five portals
- Term badge / internship context for students
- Announcements on student/faculty dashboards (with Policy Update badge when applicable)

### Internship Management
- Coordinator placement and records
- Status tagging with reason and history (coordinator + director)
- Absorption after completion (supervisor/coordinator record; student declare; director analytics)

### Document Management
- Student document uploads
- Coordinator → faculty stage routing and review

### Attendance
- Student attendance logging
- Industry Supervisor validation (incl. bulk)
- Faculty Supervisor monitoring (assigned students, read-only)

### Journals
- Weekly logbook / journal entries (file upload)
- Review paths for supervisor, faculty, and coordinator

### Messaging
- Shared inbox for student / faculty / industry supervisor / coordinator
- Attachments, archive/unarchive, unsend, clear (own view), peer avatars
- Industry ↔ Faculty on shared internships

### Announcements
- Coordinator compose with audience targeting and **General** / **Policy Update** categories
- Optional file attachment

### Supervisor Management
- QR-based supervisor invitation
- Public supervisor registration
- Coordinator approval of pending supervisors

### Notifications
- In-app notifications (Topbar)
- Server-persisted notification preferences

### Reports & Analytics
- Coordinator reports with **program** and **industry** filters
- Coordinator evaluations / supervisor-feedback oversight pages
- Director companies, MOA monitoring, absorption analytics
- Director **3-year placement trends** by company
- Coordinator monitoring dashboard

### Portfolio Builder
- Student portfolio generation (active internships included)

### Certificates
- Completion certificate PDF for completed internships

### User Settings
- Profile edit (saved to DB)
- Avatar upload
- Password change
- Notification preferences
- Archive / unarchive students (coordinator & faculty)

### Role-Based Access Control
- Five portals with API `role:*` guards
- Unauthorized routes return 403 for restricted roles

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | React 18, Vite, React Router |
| Backend | Laravel 12 (PHP 8.2+), Sanctum API tokens |
| Database | MySQL 8.x |
| Auth | Laravel Sanctum |
| PDF | Server-side completion certificate generation |
| Tooling | Composer, npm, PHPUnit feature tests, GitHub (`develop`) |

---

## 📈 Project Status

INTERNTRACK is under **active capstone development**. Core modules (auth, internships, documents, attendance, journals, messaging, announcements, supervisors, absorption, certificates, settings, reports, and RBAC) are **implemented** and wired end-to-end (July 2026).

Open items that need **department policy confirmation** (not missing by accident) are listed under Future Improvements below.

---

## 🔮 Future Improvements / policy-pending

- Dual-role accounts (one login with role switcher vs separate accounts) — **not implemented**; one `users.role` per account today
- Student **name lock** (read-only after MISD provision) — **not implemented**; name remains editable in Settings
- Logbook/DTR frequency: keep **weekly** journals vs semester-end compiled submission — code is weekly-only today
- Distinct **Training Program** module vs existing Training Plan **document** type — no separate training-program module yet
- **Live MISD/EMIS API** — mock is intentional for defense; real client not wired
- Optional Two-Factor Authentication (planned; not implemented)
- Stronger password validation UX; broader API/UI error-handling consistency
- End-user / IT-expert surveys (outside the app)

---

## Features (quick list)

- Five portals: Student, Faculty Supervisor, Industry Supervisor, Coordinator, PALD Director
- Student attendance, weekly logbook, documents, evaluations, portfolio, messages
- Faculty attendance monitoring + archive inactive assigned students
- Coordinator placement (ownership-checked), document stage routing, supervisor QR approvals, Policy Update announcements
- Internship status tagging with reason + history
- **500-hour** OJT progress (hours vs target; no fixed weekly-pace chart line)
- Messaging: attachments, archive, unsend, clear, avatars; Industry ↔ Faculty
- Completion certificate PDF; absorption tracking; director placement trends
- Coordinator report filters by program and industry
- Profile + avatar + notification preferences; MISD mocked (`MISD_USE_MOCK`)

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
INTERNTRACK_TARGET_HOURS=500
MISD_USE_MOCK=true
INTERNTRACK_UPLOAD_MAX_MB=10
CACHE_STORE=file
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
VITE_INTERNTRACK_TARGET_HOURS=500
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
| Director (PALD) | `DIR-1001` | — |

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
- `php artisan storage:link` is required for avatars **and** message/announcement/document uploads on the public disk.
- If login fails after a teammate’s DB reset, run `php artisan db:seed --class=StudentAccountsSeeder` (or `migrate:fresh --seed`).
- Prefer the **`develop`** branch for ongoing capstone work.

## License

Capstone academic project — University of Cabuyao.
