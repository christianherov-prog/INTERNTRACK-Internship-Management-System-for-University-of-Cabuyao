# INTERNTRACK

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

Capstone project — Group 4  
Stack: **React (Vite)** frontend · **Laravel Sanctum** REST API · **MySQL**

Repository: [Orb-BIT/interntrack-capstone](https://github.com/Orb-BIT/interntrack-capstone)  
Integration branch: **`MERGE-ONLY-BAWAL-MAG-PUSH`** (combined `develop` + `GawaNiValinadoV2`)  
Source branches stay unchanged: `develop`, `GawaNiValinadoV2`

## Features (MERGE-ONLY combined)

- Role-based portals: **Student**, **Faculty**, **Coordinator**, **Director**, **Industry Supervisor**, **MISD Admin**
- **In-app messaging** with attachments, archive/unarchive, unsend, clear-own-view, avatars, smoother navigation — includes **Director**
- Student **Clock In / Clock Out** attendance; **FO-30** DTR upload; **FO-31** weekly journal upload
- **Meetings** (orientation/check-in + RSVP) and canvas **e-signatures** (acknowledgment, not PKI)
- **Live notifications** via Laravel Reverb (HTTP polling fallback if Reverb is down)
- **500-hour OJT** requirement; faculty read-only attendance monitoring
- **Announcements** with audience targeting + attachments
- Coordinator report filters (program/industry); Director **3-year placement trends**
- Archive inactive students (coordinator/faculty)
- Secure private file downloads; **must-change-password** after staff reset; stronger ownership ACL
- Settings: iEnroll identity fields **read-only** (password / avatar / notifications still editable); supervisors fully editable
- MISD: **local mock** + Admin Sync portal (`ADMIN-MISD-001`) — not live institutional SSO

See [`PROGRESS_NOTES.md`](PROGRESS_NOTES.md), [`DEFENSE_SCRIPT.md`](DEFENSE_SCRIPT.md), and [`thesis/MANUSCRIPT_WORDING.md`](thesis/MANUSCRIPT_WORDING.md) for defense wording aligned to the code.

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

`php artisan migrate` (or `migrate:fresh --seed`) is required after pull so tables like **`meetings`** exist.  
`php artisan storage:link` is required for **avatars**, **message/announcement attachments**, and public-disk files.

Notifications are stored synchronously — no queue worker required for messaging or preference enforcement.

---

## Recent updates (MERGE-ONLY)

- Combined **develop** polish + **GawaNiValinadoV2** defense hardening on this branch only
- **MISD Admin** demo account seeded: `ADMIN-MISD-001` / `interntrack123`
- Director **placement trends** API restored
- Richer messaging (attachments, archive, unsend, clear) with **Director** kept in inbox
- FO-30 DTR fields, secure file access, must-change-password
- 500-hour OJT, announcements with attachments, archive inactive students
- Meetings / e-sign migrations; ErrorBoundary + portfolio confirm polish

---

## Recently fixed

- Missing `meetings` / `meeting_attendees` tables (run pending migrations)
- MISD admin login failing (`ADMIN-MISD-001` not seeded; username hyphen regex)
- `DirectorController::placementTrends()` missing after merge
- Login rate-limit copy; coordinator monitoring live statuses; Sem 2 term alignment

---

## Current features

### Authentication
- Username/password login via Laravel Sanctum
- Role-based session and protected API routes
- Change password; forced change after staff reset when flagged
- Rate-limited sensitive endpoints
- Inactive (`is_active=false`) accounts cannot log in

### Portals / roles
Six portals: **Student**, **Faculty Supervisor**, **Industry Supervisor**, **Internship Coordinator**, **PALD Director**, **MISD Admin**.

### Dashboard
- Role-specific dashboards
- Term badge / internship context for students
- Announcements on student/faculty dashboards (Policy Update badge when applicable)

### Internship management
- Coordinator placement and records
- Status tagging with reason and history
- Absorption after completion (Director finalizes hire outcomes)

### Documents
- Student uploads (private disk + secure download)
- Coordinator → faculty stage routing and review

### Attendance
- Student clock in/out; Industry Supervisor validation
- Faculty read-only monitoring
- FO-30 as uploaded DTR appendix (not an in-app form filler)

### Journals
- Weekly FO-31 logbook uploads
- Review paths for supervisor, faculty, and coordinator

### Messaging
- Inbox for student / faculty / industry supervisor / coordinator / **director**
- Attachments, archive/unarchive, unsend, clear (own view), peer avatars

### Meetings
- Orientation / check-in scheduling + RSVP

### Announcements
- Coordinator compose with audience targeting and attachments

### Supervisor management
- QR invite → public register → coordinator approval

### Notifications
- In-app Topbar notifications + persisted preferences

### Reports & analytics
- Coordinator reports with program/industry filters
- Director companies, MOA monitoring, absorption, **3-year placement trends**

### Portfolio
- Student portfolio builder (active internships included)

### User settings
- iEnroll identity **display-only** for student/faculty/coordinator/director/admin
- Password, avatar, and notification preferences remain editable
- Supervisors: full profile edit

### Access control
- API `role:*` guards; internship ownership checks for coordinators
- Secure file download endpoint for private uploads

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | React 18, Vite, React Router |
| Backend | Laravel 12 (PHP 8.2+), Sanctum API tokens |
| Database | MySQL 8.x |
| Auth | Laravel Sanctum |
| Realtime | Laravel Reverb (optional; UI falls back to polling) |
| Tooling | Composer, npm, PHPUnit feature tests, GitHub |

---

## Project status

INTERNTRACK is under **active capstone development**. On **`MERGE-ONLY-BAWAL-MAG-PUSH`**, core modules (auth, internships, documents, attendance, journals, messaging, meetings, announcements, supervisors, absorption, settings, reports, MISD admin, and RBAC) are **implemented** and wired end-to-end (July 2026).

Open items that need **department policy confirmation** (not missing by accident) are listed under Future Improvements below.

---

## Future improvements / policy-pending

- Dual-role accounts (one login with role switcher vs separate accounts) — **not implemented**; one `users.role` per account today
- Digital in-app FO-30 / FO-31 form fillers — currently **upload** of offline-filled forms
- Distinct **Training Program** module vs existing Training Plan **document** type
- **Live MISD/EMIS API** — mock is intentional for defense; real client not wired
- Optional Two-Factor Authentication (planned; not implemented)
- Chat attachments already shipped; browser Web Push / PKI DigiSign still future
- End-user / IT-expert surveys (outside the app)

---

## Features (quick list)

- Six portals including **MISD Admin**
- Student attendance, FO-30/FO-31 uploads, documents, evaluations, portfolio, messages, meetings
- Faculty attendance monitoring + archive inactive assigned students
- Coordinator placement, document stage routing, supervisor QR approvals, announcement attachments
- Internship status tagging with reason + history
- **500-hour** OJT progress
- Messaging with attachments/archive/unsend/clear/avatars — **Director included**
- Absorption tracking; director placement trends; coordinator report filters
- iEnroll identity lock in Settings; secure private files; must-change-password
- MISD mocked (`MISD_USE_MOCK`) + Admin Sync portal

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
git checkout MERGE-ONLY-BAWAL-MAG-PUSH
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

Create the empty MySQL databases, then migrate:

```sql
CREATE DATABASE interntrack;
CREATE DATABASE interntrack_testing;  -- used only by php artisan test
```

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001
```

**Database:** INTERNTRACK is **MySQL-only** (SQLite is not used for the app or PHPUnit).

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

**MISD default-password provision:** keep `MISD_ALLOW_DEFAULT_PASSWORD_PROVISION=false` on staging/production (non-local). First-login auto-provision from MISD using the demo default password is allowed only when `APP_ENV=local`, or when you explicitly set `MISD_ALLOW_DEFAULT_PASSWORD_PROVISION=true` for a controlled demo. Prefer pre-seeded or staff-assigned accounts outside local.

| Role | Username | Name |
|------|----------|------|
| MISD Admin | `ADMIN-MISD-001` | MISD Administrator |
| Student | `2300600` | Christian Hero Valinado (4ITD) |
| Student | `2300592` | Clarence Montealegre (4ITD) |
| Faculty | `FAC-1001` | — |
| Coordinator | `COR-1001` | — |
| Director (PALD) | `DIR-1001` | — |
| Industry Supervisor | `SUP-1001` | (via `SurveyPlacementSeeder`) |

After `migrate:fresh --seed`, use the table above. If you only ran `migrate` on an existing DB, create/reset the MISD admin with seed or ask a teammate — login is `ADMIN-MISD-001` / `interntrack123`.

Additional Industry Supervisors can also be created via:

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
