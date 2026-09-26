# INTERNTRACK
## Internship Management System for the University of Cabuyao

INTERNTRACK is a web-based internship management system for the **University of Cabuyao (Pamantasan ng Cabuyao)**. It supports the whole internship lifecycle — placement and Host Training Establishment (HTE) selection, Industry Supervisor invitation and approval, attendance, weekly journals, document compliance, evaluations, portfolio generation, reports, messaging, appointments, and system administration.

This README explains the current system, the controlled demonstration dataset shipped with the repository, and how to reproduce it locally.

**Branch:** `Internet-Develop`

---

## Purpose

- Give Students one place to complete every internship requirement.
- Let Faculty advisers monitor and review only their own advisees.
- Give Coordinators, the PALD Director, and MISD administrators the oversight and administration tools their roles need.
- Produce official forms (FO-30 Daily Time Record, FO-31 Weekly Journal) and the internship Portfolio directly from authoritative records.

## Current System Status

- All modules listed below are implemented and covered by automated tests (backend PHPUnit and frontend Node test runner).
- The repository includes a **controlled demonstration database snapshot dated September 26, 2026** for the College of Computing Studies (CCS), together with synthetic demonstration files. It is intended for development, demonstration, thesis defense, and team reproduction — **not production**.

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2+, Laravel 12, Laravel Sanctum |
| Frontend | React 18, Vite 6, Bootstrap 5 |
| Database | MySQL / MariaDB (MariaDB 10.4 is used locally) |
| PDF | barryvdh/laravel-dompdf |
| Realtime (optional) | Laravel Reverb / Echo |
| Package managers | Composer, npm |

```
├── backend/     Laravel API, migrations, seeders, console commands, PHPUnit tests
│   └── database/
│       ├── snapshots/    controlled demonstration database snapshot (+ schema-only reference)
│       └── demo-files/   synthetic files referenced by the snapshot
├── frontend/    React (Vite) single-page application and Node tests
└── README.md
```

---

## Main Roles

| Role | Scope |
|------|-------|
| Student | Own internship records only |
| Faculty | Assigned advisees (from section mappings or an explicit adviser assignment) |
| Coordinator | College-wide scope for their college (CCS for the demonstration dataset) |
| Industry Supervisor | Interns whose placement they supervise |
| Director (PALD) | Partner companies, MOAs, supervisors, placement analytics, HTE evaluations, absorption |
| MISD / Administrator | Accounts, staff assignments, section mappings, directory synchronization, audit logs |

