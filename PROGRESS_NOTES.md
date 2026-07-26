# INTERNTRACK — Progress Notes (aligned to codebase)

**Updated:** 23 July 2026  
Use with [`DEFENSE_SCRIPT.md`](DEFENSE_SCRIPT.md) and [`thesis/MANUSCRIPT_WORDING.md`](thesis/MANUSCRIPT_WORDING.md) so Chapters 1–3 match the demo.

## Implemented (defend as done)

| Area | What the code does |
|------|--------------------|
| **MISD Admin portal** | `/admin/*` (`ADMIN-MISD-001`): directors, coordinators, section mappings, users, sync monitor. |
| **MISD data source** | **Local mock** + Admin Sync only — institutional MISD protects data privacy; **not** live iEnroll SSO. |
| **Attendance** | Student **Clock In / Clock Out**; supervisor validates presence. Separate from FO-30 / FO-31 uploads. |
| **FO-30 (DTR)** | **Manual** Student Internship Daily Time Record form — filled offline, uploaded (e.g. portfolio appendix `dtr_form`). **Not** a digital FO-30 builder in Attendance. |
| **FO-31 (Journal)** | **Manual** Daily Journal form — uploaded **once per week** in the student logbook. **Not** a daily journal API. |
| **Portfolio** | Available for **active** internships (includes FO-30 DTR appendix upload + FO-31 journals pulled from logbook). |
| **Status timeline** | History + reason UI in `StatusChangeModal`. |
| **Notification prefs** | Persisted JSON; `GET/PUT /auth/notification-preferences`. |
| **MOA / HTE gate** | Hard-enforced on place; slot consume/release. |
| **Absorption** | **Director** finalizes; supervisor/coord view-only (no PATCH stubs). |
| **Realtime** | **Laravel Reverb** WebSockets when configured; if Reverb is down / keys missing, UI falls back to **HTTP polling**. |
| **Chat / messaging** | Internship threads for student, supervisor, faculty, coordinator, **and PALD Director** (oversight). |
| **Chat attachments** | **Shipped** — private storage; download via authenticated `GET /files/download` (path-based, not public disk URLs). |
| **Announcement attachments** | Campus-wide posts stay on **public disk** intentionally (broadcast visibility). |
| **Meetings** | Schedule + RSVP; attendees limited to internship parties (+ director OK). |
| **Electronic signatures** | Canvas PNG + typed name + timestamp on docs/evals = **acknowledgment**, **not** DigiSign / PKI. |
| **Auth hardening** | `must_change_password` after staff/admin reset; API middleware + password-change wall until cleared. |
| **At-risk monitoring** | Composite rules; SQL count (no full hydrate for stats). |
| **Required documents** | `RequiredDocuments` — **13** types. |
| **Faculty reports** | Summary / compliance / performance + CSV (routes wired). |
| **Coordinator reports** | Overview + student-summary / compliance / performance. |
| **Policies starter** | `InternshipPolicy` registered (gradual migration from ad-hoc ACL). |
| **API appendix** | [`docs/API_ROUTES.md`](docs/API_ROUTES.md). |

## Thesis Obj 2 & 3 (ISO/IEC 25010)

External instruments (not in-app): [`thesis/iso25010/`](thesis/iso25010/) — end-user + IT expert questionnaires, scoring sheet, analysis template.

## Still Phase 2 / future

| Topic | Note |
|-------|------|
| Live institutional MISD / iEnroll SSO | Considered in scope language only (privacy); **Phase 2 — still out** |
| Browser Web Push / FCM | Not shipped |
| PKI / DigiSign | Acknowledgment e-sign only |
| Digital FO-30 / FO-31 form fillers | Out of scope — forms stay **manual** + upload |
| Recurring meetings / ICS | Single meetings + RSVP |

## Preferred defense phrasing

- **FO-30:** Manual DTR form filled offline → upload (portfolio appendix). Attendance Clock In/Out is separate.
- **FO-31:** Manual Daily Journal form → **weekly** logbook upload (not daily API).
- **Attendance:** Clock in / clock out; supervisor validates presence.
- **MISD:** Local mock + Admin Sync because institutional MISD protects privacy — not live SSO. Phase 2 live MISD still out.
- **Chat attachments:** Private (auth download). Announcement attachments remain public disk for campus-wide posts.
- **E-sign:** Electronic acknowledgment on docs/evals — not PKI / DigiSign.
- **Realtime:** Reverb when configured; HTTP polling when Reverb is down.

## Chapters 1–3 checklist (paste into manuscript)

- [ ] Replace “daily journal submissions” → **weekly FO-31 manual form upload**
- [ ] Clarify **FO-30** = manual DTR form upload (not an in-app FO-30 builder)
- [ ] Clarify **Attendance** = Clock In / Clock Out (separate from FO-30/FO-31)
- [ ] Clarify **MISD** = local mock + Admin Sync (privacy); not live iEnroll SSO
- [ ] Clarify **e-sign** ≠ DigiSign / PKI
- [ ] Clarify **realtime** = Reverb WebSockets when configured, else HTTP polling
- [ ] Clarify **chat attachments** = private (auth download); announcement attachments = public disk (campus-wide)

## Local realtime stack

See [`README.md`](README.md) § Reverb and [`SETUP.md`](SETUP.md).
