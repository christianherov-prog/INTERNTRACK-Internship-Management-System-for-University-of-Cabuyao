# Manuscript wording sync checklist

Use with [`DEFENSE_SCRIPT.md`](../DEFENSE_SCRIPT.md) and [`PROGRESS_NOTES.md`](../PROGRESS_NOTES.md) so Chapters 1–3 match the shipped product.

## Canonical product wording

| Topic | Correct claim |
|-------|----------------|
| **FO-30 (DTR)** | **Manual** Student Internship Daily Time Record form — student fills it **offline** and **uploads** it (e.g. portfolio appendix `dtr_form`). **Not** a digital FO-30 builder in Attendance. |
| **FO-31 (Journal)** | **Manual** Daily Journal form — uploaded **once per week** in the student logbook. **Not** a daily journal API. |
| **Attendance** | **Clock In / Clock Out**; supervisor validates presence. **Separate** from FO-30 / FO-31 uploads. |
| **MISD** | **Local mock + Admin Sync** only — institutional MISD protects data privacy. **Not** live iEnroll SSO. Live MISD remains Phase 2. |
| **E-sign** | Electronic **acknowledgment** (canvas + typed name + timestamp) on docs/evals. **Not** DigiSign / PKI. |
| **Realtime** | Laravel **Reverb** WebSockets when configured; if Reverb is down / keys missing → **HTTP polling**. |
| **Chat attachments** | **Shipped** — private storage; authenticated download. Announcement attachments remain on **public disk** for campus-wide posts. |

## Chapters 1–3 checklist (paste into manuscript PDF)

- [ ] Replace “daily journal submissions” → **weekly FO-31 manual form upload**
- [ ] Clarify **FO-30** is a **manual DTR form upload** (not an in-app form filler / Attendance screen)
- [ ] Clarify **Attendance** = Clock In / Clock Out (separate from FO-30 / FO-31)
- [ ] Clarify **MISD** = local mock + Admin Sync (privacy); not live iEnroll SSO
- [ ] Clarify **e-sign** ≠ DigiSign / PKI
- [ ] Clarify **realtime** = Reverb when configured, else HTTP polling
- [ ] Clarify **chat attachments** = private (auth download); announcement attachments = public disk (intentional)

## Required search-and-replace in Chapters

| Avoid in manuscript | Use instead |
|---------------------|-------------|
| Daily journal submissions / daily journal API | Weekly FO-31 **manual** form upload |
| Digital FO-30 DTR builder / FO-30 in Attendance | Manual FO-30 DTR filled offline + upload |
| Live MISD / iEnroll SSO | Local mock + Admin Sync (privacy); Phase 2 for live MISD |
| DigiSign / qualified digital signature / PKI | Electronic acknowledgment (canvas + typed name + timestamp) |
| Always push / always WebSocket | Reverb when configured; otherwise HTTP polling |
| Chat attachments not shipped / text-only MVP | Chat attachments **shipped** (private auth download) |
| Obj 2 & 3 as an in-app module | External ISO/IEC 25010 study under [`iso25010/`](iso25010/) |

## ISO/IEC 25010 (Obj 2 & 3) — tick as you go

- [ ] Adviser / ethics clearance obtained
- [ ] End-user instrument deployed ([`iso25010/instrument_end_users.md`](iso25010/instrument_end_users.md))
- [ ] IT expert instrument deployed ([`iso25010/instrument_it_experts.md`](iso25010/instrument_it_experts.md))
- [ ] Responses collected (suggested: ≥30 end users, ≥5 IT experts)
- [ ] Scored with [`iso25010/scoring_sheet.md`](iso25010/scoring_sheet.md)
- [ ] Results written into [`iso25010/analysis_template.md`](iso25010/analysis_template.md) for Chapters 4–5

## Defense one-liners

See the three memorized lines and feature table in [`DEFENSE_SCRIPT.md`](../DEFENSE_SCRIPT.md).
