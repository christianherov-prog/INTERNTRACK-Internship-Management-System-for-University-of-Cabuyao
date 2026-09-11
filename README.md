# InternTrack
## Internship Management System for the University of Cabuyao

InternTrack is a web-based internship management system for the **University of Cabuyao (Pamantasan ng Cabuyao)**. It centralizes the internship lifecycle: placement and HTE selection, supervisor invitation and approval, attendance, weekly journals, documents and compliance, evaluations, portfolio generation, reporting, messaging, and administration.

This README is for **IT evaluators** who have never used InternTrack. It explains how to set up, run, and walk through the system.

**Evaluation branch:** [`Internet-Develop`](https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao/tree/Internet-Develop)

---

## System Overview

InternTrack supports these implemented capabilities:

- Student profiles and academic scoping
- Placement Hub (partner companies / HTEs)
- Company applications and New HTE requests
- Industry Supervisor invitations and registration
- Acceptance Form upload and Faculty approval/rejection
- Attendance (clock in/out, break/resume, end day)
- Dynamic Supervisor QR attendance
- Attendance validation and correction requests
- Weekly Journals with Faculty review
- Document requirements, submissions, and compliance reports
- Evaluations (including supervisor performance evaluation / FO-24)
- Portfolio Builder (college builders; image-only manual uploads)
- Reports and analytics
- Messaging and meetings
- In-app notifications and email alerts (when mail is configured)
- MISD / Admin user management and **Audit Logs**

---

## Technology Stack

Verified from this repository:

| Layer | Technology |
|-------|------------|
| Backend | PHP **^8.2**, **Laravel ^12**, Laravel Sanctum |
| Frontend | **React 18**, **Vite 6**, Bootstrap 5 |
| Database | **MySQL** (required for app and tests) |
| PDF | barryvdh/laravel-dompdf |
| QR (frontend) | qrcode.react |
| Realtime (optional) | Laravel Reverb / Echo |
| Package managers | Composer, npm |

---

## Project Structure

```
├── backend/     Laravel API, migrations, seeders, PHPUnit tests
├── frontend/    React (Vite) SPA
├── README.md    This evaluator guide
└── .gitignore
```

---

## User Roles

### Student

- Dashboard
- Placement Hub
- Attendance (including QR scan and corrections)
- Weekly Journal
- Documents
- Portfolio
- Evaluations
- Records
- Messages / Meetings
- Settings

### Faculty

- Assigned Students
- Journal review (approve / needs revision)
- Document review
- Supervisor registration / Acceptance Form approval or rejection
- Manage Requirements (standard + own customs)
- Reports
- Messages / Meetings / Settings

**Faculty reviews Student Journals.** Industry Supervisors do **not**.

### Coordinator

- Internship monitoring and analytics
- Internship management (applications, placements, HTE requests)
- Custom / additional requirements
- Supervisors, evaluations, absorption, records, reports
- Announcements, messages, meetings

### Industry Supervisor

- Invitation acceptance / registration with username
- Acceptance Form per Student invitation
- Multiple Student assignments on one account
- Attendance QR and attendance validation
- Working schedule / DTR participation
- Feedback and evaluations
- Messages / Meetings / Settings

**No Journal preview or Journal validation.**

### Director

- Partner companies and organization type
- Supervisors overview
- MOA management
- Analytics and reports
- HTE evaluations
- Placement and absorption
- Announcements / messages / meetings

### MISD / Administrator

- Directors / Coordinators assignment
- Section mappings
- Users and directory sync
- Dashboard activity summary
- Audit Logs (meaningful workflow events — not keystroke tracking)
- Settings

---

## Internship Workflow

```
Student account
  → Placement / HTE selection
  → Company application or New HTE request
  → Review / approval
  → Supervisor invitation
  → Supervisor login or registration
  → Acceptance Form upload
  → Faculty approval / rejection
  → Supervisor assignment becomes active
  → Working schedule
  → Attendance
  → Weekly Journal (Faculty review)
  → Documents
  → Evaluation
  → Portfolio
  → Reports / completion
```

---

## Supervisor Workflow

### New Supervisor

1. Student sends an invitation.
2. Supervisor registers, creates a username, uploads an Acceptance Form for that invitation.
3. Faculty reviews and approves or rejects.
4. Supervisor is notified (in-app / email when configured).
5. Student assignment activates only after approval.

### Existing Supervisor

1. Existing Supervisor receives another Student invitation.
2. Signs in with the **existing** account (no duplicate Supervisor account).
3. Uploads a **new** Acceptance Form for the new Student invitation.
4. Faculty reviews; assignment activates only after approval.

Acceptance Forms belong to the **specific Student invitation**.

---

## Attendance

Official attendance uses **server-generated timestamps** (display timezone: **Asia/Manila**).

- Supervisor generates a short-lived dynamic QR.
- Student scans the QR; the server records the time.
- Students cannot manually type official clock-in/out for QR-backed events.
- Supports break start / resume and end day / clock out.
- Supervisor validates attendance.
- Legitimate discrepancies use attendance correction requests.

