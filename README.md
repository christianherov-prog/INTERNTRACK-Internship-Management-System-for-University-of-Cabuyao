# InternTrack
## Internship Management System for the University of Cabuyao

InternTrack is a web-based internship management system designed to centralize and manage internship-related workflows for the **University of Cabuyao (Pamantasan ng Cabuyao)**.

This README is written for **IT evaluators and technical reviewers** who have no prior knowledge of InternTrack. The evaluation walkthrough emphasizes the **College of Computing Studies (CCS)** workflow, which is the primary reference college in the current seed and demo data.

**Active integration branch:** [`develop`](https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao/tree/develop)

---

## System Purpose

InternTrack supports end-to-end internship operations, including:

- Student internship profiles and academic scoping
- Partner companies / Host Training Establishments (HTEs)
- Company applications and New HTE requests
- Internship placement
- Industry Supervisor invitation and registration
- Faculty approval or rejection of Supervisor Acceptance Forms
- Attendance (clock in/out, breaks, end day)
- Dynamic QR attendance
- Supervisor attendance validation and correction requests
- Weekly Journals (FO-31) with Faculty review
- Documents, standard/custom requirements, and document compliance reports
- Evaluations (including FO-24 supervisor performance evaluation)
- Portfolio generation (college builders; CCS Portfolio Builder)
- Reports and analytics
- In-app notifications and email alerts (where configured)
- Messaging and meetings
- Administrative monitoring (MISD)
- Admin Audit Logs for meaningful system activity

---

## Technology Stack

Verified from this repository:

| Layer | Technology |
|-------|------------|
| Backend | PHP **^8.2**, **Laravel ^12**, Laravel Sanctum |
| Frontend | **React 18**, **Vite 6**, Bootstrap 5 |
| Database | **MySQL** (required for app and tests) |
| Auth API | Laravel Sanctum personal access tokens |
| PDF | barryvdh/laravel-dompdf |
| QR (frontend) | qrcode.react |
| Realtime (optional) | Laravel Reverb / Echo / Pusher client |
| Package managers | Composer (backend), npm (frontend) |

---

## Project Structure

```
INTERNTRACK/
├── backend/          Laravel API, migrations, seeders, PHPUnit tests
├── frontend/         React (Vite) SPA
├── docs/             Supporting technical notes (optional reading)
├── qa/               QA helper scripts (not required to run the app)
├── README.md         This evaluator guide
└── .gitignore
```

---

## Prerequisites

- PHP **8.2+** with common Laravel extensions
- Composer
- Node.js with **npm** (Vite 6 compatible)
- MySQL **8.x** recommended
- Git

Create databases before migrate/test:

```sql
CREATE DATABASE interntrack;
CREATE DATABASE interntrack_testing;
```

---

## Installation

```bash
git clone https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao.git
cd INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao
git checkout develop
```

### Backend

```bash
cd backend
composer install
copy .env.example .env          # Windows
# cp .env.example .env          # macOS/Linux
php artisan key:generate
```

Edit `.env` and set MySQL credentials (`DB_DATABASE=interntrack`, username/password). Keep `APP_URL` aligned with the port you serve (default example: `http://127.0.0.1:8001`).

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
# cp .env.example .env          # macOS/Linux
npm run dev
```

Typical local URLs:

- Frontend: `http://localhost:5173`
- API: `http://127.0.0.1:8001`

---

## Environment Configuration

Use placeholders only. **Never commit real passwords or API keys.**

### Backend (`backend/.env`)

| Category | Variables (examples) |
|----------|----------------------|
| App URL | `APP_URL`, `FRONTEND_URL` |
| Database | `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` |
| Auth | Sanctum token lifetime (`SANCTUM_TOKEN_EXPIRATION`) |
| Demo seed password | `INTERNTRACK_DEFAULT_PASSWORD` (local/demo only) |
| MISD | `MISD_USE_MOCK` for local mock directory sync |

QR invite / registration links rely on `FRONTEND_URL` / `APP_URL` so generated URLs resolve to your running SPA and API.

### Frontend (`frontend/.env`)

| Variable | Purpose |
|----------|---------|
| `VITE_API_BASE_URL` | Backend API base (e.g. `http://127.0.0.1:8001/api/v1`) |

See `backend/.env.example` and `frontend/.env.example` for the full template set.

---

## User Roles

### Student

Typical CCS modules:

- Dashboard
- Placement Hub (companies, applications, HTE requests, supervisor invitation)
- Attendance (clock in/out, break/resume, end day, QR scan, corrections)
- Journal (weekly FO-31)
- Documents (requirement submissions)
- Portfolio Builder
- Evaluations
- Records
- Messages / Meetings
- Settings

