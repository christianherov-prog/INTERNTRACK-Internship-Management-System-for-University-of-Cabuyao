import time
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = "http://localhost:5173"
PW = "interntrack123"
OUT = Path(__file__).parent / "output" / "placement-enhance"
OUT.mkdir(parents=True, exist_ok=True)
PDF = OUT / "sample-moa.pdf"
PDF.write_bytes(
    b"%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
    b"3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF\n"
)

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


def wait_ready(timeout=12):
    end = time.time() + timeout
    while time.time() < end:
        src = driver.page_source
        if "Checking your session" not in src:
            return
        time.sleep(0.2)


def login(user, hint):
    driver.get(BASE + "/")
    driver.execute_script("try { sessionStorage.clear(); localStorage.clear(); } catch(e) {}")
    driver.get(BASE + "/")
    wait.until(EC.visibility_of_element_located((By.ID, "studentNumber")))
    u = driver.find_element(By.ID, "studentNumber"); u.clear(); u.send_keys(user)
    p = driver.find_element(By.ID, "password"); p.clear(); p.send_keys(PW)
    driver.find_element(By.CSS_SELECTOR, '#loginForm button.btn-signin[type="submit"]').click()
    wait.until(lambda d: hint in (d.current_url or ""))
    wait_ready()
    time.sleep(0.8)


def disruptive_spinner():
    src = driver.page_source
    return "fa-spin fa-2x" in src and "Checking your session" not in src


try:
    login("2300600", "/student/")
    driver.get(BASE + "/student/companies")
    wait_ready(); time.sleep(1.2)
    shot("01-student-placement")
    btns = driver.find_elements(By.CSS_SELECTOR, 'button[id^="apply-company-"]')
    check("student placement hub loads", "Placement Hub" in driver.page_source or "Eligible Companies" in driver.page_source)
    if btns:
        driver.execute_script("arguments[0].click();", btns[0])
        time.sleep(0.5)
        files = driver.find_elements(By.CSS_SELECTOR, '.it-confirm-modal input[type=file]')
        check("apply modal with MOA upload", len(files) > 0)
        if files:
            files[0].send_keys(str(PDF))
        confirms = [b for b in driver.find_elements(By.CSS_SELECTOR, ".it-confirm-btn") if "Submit" in (b.text or "")]
        if confirms:
            driver.execute_script("arguments[0].click();", confirms[0])
            time.sleep(2.0)
        shot("02-student-applied")
        check("application pending visible", "PENDING" in driver.page_source.upper())
    else:
        check("apply button present", False, "no eligible companies")

    # Coordinator
    login("COR-CCS-001", "/coordinator/")
    driver.get(BASE + "/coordinator/internship-management")
    wait_ready(); time.sleep(1.5)
    src = driver.page_source
    check("coordinator sees applications table", "Student Placements" in src)
    check("approve/reject actions exist", "Approve" in src or "No applications" in src)
    shot("03-coordinator-applications")

    # Navigation loaders
    for href in ["/coordinator/monitoring", "/coordinator/records", "/coordinator/reports", "/coordinator/meetings"]:
        driver.get(BASE + href)
        time.sleep(0.35)
        spinning = disruptive_spinner()
        check(f"nav {href} no full-page spinner", not spinning, "spinner" if spinning else "")
        wait_ready(); time.sleep(0.4)

    # Faculty reports print options
    login("FAC-CHAS-001", "/faculty/")
    driver.get(BASE + "/faculty/reports")
    wait_ready(); time.sleep(1.0)
    check("faculty reports page", "Student Summary Report" in driver.page_source)
    shot("04-faculty-reports")

    # Faculty account has no coordinator switcher wait that's previous feature
    for href in ["/faculty/dashboard", "/faculty/assigned-students", "/faculty/documents"]:
        driver.get(BASE + href)
        time.sleep(0.3)
        check(f"faculty nav {href} no full-page spinner", not disruptive_spinner())

finally:
    driver.quit()

fails = [r for r in results if not r[1]]
print(f"\n{len(results)-len(fails)}/{len(results)} checks passed")
if fails:
    raise SystemExit(1)
