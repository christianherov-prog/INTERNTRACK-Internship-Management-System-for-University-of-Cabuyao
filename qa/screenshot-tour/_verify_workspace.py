"""Verify the coordinator Faculty Supervisor workspace switcher end to end."""
import time
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = "http://localhost:5173"
PW = "interntrack123"
OUT = Path(__file__).parent / "output" / "workspace-verify"
OUT.mkdir(parents=True, exist_ok=True)

opts = Options()
opts.add_argument("--headless=new")
opts.add_argument("--window-size=1440,900")
opts.binary_location = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
driver = webdriver.Chrome(
    service=Service(str(Path(__file__).parent / "drivers" / "chromedriver.exe")),
    options=opts,
)
wait = WebDriverWait(driver, 30)

results = []


def check(name, ok, detail=""):
    results.append((name, ok, detail))
    print(("PASS " if ok else "FAIL ") + name + (f" -- {detail}" if detail else ""))


def shot(name):
    driver.save_screenshot(str(OUT / f"{name}.png"))


def fresh_login(username, hint):
    driver.get(BASE + "/")
    driver.execute_script("try { sessionStorage.clear(); localStorage.clear(); } catch(e) {}")
    driver.get(BASE + "/")
    wait.until(EC.visibility_of_element_located((By.ID, "studentNumber")))
    u = driver.find_element(By.ID, "studentNumber"); u.clear(); u.send_keys(username)
    p = driver.find_element(By.ID, "password"); p.clear(); p.send_keys(PW)
    driver.find_element(By.CSS_SELECTOR, '#loginForm button.btn-signin[type="submit"]').click()
    wait.until(lambda d: hint in (d.current_url or ""))
    time.sleep(1.2)


def workspace_select():
    els = driver.find_elements(By.CSS_SELECTOR, 'select[aria-label="Workspace"]')
    return els[0] if els else None


def wait_ready(timeout=15):
    end = time.time() + timeout
    while time.time() < end:
        src = driver.page_source
        if "fa-spin fa-2x" not in src and "Checking your session" not in src:
            return
        time.sleep(0.25)


def sidebar_hrefs():
    return [a.get_attribute("href") or "" for a in driver.find_elements(By.CSS_SELECTOR, ".sidebar-nav a")]


try:
    # ── 1. Mapped coordinator: COR-CCS-001 (faculty on internship 8 / student 2300592)
    fresh_login("COR-CCS-001", "/coordinator/monitoring")
    wait_ready()
    sel = workspace_select()
    check("coordinator sees workspace switcher", sel is not None)
    check("switcher defaults to coordinator", sel is not None and Select(sel).first_selected_option.get_attribute("value") == "coordinator")
    shot("01-coordinator-workspace")

    # Switch to Faculty Supervisor
    Select(sel).select_by_value("faculty")
    wait.until(lambda d: "/faculty/dashboard" in d.current_url)
    wait_ready()
    time.sleep(1.0)
    hrefs = " ".join(sidebar_hrefs())
    check("faculty dashboard loads for coordinator", "Forbidden" not in driver.page_source)
    check("sidebar shows faculty nav", "/faculty/assigned-students" in hrefs and "/coordinator/monitoring" not in hrefs)
    time.sleep(1.5)
    src = driver.page_source
    check(
        "faculty workspace summary is advisee-scoped",
        "FACULTY DASHBOARD" in src and "Pending Journals" in src and "Assigned Companies" not in src,
    )
    shot("02-faculty-workspace-dashboard")

    # Assigned students: advisee visible (2300592 = Montealegre; roster renders name/email,
    # not the student number), other faculty's student (2300600 = Bautista) not.
    driver.get(BASE + "/faculty/assigned-students")
    wait_ready(); time.sleep(1.5)
    src = driver.page_source
    check("advisee (2300592 Montealegre) listed", "montealegre" in src.lower())
    check("roster shows exactly 1 student", "1 student" in src.lower())
    shot("03-faculty-assigned-students")

    # Other faculty workspace pages respond without 403
    for path in ["/faculty/journals", "/faculty/documents", "/faculty/supervisor-approvals"]:
        driver.get(BASE + path)
        wait_ready(); time.sleep(1.0)
        ok = "Forbidden" not in driver.page_source and driver.current_url.endswith(path)
        check(f"{path} loads in faculty workspace", ok, driver.current_url)
    shot("04-faculty-supervisor-approvals")

    # Reload keeps faculty workspace
    driver.get(BASE + "/faculty/dashboard")
    wait_ready(); time.sleep(0.8)
    driver.refresh()
    wait_ready(); time.sleep(1.2)
    hrefs = " ".join(sidebar_hrefs())
    check("reload keeps faculty workspace", "/faculty/assigned-students" in hrefs)

    # Switch back to Coordinator
    sel = workspace_select()
    Select(sel).select_by_value("coordinator")
    wait.until(lambda d: "/coordinator/monitoring" in d.current_url)
    wait_ready(); time.sleep(1.0)
    hrefs = " ".join(sidebar_hrefs())
    check("switch back to coordinator nav", "/coordinator/internship-management" in hrefs and "/faculty/assigned-students" not in hrefs)
    driver.get(BASE + "/coordinator/internship-management")
    wait_ready(); time.sleep(1.5)
    check("coordinator placement hub still works", "Forbidden" not in driver.page_source)
    shot("05-back-to-coordinator")

    # ── 2. Unmapped coordinator: COR-CAS-001 → faculty workspace is EMPTY, not department-wide
    fresh_login("COR-CAS-001", "/coordinator/monitoring")
    wait_ready()
    sel = workspace_select()
    Select(sel).select_by_value("faculty")
    wait.until(lambda d: "/faculty/dashboard" in d.current_url)
    driver.get(BASE + "/faculty/assigned-students")
    wait_ready(); time.sleep(1.5)
    src = driver.page_source.lower()
    check("unmapped coordinator sees no advisees", "montealegre" not in src and "1 student" not in src)
    shot("06-unmapped-coordinator-empty")

    # ── 3. Faculty account: no switcher
    fresh_login("FAC-CHAS-001", "/faculty/dashboard")
    wait_ready()
    check("faculty account has NO workspace switcher", workspace_select() is None)
    shot("07-faculty-no-switcher")

finally:
    driver.quit()

fails = [r for r in results if not r[1]]
print(f"\n{len(results) - len(fails)}/{len(results)} checks passed")
if fails:
    raise SystemExit(1)
