#!/usr/bin/env python3
"""MISD Admin full click-through audit (Selenium + live API probes)."""

from __future__ import annotations

import json
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from capture import (  # noqa: E402
    build_driver,
    fill_react_input,
    login,
    wait_document_complete,
    wait_for_page,
)

from selenium.common.exceptions import ElementClickInterceptedException, TimeoutException
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = "http://localhost:5173"
API = "http://127.0.0.1:8001/api/v1"
USERNAME = "ADMIN-MISD-001"
OUT = ROOT / "misd_audit_results.json"
TIMEOUT = 25


def _demo_password() -> str:
    """Prefer QA_PASSWORD env, then qa/screenshot-tour/config.json default_password."""
    import os

    env = (os.environ.get("QA_PASSWORD") or "").strip()
    if env:
        return env
    cfg_path = ROOT / "config.json"
    if cfg_path.exists():
        try:
            cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
            return str(cfg.get("default_password") or "").strip()
        except Exception:
            pass
    return ""


PASSWORD = _demo_password()


class Findings:
    def __init__(self):
        self.items = []

    def add(self, page, control, status, detail, console=None):
        self.items.append({
            "page": page,
            "control": control,
            "status": status,
            "detail": detail,
            "console": console or [],
        })


def drain_console(driver):
    logs = []
    try:
        for entry in driver.get_log("browser"):
            level = entry.get("level", "")
            msg = entry.get("message", "")
            if level in ("SEVERE", "WARNING") and "favicon" not in msg:
                logs.append(f"{level}: {msg[:350]}")
    except Exception:
        pass
    return logs


def api_json(method, path, token=None, body=None, params=None):
    url = API + path
    if params:
        from urllib.parse import urlencode
        url += "?" + urlencode(params)
    data = None
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if body is not None:
        data = json.dumps(body).encode("utf-8")
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", errors="replace")
        try:
            payload = json.loads(raw) if raw else {}
        except Exception:
            payload = {"raw": raw}
        return e.code, payload


def get_token():
    status, payload = api_json("POST", "/auth/login", body={"username": USERNAME, "password": PASSWORD})
    if status != 200:
        raise RuntimeError(f"API login failed: {status} {payload}")
    return payload["token"]


def safe_click(driver, el):
    driver.execute_script("arguments[0].scrollIntoView({block:'center'});", el)
    time.sleep(0.1)
    try:
        el.click()
    except ElementClickInterceptedException:
        driver.execute_script("arguments[0].click();", el)


def click_confirm(driver):
    time.sleep(0.35)
    # ConfirmModal uses Confirm label
    for xpath in [
        "//button[normalize-space()='Confirm']",
        "//button[contains(., 'Confirm')]",
        "//div[contains(@class,'modal')]//button[contains(@class,'btn-danger')]",
        "//div[contains(@class,'modal')]//button[contains(@class,'btn-primary')]",
    ]:
        try:
            btns = driver.find_elements(By.XPATH, xpath)
            for b in btns:
                if b.is_displayed() and "Cancel" not in (b.text or ""):
                    safe_click(driver, b)
                    time.sleep(0.6)
                    return True
        except Exception:
            continue
    return False


def nav(driver, cfg, path):
    driver.get(urljoin(BASE + "/", path.lstrip("/")))
    ok, msg = wait_for_page(driver, cfg, path, TIMEOUT)
    time.sleep(0.5)
    return ok, msg


def alert_text(driver):
    texts = []
    for e in driver.find_elements(By.CSS_SELECTOR, ".alert"):
        if e.is_displayed() and e.text.strip():
            texts.append(e.text.strip().replace("\n", " "))
    return " | ".join(texts)


def page_error(driver):
    for sel in [".alert-danger", "[class*='page-error']", ".text-danger"]:
        for e in driver.find_elements(By.CSS_SELECTOR, sel):
            if e.is_displayed() and "Failed to load" in (e.text or ""):
                return e.text.strip()
    return ""


