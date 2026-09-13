# Frontend completion notes (current app)

This file previously tracked an HTML→React conversion inventory. The live app is past that milestone. Treat this as a **panel-facing feature snapshot**, not a conversion checklist.

## Portals

- Student — dashboard (Chart.js weekly hours), attendance, weekly FO-31 journals, documents, evaluations, records/hire progress, portfolio (active+), supervisor invite QR, settings
- Supervisor — attendance/journal validation, evaluations + e-sign, absorption (view-only), messages, meetings
- Faculty — assigned students, document stage, journals, evaluations + e-sign, reports, feedback, meetings
- Coordinator — monitoring (own cohort), placement/records, doc approvals + e-sign, reports, absorption (view-only), supervisor approvals, announcements, meetings
- Director — dashboard, MOA, absorption finalize, internships status, reports, meetings/announcements oversight
- MISD Admin — directors/coordinators, section mappings, users, sync monitor (mock/local)

## Auth & security notes

- Sanctum token in `sessionStorage`; boot revalidates via `GET /auth/user`
- Token TTL via `SANCTUM_TOKEN_EXPIRATION` (minutes)
- Sensitive uploads on private disk; download through authenticated API
- Supervisor self-register endpoints are rate-limited

## Out of scope / Phase 2

Live MISD SSO, browser push/FCM, PKI DigiSign, chat attachments — see [`../PROGRESS_NOTES.md`](../PROGRESS_NOTES.md).
