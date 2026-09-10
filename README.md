# InternTrack

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

**Capstone Project ? Group 4**  
**Tech Stack:** React (Vite) Frontend | Laravel Sanctum REST API | MySQL

**Repository:** [christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao](https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao)  
**Main branch:** [`develop`](https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao/tree/develop)

---

## Current workflow on `develop`

| Area | Behavior |
|------|----------|
| Journals (FO-31) | Student submits a **weekly** journal. **Faculty** reviews (approve / needs revision). **Supervisors do not validate journals.** Student Journal, Faculty review, and Portfolio FO-31 use the **same record**. |
| Attendance (FO-30) | Clock-in/out and DTR share official-form data. Display times use **Asia/Manila (UTC+08:00)**. |
| Supervisors | Unique `SUP-xxxx` IDs. Students invite; Faculty/Coordinator approve registration. Industry supervisors validate **attendance**, not journals. |
| Identity | Student name and student number come from the Student/Profile resource (not generic ?UC Student? fallbacks when a profile exists). |
| Loading | Page shell (sidebar, header, title) stays visible. The PNC logo loader is used only inside content when there is no cached data yet. |
| Departments | Faculty/Coordinator APIs stay scoped to assigned college/department (CCS faculty do not see CHAS/COE journals by default). |

---

## Core Features & Modules

This project was built with a modular feature-branch workflow. Isolated module history still exists on these branches; **`develop` is the integration branch to use**.

* `feature/login-authentication` ? Secure login, role-based routing, and must-change-password flows.
* `feature/messaging-system` ? In-app messaging with attachments, archive/unarchive, unsend, and avatars.
* `feature/dtr-management` ? Student clock in / clock out, FO-30 DTR, and FO-31 weekly journals.
* `feature/supervisor-evaluations` ? HTE / industry supervisor performance evaluations and intern feedback (not journal approval).
* `feature/pdf-reports-export` ? PDF generation for official forms, portfolios, and compliance reports.
* `feature/email-notifications` ? Reverb notifications and system email alerts.
* `feature/admin-dashboard` ? MISD Admin dashboard for mock sync and section mapping.
* `feature/coordinator-compliance` ? Coordinator dashboards and Director placement reporting.
* `feature/faculty-monitoring` ? Faculty assigned students, **journal review**, documents, evaluations, and supervisor approvals.
* `CCSportfoliofeatures` / `COEDportfoliofeatures` / `COEportfoliofeatures` ? College e-Portfolio builders.

---

## Setup Instructions

### Backend (Laravel)
1. Navigate to the `backend` folder: `cd backend`
2. Install dependencies: `composer install`
3. Copy environment variables: `cp .env.example .env` (ensure variables match the setup notes below)
4. Generate app key: `php artisan key:generate`
5. Migrate and seed the database: `php artisan migrate:fresh --seed`
6. Link storage (for avatars/documents): `php artisan storage:link`
7. Start the server: `php artisan serve --host=127.0.0.1 --port=8001`

Local development URLs (do not treat a custom hostname as a production requirement):
- Frontend: `http://localhost:5173` (Vite may also show `http://127.0.0.1:5173`)
- API: `http://127.0.0.1:8001` (`APP_URL` / `VITE_API_BASE_URL` in this repo default to this port)

Optional: map `interntrack.local` in your hosts file for convenience. It is not required for the app to run.

Fresh seed password (meets the change-password policy): `InternTrack123!`  
Existing local databases that were seeded earlier may still use `interntrack123` until you reseed or reset those accounts.

Demo staff / student logins after seed include `FAC-1001` (Marvin Bicua, CCS Faculty), `2300592` (Clarence Montealegre), and `2300590` (Angel Luis Taac - Taac). See [`SETUP.md`](SETUP.md) and [`docs/MANUAL_ACCOUNTS.md`](docs/MANUAL_ACCOUNTS.md).

### Frontend (React/Vite)
1. Navigate to the `frontend` folder: `cd frontend`
2. Install dependencies: `npm install`
3. Copy environment variables: `cp .env.example .env`
4. Start the development server: `npm run dev`

### Environment Configuration Notes
| Variable | File | Purpose |
|----------|------|---------|
| `INTERNTRACK_DEFAULT_PASSWORD` | `backend/.env` | Seed/demo password (`InternTrack123!`) |
| `INTERNTRACK_TARGET_HOURS=500` | `backend/.env` | Legacy seeder fallback only ? live hours come from program configuration |
| `VITE_INTERNTRACK_TARGET_HOURS=500` | `frontend/.env` | Do not treat as the program requirement |
| `INTERNTRACK_CURRENT_TERM` | `backend/.env` | Academic term label (e.g., "AY 2025-2026, Sem 2") |
| `VITE_INTERNTRACK_CURRENT_TERM` | `frontend/.env` | Must match backend term |
| `MISD_USE_MOCK=true` | `backend/.env` | Use local mock MISD (default) |
| `INTERNTRACK_UPLOAD_MAX_MB=10` | `backend/.env` | Max upload size (messages, announcements) |

---
*Created for the University of Cabuyao (Pamantasan ng Cabuyao)*