def audit_staff(driver, cfg, findings, page, path, assign_label, token):
    ok, msg = nav(driver, cfg, path)
    cons = drain_console(driver)
    if not ok:
        findings.add(page, "page load", "broken", msg, cons)
        return
    findings.add(page, "page load", "ok", msg, cons)

    # API list sanity
    api_path = "/admin/directors" if "Director" in assign_label else "/admin/coordinators"
    st, payload = api_json("GET", api_path, token=token)
    items = payload.get("data") or payload.get("items") or (payload if isinstance(payload, list) else [])
    if isinstance(payload, dict) and "data" in payload and isinstance(payload["data"], dict):
        items = payload["data"].get("data") or payload["data"].get("items") or []
    # unwrap common shapes
    if isinstance(payload, dict):
        if isinstance(payload.get("data"), list):
            items = payload["data"]
        elif isinstance(payload.get("items"), list):
            items = payload["items"]
    findings.add(page, "API list", "ok" if st == 200 else "broken", f"HTTP {st}; count={len(items) if isinstance(items, list) else 'n/a'}")

    rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
    findings.add(page, "table rows", "ok", f"{len(rows)} visible rows")

    # Assign open/cancel
    try:
        btn = driver.find_element(By.XPATH, f"//button[contains(., '{assign_label}')]")
        safe_click(driver, btn)
        time.sleep(0.4)
        assert driver.find_elements(By.XPATH, f"//h6[contains(., '{assign_label}')]")
        cancel = driver.find_element(By.XPATH, "//button[normalize-space()='Cancel']")
        safe_click(driver, cancel)
        time.sleep(0.3)
        findings.add(page, f"{assign_label} open/cancel", "ok", "Form opened and cancelled")
    except Exception as e:
        findings.add(page, f"{assign_label} open/cancel", "broken", str(e), drain_console(driver))

    if not rows:
        findings.add(page, "row actions", "note", "No rows")
        return

    # Sync first row via UI
    try:
        row = rows[0]
        uid = row.find_elements(By.CSS_SELECTOR, "td")[1].text.strip()
        safe_click(driver, row.find_element(By.CSS_SELECTOR, "button[title='Sync from MISD']"))
        time.sleep(1.4)
        findings.add(page, "sync icon", "ok", f"{uid}: {alert_text(driver)}", drain_console(driver))
    except Exception as e:
        findings.add(page, "sync icon", "broken", str(e), drain_console(driver))

    # Reset password
    try:
        rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
        row = rows[0]
        uid = row.find_elements(By.CSS_SELECTOR, "td")[1].text.strip()
        safe_click(driver, row.find_element(By.CSS_SELECTOR, "button[title='Reset password']"))
        confirmed = click_confirm(driver)
        time.sleep(1.0)
        after = alert_text(driver)
        status = "ok" if confirmed and ("reset" in after.lower() or "Password" in after) else ("broken" if not confirmed else "note")
        findings.add(page, "reset-password icon", status, f"{uid}: confirm={confirmed}; {after}", drain_console(driver))
    except Exception as e:
        findings.add(page, "reset-password icon", "broken", str(e), drain_console(driver))

    # Toggle via API on a non-primary account if available, else UI toggle + restore on last row
    try:
        rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
        # Prefer last row to avoid toggling DIR-1001 / COR-CCS-001 first
        row = rows[-1]
        uid = row.find_elements(By.CSS_SELECTOR, "td")[1].text.strip()
        before = "Inactive" if "Inactive" in row.text else "Active"
        btn = row.find_element(By.CSS_SELECTOR, "button[title='Deactivate'], button[title='Activate']")
        title = btn.get_attribute("title")
        safe_click(driver, btn)
        confirmed = click_confirm(driver)
        time.sleep(1.3)
        nav(driver, cfg, path)
        rows2 = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
        matched = next((r for r in rows2 if uid in r.text), None)
        after = ("Inactive" if matched and "Inactive" in matched.text else "Active") if matched else "missing"
        flipped = matched and after != before
        findings.add(page, f"toggle ({title})", "ok" if flipped else "broken", f"{uid}: {before}->{after}; confirm={confirmed}; {alert_text(driver)}", drain_console(driver))
        if flipped and before == "Active":
            btn2 = matched.find_element(By.CSS_SELECTOR, "button[title='Activate']")
            safe_click(driver, btn2)
            click_confirm(driver)
            time.sleep(1.0)
            findings.add(page, "toggle restore", "ok", f"Restored {uid}")
    except Exception as e:
        findings.add(page, "toggle active", "broken", str(e), drain_console(driver))

    # Revoke: API dry-run only on already-inactive if exists; UI click cancel path
    try:
        nav(driver, cfg, path)
        rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
        inactive = next((r for r in rows if "Inactive" in r.text), None)
        if inactive:
            uid = inactive.find_elements(By.CSS_SELECTOR, "td")[1].text.strip()
            safe_click(driver, inactive.find_element(By.CSS_SELECTOR, "button[title='Revoke']"))
            # Cancel revoke to avoid destroying seed accounts
            time.sleep(0.4)
            cancel = None
            for b in driver.find_elements(By.XPATH, "//button[normalize-space()='Cancel']"):
                if b.is_displayed():
                    cancel = b
                    break
            if cancel:
                safe_click(driver, cancel)
                findings.add(page, "revoke icon (confirm dialog)", "ok", f"Opened revoke confirm for {uid} and cancelled")
            else:
                # if confirm auto-focused differently
                findings.add(page, "revoke icon (confirm dialog)", "note", f"Revoke clicked for {uid}; cancel button not found")
        else:
            findings.add(page, "revoke icon", "note", "No inactive row to open revoke dialog safely")
    except Exception as e:
        findings.add(page, "revoke icon", "broken", str(e), drain_console(driver))


