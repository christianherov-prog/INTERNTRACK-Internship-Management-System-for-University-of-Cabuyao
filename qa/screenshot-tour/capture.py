#!/usr/bin/env python3
"""Read-only InternTrack screenshot tour.

Logs in per role from config.json, visits listed routes, captures desktop /
tablet / mobile viewports, and writes a browsable HTML index.

Does not submit forms, upload files, generate reports, or call logout.
"""

from __future__ import annotations

import argparse
import html
import json
import os
import shutil
import sys
import time
import urllib.error
import urllib.request
import zipfile
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin

from selenium import webdriver
from selenium.common.exceptions import TimeoutException, WebDriverException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

ROOT = Path(__file__).resolve().parent
DEFAULT_CONFIG = ROOT / "config.json"
DRIVERS_DIR = ROOT / "drivers"
CHROME_CFT_STABLE = "https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json"


def windows_chrome_binary() -> str | None:
    candidates = [
        Path(os.environ.get("PROGRAMFILES", r"C:\Program Files")) / "Google/Chrome/Application/chrome.exe",
        Path(os.environ.get("PROGRAMFILES(X86)", r"C:\Program Files (x86)")) / "Google/Chrome/Application/chrome.exe",
        Path.home() / "AppData/Local/Google/Chrome/Application/chrome.exe",
    ]
    for path in candidates:
        if path.is_file():
            return str(path)
    return None


def cft_platform() -> str:
    if sys.platform.startswith("win"):
        return "win64" if sys.maxsize > 2**32 else "win32"
    if sys.platform == "darwin":
        return "mac-arm64" if os.uname().machine == "arm64" else "mac-x64"
    return "linux64"


def driver_filename() -> str:
    return "chromedriver.exe" if sys.platform.startswith("win") else "chromedriver"


def download_chromedriver() -> Path:
    """Fetch ChromeDriver from Chrome for Testing. Selenium Manager is often blocked on locked-down Windows."""
    DRIVERS_DIR.mkdir(parents=True, exist_ok=True)
    dest = DRIVERS_DIR / driver_filename()
    env_path = os.environ.get("CHROMEDRIVER_PATH")
    if env_path and Path(env_path).is_file():
        return Path(env_path)
    if dest.is_file():
        return dest

    print("ChromeDriver not cached; downloading from Chrome for Testing...")
    with urllib.request.urlopen(CHROME_CFT_STABLE, timeout=30) as resp:
        payload = json.loads(resp.read().decode("utf-8"))
    downloads = (
        payload.get("channels", {})
        .get("Stable", {})
        .get("downloads", {})
        .get("chromedriver", [])
    )
    platform = cft_platform()
    url = next((item["url"] for item in downloads if item.get("platform") == platform), None)
    if not url:
        raise RuntimeError(f"No ChromeDriver download for platform {platform}")

    zip_path = DRIVERS_DIR / "chromedriver.zip"
    urllib.request.urlretrieve(url, zip_path)
    with zipfile.ZipFile(zip_path) as zf:
        member = next(name for name in zf.namelist() if name.endswith(driver_filename()))
        extracted = DRIVERS_DIR / Path(member).name
        with zf.open(member) as src, extracted.open("wb") as out:
            shutil.copyfileobj(src, out)
    zip_path.unlink(missing_ok=True)
    if sys.platform != "win32":
        extracted.chmod(extracted.stat().st_mode | 0o111)
    if extracted.resolve() != dest.resolve():
        shutil.move(str(extracted), str(dest))
    print(f"ChromeDriver saved to {dest}")
    return dest


def resolve_chromedriver() -> str | None:
    try:
        return str(download_chromedriver())
    except (urllib.error.URLError, OSError, RuntimeError, StopIteration, KeyError) as exc:
        print(f"Could not download ChromeDriver ({exc}); falling back to Selenium Manager")
        return None


def load_config(path: Path) -> dict:
    with path.open(encoding="utf-8") as fh:
        return json.load(fh)


def slug(value: str) -> str:
    keep = []
    for ch in value.lower().replace("/", "-"):
        if ch.isalnum() or ch in "-_":
            keep.append(ch)
        elif ch.isspace():
            keep.append("-")
    text = "".join(keep).strip("-")
    while "--" in text:
        text = text.replace("--", "-")
    return text or "page"


