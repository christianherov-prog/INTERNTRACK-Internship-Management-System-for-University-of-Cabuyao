# INTERNTRACK React - Quick Start Guide

## ⚡ Get Started in 3 Steps

### Step 1: Install Dependencies (2 minutes)
```bash
cd C:\Users\Hero\OneDrive\Desktop\Interntrack-UI
npm install
```

### Step 2: Copy Files (30 seconds)
**Windows - Run this:**
```bash
setup.bat
```

**Linux/Mac - Run these commands:**
```bash
mkdir -p src/styles public
cp master-style.css src/styles/
cp director-enhancements.css src/styles/
cp coordinator-fix.css src/styles/
cp styles.css src/styles/
cp logo.jpg public/ 2>/dev/null || true
```

### Step 3: Start Application (10 seconds)
```bash
npm run dev
```

Visit: **http://localhost:5173**

---

## 🔑 Test Login

Use these credentials to test:

| Role | ID | Password |
|------|-----|----------|
| Student | `2021-00123` | `interntrack123` |
| Director | `DIR-001` | `interntrack123` |
| Supervisor | `SUP-001` | `interntrack123` |
| Faculty | `FAC-001` | `interntrack123` |
| Coordinator | `EMP-1001` | `interntrack123` |

---

## ✅ What to Check

After logging in, verify:

1. **Navigation** - Sidebar links work correctly
2. **Dashboard** - Statistics cards display
3. **Charts** - Canvas chart renders (Student Dashboard)
4. **Tables** - Data tables display correctly
5. **Forms** - Input fields and buttons work
6. **Responsive** - Resize browser window
7. **Logout** - Logout button works

---

## 🛠️ Build for Production

```bash
npm run build
npm run preview
```

---

## ❓ Troubleshooting

**Issue: Blank page after npm run dev**
- Solution: Run `setup.bat` or manually copy CSS files

**Issue: Logo not showing**
- Solution: Copy `logo.jpg` to `public/` folder

**Issue: Cannot login**
- Solution: Use exact IDs and password from table above

**Issue: CSS looks broken**
- Solution: Verify all 4 CSS files are in `src/styles/`

---

## 📚 Need More Info?

- **Full Documentation**: See `README.md`
- **Setup Details**: See `SETUP.md`
- **Conversion Summary**: See `COMPLETION_SUMMARY.md`

---

**Ready to go! 🚀**