def audit_mappings(driver, cfg, findings, token):
    page = "Section Mappings"
    ok, msg = nav(driver, cfg, "/admin/section-mappings")
    cons = drain_console(driver)
    findings.add(page, "page load", "ok" if ok else "broken", msg, cons)

    st, payload = api_json("GET", "/admin/section-assignments", token=token)
    items = payload.get("data") if isinstance(payload.get("data"), list) else payload.get("items") or []
    if isinstance(payload.get("data"), dict):
        items = payload["data"].get("data") or payload["data"].get("items") or []
    # also try unwrap
    if not items and isinstance(payload, dict):
        maybe = payload.get("data")
        if isinstance(maybe, list):
            items = maybe

    fac_coe = []
    for row in items if isinstance(items, list) else []:
        fac = row.get("faculty") or {}
        uname = (fac.get("username") or fac.get("faculty_number") or "")
        name = fac.get("name") or ""
        if "FAC-COE-001" in str(uname) or "FAC-COE-001" in str(row) or ("Fac" in name and "COE" in str(uname)):
            fac_coe.append({
                "section": row.get("section"),
                "program": row.get("program"),
                "faculty": name,
                "username": uname,
            })
    # broader search
    if not fac_coe:
        for row in items if isinstance(items, list) else []:
            blob = json.dumps(row)
            if "FAC-COE-001" in blob:
                fac_coe.append({
                    "section": row.get("section"),
                    "program": row.get("program"),
                    "faculty": (row.get("faculty") or {}).get("name"),
                    "username": (row.get("faculty") or {}).get("username"),
                })

    findings.add(page, "API mappings", "ok" if st == 200 else "broken", f"HTTP {st}; {len(items) if isinstance(items, list) else 0} mappings")
    findings.add(page, "FAC-COE-001 mapping analysis", "note", json.dumps(fac_coe[:10]))

    # Filter Apply — semester mismatch is a likely bug
    try:
        ay = driver.find_element(By.CSS_SELECTOR, "input[placeholder='School Year']")
        ay.clear()
        ay.send_keys("2025-2026")
        Select(driver.find_element(By.CSS_SELECTOR, "select.form-select-sm")).select_by_value("2")
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Apply')]"))
        time.sleep(1.3)
        body = driver.find_element(By.CSS_SELECTOR, "table tbody").text
        empty = "No mappings found" in body
        # API with semester=2
        st2, p2 = api_json("GET", "/admin/section-assignments", token=token, params={"academic_year": "2025-2026", "semester": "2"})
        items2 = p2.get("data") if isinstance(p2.get("data"), list) else []
        st3, p3 = api_json("GET", "/admin/section-assignments", token=token, params={"academic_year": "2025-2026", "semester": "2nd Semester"})
        items3 = p3.get("data") if isinstance(p3.get("data"), list) else []
        findings.add(
            page,
            "filters Apply",
            "broken" if empty and len(items) > 0 else "ok",
            f"UI empty={empty}; API semester=2 count={len(items2)}; API semester='2nd Semester' count={len(items3)}; body={body[:180]}",
            drain_console(driver),
        )
        # reset
        ay.clear()
        Select(driver.find_element(By.CSS_SELECTOR, "select.form-select-sm")).select_by_value("")
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Apply')]"))
        time.sleep(0.8)
    except Exception as e:
        findings.add(page, "filters Apply", "broken", str(e), drain_console(driver))

    # Add mapping form validation + create/delete via API (safer) and UI edit
    try:
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Add Mapping')]"))
        time.sleep(0.4)
        form_visible = "Add Mapping" in driver.page_source
        findings.add(page, "Add Mapping open", "ok" if form_visible else "broken", f"visible={form_visible}")
        safe_click(driver, driver.find_element(By.XPATH, "//button[normalize-space()='Cancel']"))
        time.sleep(0.3)
        findings.add(page, "Add Mapping cancel", "ok", "Cancelled")
    except Exception as e:
        findings.add(page, "Add Mapping form", "broken", str(e), drain_console(driver))

    # Create disposable mapping via API then edit/delete in UI
    try:
        stf, fac_payload = api_json("GET", "/admin/faculty-options", token=token)
        fac_items = fac_payload.get("data") if isinstance(fac_payload.get("data"), list) else fac_payload.get("items") or []
        if not fac_items and isinstance(fac_payload.get("data"), dict):
            fac_items = fac_payload["data"].get("data") or []
        fac_id = fac_items[0]["id"] if fac_items else None
        if not fac_id:
            findings.add(page, "faculty options", "broken", f"HTTP {stf}; no faculty options")
        else:
            findings.add(page, "faculty options", "ok", f"{len(fac_items)} faculty options")
            create_body = {
                "program": "Bachelor of Science in Information Technology",
                "section": "4IT-Z-AUDIT",
                "academic_year": "2025-2026",
                "semester": 2,
                "faculty_user_id": fac_id,
                "is_active": True,
            }
            stc, created = api_json("POST", "/admin/section-assignments", token=token, body=create_body)
            # try string semester if numeric fails
            if stc >= 400:
                create_body["semester"] = "2nd Semester"
                stc, created = api_json("POST", "/admin/section-assignments", token=token, body=create_body)
            findings.add(page, "Add Mapping API create", "ok" if stc in (200, 201) else "broken", f"HTTP {stc}; {created}")

            nav(driver, cfg, "/admin/section-mappings")
            # Edit icon on first row
            pens = driver.find_elements(By.CSS_SELECTOR, "button.btn-outline-primary")
            if pens:
                safe_click(driver, pens[0])
                time.sleep(0.5)
                opened = "Edit Mapping" in driver.page_source
                findings.add(page, "edit icon", "ok" if opened else "broken", f"edit opened={opened}")
                if opened:
                    safe_click(driver, driver.find_element(By.XPATH, "//button[normalize-space()='Cancel']"))
            else:
                findings.add(page, "edit icon", "note", "No edit buttons")

            # Delete audit mapping via UI if present
            nav(driver, cfg, "/admin/section-mappings")
            deleted = False
            for row in driver.find_elements(By.CSS_SELECTOR, "table tbody tr"):
                if "AUDIT" in row.text.upper() or "4IT-Z" in row.text.upper():
                    safe_click(driver, row.find_element(By.CSS_SELECTOR, "button.btn-outline-danger"))
                    time.sleep(0.3)
                    try:
                        driver.switch_to.alert.accept()
                    except Exception:
                        pass
                    time.sleep(1.0)
                    deleted = True
                    findings.add(page, "delete icon", "ok", f"Deleted audit mapping; alert={alert_text(driver)}", drain_console(driver))
                    break
            if not deleted:
                # cleanup via API
                stl, plist = api_json("GET", "/admin/section-assignments", token=token)
                plist_items = plist.get("data") if isinstance(plist.get("data"), list) else []
                for row in plist_items:
                    if "AUDIT" in str(row.get("section", "")).upper():
                        api_json("DELETE", f"/admin/section-assignments/{row['id']}", token=token)
                        findings.add(page, "delete icon", "note", "UI row not found; cleaned via API")
                        deleted = True
                        break
                if not deleted:
                    findings.add(page, "delete icon", "note", "No audit mapping to delete")
    except Exception as e:
        findings.add(page, "mapping CRUD", "broken", str(e), drain_console(driver))