QR is a practical capture control, not an absolute anti-fraud guarantee.

---

## Journals

1. Student submits a **Weekly Journal**.
2. Journal date ranges **cannot overlap**.
3. **Faculty** reviews (approve / needs revision).
4. Journal data feeds Portfolio FO-31 content.

**Industry Supervisors do not review Student Journals.**

---

## Documents and Compliance

- Standard / fixed requirements are system-defined and apply to eligible Students.
- Students submit documents; Faculty approve or reject.
- Coordinators may add custom / additional requirements for targeted Students.
- Document Compliance Report uses one shared resolver: an **approved** submission satisfies the matching requirement and leaves **Missing Documents**.
- Pending is not treated as approved; rejected stays unsatisfied unless a later approved submission exists.

---

## Portfolio

Portfolio content is built from authoritative system records where available:

| Form / data | Source |
|-------------|--------|
| FO-30 | Attendance / DTR |
| FO-31 | Weekly Journal |
| FO-24 | Supervisor evaluation |
| Company / HTE | Placement and portfolio fields |
| Signatures | Saved authorized signature sources |

Portfolio Builder **manual uploads accept images only**. Other document workflows may allow PDF where that module permits it.

---

## Notifications

Workflow notifications (in-app and email when configured) cover events such as:

- Application / HTE decisions
- Supervisor invitation and Acceptance Form approve/reject (email to Supervisor when configured)
- Document decisions
- Journal review outcomes
- Attendance-related notices
- Evaluation-related events

---

## Audit Logs

Administrators can review **meaningful** activity, for example:

- Login / logout / password changes
- Document upload and approval/rejection
- Requirement changes
- Attendance validation and related DTR events
- Supervisor approval/rejection
- Account / staff administration changes

This is not surveillance of mouse or keystroke activity. Ordinary roles cannot access global Audit Logs.

---

## Important Business Rules

- Academic users only access authorized Students (program / section / department scoping).
- Supervisor access is based on internship / placement assignment.
- Supervisor assignment requires the Faculty approval workflow.
- Existing Supervisors can accept additional Students without duplicate accounts.
- Acceptance Forms are invitation-specific.
- Official attendance timestamps for QR/server flows are server-generated; timezone is Asia/Manila.
- Supervisor validates attendance; Faculty reviews journals.
- Journal date ranges cannot overlap.
- Portfolio manual uploads are images only.
- Standard document requirements are predefined; authorized custom requirements remain supported.
- Approved documents satisfy compliance and leave the Missing list.

---

## Prerequisites

- PHP **8.2+**
- Composer
- Node.js with **npm** (Vite 6 compatible)
- MySQL **8.x** recommended
- Git

```sql
CREATE DATABASE interntrack;
CREATE DATABASE interntrack_testing;
```

---

## Installation

```bash
git clone https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao.git
cd INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao
git checkout Internet-Develop
```

### Backend

```bash
cd backend
composer install
copy .env.example .env          # Windows
# cp .env.example .env          # macOS / Linux
php artisan key:generate
```

Configure MySQL in `.env` (`DB_DATABASE=interntrack`, username/password). Keep `APP_URL` aligned with the port you serve (example: `http://127.0.0.1:8001`).

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8001
```

### Frontend

```bash
cd frontend
npm install
copy .env.example .env          # Windows
# cp .env.example .env          # macOS / Linux
npm run dev
```

Typical local URLs when using the examples above:

- Frontend: `http://localhost:5173` or `http://127.0.0.1:5173`
- API: `http://127.0.0.1:8001`

Set `VITE_API_BASE_URL` in `frontend/.env` to match your API (e.g. `http://127.0.0.1:8001/api/v1`).

---

## Environment Setup

Copy `.env.example` → `.env` for backend and frontend. Configure categories only:

