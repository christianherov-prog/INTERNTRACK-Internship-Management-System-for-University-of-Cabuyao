# InternTrack screenshot tour

Config-driven Selenium tool that logs into each role and captures every major page at desktop, tablet, and mobile widths. It is **read-only** (login + navigate + screenshot). It is **not** part of the Vite or Laravel runtime.

There is no Playwright/Cypress/Selenium setup in the main app. This folder is a standalone QA helper with its own `requirements.txt`.

## Run

Frontend and API must already be running (typical local: Vite `http://localhost:5173`, Laravel `http://127.0.0.1:8001`).

```powershell
cd qa/screenshot-tour
python -m venv .venv
.\.venv\Scripts\pip install -r requirements.txt
.\.venv\Scripts\python -u capture.py
```

Headed (visible Chrome) for debugging:

```powershell
.\.venv\Scripts\python -u capture.py --headed
```

Useful flags:

| Flag | Meaning |
|------|---------|
| `--headed` | Show the browser |
| `--base-url http://localhost:5173` | Override `config.json` |
| `--roles student,faculty` | Capture only these role keys |
| `--password interntrack123` | Override default password |
| `--config path\to\config.json` | Use another config file |

After a run, open `output/<run-id>/index.html`.

ChromeDriver is resolved in this order: `CHROMEDRIVER_PATH`, a cached `drivers/chromedriver.exe`, a download from [Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/), then Selenium Manager. The extra download path exists because some Windows Application Control policies block `selenium-manager.exe`.

## Update accounts or pages

Edit `config.json` only.

- Add/remove an object under `accounts`.
- Each account needs `role`, `label`, `username`, `enabled`, and `pages`.
- Each page needs `id`, `name`, and `path` (app path, not a full URL).
- Set `"enabled": false` to skip a role without deleting it.
- Per-account `password` overrides `default_password`.
- Optional page `note` shows up in the HTML report as a limitation.

Password can also come from the `QA_PASSWORD` environment variable.
