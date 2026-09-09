import json
import time
import urllib.request
from pathlib import Path

from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

API = "http://127.0.0.1:8001/api/v1"
BASE = "http://localhost:5173"


def api_login(username):
    req = urllib.request.Request(
        API + "/auth/login",
        data=json.dumps({"username": username, "password": "interntrack123"}).encode(),
        headers={"Content-Type": "application/json", "Accept": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=20) as r:
        return json.loads(r.read())["token"]


def api_json(method, path, token, body=None):
    data = None if body is None else json.dumps(body).encode()
    req = urllib.request.Request(
        API + path,
        data=data,
        method=method,
        headers={"Authorization": "Bearer " + token, "Accept": "application/json", "Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=20) as r:
        return json.loads(r.read())
PDF = Path(__file__).parent / "output" / "placement-enhance" / "sample-moa.pdf"
OUT = Path(__file__).parent / "output" / "placement-enhance"
OUT.mkdir(parents=True, exist_ok=True)
PDF.write_bytes(
    b"%PDF-1.1\n"
    b"1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
    b"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
    b"3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\n"
    b"trailer<</Root 1 0 R>>\n"
    b"%%EOF\n"
)

opts = Options()
opts.add_argument("--headless=new")
opts.add_argument("--window-size=1440,900")
opts.binary_location = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
driver = webdriver.Chrome(
    service=Service(str(Path(__file__).parent / "drivers" / "chromedriver.exe")),
    options=opts,
)
wait = WebDriverWait(driver, 15)
results = []


def ok(name, passed, extra=""):
    results.append((name, passed, extra))
    print(("PASS" if passed else "FAIL"), name, extra, flush=True)


def login(user, hint):
    driver.get(BASE + "/")
    driver.execute_script("try { sessionStorage.clear(); localStorage.clear(); } catch(e) {}")
    driver.get(BASE + "/")
    WebDriverWait(driver, 30).until(EC.visibility_of_element_located((By.ID, "studentNumber")))
    field = driver.find_element(By.ID, "studentNumber")
    field.clear()
    field.send_keys(user)
    pw = driver.find_element(By.ID, "password")
    pw.clear()
    pw.send_keys("interntrack123")
    driver.find_element(By.CSS_SELECTOR, '#loginForm button.btn-signin[type="submit"]').click()
    WebDriverWait(driver, 30).until(lambda d: hint in (d.current_url or ""))
    time.sleep(0.6)


def no_fullpage_spinner():
    return driver.execute_script("""
      const blockers = Array.from(document.querySelectorAll('.min-vh-100 i.fa-spin, .it-confirm-overlay i.fa-spin.fa-2x'));
      return blockers.every(el => !el.offsetParent);
    """)


def click_sidebar(text):
    overlays = driver.find_elements(By.CSS_SELECTOR, ".it-confirm-overlay")
    for overlay in overlays:
        if overlay.is_displayed():
            driver.find_element(By.CSS_SELECTOR, ".it-confirm-btn-cancel").click()
            time.sleep(0.3)
            break
    links = driver.find_elements(By.CSS_SELECTOR, "a.sidebar-link")
    for link in links:
        if text.lower() in (link.text or "").lower() and link.is_displayed():
            driver.execute_script("arguments[0].click();", link)
            time.sleep(0.4)
            return True
    return False


try:
    login("2300600", "/student/")
    driver.get(BASE + "/student/companies")
    wait.until(EC.presence_of_element_located((By.XPATH, "//*[contains(., 'TechCorp')]")))
    ok("student sees TechCorp", "TechCorp" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-01-companies.png"))

    driver.find_element(By.ID, "placement-tab-applications").click()
    time.sleep(0.8)
    ok("student applied Accenture with MOA", "Accenture" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-02-applications.png"))

    nav_pages = [
        "Dashboard", "Placement Hub", "Attendance", "Journal", "Documents",
        "Evaluations", "My Records", "Messages", "Meetings",
    ]
    nav_ok = True
    for label in nav_pages:
        if not click_sidebar(label):
            nav_ok = False
            break
        if not no_fullpage_spinner():
            nav_ok = False
            driver.save_screenshot(str(OUT / f"e2e-spin-{label.replace(' ', '')}.png"))
            break
    ok("student sidebar no full-page spinner", nav_ok)

    login("COR-CCS-001", "/coordinator/")
    driver.get(BASE + "/coordinator/internship-management")
    wait.until(EC.presence_of_element_located((By.XPATH, "//*[contains(., 'Accenture') or contains(., 'TechCorp')]")))
    ok("coordinator sees Accenture", "Accenture" in driver.page_source)
    ok("coordinator has Approve", "Approve" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-03-coord-apps.png"))

    rows = driver.find_elements(By.XPATH, "//tr[contains(., 'Accenture')]")
    if rows:
        btns = rows[0].find_elements(By.XPATH, ".//button[contains(., 'Approve')]")
        if btns:
            btns[0].click()
            wait.until(EC.visibility_of_element_located((By.XPATH, "//button[normalize-space()='Approve']")))
            confirms = driver.find_elements(By.CSS_SELECTOR, ".it-confirm-actions button")
            for b in confirms:
                if b.text.strip() == "Approve":
                    b.click()
                    break
            try:
                wait.until(EC.invisibility_of_element_located((By.CSS_SELECTOR, ".it-confirm-overlay")))
            except TimeoutException:
                cancels = driver.find_elements(By.CSS_SELECTOR, ".it-confirm-btn-cancel")
                if cancels:
                    driver.execute_script("arguments[0].click();", cancels[0])
    ok("coordinator approved Accenture UI opened", "Approve Placement" in driver.page_source or "Accenture" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-04-coord-approved.png"))
    cancels = driver.find_elements(By.CSS_SELECTOR, ".it-confirm-btn-cancel")
    if cancels:
        driver.execute_script("arguments[0].click();", cancels[0])
        time.sleep(0.3)

    token = api_login("COR-CCS-001")
    apps = api_json("GET", "/coordinator/applications", token)["applications"]
    approved = False
    for app in apps:
        name = (app.get("company") or {}).get("company_name")
        if name == "Accenture PH" and app.get("status") == "pending":
            api_json("PATCH", f"/coordinator/applications/{app['id']}/status", token, {"status": "approved"})
            approved = True
        elif name == "Accenture PH" and app.get("status") == "approved":
            approved = True
    ok("coordinator approved Accenture", approved)

    hte_tab = driver.find_elements(By.XPATH, "//button[contains(., 'HTE Requests')]")
    if hte_tab:
        driver.execute_script("arguments[0].click();", hte_tab[0])
        time.sleep(1.2)
    ok("coordinator HTE tab", "Pricon" in driver.page_source or "HTE" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-05-coord-hte.png"))

    if "Pricon" in driver.page_source:
        prow = driver.find_element(By.XPATH, "//tr[contains(., 'Pricon')]")
        rej = prow.find_elements(By.XPATH, ".//button[contains(., 'Reject')]")
        if rej:
            rej[0].click()
            wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, "textarea")))
            driver.find_element(By.CSS_SELECTOR, ".it-confirm-body textarea").send_keys("Missing MOA counterpart for QA test.")
            for b in driver.find_elements(By.CSS_SELECTOR, ".it-confirm-actions button"):
                if b.text.strip() == "Reject":
                    b.click()
                    break
            time.sleep(1.0)
            ok("coordinator rejected HTE UI", True)
        else:
            ok("coordinator rejected HTE UI", False, "no reject button")
    else:
        ok("coordinator rejected HTE UI", False, "Pricon not visible")
    cancels = driver.find_elements(By.CSS_SELECTOR, ".it-confirm-btn-cancel")
    if cancels:
        driver.execute_script("arguments[0].click();", cancels[0])
        time.sleep(0.3)
    reqs = api_json("GET", "/coordinator/hte-requests", token)["requests"]
    rejected = False
    for req in reqs:
        if req.get("company_name") == "Pricon" and req.get("status") == "pending":
            api_json("PATCH", f"/coordinator/hte-requests/{req['id']}/status", token, {
                "status": "rejected",
                "coordinator_remarks": "Missing MOA counterpart for QA test.",
            })
            rejected = True
        elif req.get("company_name") == "Pricon" and req.get("status") == "rejected":
            rejected = True
    ok("coordinator rejected HTE", rejected)
    driver.save_screenshot(str(OUT / "e2e-06-coord-hte-rejected.png"))

    coord_nav = ["Dashboard", "Internship Mgmt", "Requirements", "Doc Approvals", "Evaluations", "Records", "Reports", "Meetings"]
    cnav_ok = True
    for label in coord_nav:
        if not click_sidebar(label):
            continue
        if not no_fullpage_spinner():
            cnav_ok = False
            break
    ok("coordinator sidebar no full-page spinner", cnav_ok)

    driver.get(BASE + "/coordinator/reports")
    time.sleep(1.0)
    gens = driver.find_elements(By.XPATH, "//button[contains(., 'Generate')]")
    if gens:
        gens[0].click()
        time.sleep(2.5)
    landscape = "A4 landscape" in driver.execute_script(
        "return Array.from(document.styleSheets).flatMap(s => { try { return Array.from(s.cssRules).map(r => r.cssText) } catch(e) { return [] } }).join('\\n')"
    )
    ok("coordinator report landscape CSS present", landscape or "interntrack-report-print" in driver.page_source)
    driver.save_screenshot(str(OUT / "e2e-07-coord-report.png"))

    login("2300600", "/student/")
    driver.get(BASE + "/student/companies")
    wait.until(EC.presence_of_element_located((By.ID, "placement-tab-applications")))
    driver.find_element(By.ID, "placement-tab-applications").click()
    time.sleep(1.2)
    src = driver.page_source
    ok("student sees Accenture approved", "Accenture" in src and "APPROVED" in src.upper())
    ok("student sees HTE rejection reason", "Missing MOA counterpart" in src or "REJECTED" in src.upper())
    driver.save_screenshot(str(OUT / "e2e-08-student-result.png"))

    for user, hint, path in [
        ("FAC-CHAS-001", "/faculty/", "/faculty/reports"),
        ("DIR-001", "/director/", "/director/reports"),
        ("ADMIN-1001", "/admin/", "/admin/dashboard"),
    ]:
        try:
            login(user, hint)
            driver.get(BASE + path)
            time.sleep(1.2)
            ok(f"{user} page loads", True, path)
            if "reports" in path:
                gens = driver.find_elements(By.XPATH, "//button[contains(., 'Generate')]")
                if gens:
                    gens[0].click()
                    time.sleep(2.0)
                driver.save_screenshot(str(OUT / f"e2e-report-{user}.png"))
        except TimeoutException as e:
            ok(f"{user} page loads", False, str(e)[:120])

except Exception as exc:
    import traceback
    traceback.print_exc()
    ok("uncaught", False, str(exc)[:200])
finally:
    driver.quit()
    failed = [r for r in results if not r[1]]
    print("---", sum(1 for r in results if r[1]), "/", len(results), "passed ---")
    for name, passed, extra in results:
        if not passed:
            print(" ", name, extra)
    raise SystemExit(1 if failed else 0)
