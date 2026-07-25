# INTERNTRACK — React frontend

Vite + React SPA for the INTERNTRACK internship management system. Talks to the Laravel Sanctum API under `../backend`.

## Stack

- React 18 + React Router 6 + Bootstrap 5
- Axios (`src/services/api.js`) with Bearer token from `sessionStorage`
- Laravel Echo / Reverb when `VITE_REVERB_*` is set; otherwise Topbar ~60s polling
- Chart.js on the student dashboard weekly-hours chart

## Auth model

- Login: `POST /api/v1/auth/login` → Sanctum personal access token
- Token + user snapshot stored in `sessionStorage` (`interntrack_token`, `interntrack_session`)
- On reload, `AuthContext` calls `GET /auth/user` to revalidate (401 clears the session)
- Roles: student, supervisor, faculty, coordinator, director, admin (MISD)

## Notable product behaviors (keep docs aligned)

| Area | Behavior |
|------|----------|
| Journals | Weekly upload of the **FO-31 Daily Journal** form (not a daily submission API) |
| Realtime | Reverb WebSockets when configured; else polling |
| MISD | Local mock + Admin Sync — not live institutional SSO |
| Absorption | **PALD Director** finalizes Absorbed / Not Hired; supervisor/coord view-only |
| Portfolio | Available for **active** (and related) internships, not only completed |
| Private files | Journals/docs/signatures/portfolio via authenticated `GET /files/download` |
| Term labels | Driven by `user.term` from API (`INTERNTRACK_CURRENT_TERM`) |

## Setup

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Point `VITE_API_BASE_URL` at the Laravel API (default `http://127.0.0.1:8001/api/v1`). See root [`SETUP.md`](../SETUP.md) and [`DEFENSE_SCRIPT.md`](../DEFENSE_SCRIPT.md).

## Project structure

```
frontend/
├── public/
├── src/
│   ├── components/     # Layout, Sidebar, AuthenticatedFile, SignatureUpload, …
│   ├── contexts/       # AuthContext
│   ├── hooks/          # useCurrentTerm
│   ├── pages/          # role portals + public supervisor register
│   ├── services/       # api.js, echo.js
│   └── utils/
├── package.json
└── vite.config.js
```

## Scripts

| Command | Purpose |
|---------|---------|
| `npm run dev` | Vite dev server |
| `npm run build` | Production build |
| `npm run preview` | Preview production build |