def audit_users(driver, cfg, findings, token):
    page = "Users"
    ok, msg = nav(driver, cfg, "/admin/users")
    findings.add(page, "page load", "ok" if ok else "broken", msg, drain_console(driver))

    st, payload = api_json("GET", "/admin/users", token=token, params={"role": "faculty", "per_page": 50})
    items = []
    if isinstance(payload.get("data"), list):
        items = payload["data"]
    elif isinstance(payload.get("data"), dict):
        items = payload["data"].get("data") or payload["data"].get("items") or []
    formats = [{"name": u.get("name"), "username": u.get("username"), "email": u.get("email"), "id": u.get("id")} for u in items]
    email_usernames = [f for f in formats if f.get("username") and "@" in str(f["username"])]
    fac_codes = [f for f in formats if str(f.get("username", "")).upper().startswith("FAC-")]
    findings.add(page, "API faculty users", "ok" if st == 200 else "broken", f"HTTP {st}; faculty={len(formats)}")
    findings.add(page, "USERNAME/ID format check", "note", json.dumps({"fac_codes": fac_codes, "email_as_username": email_usernames}))

    # Search UI
    try:
        search = driver.find_element(By.CSS_SELECTOR, "input[placeholder='Search Users']")
        fill_react_input(driver, search, "FAC-1001")
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Filter')]"))
        time.sleep(1.2)
        body = driver.find_element(By.CSS_SELECTOR, "table tbody").text
        findings.add(page, "Search + Filter", "ok" if "FAC-1001" in body or "Bicua" in body else "broken", body[:220], drain_console(driver))
    except Exception as e:
        findings.add(page, "Search + Filter", "broken", str(e), drain_console(driver))

    # Role filter faculty via UI select
    try:
        nav(driver, cfg, "/admin/users")
        selects = driver.find_elements(By.CSS_SELECTOR, "select.form-select")
        Select(selects[0]).select_by_value("faculty")
        time.sleep(1.2)  # useEffect loads
        body = driver.find_element(By.CSS_SELECTOR, "table tbody").text
        findings.add(page, "Role Filter", "ok" if "faculty" in body.lower() or "FAC-" in body else "note", body[:240], drain_console(driver))
    except Exception as e:
        findings.add(page, "Role Filter", "broken", str(e), drain_console(driver))

    # Status inactive
    try:
        selects = driver.find_elements(By.CSS_SELECTOR, "select.form-select")
        Select(selects[1]).select_by_value("false")
        time.sleep(1.2)
        body = driver.find_element(By.CSS_SELECTOR, "table tbody").text
        findings.add(page, "Account Status filter", "ok", body[:240], drain_console(driver))
    except Exception as e:
        findings.add(page, "Account Status filter", "broken", str(e), drain_console(driver))

    # Reset password FAC-1002
    try:
        nav(driver, cfg, "/admin/users")
        search = driver.find_element(By.CSS_SELECTOR, "input[placeholder='Search Users']")
        fill_react_input(driver, search, "FAC-1002")
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Filter')]"))
        time.sleep(1.0)
        rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
        if rows and "FAC-1002" in rows[0].text:
            safe_click(driver, rows[0].find_element(By.CSS_SELECTOR, "button[title='Reset Password']"))
            confirmed = click_confirm(driver)
            time.sleep(1.0)
            findings.add(page, "reset-password icon", "ok" if confirmed else "broken", f"confirm={confirmed}; {alert_text(driver)}", drain_console(driver))
            # toggle deactivate/activate
            rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
            before = "Inactive" if "Inactive" in rows[0].text else "Active"
            safe_click(driver, rows[0].find_element(By.CSS_SELECTOR, "button[title='Deactivate'], button[title='Activate']"))
            click_confirm(driver)
            time.sleep(1.2)
            nav(driver, cfg, "/admin/users")
            search = driver.find_element(By.CSS_SELECTOR, "input[placeholder='Search Users']")
            fill_react_input(driver, search, "FAC-1002")
            safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Filter')]"))
            time.sleep(1.0)
            rows = [r for r in driver.find_elements(By.CSS_SELECTOR, "table tbody tr") if r.is_displayed()]
            after = "Inactive" if rows and "Inactive" in rows[0].text else "Active"
            findings.add(page, "disable/enable icon", "ok" if before != after else "broken", f"{before}->{after}", drain_console(driver))
            if after == "Inactive" and rows:
                safe_click(driver, rows[0].find_element(By.CSS_SELECTOR, "button[title='Activate']"))
                click_confirm(driver)
                time.sleep(1.0)
                findings.add(page, "disable restore", "ok", "Restored FAC-1002")
        else:
            findings.add(page, "user row actions", "note", "FAC-1002 not in list")
    except Exception as e:
        findings.add(page, "user row actions", "broken", str(e), drain_console(driver))