A Coordinator account that is also mapped to sections works in two workspaces from one account: the **Coordinator** workspace (college-wide) and the **Faculty** workspace (only that person's advisees). The workspace switcher is in the top bar.

---

## System Features

**Student** — Dashboard · Placement Hub (company applications and new HTE requests) · Attendance (clock in/out, break/resume, working schedule, correction requests) · Supervisor Details and invitation · Weekly Journal · Documents · My Portfolio · Evaluations · My Records · Messages · Appointments · Settings.

**Faculty** — Dashboard · Assigned Students (Student Roster, Journal Review Queue, Attendance Monitor, Portfolio Preview) · Journal review and journal deadlines · Supervisor Approvals (acceptance forms) · Manage Requirements · Document review · Evaluations (FO-24 review, evaluation-period approval, release to Student) · Reports · Messages · Appointments · Settings.

**Coordinator** — Intern Monitoring · Internship Management (applications, placements, HTE requests) · Records · Supervisors · Absorption · Document approvals · Journal review · Requirements · Evaluations · Analytics · Reports · Announcements · Messages · Appointments · Settings.

**Industry Supervisor** — Dashboard · Assigned Interns · Attendance Validation (schedules, overtime, correction reviews) · Feedback · Performance Evaluation (FO-24, FO-03) · Absorption · Messages · Settings. Industry Supervisors do **not** review weekly journals.

**Director** — Dashboard · Analytics · Partner Companies · Supervisors · MOA Management (monitoring and updates) · Placement / internships · HTE Evaluations (FO-03 release) · Absorption · Reports · Announcements · Messages · Appointments · Settings.

**MISD / Administrator** — Dashboard (account overview, quick actions, directory status, recent activity) · Directors · Coordinators · Section Mappings · Users · MISD Sync (iEnroll directory lookup and student sync) · Audit Logs · Settings.

---

## Current Workflow

```
Student account (from the iEnroll directory)
 → Placement Hub: company application or new HTE request → Coordinator review
 → Student invites an Industry Supervisor → Supervisor registers / signs in
 → Supervisor uploads the Acceptance Form → Faculty approves or rejects
 → Assignment becomes active → Supervisor approves the working schedule
 → Daily attendance → Supervisor validation
 → Weekly journals → Faculty review
 → Documents → review → compliance
 → Evaluations (evaluation period approved by Faculty)
 → Portfolio (FO-30, FO-31, evaluations) → reports → completion / absorption
```

---

## CCS Controlled Demonstration Dataset

Values below were verified against the snapshot on September 26, 2026.

### Programs and Target Hours

Target hours come from each program's HTE requirements (`program_hte_requirements`), never from hard-coded values.

| Program | Target hours |
|---------|-------------:|
| BS Information Technology (BSIT) | 500 |
| BS Computer Science (BSCS) | 300 |

### Sections and Faculty Assignments

| Adviser | ID | Role | Sections | Advisees |
|---------|----|------|----------|---------:|
| Marvin M. Bicua | FAC-1001 | Faculty | 4IT-A, 4IT-D, 4CS-A | 7 |
| Arcelito C. Quiatchon | COR-CCS-001 | Coordinator (+ Faculty workspace) | 4IT-B, 4CS-B | 5 |

### Summary

| Category | Count |
|----------|------:|
| CCS Students | 12 |
| BSIT Students | 9 |
| BSCS Students | 3 |
| Finished (completed) | 4 |
| Ongoing (active) | 4 |
| Fresh / pending placement | 4 |
| Partner companies | 10 |
| CCS Faculty-capable advisers | 2 |
| Industry Supervisors | 9 |

### Demo Students

| Student No. | Student | Program | Section | Adviser | Status | Company | Hours | Supervisor |
|---|---|---|---|---|---|---|---:|---|
| 2300592 | Clarence Montealegre | BSIT | 4ITA | Marvin M. Bicua | Completed | Infor | 500 / 500 | Miguel Santos (SUP-0003) |
| 2300590 | Angel Luis Taac-Taac | BSIT | 4ITB | Arcelito C. Quiatchon | Completed | Accenture Philippines | 500 / 500 | Patricia Gomez (SUP-0004) |
| 2300595 | Arthur Morgan | BSIT | 4ITA | Marvin M. Bicua | Completed | Microsoft Philippines | 500 / 500 | Daniel Cruz (SUP-0005) |
| 2300613 | Terrence John Manlapaz | BSCS | 4CSA | Marvin M. Bicua | Completed | Cognizant Philippines | 300 / 300 | Rafael Villanueva (SUP-0009) |
| 2300600 | Christian Hero Aboy Valinado | BSIT | 4ITA | Marvin M. Bicua | Active | Oracle Philippines | 240 / 500 | Andrea Lim (SUP-0006) |
| 2300609 | Lara Croft | BSIT | 4ITB | Arcelito C. Quiatchon | Active | IBM Philippines | 184 / 500 | Kevin Tan (SUP-0007) |
| 2300610 | Max Payne | BSIT | 4ITA | Marvin M. Bicua | Active | DXC Technology Philippines | 296 / 500 | Melissa Ramos (SUP-0008) |
| 2300611 | Ada Wong | BSCS | 4CSB | Arcelito C. Quiatchon | Active | NTT DATA Philippines | 216 / 300 | Katrina Mendoza (SUP-0010) |
| 2300500 | Mark Joseph V. Taduran | BSIT | 4ITA | Marvin M. Bicua | Pending placement | — | 0 / 500 | — |
| 2300501 | Ellie Williams | BSIT | 4ITB | Arcelito C. Quiatchon | Pending placement | — | 0 / 500 | — |
| 2300502 | Leon Kennedy | BSIT | 4ITB | Arcelito C. Quiatchon | Pending placement | — | 0 / 500 | — |
| 2300612 | Nathan Drake | BSCS | 4CSA | Marvin M. Bicua | Pending placement | — | 0 / 300 | — |

- **Finished** Students have validated attendance equal to the program target, approved weekly journals, FO-30/FO-31, evaluations, requirements, and a portfolio. No attendance exists after an internship's end date.
- **Ongoing** Students are below target, with realistic 8:00 AM–5:00 PM attendance (Asia/Manila), a mix of approved and pending journals, and partial document compliance.
- **Fresh** Students have no company, supervisor, attendance, journals, or evaluations.

The snapshot also contains placeholder accounts for other colleges (CAS, CBAA, CHAS, COE, COED) that are used for college scoping and program-hour configuration; they have no placements.

### Industry Supervisors

| Supervisor ID | Name | Company | Assigned Student(s) | Status |
|---|---|---|---|---|
| SUP-0002 | Adrian Reyes | Accenture Philippines | — | Active |
| SUP-0003 | Miguel Santos | Infor | Clarence Montealegre (2300592, completed) | Active |
| SUP-0004 | Patricia Gomez | Accenture Philippines | Angel Luis Taac-Taac (2300590, completed) | Active |
| SUP-0005 | Daniel Cruz | Microsoft Philippines | Arthur Morgan (2300595, completed) | Active |
| SUP-0006 | Andrea Lim | Oracle Philippines | Christian Hero Aboy Valinado (2300600, active) | Active |
| SUP-0007 | Kevin Tan | IBM Philippines | Lara Croft (2300609, active) | Active |
| SUP-0008 | Melissa Ramos | DXC Technology Philippines | Max Payne (2300610, active) | Active |
| SUP-0009 | Rafael Villanueva | Cognizant Philippines | Terrence John Manlapaz (2300613, completed) | Active |
| SUP-0010 | Katrina Mendoza | NTT DATA Philippines | Ada Wong (2300611, active) | Active |

Supervisor e-mail addresses in the dataset use the reserved `interntrack.test` domain so no message can reach a real mailbox.

### Companies and Available Slots

Slots are consumed when an internship becomes active and released when it is completed, so *Capacity = Occupied + Available*.

| Company | Industry | MOA expiry | Occupied | Available | Capacity |
|---|---|---|---:|---:|---:|
| Accenture Philippines | IT Consulting | Jan 5, 2028 | 0 | 72 | 72 |
| Cognizant Philippines | IT Consulting | Jan 5, 2028 | 0 | 59 | 59 |
| DXC Technology Philippines | IT Services | Jan 5, 2028 | 1 | 79 | 80 |
| IBM Philippines | IT Services | Jan 5, 2028 | 1 | 63 | 64 |
| Infor | Enterprise Software | Jan 5, 2028 | 0 | 38 | 38 |
| Microsoft Philippines | Software | Jan 5, 2028 | 0 | 47 | 47 |
| NTT DATA Philippines | IT Services | Jan 5, 2028 | 1 | 32 | 33 |
| Oracle Philippines | Enterprise Software | Jan 5, 2028 | 1 | 54 | 55 |
| Tata Consultancy Services (TCS) Philippines | IT Services | Jan 5, 2028 | 0 | 68 | 68 |
| Wipro Philippines | IT Services | Jan 5, 2028 | 0 | 41 | 41 |

---

## Attendance Workflow

- **Timezone:** business time is **Asia/Manila**. Clock events are stored in the application timezone (UTC) and converted exactly once for display; approved schedules are Manila wall-clock times.
- **Working schedule:** the Student proposes working hours; the Industry Supervisor approves them. The approved schedule is authoritative for credited time (any schedule is supported, e.g. 7–4, 8–5, 9–6).
- **Clock In / Clock Out / Break / Resume:** recorded from server time. Credited hours count only the part of the day inside the approved schedule, minus the break — arriving early or leaving late adds nothing; excess time can be submitted as overtime for Supervisor approval.
- **Validation:** the Industry Supervisor validates or rejects each day.
- **Corrections:** a Student may request a correction for a recent day (entered in Manila time); it applies only after Supervisor and Faculty approval.
- **FO-30:** the Daily Time Record is generated from validated attendance with AM/PM sessions and the Supervisor's signature.
- **Completed internships:** once an internship is marked **Completed**, no new attendance, breaks, schedules, or corrections can be created (the API answers `409`); history and FO-30 remain available.

## Weekly Journal Workflow

- The Student submits one journal per week with a date range; ranges cannot overlap and must fall within the internship period.
- Faculty can set journal deadlines; late submissions are marked.
- The **assigned Faculty adviser** approves or returns each journal. Industry Supervisors do **not** approve journals.
- Approved journals feed the **FO-31** Weekly Journal and the Portfolio.

## Document Compliance

- Standard requirements (13 canonical documents) apply to every eligible Student; Faculty/Coordinators can add custom requirements for targeted Students.
- Students upload submissions; reviewers approve or reject them.
- Compliance uses one shared resolver and a dynamic denominator: an **approved** submission satisfies its requirement; pending does not; rejected stays unsatisfied until a later approval.
- **Certificate of Completion** is a standard *uploaded* requirement provided by the HTE. The former system-generated completion certificate feature has been removed because it is outside the system objectives.

## Evaluations

| Form | Evaluator | Purpose | Student visibility |
|------|-----------|---------|--------------------|
| FO-24 | Industry Supervisor | Student Internship Performance Evaluation (official grading basis) | Completion only, until the assigned Faculty releases it |
| FO-03 | Industry Supervisor | HTE evaluation of the University internship program | Completion only, until the Director releases it |
| FO-22 | Student | Student's evaluation of the HTE | Own submission |
| FO-23 | Student | Student's evaluation of the internship program | Own submission |

Faculty approve the evaluation period before evaluation forms unlock for a Student and Supervisor.

## Portfolio

- Student-entered sections (company profile, reflections, recommendations) plus image uploads.
- Official content generated from records: FO-30 (attendance), FO-31 (approved journals), and evaluations.
- Faculty have a read-only **Portfolio Preview** for each advisee.
- Printable, paginated preview that adapts to smaller screens.

## Reports

- **Faculty / Coordinator:** Student Summary, Document Compliance (per-requirement status chips), Performance Analytics.
- **Coordinator:** Intern Monitoring export. **Director:** Internship Summary, Company Partnerships, MOA Status, CHED Annual report, MOA Monitoring export.
- Every report can be previewed before **Export CSV** (plain data) and printed with **Print / Save PDF**.
- Report data is derived from authoritative records (validated attendance, approved journals, compliance resolver).

## Messaging and Appointments

- Internship-scoped conversations between Students, their Faculty adviser, and their Industry Supervisor (attachments, unsend, archive).
- Staff can schedule appointments; invited participants respond (RSVP).
- In-app notifications for workflow events; e-mail notifications when mail is configured.

## MISD / Admin

- Account overview, quick actions, and recent activity; Directors and Coordinators assignment; section-to-Faculty mappings; users.
- **MISD Sync:** Student and staff identity data come from the iEnroll directory. In this repository the directory is served **locally by the application** (a local directory interface) — no external iEnroll service is contacted.
- **Audit Logs** of meaningful events (logins, approvals, document and attendance decisions, account changes). Passwords and tokens are never logged.

## Security and Authorization

- Every API route checks the role and the record scope (own records, advisees, college, supervised placements).
- Student-facing evaluation data passes through a visibility filter so unreleased details cannot be requested directly.
- Protected files are served only through authorized API routes.
- Secrets live only in local `.env` files, which are ignored by Git.

---

## Installation

Prerequisites: PHP 8.2+, Composer, Node.js with npm, MySQL/MariaDB, Git.

```bash
git clone https://github.com/christianherov-prog/INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao.git
cd INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao
git checkout Internet-Develop
```

### Backend Setup

```bash
cd backend
composer install
copy .env.example .env          # Windows  (macOS/Linux: cp .env.example .env)
php artisan key:generate
```

### Frontend Setup

```bash
cd frontend
npm install
copy .env.example .env          # Windows  (macOS/Linux: cp .env.example .env)
```

### Environment Setup

| File | Keys to set |
|------|-------------|
| `backend/.env` | `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, mail settings (optional), `DEMO_PASSWORD` (local only) |
| `frontend/.env` | `VITE_API_BASE_URL` (e.g. `http://127.0.0.1:8001/api/v1`) |

Never commit `.env` files or real credentials.

---

## Database Restore

The snapshot is `backend/database/snapshots/interntrack-defense-demo-2026-09-26.sql` (schema, migration history, and all controlled records). These steps were tested on a clean database.

1. Create an empty database:
   ```sql
   CREATE DATABASE interntrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Set `DB_DATABASE=interntrack` (and your credentials) in `backend/.env`.
3. Import the snapshot (from `backend/`):
   ```bash
   mysql -u root -p interntrack < database/snapshots/interntrack-defense-demo-2026-09-26.sql
   ```
4. Confirm nothing is pending: `php artisan migrate:status` (all migrations show **Ran**). Do not run `migrate:fresh` on the restored database.

## Demo File Restore

The snapshot references signatures and documents. Synthetic copies are in `backend/database/demo-files/private/`. Copy them into storage (from `backend/`):

```bash
php artisan interntrack:restore-demo-files
php artisan storage:link
```

Existing files are kept; add `--force` to overwrite them.

## Demo / Defense Accounts

No passwords are published in this repository. After restoring, choose a local password and apply it to every account in the snapshot (from `backend/`, not available in production):

```bash
php artisan interntrack:set-demo-passwords --password="YourLocalPassword"
# or set DEMO_PASSWORD=... in backend/.env and run the command without --password
```

Sign in with the account identifier below and that password.

### Student Accounts

| Student No. | Name | Program | Section | Status | Company |
|---|---|---|---|---|---|
| 2300592 | Clarence Montealegre | BSIT | 4ITA | Completed | Infor |
| 2300590 | Angel Luis Taac-Taac | BSIT | 4ITB | Completed | Accenture Philippines |
| 2300595 | Arthur Morgan | BSIT | 4ITA | Completed | Microsoft Philippines |
| 2300613 | Terrence John Manlapaz | BSCS | 4CSA | Completed | Cognizant Philippines |
| 2300600 | Christian Hero Aboy Valinado | BSIT | 4ITA | Active | Oracle Philippines |
| 2300609 | Lara Croft | BSIT | 4ITB | Active | IBM Philippines |
| 2300610 | Max Payne | BSIT | 4ITA | Active | DXC Technology Philippines |
| 2300611 | Ada Wong | BSCS | 4CSB | Active | NTT DATA Philippines |
| 2300500 | Mark Joseph V. Taduran | BSIT | 4ITA | Pending placement | — |
| 2300501 | Ellie Williams | BSIT | 4ITB | Pending placement | — |
| 2300502 | Leon Kennedy | BSIT | 4ITB | Pending placement | — |
| 2300612 | Nathan Drake | BSCS | 4CSA | Pending placement | — |

### Faculty / Coordinator Accounts

| ID | Name | Role | Scope |
|---|---|---|---|
| FAC-1001 | Marvin M. Bicua | Faculty | Advisees in 4IT-A, 4IT-D, 4CS-A (7) |
| COR-CCS-001 | Arcelito C. Quiatchon | Coordinator + Faculty workspace | CCS-wide; advisees in 4IT-B, 4CS-B (5) |

### Industry Supervisor Accounts

| ID | Name | Company | Assigned Intern |
|---|---|---|---|
| SUP-0002 (login `adrian.reyes`) | Adrian Reyes | Accenture Philippines | — |
| SUP-0003 | Miguel Santos | Infor | Clarence Montealegre |
| SUP-0004 | Patricia Gomez | Accenture Philippines | Angel Luis Taac-Taac |
| SUP-0005 | Daniel Cruz | Microsoft Philippines | Arthur Morgan |
| SUP-0006 | Andrea Lim | Oracle Philippines | Christian Hero Aboy Valinado |
| SUP-0007 | Kevin Tan | IBM Philippines | Lara Croft |
| SUP-0008 | Melissa Ramos | DXC Technology Philippines | Max Payne |
| SUP-0009 | Rafael Villanueva | Cognizant Philippines | Terrence John Manlapaz |
| SUP-0010 | Katrina Mendoza | NTT DATA Philippines | Ada Wong |

### Director / MISD Accounts

| ID | Name | Role |
|---|---|---|
| DIR-1001 | Gina M. Oloresisimo | PALD Director |
| ADMIN-MISD-001 | Alon Isagani Dimaculangan | MISD Administrator |

Suggested walkthrough: a completed Student (2300592) → an ongoing Student (2300600) → a fresh Student (2300500) → Faculty (FAC-1001) → Coordinator (COR-CCS-001) → Industry Supervisor (SUP-0003) → Director (DIR-1001) → MISD Administrator (ADMIN-MISD-001).

---

## Running the System

```bash
# backend/
php artisan serve --host=127.0.0.1 --port=8001

# frontend/
npm run dev          # http://127.0.0.1:5173
```

### Fresh install without the snapshot

```bash
# backend/
php artisan migrate --seed
php artisan interntrack:seed-ccs-demo
```

`interntrack:seed-ccs-demo` recreates the controlled CCS dataset (companies, Students, supervisors, attendance, journals, requirements, evaluations, portfolios). Generated records follow the same rules, but identifiers, timestamps, and message/audit history differ from the snapshot.

## Running Tests

```bash
# backend/ — uses the MySQL database interntrack_testing (see phpunit.xml)
php artisan test

# frontend/
npm test
```

## Build Commands

```bash
# frontend/
npm run build
```

---

## Known Limitations

- The iEnroll directory is served locally by the application; no live connection to an external iEnroll service is configured.
- E-mail delivery requires local mail credentials in `backend/.env`; with the default `MAIL_MAILER=log`, messages are written to the Laravel log.
- Stored uploads in the snapshot are **synthetic stand-ins** (typed-script signatures and labelled placeholder documents); original uploads, profile photos, and a small number of unsuitable test uploads are not distributed.
- Contact numbers and invitation details in the snapshot were replaced with fictional values.
- The system does not generate a completion certificate; the HTE's Certificate of Completion is handled as an uploaded document.
- Placeholder accounts for colleges other than CCS exist but have no internship data.

## Current Branch

`Internet-Develop` — the controlled demonstration snapshot is dated **September 26, 2026**.
