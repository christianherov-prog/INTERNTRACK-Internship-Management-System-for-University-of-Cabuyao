# INTERNTRACK - React.js Application

A complete internship management system converted from HTML to React.js, preserving all visual and functional aspects of the original application.

## Features

- **Multi-Role Support**: Student, Director, Supervisor, Faculty, Coordinator
- **Role-Based Routing**: Protected routes based on user roles
- **Responsive Design**: Fully responsive with Bootstrap 5
- **Authentication**: Session-based authentication with role detection
- **Dashboard**: Comprehensive dashboards for each role
- **Document Management**: Upload, review, and approve documents
- **Attendance Tracking**: Track and validate student attendance
- **Logbook/Journal**: Student daily journal entries with review workflow
- **Evaluations**: Performance evaluation system
- **Reports**: Generate various reports and analytics

## Project Structure

```
Interntrack-UI/
├── public/
│   └── logo.jpg              # Application logo
├── src/
│   ├── components/
│   │   ├── Layout.jsx        # Main layout component
│   │   ├── ProtectedRoute.jsx # Route protection
│   │   ├── Sidebar.jsx       # Navigation sidebar
│   │   └── Topbar.jsx        # Top navigation bar
│   ├── contexts/
│   │   └── AuthContext.jsx   # Authentication context
│   ├── pages/
│   │   ├── student/          # Student portal pages
│   │   ├── director/         # Director portal pages
│   │   ├── supervisor/       # Supervisor portal pages
│   │   ├── faculty/          # Faculty portal pages
│   │   ├── coordinator/      # Coordinator portal pages
│   │   └── LoginPage.jsx     # Login page
│   ├── styles/
│   │   ├── master-style.css  # Main stylesheet
│   │   ├── director-enhancements.css
│   │   ├── coordinator-fix.css
│   │   └── styles.css
│   ├── App.jsx               # Main app component with routes
│   └── main.jsx              # Entry point
├── index.html
├── package.json
├── vite.config.js
├── setup.bat                 # Windows setup script
└── README.md
```

## Installation

### 1. Install Dependencies

```bash
npm install
```

### 2. Setup Files

#### Option A: Run Setup Script (Windows)
```bash
setup.bat
```

#### Option B: Manual Setup
Create directories and copy files:

**Windows:**
```bash
mkdir src\styles
mkdir public
copy master-style.css src\styles\
copy director-enhancements.css src\styles\
copy coordinator-fix.css src\styles\
copy styles.css src\styles\
copy logo.jpg public\ 2>nul
```

**Linux/Mac:**
```bash
mkdir -p src/styles public
cp master-style.css src/styles/
cp director-enhancements.css src/styles/
cp coordinator-fix.css src/styles/
cp styles.css src/styles/
cp logo.jpg public/ 2>/dev/null || true
```

### 3. Start Development Server

```bash
npm run dev
```

The application will be available at `http://localhost:5173`

## Default Login Credentials

**Default Password for all accounts**: `interntrack123`

### Demo Accounts

| Role | ID | Description |
|------|-----|-------------|
| Student | `2021-00123` | Juan dela Cruz |
| Director | `DIR-001` | Dr. Patricia dela Rosa |
| Supervisor | `SUP-001` | Mr. David Reyes |
| Faculty | `FAC-001` | Prof. Andrea Reyes |
| Coordinator | `EMP-1001` | Maria Santos |

### ID Format Detection

The system automatically detects role based on ID format:

- **Student**: `20XX-XXXXX` (e.g., 2021-00123)
- **Director**: `DIR-XXX` (e.g., DIR-001)
- **Supervisor**: `SUP-XXX` (e.g., SUP-001)
- **Faculty**: `FAC-XXX` (e.g., FAC-001)
- **Coordinator**: `EMP-XXX`, `COORD-XXX`, or `ADMIN-XXX`

Any ID matching these patterns with the default password will work.

## Application Routes

### Public Routes
- `/` - Login Page