### Faculty (CCS focus)

- Assigned Students (section-scoped)
- Journal review (approve / needs revision) — **Faculty validates Journals**
- Document review (approve / reject)
- Manage Requirements (standard system requirements + own customs)
- Supervisor Approvals (Acceptance Form per invitation)
- Supervisors directory
- Reports (including document compliance)
- Messages / Meetings / Settings

### Coordinator (CCS)

- Department internship monitoring and analytics
- Internship management (applications, placements, HTE requests)
- Custom / additional requirements (**Add Requirement**); standard fixed requirements are Faculty-side
- Supervisors, evaluations, absorption, records, reports
- Announcements, messages, meetings

### Industry Supervisor

- Username-based account (no duplicate account when accepting additional students)
- Assigned Students
- Attendance QR generation and attendance validation
- Working schedule / DTR workflow participation
- Correction handling
- Feedback and performance evaluations
- Messages / Meetings / Settings

**Industry Supervisors do not review or validate Student Journals.**

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
- Users and MISD sync
- Dashboard activity summary
- **Audit Logs** (meaningful authentication, workflow, and admin events — not keystroke tracking)
- Settings

---

## Department Scoping (CCS)

Operational academic access is scoped by:

**Student → Program → Department (e.g., CCS)**

- CCS Students are handled by authorized **CCS Faculty** (section assignments) and the **CCS Coordinator**.
- Faculty/Coordinator APIs do not expose other colleges’ students by default.

Industry Supervisor access is scoped by placement:

**Student → Internship / Placement → HTE → Assigned Supervisor**

This distinction matters for evaluation: academic oversight is college/section based; workplace oversight is internship/HTE based.

---

## CCS Internship Workflow

1. Student account / profile is available (MISD sync or seeded demo identity).
2. CCS Faculty is mapped to the student’s section.
3. CCS Coordinator oversees department internship operations.
4. Student uses Placement Hub to select an MOA company or submit a New HTE Request.
5. Coordinator reviews company applications / HTE requests as applicable.
6. Student invites an Industry Supervisor for the placement.
7. **New Supervisor:** registers, creates a username, uploads an Acceptance Form for that invitation.
8. **Existing Supervisor:** signs in with the existing account, uploads a **new** Acceptance Form for the new student invitation (no duplicate supervisor account).
9. CCS Faculty approves or rejects the Acceptance Form / registration for that invitation.
10. Supervisor assignment becomes active for the internship.
11. Working schedule / DTR setup proceeds per workflow.
12. Student records attendance (including short-lived Supervisor QR scans).
13. Supervisor validates attendance; corrections require a correction request when needed.
14. Student submits Weekly Journals (non-overlapping date ranges).
15. **Faculty** reviews Journals.
16. Student submits required documents; Faculty reviews; Coordinator may add custom requirements.
17. Evaluations (e.g., supervisor FO-24) are completed as configured.
18. Portfolio compiles authoritative records (attendance FO-30, journal FO-31, evaluation FO-24, company data, signatures).
19. Faculty/Coordinator/Director use reports; MISD uses Audit Logs for system activity.

---

## Supervisor Invitation Flow

1. Student sends a Supervisor invitation from Placement Hub.
2. Each invitation expects its **own Acceptance Form**.
3. New supervisors complete registration + username + Acceptance Form upload.
4. Returning supervisors reuse one account across multiple students and upload a fresh Acceptance Form per invitation.
5. Faculty (or authorized academic staff) approves/rejects; email/in-app notifications fire when configured.
6. Rejected invitations can be corrected and resubmitted according to the live workflow rules.

---

## Attendance

Authoritative attendance behavior in the current system:

- Dynamic Supervisor Attendance QR is **short-lived**.
- Student scans QR; the **server** records the timestamp.
- Display/storage conventions use **Asia/Manila**.
- Students cannot manually type the official clock time for QR-backed events.
- Workflow supports clock in, break start/end (resume), and end day / clock out.
- Supervisor validates attendance.
- Discrepancies are handled through attendance correction requests (not free-form time edits).

Do not treat QR as an absolute anti-fraud guarantee; it is a practical attendance capture control.

---

## Journal Workflow

1. Student creates/submits a **Weekly Journal**.
2. Journal date ranges **cannot overlap**.
3. **Faculty** reviews (approve / needs revision).
4. Portfolio FO-31 reads the same journal records.

**Industry Supervisor does not review/validate Student Journals.**