def build_driver(headed: bool, width: int, height: int) -> webdriver.Chrome:
    options = Options()
    if not headed:
        options.add_argument("--headless=new")
    options.add_argument("--disable-gpu")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--no-sandbox")
    options.add_argument("--hide-scrollbars")
    options.add_argument(f"--window-size={width},{height}")
    options.add_argument("--force-device-scale-factor=1")
    chrome_bin = windows_chrome_binary() if sys.platform.startswith("win") else None
    if chrome_bin:
        options.binary_location = chrome_bin
    driver_path = resolve_chromedriver()
    service = Service(executable_path=driver_path) if driver_path else Service()
    driver = webdriver.Chrome(service=service, options=options)
    driver.set_window_size(width, height)
    driver.set_page_load_timeout(45)
    return driver


def js_ready_state(driver) -> str:
    return driver.execute_script("return document.readyState") or ""


def visible_loading(driver, selectors: list[str]) -> bool:
    script = """
    const selectors = arguments[0];
    for (const sel of selectors) {
      const nodes = document.querySelectorAll(sel);
      for (const el of nodes) {
        const style = window.getComputedStyle(el);
        if (style && style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null) {
          return true;
        }
      }
    }
    return false;
    """
    try:
        return bool(driver.execute_script(script, selectors))
    except WebDriverException:
        return False


def wait_document_complete(driver, timeout: float) -> None:
    WebDriverWait(driver, timeout).until(lambda d: js_ready_state(d) == "complete")


def wait_network_idle(driver, idle_ms: int, timeout: float) -> bool:
    """Wait until no new resource entries have completed for idle_ms."""
    script = """
    const idleMs = arguments[0];
    const timeoutMs = arguments[1];
    const started = performance.now();
    const latestEnd = () => {
      const entries = performance.getEntriesByType('resource');
      let max = 0;
      for (const e of entries) {
        const end = e.responseEnd || e.startTime || 0;
        if (end > max) max = end;
      }
      return max;
    };
    return new Promise((resolve) => {
      const tick = () => {
        const now = performance.now();
        if (now - latestEnd() >= idleMs) {
          resolve(true);
          return;
        }
        if (now - started >= timeoutMs) {
          resolve(false);
          return;
        }
        setTimeout(tick, 120);
      };
      tick();
    });
    """
    try:
        return bool(driver.execute_script(script, int(idle_ms), int(timeout * 1000)))
    except WebDriverException:
        time.sleep(max(0.2, idle_ms / 1000))
        return True


def fill_react_input(driver, element, value: str) -> None:
    element.click()
    element.clear()
    element.send_keys(value)
    if (element.get_attribute("value") or "") == value:
        return
    driver.execute_script(
        """
        const el = arguments[0];
        const v = arguments[1];
        const proto = window.HTMLInputElement.prototype;
        const desc = Object.getOwnPropertyDescriptor(proto, 'value');
        desc.set.call(el, v);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        """,
        element,
        value,
    )


def wait_for_page(driver, cfg: dict, expected_path: str, timeout: float) -> tuple[bool, str]:
    """Wait until the route is showing and loading indicators have settled."""
    try:
        WebDriverWait(driver, timeout).until(
            lambda d: expected_path.rstrip("/") in (d.current_url or "").split("?")[0]
        )
    except TimeoutException:
        return False, f"URL did not reach {expected_path} (now {driver.current_url})"

    try:
        wait_document_complete(driver, timeout)
    except TimeoutException:
        return False, "document.readyState never became complete"

    ready_selectors = cfg.get("ready_selectors") or ["main.main-content"]
    found_ready = False
    deadline = time.time() + timeout
    last_err = "ready selector not found"
    while time.time() < deadline:
        for sel in ready_selectors:
            try:
                el = driver.find_element(By.CSS_SELECTOR, sel)
                if el.is_displayed():
                    found_ready = True
                    break
            except WebDriverException:
                continue
        if found_ready:
            break
        time.sleep(0.15)
    if not found_ready:
        return False, last_err

    loading_selectors = cfg.get("loading_selectors") or []
    settle_s = max(0.2, int(cfg.get("settle_ms", 400)) / 1000)
    load_deadline = time.time() + min(12, timeout)
    while time.time() < load_deadline:
        if not visible_loading(driver, loading_selectors):
            idle_ms = int(cfg.get("network_idle_ms", 600))
            idle_timeout = max(2.0, (idle_ms / 1000.0) + 1.2)
            idle = wait_network_idle(driver, idle_ms, idle_timeout)
            time.sleep(settle_s)
            if not visible_loading(driver, loading_selectors) and js_ready_state(driver) == "complete":
                return True, "ok" if idle else "ok (network still busy; captured after settle)"
        time.sleep(0.2)

    return True, "captured after loading indicators stayed visible (possible spinner/empty fetch)"