def audit_sync(driver, cfg, findings, token):
    page = "MISD Sync"
    ok, msg = nav(driver, cfg, "/admin/sync")
    findings.add(page, "page load", "ok" if ok else "broken", msg, drain_console(driver))

    st, status = api_json("GET", "/admin/misd/status", token=token)
    findings.add(page, "API health", "ok" if st == 200 else "broken", json.dumps(status)[:300])

    try:
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Recheck')]"))
        time.sleep(1.5)
        findings.add(page, "Recheck", "ok", "Recheck clicked", drain_console(driver))
    except Exception as e:
        findings.add(page, "Recheck", "broken", str(e), drain_console(driver))

    try:
        inp = driver.find_element(By.CSS_SELECTOR, "input[placeholder='Student Number']")
        fill_react_input(driver, inp, "2300600")
        safe_click(driver, driver.find_element(By.XPATH, "//button[contains(., 'Lookup')]"))
        time.sleep(1.5)
        ok_lookup = "Valinado" in driver.page_source or "Local" in driver.page_source
        findings.add(page, "Student lookup", "ok" if ok_lookup else "broken", f"alert={alert_text(driver)}", drain_console(driver))
    except Exception as e:
        findings.add(page, "Student lookup", "broken", str(e), drain_console(driver))

    # Directory via API + UI
    st_s, dir_s = api_json("POST", "/admin/misd/directory", token=token, body={"type": "students"})
    st_f, dir_f = api_json("POST", "/admin/misd/directory", token=token, body={"type": "faculty"})
    findings.add(page, "API directory students", "ok" if st_s == 200 and (dir_s.get("count") or 0) > 0 else "broken", f"HTTP {st_s}; count={dir_s.get('count')}; keys={list(dir_s.keys())}")
    findings.add(page, "API directory faculty", "ok" if st_f == 200 and (dir_f.get("count") or 0) > 0 else "broken", f"HTTP {st_f}; count={dir_f.get('count')}; keys={list(dir_f.keys())}")

    try:
        safe_click(driver, driver.find_element(By.XPATH, "//button[normalize-space()='Students']"))
        time.sleep(1.8)
        card = next((c.text for c in driver.find_elements(By.CSS_SELECTOR, ".content-card") if "Directory" in c.text), "")
        findings.add(page, "Directory Students button", "ok" if "returned" in card.lower() and "0 students" not in card.lower() else "broken", f"alert={alert_text(driver)}; {card[:280]}", drain_console(driver))
        safe_click(driver, driver.find_element(By.XPATH, "//button[normalize-space()='Faculty']"))
        time.sleep(1.8)
        card = next((c.text for c in driver.find_elements(By.CSS_SELECTOR, ".content-card") if "Directory" in c.text), "")
        findings.add(page, "Directory Faculty button", "ok" if "returned" in card.lower() and "0 faculty" not in card.lower() else "broken", f"alert={alert_text(driver)}; {card[:280]}", drain_console(driver))
    except Exception as e:
        findings.add(page, "Directory buttons", "broken", str(e), drain_console(driver))