---

## Document Management & Compliance

- **Standard / fixed requirements** are system-defined (`is_system` + stable `system_code`), auto-applicable to eligible students, and managed primarily on the Faculty side.
- Students upload submissions; Faculty approve or reject (with remarks/notifications).
- **Custom Coordinator requirements** remain available via **Add Requirement** and only apply to targeted students.
- Document Compliance Report uses a shared resolver: an **approved** submission satisfies the matching requirement and is **removed from Missing Documents**.
- Pending is not treated as approved; rejected stays unsatisfied unless a later approved submission exists.
- Some requirements can be satisfied by system-generated sources (e.g., attendance for DTR, submitted evaluations) where implemented.

### Portfolio Builder note

Portfolio Builder **manual uploads accept images only**. Other official document workflows may accept PDF where the document module allows it.

---

## Portfolio

The Portfolio compiles data from authoritative InternTrack records when available, for example:

| Form / data | Source |
|-------------|--------|
| FO-30 | Attendance / DTR |
| FO-31 | Student Weekly Journal |
| FO-24 | Supervisor evaluation |
| Company / HTE | Placement and portfolio fields |
| Signatures | Saved authorized signature sources |

---

## Notifications & Email

Meaningful events can trigger in-app notifications and email (when mail is configured), including areas such as:

- Placement / HTE decisions
- Supervisor invitation and Acceptance Form approve/reject
- Attendance-related notices
- Document decisions
- Journal review outcomes
- Evaluation-related events

Mail credentials belong only in local `.env` — never in the repository.

---

## Admin Audit Logs

MISD/Admin can inspect **meaningful** activity such as:

- Login / logout / password changes
- Document upload and approval/rejection
- Requirement create/update/delete
- Attendance validation and related DTR events
- Supervisor approval/rejection
- Account / staff administration changes

This is **not** keystroke or mouse surveillance. Ordinary roles cannot access global audit logs.

---

## Important Business Rules

- CCS Students are scoped to authorized CCS academic personnel.
- Supervisor assignment requires academic approval of the invitation / Acceptance Form.
- Acceptance Form is **invitation-specific**.
- One Supervisor account can handle multiple Students (no duplicate accounts for additional assignments).
- Official attendance timestamps for QR/server flows are server-generated; timezone display uses Asia/Manila.
- Supervisor validates Attendance; Faculty validates Journals.
- Journal date ranges cannot overlap.
- Portfolio manual uploads are images only.
- Standard document requirements are system-defined; Coordinator custom requirements remain supported.
- Approved documents satisfy compliance and leave the Missing list.

---

## Suggested CCS Evaluation Walkthrough

Use credentials provided by the evaluation team or your local seeded accounts. **Do not publish production passwords in documentation.**

1. Login as a **CCS Student** → review Dashboard.
2. Open **Placement Hub** → inspect company / HTE / invite flows.
3. Open **Attendance** → review clock/QR/correction UX.
4. Open **Journal** → create or inspect a weekly entry.
5. Login as **CCS Faculty** → review assigned students and Journals.
6. Review **Documents** / Manage Requirements and compliance reports.
7. Open **Supervisor Approvals** → inspect Acceptance Form decisions.
8. Login as **CCS Coordinator** → Internship Management, custom Requirements, reports.
9. Login as **Industry Supervisor** → Assigned Students, Attendance Validation, Evaluations (no Journal module).
10. Login as **MISD/Admin** → Dashboard recent activity and **Audit Logs**.

After `php artisan migrate --seed`, local demo identities are created for CCS and other colleges. Ask the project owner for the current demo username list and default password policy; reset via reseed if your environment differs.

---

## Testing

### Backend

```bash
cd backend
php artisan test
```

PHPUnit uses the MySQL database `interntrack_testing` (see `backend/phpunit.xml`).

### Frontend

```bash
cd frontend
npm run build
```

There is no separate `npm test` script in the current frontend package. Production build (`vite build`) is the primary frontend validation command.

---

## Security & Privacy Notes

- Role-based access control for Student, Faculty, Coordinator, Supervisor, Director, and Admin.
- College/section scoping for academic roles; placement scoping for Supervisors.
- Protected document/file access through authorized API routes.
- Server-generated attendance timestamps for QR-backed events.
- No plaintext production passwords in the repository; secrets stay in `.env`.
- Audit payloads are sanitized to avoid logging passwords, tokens, and similar secrets.

---

## License / Academic Context

InternTrack is developed as a University of Cabuyao internship management system. Use the `develop` branch for evaluation of the integrated application.