def login(driver, cfg: dict, account: dict, password: str, timeout: float) -> tuple[bool, str]:
    base = cfg["base_url"].rstrip("/")
    driver.get(urljoin(base + "/", cfg.get("login_path", "/").lstrip("/")))
    wait_document_complete(driver, timeout)

    try:
        user_input = WebDriverWait(driver, timeout).until(
            EC.visibility_of_element_located((By.ID, "studentNumber"))
        )
        pass_input = driver.find_element(By.ID, "password")
    except TimeoutException:
        return False, "Login form (#studentNumber / #password) did not appear"

    fill_react_input(driver, user_input, account["username"])
    fill_react_input(driver, pass_input, password)

    driver.find_element(By.CSS_SELECTOR, "#loginForm button.btn-signin[type='submit']").click()

    deadline = time.time() + timeout
    while time.time() < deadline:
        url = driver.current_url or ""
        if "/student/" in url or "/faculty/" in url or "/coordinator/" in url or "/director/" in url or "/admin/" in url or "/supervisor/" in url:
            wait_document_complete(driver, 10)
            return True, url
        try:
            err = driver.find_element(By.ID, "loginError")
            if err.is_displayed() and err.text.strip():
                return False, err.text.strip()
        except WebDriverException:
            pass
        time.sleep(0.2)

    return False, f"Login timed out; still at {driver.current_url}"