def main():
    findings = Findings()
    cfg = {
        "base_url": BASE,
        "login_path": "/",
        "timeout_seconds": TIMEOUT,
        "ready_selectors": ["main.main-content", ".sidebar", ".login-page-redesign"],
        "loading_selectors": [".fa-spinner", ".fa-circle-notch.fa-spin", "[aria-busy='true']"],
        "settle_ms": 500,
        "network_idle_ms": 500,
    }

    try:
        token = get_token()
        findings.add("API", "login", "ok", "Token acquired")
    except Exception as e:
        findings.add("API", "login", "broken", str(e))
        OUT.write_text(json.dumps(findings.items, indent=2), encoding="utf-8")
        print(json.dumps(findings.items, indent=2))
        return 1

    driver = build_driver(False, 1440, 900)
    # enable browser logs
    try:
        driver.execute_cdp_cmd("Network.enable", {})
    except Exception:
        pass

    try:
        ok, info = login(driver, cfg, {"username": USERNAME}, PASSWORD, TIMEOUT)
        findings.add("Login", "browser", "ok" if ok else "broken", info)
        if not ok:
            OUT.write_text(json.dumps(findings.items, indent=2), encoding="utf-8")
            print(json.dumps(findings.items, indent=2))
            return 1

        audit_staff(driver, cfg, findings, "Directors", "/admin/directors", "Assign Director", token)
        audit_staff(driver, cfg, findings, "Coordinators", "/admin/coordinators", "Assign Coordinator", token)
        audit_mappings(driver, cfg, findings, token)
        audit_users(driver, cfg, findings, token)
        audit_sync(driver, cfg, findings, token)

        OUT.write_text(json.dumps(findings.items, indent=2), encoding="utf-8")
        print(json.dumps(findings.items, indent=2))
        broken = [f for f in findings.items if f["status"] == "broken"]
        print(f"\nSUMMARY: {len(findings.items)} checks, {len(broken)} broken", file=sys.stderr)
        return 1 if broken else 0
    finally:
        driver.quit()


if __name__ == "__main__":
    raise SystemExit(main())
