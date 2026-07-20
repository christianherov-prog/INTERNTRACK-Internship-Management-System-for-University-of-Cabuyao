# Changelog

All notable changes to INTERNTRACK are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/)-style dated entries.

---

## [2026-07-21]

### Added

- README sections for Recent Updates, Recently Fixed, Current Features (by module), Project Status, and Future Improvements
- This `CHANGELOG.md` for dated release notes on `develop`

### Improved

- Documentation accuracy against the current codebase (absorption, status history, certificates, supervisor invite, term config Sem 2, seeded students)
- Setup guidance: clone/`develop`, `INTERNTRACK_CURRENT_TERM` / `VITE_INTERNTRACK_CURRENT_TERM`

### Fixed

- Documentation drift vs verified system audit (July 2026)
- Excluded local scratch login script from the working tree before push

### Refactored

- README structure refreshed while retaining existing setup, demo accounts, and stack information

### Known Issues

- Password change UX still uses inline alerts (stronger confirm/toast flow not shipped)
- Two-Factor Authentication not implemented
- Some UI still uses `window.confirm` (e.g. portfolio builder)
- Unused helper components may remain (e.g. StatCard)
- Console `error` logging remains in some catch paths (dev diagnostics only)

---

## [2026-07] — Prior develop milestones (summary)

### Added

- Post-completion absorption tracking (UI + API + director analytics)
- Shared `RoleAbsorption` component with load-error handling
- Internship status tagging with reason and history timeline
- Document stage routing (coordinator → faculty)
- Completion certificate PDF generation
- Supervisor QR invite → register → coordinator approve
- Server-persisted notification preferences
- Toast on successful profile save
- Feature tests: Auth, Absorption, InternshipStatus
- Student accounts seeder (`2300600`, `2300592`)
- Rate limiting on supervisor-register, change-password, and avatar
- Index on `internships.absorption_status`
- Academic term config (`AY 2025-2026, Sem 2`)

### Improved

- Coordinator monitoring eager-loads `company` (N+1)
- Portfolio/records include active internships
- `.env.example` aligned with MySQL setup docs
- Progress Report DOCX retention (Updated) + ignore future `.docx` adds

### Fixed

- UTF-8 BOM on PHP sources that broke login after config cache clear
- Semester fallback defaults aligned to Sem 2 (seeder + new internships)
- Footer copyright drift removed
- Scratch files untracked; `.gitignore` encoding hardened
