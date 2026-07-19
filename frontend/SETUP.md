# INTERNTRACK React Setup

> Full stack setup (backend migrate, `storage:link`, env vars): see [`../SETUP.md`](../SETUP.md).

## Installation Steps

1. **Install Dependencies**
```bash
npm install
cp .env.example .env
```

Ensure `VITE_API_BASE_URL` matches your Laravel API origin (default `http://127.0.0.1:8001/api/v1`).

2. **Copy CSS Files to src/styles**
```bash
# Windows
copy master-style.css src\styles\
copy director-enhancements.css src\styles\
copy coordinator-fix.css src\styles\
copy styles.css src\styles\

# Linux/Mac
cp master-style.css src/styles/
cp director-enhancements.css src/styles/
cp coordinator-fix.css src/styles/
cp styles.css src/styles/
```

3. **Copy Logo to Public Folder**
```bash
# Windows  
copy logo.jpg public\

# Linux/Mac
cp logo.jpg public/
```

4. **Run Development Server**
```bash
npm run dev
```

## Default Login Credentials

- **Password for all users**: `interntrack123`

### Student Account
- **ID**: `2021-00123`

### Director Account
- **ID**: `DIR-001`

### Supervisor Account
- **ID**: `SUP-001`

### Faculty Account
- **ID**: `FAC-001`

### Coordinator Account
- **ID**: `EMP-1001`

## Routes

- `/` - Login Page
- `/student/*` - Student Portal
- `/director/*` - Director Portal
- `/supervisor/*` - Supervisor Portal
- `/faculty/*` - Faculty Portal
- `/coordinator/*` - Coordinator Portal

## Build for Production

```bash
npm run build
```

## Preview Production Build

```bash
npm run preview
```
