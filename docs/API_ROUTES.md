# INTERNTRACK API routes (appendix)

Base prefix: `/api/v1`  
Auth: Laravel Sanctum Bearer token (`Authorization: Bearer …`)

## Hard constraints (not endpoints)

- **No live iEnroll / MISD SSO** — mock + Admin Sync only (privacy).
- Chat attachments: private disk → `GET /files/download?path=`
- Announcement attachments: public disk (campus-wide) by design.

## Auth

| Method | Path | Notes |
|--------|------|--------|
| POST | `/auth/login` | throttle:login |
| POST | `/auth/logout` | auth:sanctum |
| GET | `/auth/user` | auth:sanctum |
| POST | `/auth/change-password` | required when `must_change_password` |
| POST | `/auth/avatar` | |
| PUT | `/auth/profile` | |
| GET/PUT | `/auth/notification-preferences` | |

While `must_change_password` is true, other authenticated APIs return **403**.

## Shared (auth + password.changed)

| Area | Paths |
|------|--------|
| Dashboard | `GET /dashboard/summary` |
| Notifications | `GET /notifications`, mark-read |
| Messages | conversations, thread, send, unsend, archive, clear |
| Meetings | index, store, update, rsvp |
| Files | `GET /files/download?path=` |

## Role prefixes

- `student/*` — dashboard, attendance, logbook, documents, evaluations, records, absorption declare, portfolio, supervisor-invite
- `supervisor/*` — interns, attendance validate, journals, evaluations, feedback, absorption list
- `faculty/*` — advisees, journals, documents, evaluations, feedback, **reports** (student-summary, compliance, performance), **supervisor-approvals**
- `coordinator/*` — monitoring, announcements, documents, logbook, records, place, absorption, **reports** (overview + 3 reports)
- `director/*` — analytics, companies, MOA, placement-trends, internships status, absorption finalize, announcements
- `admin/*` — MISD staff, users, section mappings, sync, audit

Generate a live list anytime:

```bash
cd backend
php artisan route:list --path=api/v1
```
