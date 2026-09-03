# INTERNTRACK React Setup

> **Full stack setup** (Laravel backend, migrate, `storage:link`, env vars, demo accounts): see **[`../SETUP.md`](../SETUP.md)** and the repo **[`../README.md`](../README.md)**.

This folder is the Vite + React SPA. Styles live under `src/styles/` in the repo — no CSS copy from Interntrack-UI is required.

## Installation

1. **Install dependencies and env**

```bash
npm install
cp .env.example .env
```

Set `VITE_API_BASE_URL` to your Laravel API origin (default `http://127.0.0.1:8001/api/v1`).

2. **Run development server**

```bash
npm run dev
```

Open the URL Vite prints (usually `http://localhost:5173`).

## Default login credentials

- **Password for all users**: `interntrack123`

| Role | ID |
|------|-----|
| Student | `2021-00123` |
| Director | `DIR-001` |
| Supervisor | `SUP-001` |
| Faculty | `FAC-001` |
| Coordinator | `EMP-1001` |

See [`../SETUP.md`](../SETUP.md) for the complete seeded user list.

## Routes

- `/` — Login
- `/student/*` — Student portal
- `/director/*` — Director portal
- `/supervisor/*` — Supervisor portal
- `/faculty/*` — Faculty portal
- `/coordinator/*` — Coordinator portal

## Production

```bash
npm run build
npm run preview
```
