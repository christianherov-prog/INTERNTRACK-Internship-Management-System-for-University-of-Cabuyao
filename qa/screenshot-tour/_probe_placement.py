import requests
API = "http://127.0.0.1:8001/api/v1"

def login(u):
    r = requests.post(f"{API}/auth/login", json={"username": u, "password": "interntrack123"})
    print(u, r.status_code)
    r.raise_for_status()
    return r.json()["token"]

tok = login("2300592")
h = {"Authorization": f"Bearer {tok}"}
print("companies", requests.get(f"{API}/student/companies", headers=h).json().get("companies", []))
print("apps", requests.get(f"{API}/student/applications", headers=h).json())
tokc = login("COR-CCS-001")
hc = {"Authorization": f"Bearer {tokc}"}
apps = requests.get(f"{API}/coordinator/applications", headers=hc).json().get("applications", [])
print("coord apps", len(apps), [(a.get("id"), a.get("status"), (a.get("company") or {}).get("company_name"), a.get("student", {}).get("student_number")) for a in apps[:8]])