### Student Routes
- `/student/dashboard` - Student Dashboard
- `/student/attendance` - Attendance Records
- `/student/logbook` - Journal/Logbook
- `/student/documents` - Document Management
- `/student/evaluations` - Performance Evaluations
- `/student/records` - Academic Records
- `/student/settings` - Account Settings

### Director Routes
- `/director/dashboard` - Director Dashboard
- `/director/analytics` - Analytics Overview
- `/director/companies` - Partner Companies
- `/director/moa-monitoring` - MOA Monitoring
- `/director/reports` - Reports Generation
- `/director/settings` - Account Settings

### Supervisor Routes
- `/supervisor/dashboard` - Supervisor Dashboard
- `/supervisor/assigned-interns` - Assigned Interns
- `/supervisor/attendance-validation` - Attendance Validation
- `/supervisor/journal-validation` - Journal Review
- `/supervisor/performance-evaluation` - Performance Evaluations
- `/supervisor/notifications` - Notifications
- `/supervisor/settings` - Account Settings

### Faculty Routes
- `/faculty/dashboard` - Faculty Dashboard
- `/faculty/assigned-students` - Assigned Students
- `/faculty/journals` - Journal Reviews
- `/faculty/evaluations` - Student Evaluations
- `/faculty/feedback` - Student Feedback
- `/faculty/settings` - Account Settings

### Coordinator Routes
- `/coordinator/monitoring` - Progress Monitoring
- `/coordinator/announcements` - Announcements
- `/coordinator/doc-approvals` - Document Approvals
- `/coordinator/logbook-review` - Logbook Review
- `/coordinator/records` - Student Records
- `/coordinator/reports` - Reports
- `/coordinator/settings` - Account Settings

## Technologies Used

- **React 18** - UI library
- **React Router DOM 6** - Client-side routing
- **Vite** - Build tool and dev server
- **Bootstrap 5** - CSS framework
- **Font Awesome 6** - Icons

## Build for Production

```bash
npm run build
```

The production-ready files will be in the `dist/` folder.

## Preview Production Build

```bash
npm run preview
```

## Key Features Preserved

✓ All original HTML pages converted to React components
✓ Complete CSS preservation (master-style.css, director-enhancements.css, etc.)
✓ Bootstrap 5 classes and utilities unchanged
✓ Font Awesome icons preserved
✓ Role-based authentication and routing
✓ Responsive design maintained
✓ All animations and transitions preserved
✓ Canvas-based charts (Weekly OJT Hours chart)
✓ Interactive UI elements
✓ Session management
✓ Protected routes by role

## Development Notes

### Authentication

The application uses React Context API for authentication state management. The `AuthContext` provides:
- `user` - Current logged-in user object
- `login(userId, password)` - Login function
- `logout()` - Logout function
- `loading` - Loading state

### Routing

- Routes are protected using the `ProtectedRoute` component
- Unauthorized access redirects to login
- Wrong role access redirects to appropriate dashboard

### Styling

- All original CSS preserved in `src/styles/`
- Imported in order: master-style.css, director-enhancements.css, coordinator-fix.css, styles.css
- No CSS modules or styled-components used
- Bootstrap 5 CDN imported in index.html
- Font Awesome 6 CDN imported in index.html

### Components

- **Layout**: Wraps page content with Sidebar, Topbar, and Footer
- **Sidebar**: Dynamic navigation based on user role
- **Topbar**: Top navigation with role badge and avatar
- **ProtectedRoute**: Route guard for authentication and authorization

## Troubleshooting

### Issue: CSS not loading
**Solution**: Run `setup.bat` or manually copy CSS files to `src/styles/`

### Issue: Logo not showing
**Solution**: Copy `logo.jpg` to `public/` folder

### Issue: White screen after login
**Solution**: Check browser console for errors, ensure all CSS files are copied

### Issue: Cannot access certain pages
**Solution**: Verify you're logged in with the correct role

## Browser Support

- Chrome (recommended)
- Firefox
- Edge
- Safari

## License

© 2024-2025 INTERNTRACK - Internship Management System

## Contact

For questions or issues regarding this application, please contact the system administrator.