def capture_page(driver, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    driver.save_screenshot(str(path))


def write_index(run_dir: Path, cfg: dict, run_id: str, results: list[dict]) -> Path:
    index_path = run_dir / "index.html"
    notes = cfg.get("notes") or []
    cards = []
    current_role = None
    failures = [r for r in results if r["status"] != "ok"]
    page_ok = [r for r in results if r.get("kind") == "page" and r["status"] == "ok"]
    logins_ok = [r for r in results if r.get("kind") == "login" and r["status"] == "ok"]

    def close_role():
        if current_role is not None:
            cards.append("</section>")

    for row in results:
        if row.get("kind") == "login" and row["status"] != "ok":
            close_role()
            current_role = None
            cards.append(
                f"""
                <section class="role fail">
                  <h2>{html.escape(row['label'])} <span class="badge bad">login failed</span></h2>
                  <p class="meta">{html.escape(row.get('username', ''))} — {html.escape(row.get('detail', ''))}</p>
                </section>
                """
            )
            continue

        if row.get("kind") != "page":
            continue

        if row["role"] != current_role:
            close_role()
            current_role = row["role"]
            cards.append(f'<section class="role"><h2>{html.escape(row["label"])}</h2>')

        note = row.get("note") or ""
        status_class = "ok" if row["status"] == "ok" else "bad"
        shots = []
        for shot in row.get("shots") or []:
            rel = shot["file"].replace("\\", "/")
            shots.append(
                f"""
                <figure>
                  <a href="{html.escape(rel)}" target="_blank" rel="noopener">
                    <img src="{html.escape(rel)}" alt="{html.escape(shot['viewport'])}" loading="lazy">
                  </a>
                  <figcaption>{html.escape(shot['viewport'])} · {shot['width']}×{shot['height']}</figcaption>
                </figure>
                """
            )
        limit_note = f'<p class="limit">{html.escape(note)}</p>' if note else ""
        fail_note = f'<p class="fail-detail">{html.escape(row.get("detail", ""))}</p>' if row["status"] != "ok" else ""
        cards.append(
            f"""
            <article class="page {status_class}">
              <h3>{html.escape(row['page_name'])} <span class="badge {status_class}">{html.escape(row['status'])}</span></h3>
              <p class="meta"><code>{html.escape(row['path'])}</code></p>
              {limit_note}{fail_note}
              <div class="grid">{''.join(shots)}</div>
            </article>
            """
        )

    close_role()
    body_inner = "".join(cards)

    notes_html = "".join(f"<li>{html.escape(n)}</li>" for n in notes)
    fail_html = "".join(
        f"<li><strong>{html.escape(f.get('label') or f.get('role') or '')}</strong> "
        f"{html.escape(f.get('page_name') or 'login')}: {html.escape(f.get('detail') or f['status'])}</li>"
        for f in failures
    ) or "<li>None</li>"

    index_path.write_text(
        f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>InternTrack screenshot tour — {html.escape(run_id)}</title>
  <style>
    :root {{ font-family: Segoe UI, system-ui, sans-serif; color: #0f172a; background: #f1f5f9; }}
    body {{ margin: 0; padding: 24px; }}
    h1 {{ margin: 0 0 8px; }}
    .summary, .notes {{ background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgb(15 23 42 / 8%); }}
    .badge {{ font-size: 12px; padding: 2px 8px; border-radius: 999px; vertical-align: middle; }}
    .badge.ok {{ background: #dcfce7; color: #166534; }}
    .badge.bad {{ background: #fee2e2; color: #991b1b; }}
    section.role {{ margin-bottom: 28px; }}
    article.page {{ background: #fff; border-radius: 12px; padding: 16px; margin: 12px 0; box-shadow: 0 1px 3px rgb(15 23 42 / 8%); }}
    article.page.bad {{ outline: 1px solid #fecaca; }}
    .meta, .limit, .fail-detail {{ color: #64748b; font-size: 13px; }}
    .fail-detail {{ color: #b91c1c; }}
    .grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 12px; }}
    figure {{ margin: 0; }}
    img {{ width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }}
    figcaption {{ font-size: 12px; color: #64748b; margin-top: 4px; }}
    code {{ background: #f8fafc; padding: 1px 6px; border-radius: 4px; }}
  </style>
</head>
<body>
  <h1>InternTrack screenshot tour</h1>
  <p class="meta">Run <code>{html.escape(run_id)}</code> · {len(logins_ok)} role login(s) · {len(page_ok)} page(s) captured · {len(failures)} failed · read-only (login + navigate only)</p>
  <div class="summary">
    <h2>Failures</h2>
    <ul>{fail_html}</ul>
  </div>
  <div class="notes">
    <h2>Limitations / notes</h2>
    <ul>{notes_html}</ul>
  </div>
  {body_inner}
</body>
</html>
""",
        encoding="utf-8",
    )
    return index_path


def run(cfg: dict, headed: bool, roles_filter: set[str] | None, password_override: str | None) -> int:
    run_id = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    run_dir = ROOT / "output" / run_id
    run_dir.mkdir(parents=True, exist_ok=True)
    timeout = float(cfg.get("timeout_seconds", 25))
    viewports = cfg["viewports"]
    default_pw = password_override or os.environ.get("QA_PASSWORD") or cfg.get("default_password", "")
    results: list[dict] = []

    accounts = [a for a in cfg["accounts"] if a.get("enabled", True)]
    if roles_filter:
        accounts = [a for a in accounts if a["role"] in roles_filter]

    print(f"Run {run_id}")
    print(f"Base URL {cfg['base_url']}")
    print(f"Output {run_dir}")

    desktop = viewports[0]
    driver = None
    try:
        try:
            driver = build_driver(headed, desktop["width"], desktop["height"])
        except WebDriverException as exc:
            print(f"Could not start Chrome: {exc}", file=sys.stderr)
            results.append({
                "kind": "login",
                "role": "chrome",
                "label": "Chrome / Selenium",
                "username": "",
                "status": "login_failed",
                "detail": str(exc),
            })
            (run_dir / "report.json").write_text(json.dumps({
                "run_id": run_id,
                "base_url": cfg["base_url"],
                "headed": headed,
                "read_only": True,
                "results": results,
            }, indent=2), encoding="utf-8")
            write_index(run_dir, cfg, run_id, results)
            return 2

        for account in accounts:
            role = account["role"]
            label = account.get("label") or role
            username = account["username"]
            password = account.get("password") or default_pw
            print(f"\n=== {label} ({username}) ===")
            try:
                driver.set_window_size(desktop["width"], desktop["height"])
                driver.delete_all_cookies()
                try:
                    driver.get(cfg["base_url"])
                    driver.execute_script("sessionStorage.clear(); localStorage.clear();")
                except WebDriverException:
                    pass

                ok, detail = login(driver, cfg, account, password, timeout)
            except (WebDriverException, OSError) as exc:
                print(f"  LOGIN FAILED: {exc}")
                results.append({
                    "kind": "login",
                    "role": role,
                    "label": label,
                    "username": username,
                    "status": "login_failed",
                    "detail": str(exc),
                })
                continue
            if not ok:
                print(f"  LOGIN FAILED: {detail}")
                results.append({
                    "kind": "login",
                    "role": role,
                    "label": label,
                    "username": username,
                    "status": "login_failed",
                    "detail": detail,
                })
                continue

            print(f"  Login OK -> {detail}")
            results.append({
                "kind": "login",
                "role": role,
                "label": label,
                "username": username,
                "status": "ok",
                "detail": detail,
            })

            for page in account.get("pages") or []:
                page_id = slug(page.get("id") or page["name"])
                page_row = {
                    "kind": "page",
                    "role": role,
                    "label": label,
                    "page_id": page_id,
                    "page_name": page["name"],
                    "path": page["path"],
                    "note": page.get("note"),
                    "status": "ok",
                    "detail": "",
                    "shots": [],
                }
                first_error = None
                for vp in viewports:
                    driver.set_window_size(vp["width"], vp["height"])
                    url = urljoin(cfg["base_url"].rstrip("/") + "/", page["path"].lstrip("/"))
                    try:
                        driver.get(url)
                        ready, why = wait_for_page(driver, cfg, page["path"], timeout)
                        filename = f"{vp['name']}-{vp['width']}x{vp['height']}.png"
                        rel = f"{slug(role)}/{page_id}/{filename}"
                        dest = run_dir / rel
                        capture_page(driver, dest)
                    except WebDriverException as exc:
                        first_error = first_error or str(exc)
                        print(f"  {page['name']} @ {vp['name']}: ERROR {exc}")
                        continue
                    page_row["shots"].append({
                        "viewport": vp["name"],
                        "width": vp["width"],
                        "height": vp["height"],
                        "file": rel,
                        "ready": ready,
                        "wait_detail": why,
                    })
                    flag = "ok" if ready else "warn"
                    print(f"  {page['name']} @ {vp['name']}: {flag} ({why})")
                    if not ready and not first_error:
                        first_error = why
                if not page_row["shots"]:
                    page_row["status"] = "capture_failed"
                    page_row["detail"] = first_error or "no screenshots written"
                elif first_error:
                    page_row["status"] = "partial"
                    page_row["detail"] = first_error
                results.append(page_row)

    finally:
        if driver is not None:
            driver.quit()

    (run_dir / "report.json").write_text(json.dumps({
        "run_id": run_id,
        "base_url": cfg["base_url"],
        "headed": headed,
        "read_only": True,
        "results": results,
    }, indent=2), encoding="utf-8")

    index = write_index(run_dir, cfg, run_id, results)
    print(f"\nIndex: {index}")
    failed_logins = [r for r in results if r.get("kind") == "login" and r["status"] != "ok"]
    failed_pages = [r for r in results if r.get("kind") == "page" and r["status"] != "ok"]
    print(f"Login failures: {len(failed_logins)}")
    print(f"Page issues: {len(failed_pages)}")
    return 0 if not failed_logins and not failed_pages else 1


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="InternTrack read-only screenshot tour")
    parser.add_argument("--config", default=str(DEFAULT_CONFIG), help="Path to config.json")
    parser.add_argument("--headed", action="store_true", help="Show the browser window")
    parser.add_argument("--base-url", help="Override frontend base URL")
    parser.add_argument("--roles", help="Comma-separated role keys to include")
    parser.add_argument("--password", help="Override default password")
    return parser.parse_args()


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        try:
            sys.stdout.reconfigure(encoding="utf-8", errors="replace", line_buffering=True)
        except Exception:
            pass
    args = parse_args()
    cfg_path = Path(args.config)
    if not cfg_path.exists():
        print(f"Config not found: {cfg_path}", file=sys.stderr)
        return 2
    cfg = load_config(cfg_path)
    if args.base_url:
        cfg["base_url"] = args.base_url
    roles = {r.strip() for r in args.roles.split(",")} if args.roles else None
    return run(cfg, headed=args.headed, roles_filter=roles, password_override=args.password)


if __name__ == "__main__":
    raise SystemExit(main())