| Category | Examples |
|----------|----------|
| App | `APP_URL`, `FRONTEND_URL` |
| Database | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` |
| Frontend | `VITE_API_BASE_URL` |

Never commit real secrets. Use placeholders in documentation and templates.

---

## Running the System

1. Start MySQL.
2. Start backend: `php artisan serve --host=127.0.0.1 --port=8001` (from `backend/`).
3. Start frontend: `npm run dev` (from `frontend/`).
4. Open the Vite URL shown in the terminal.

Optional evaluator data helper (no credentials in README):

```bash
cd backend
php artisan interntrack:reset-fresh-enrollee {student_number}
```

---

## Evaluation Accounts

To support system evaluation, the current InternTrack environment includes preconfigured accounts representing the primary user roles involved in the internship lifecycle. The accounts listed below correspond to the currently configured evaluation workflow. Additional departmental configurations are being integrated progressively and are therefore not included in this evaluation set.

> **Current Evaluation Scope:** The account set below represents the currently configured internship workflow available for evaluation under the College of Computing Studies. Additional departmental configurations are being integrated progressively and are not included in this evaluation account list.

> **Evaluation Access:** The accounts below are preconfigured for the current evaluation environment. Login passwords are distributed separately to authorized evaluators and are intentionally excluded from the repository.

### Student Accounts

| Name | Student Number | Program | Current Evaluation State |
|------|----------------|---------|--------------------------|
| Christian Hero Aboy Valinado | 2300600 | BSIT | Fresh Enrollee — no company or Industry Supervisor; Faculty = Marvin Bicua; 0 internship hours. Suitable for demonstrating Placement Hub, New HTE request, document requirements, and the start of the internship lifecycle. |
| Clarence Montealegre | 2300592 | BSIT | Active Internship — Accenture PH; Industry Supervisor = Adrian Reyes (`SUP-0002`); Faculty = Marvin Bicua; validated attendance hours in progress. Suitable for Attendance / QR, Weekly Journal, Faculty review, and Portfolio (FO-30 / FO-31) workflows. |

### Faculty Account

| Name | Employee ID / Login ID | Role | Evaluation Coverage |
|------|------------------------|------|---------------------|
| Marvin M. Bicua | FAC-1001 | Faculty | Assigned Students, journal review, document review, Supervisor Acceptance Form approval/rejection, requirements, and reports |

### Coordinator Account

| Name | Employee ID / Login ID | Role | Evaluation Coverage |
|------|------------------------|------|---------------------|
| Arcelito C. Quiatchon | COR-CCS-001 | Coordinator | Internship monitoring, placement / HTE oversight, custom requirements, records, and reports |

### Industry Supervisor Accounts

| Name | Supervisor ID | Username | Assigned Student(s) | Evaluation Coverage |
|------|---------------|----------|---------------------|---------------------|
| Adrian Reyes | SUP-0002 | adrian.reyes | Clarence Montealegre (2300592) | Assigned Students, Attendance QR / validation, feedback, and evaluation |

### Director Account

| Name | Employee ID / Login ID | Role | Evaluation Coverage |
|------|------------------------|------|---------------------|
| Gina M. Oloresisimo | DIR-1001 | Director | Partner companies, supervisors, MOA management, analytics, and reports |

### MISD / Administrator Account

| Name | Admin ID / Login ID | Role | Evaluation Coverage |
|------|---------------------|------|---------------------|
| MISD Administrator | ADMIN-MISD-001 | MISD / Administrator | User management, Directors / Coordinators, recent system activity, Audit Logs, and administrative monitoring |

### Recommended Account Sequence

IT evaluators can understand InternTrack by testing roles in this order:

1. **Student** — internship-user experience (start with `2300600` for fresh enrolment; then `2300592` for an active deployment).
2. **Faculty** — academic monitoring, journal review, document review, and Supervisor approval (`FAC-1001`).
3. **Coordinator** — placement and internship oversight (`COR-CCS-001`).
4. **Industry Supervisor** — company-side supervision and attendance validation (`SUP-0002` / `adrian.reyes`).
5. **Director** — companies, supervisors, reporting, and analytics (`DIR-1001`).
6. **MISD / Administrator** — account administration and Audit Logs (`ADMIN-MISD-001`).

---

## IT Evaluator Quick Start

Assume no prior knowledge of InternTrack. Use the evaluation accounts above; passwords are provided separately by the project team.

1. Start the application (backend + frontend).
2. Sign in as Student **2300600** (Fresh Enrollee) — review Dashboard, Settings, Placement Hub, Documents.
3. Sign in as Student **2300592** (Active Internship) — review Attendance, Journal, Portfolio.
4. Sign in as Faculty **FAC-1001** — Assigned Students, Journals, Documents, Supervisor Approvals.
5. Sign in as Coordinator **COR-CCS-001** — internship monitoring, requirements, reports.
6. Sign in as Industry Supervisor **SUP-0002** or username **adrian.reyes** — Assigned Students, Attendance Validation, Evaluations (no Journal module).
7. Sign in as Director **DIR-1001** — companies, MOA, reporting.
8. Sign in as Administrator **ADMIN-MISD-001** — Users, recent activity, Audit Logs.

---

## Testing

### Backend

```bash
cd backend
php artisan test
```

PHPUnit uses MySQL database `interntrack_testing` (see `backend/phpunit.xml`).

### Frontend

```bash
cd frontend
npm run build
```

There is no separate `npm test` or `npm run lint` script in the current frontend package. Production build is the primary frontend validation command.

---

## Security Notes

- Role-based access control across Student, Faculty, Coordinator, Supervisor, Director, and Admin.
- Academic scoping for Faculty/Coordinator; placement scoping for Supervisors.
- Protected document access through authorized API routes.
- Server-generated attendance timestamps for QR-backed events.
- Secrets stay in local `.env` files — never in the repository.
- Audit payloads are sanitized to avoid logging passwords and tokens.
