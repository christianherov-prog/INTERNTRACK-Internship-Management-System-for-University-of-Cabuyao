# INTERNTRACK — Short defense script

Use with [`PROGRESS_NOTES.md`](PROGRESS_NOTES.md) and [`thesis/MANUSCRIPT_WORDING.md`](thesis/MANUSCRIPT_WORDING.md). Keep answers honest and short.

---

## Three lines to rehearse (memorize)

### 1. MISD / iEnroll

> “We **consider** institutional MISD/iEnroll integration in the study scope, but this build uses a **local mock** plus an **Admin Sync** monitor because institutional MISD protects data privacy. Live MISD remains **Phase 2**. That is enough to demo provisioning without claiming live SSO.”

### 2. Realtime notifications / chat

> “With Laravel **Reverb** and matching `VITE_REVERB_*` keys, the Topbar and chat update over **WebSockets**. If Reverb is down or the Vite key is missing, the UI falls back to **HTTP polling**. Chat attachments are **private** (authenticated download). We do **not** claim mobile push (FCM).”

### 3. FO-30 / FO-31 / Attendance

> “**Attendance** is **Clock In / Clock Out** (supervisor validates presence). **FO-30** is the manual DTR form students fill offline and **upload**. **FO-31** is the manual Daily Journal form uploaded **once per week** in the logbook — not a daily journal API, and not a digital form filler.”

---

## Quick feature pointers (if asked)

| Topic | Say |
|-------|-----|
| Admin portal | Exists under MISD Admin (`ADMIN-MISD-001`) — directors, coordinators, section mappings, users, sync. |
| Attendance | Student **Clock In / Clock Out**; supervisor validates presence — separate from FO forms. |
| FO-30 DTR | **Manual** Student Internship Daily Time Record — fill offline, upload (portfolio appendix). Not a digital FO-30 builder. |
| FO-31 journals | **Manual** Daily Journal form — **weekly** logbook upload. Not a daily journal API. |
| Portfolio | Available for **active** internships, not only completed. |
| Absorption | **PALD Director** finalizes Absorbed / Not Hired; supervisor/coord are view-only. |
| Messages | Internship-scoped thread: student + supervisor + faculty + coordinator + **Director** (oversight). |
| Chat attachments | **Shipped** and **private** — storage path + authenticated `GET /files/download`. Announcement attachments stay on public disk for campus-wide posts. |
| Meetings | Orientation/check-in schedule + RSVP; attendees must be internship parties (director OK). |
| E-sign | Canvas PNG + typed name + timestamp on docs/evals = **electronic acknowledgment**, not DigiSign/PKI. |
| Realtime fallback | If Reverb is down/missing keys: **HTTP polling** (notifications ~15s, chat ~10s) + “Polling mode” banner. |
| ISO Obj 2–3 | Survey instruments are **external** under `thesis/iso25010/` — collect and analyze outside the app. |

---

## Manuscript wording checklist (Chapters 1–3)

Paste/adjust so Chapters match the code:

- [ ] Replace “daily journal submissions” → **weekly FO-31 manual form upload**
- [ ] Clarify **FO-30** = manual DTR form upload (not an in-app FO-30 builder / Attendance form)
- [ ] Clarify **Attendance** = Clock In / Clock Out (separate from FO-30 / FO-31)
- [ ] Clarify **MISD** = local mock + Admin Sync (privacy); not live iEnroll SSO; Phase 2 live MISD still out
- [ ] Clarify **e-sign** ≠ DigiSign / PKI
- [ ] Clarify **realtime** = Reverb when configured, else HTTP polling
- [ ] Clarify **chat attachments** = private auth download; announcement attachments = public disk (intentional)
- [ ] List Messages (with private attachments), Meetings, e-sign, MISD Admin as **implemented MVP** features
- [ ] Live SSO, Web Push, PKI, digital FO form fillers = **Phase 2 / future**, not shipped
- [ ] Obj 2–3 = evaluation **study** (`thesis/iso25010/`), not a new portal module

ISO instruments: score with `scoring_sheet.md`, fill `analysis_template.md`. Full tick-list: [`thesis/MANUSCRIPT_WORDING.md`](thesis/MANUSCRIPT_WORDING.md).

---

## Demo path (realtime)

```text
Terminal 1:  php artisan serve --port=8001
Terminal 2:  php artisan reverb:start
Terminal 3:  npm run dev   # with VITE_REVERB_* matching REVERB_*
```

Without Reverb keys: still demo the app; say “polling fallback.”
