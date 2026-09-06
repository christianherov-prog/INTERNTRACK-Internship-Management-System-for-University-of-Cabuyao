# InternTrack

Internship Management System for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

**Capstone Project ù Group 4**  
**Tech Stack:** React (Vite) Frontend | Laravel Sanctum REST API | MySQL

**Repository:** [christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao](https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao)  
**Main Branch:** `develop`

---

## ?? Core Features & Modules

This project is built with a modular feature-branch workflow. Below are the key feature modules available in this repository:

### Feature Branches Available
You can check out any of these branches to see the isolated work for specific modules:

* `feature/login-authentication` - Secure login, JWT authentication, role-based routing, and "must-change-password" flows.
* `feature/messaging-system` - Real-time in-app messaging with attachments, archive/unarchive, unsend, and user avatars.
* `feature/dtr-management` - Student Clock In / Clock Out attendance tracking, FO-30 DTR uploads, and FO-31 weekly journal uploads.
* `feature/supervisor-evaluations` - HTE and Industry Supervisor performance evaluations and feedback forms.
* `feature/pdf-reports-export` - Automated, pixel-perfect PDF/DOCX generation for student portfolios and coordinator compliance reports.
* `feature/email-notifications` - Live Reverb notifications and system-generated email alerts for status updates.
* `feature/admin-dashboard` - MISD Admin dashboard for syncing local mock data and mapping sections.
* `feature/coordinator-compliance` - Coordinator dashboards with filters for program/industry, and Director 3-year placement trends.
* `feature/faculty-monitoring` - Faculty read-only portals for monitoring program-configured OJT requirements and student journals.
* `CCSportfoliofeatures` - Specialized e-Portfolio builder for CCS students.
* `COEDportfoliofeatures` - Specialized e-Portfolio builder for COED students.
* `COEportfoliofeatures` - Specialized e-Portfolio builder for COE students.

---

## ??? Setup Instructions

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

### Frontend (React/Vite)
1. Navigate to the `frontend` folder: `cd frontend`
2. Install dependencies: `npm install`
3. Copy environment variables: `cp .env.example .env`
4. Start the development server: `npm run dev`

### Environment Configuration Notes
| Variable | File | Purpose |
|----------|------|---------|
| `INTERNTRACK_DEFAULT_PASSWORD` | `backend/.env` | Seed/demo password (`InternTrack123!`) |
| `INTERNTRACK_TARGET_HOURS=500` | `backend/.env` | Legacy seeder fallback only ù live hours come from program configuration |
| `VITE_INTERNTRACK_TARGET_HOURS=500` | `frontend/.env` | Do not treat as the program requirement |
| `INTERNTRACK_CURRENT_TERM` | `backend/.env` | Academic term label (e.g., "AY 2025-2026, Sem 2") |
| `VITE_INTERNTRACK_CURRENT_TERM` | `frontend/.env` | Must match backend term |
| `MISD_USE_MOCK=true` | `backend/.env` | Use local mock MISD (default) |
| `INTERNTRACK_UPLOAD_MAX_MB=10` | `backend/.env` | Max upload size (messages, announcements) |

---
*Created for the University of Cabuyao (Pamantasan ng Cabuyao)*
