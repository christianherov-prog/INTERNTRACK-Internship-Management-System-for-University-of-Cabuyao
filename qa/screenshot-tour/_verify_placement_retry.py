import time
from pathlib import Path
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = "http://localhost:5173"
opts = Options()
opts.add_argument("--headless=new")
opts.add_argument("--window-size=1440,900")
opts.binary_location = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
driver = webdriver.Chrome(service=Service(str(Path(__file__).parent / "drivers" / "chromedriver.exe")), options=opts)
wait = WebDriverWait(driver, 30)
OUT = Path(__file__).parent / "output" / "placement-enhance"
OUT.mkdir(parents=True, exist_ok=True)

def login(user, hint):
    driver.get(BASE + "/")
    driver.execute_script("try { sessionStorage.clear(); localStorage.clear(); } catch(e) {}")
    driver.get(BASE + "/")
    wait.until(EC.visibility_of_element_located((By.ID, "studentNumber")))
    driver.find_element(By.ID, "studentNumber").send_keys(user)
    driver.find_element(By.ID, "password").send_keys("interntrack123")
    driver.find_element(By.CSS_SELECTOR, '#loginForm button.btn-signin[type="submit"]').click()
    wait.until(lambda d: hint in (d.current_url or ""))
    time.sleep(1.0)

try:
    login("2300600", "/student/")
    driver.get(BASE + "/student/companies")
    wait.until(lambda d: "Eligible Companies" in d.page_source or "Placement Hub" in d.page_source)
    time.sleep(2.5)
    print("STUDENT apply buttons", len(driver.find_elements(By.CSS_SELECTOR, 'button[id^="apply-company-"]')))
    print("STUDENT has TechCorp", "TechCorp" in driver.page_source)
    driver.save_screenshot(str(OUT / "01b-student.png"))

    login("COR-CCS-001", "/coordinator/")
    driver.get(BASE + "/coordinator/internship-management")
    time.sleep(3.0)
    print("COORD has TechCorp", "TechCorp" in driver.page_source)
    print("COORD Approve", "Approve" in driver.page_source)
    print("COORD pending", "PENDING" in driver.page_source.upper())
    driver.save_screenshot(str(OUT / "03b-coord.png"))
finally:
    driver.quit()
