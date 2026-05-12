# INTERNTRACK-Internship-Management-System-for-University-of-Cabuyao
INTERNTRACK is a web-based Internship Management System for University of Cabuyao that centralizes internship records, monitors deployment and status, handles documents, and improves coordination.
| DASHBOARDS | CONTAINS | DASHBOARD FLOW | SIDEBAR MENU |
|------------|-----------|----------------|--------------|
| **STUDENT INTERN DASHBOARD** | **A. Internship Progress**<br>• Completed Hours<br>• Remaining Hours<br>• Progress Bar<br>• Internship Status<br><br>**B. Journal Submission**<br>• Submit Journal<br>• Upload DTR<br>• View Journal Status<br><br>**C. Document Compliance**<br>• Upload Requirements<br>• View Missing Documents<br>• Check Approval Status<br><br>**D. Notifications**<br>• Missing Requirements<br>• Rejected Journal<br>• Upcoming Deadline<br><br>**E. Placement Information**<br>• Company Name<br>• Supervisor<br>• Deployment Status<br>• MOA Status | Student logs in<br>• views progress<br>• uploads journals/documents<br>• checks notifications<br>• monitors internship status | • Dashboard<br>• My Journals<br>• Documents<br>• Placement<br>• Evaluations<br>• Notifications<br>• Profile |
| **INTERNSHIP COORDINATOR DASHBOARD** | **A. Internship Progress**<br>• Completed Hours<br>• Remaining Hours<br>• Progress Bar<br>• Internship Status<br><br>**B. Journal Submission**<br>• Submit Journal<br>• Upload DTR<br>• View Journal Status<br><br>**C. Document Compliance**<br>• Upload Requirements<br>• View Missing Documents<br>• Check Approval Status<br><br>**D. Notifications**<br>• Missing Requirements<br>• Rejected Journal<br>• Upcoming Deadline<br><br>**E. Placement Information**<br>• Company Name<br>• Supervisor<br>• Deployment Status<br>• MOA Status | Coordinator logs in<br>• monitors students<br>• reviews submissions<br>• tracks deployment<br>• manages compliance<br>• generates reports | • Dashboard<br>• Students<br>• Placements<br>• Documents<br>• Journals<br>• Reports<br>• Analytics<br>• Settings |
| **FACULTY SUPERVISOR DASHBOARD** | **A. Assigned Students**<br>• Student List<br>• Internship Company<br>• Hours Rendered<br><br>**B. Journal Review**<br>• Review Journals<br>• Approve/Reject Entries<br>• Add Comments<br><br>**C. Student Monitoring**<br>• Track Progress<br>• View Missing Requirements<br>• Monitor Attendance<br><br>**D. Feedback Section**<br>• Send Feedback<br>• Performance Comments<br>• Recommendations<br><br>**E. Evaluation**<br>• Rate Students<br>• Submit Evaluation<br>• View Performance Summary | Faculty logs in<br>• checks assigned students<br>• reviews journals<br>• monitors progress<br>• gives feedback<br>• evaluates performance | • Dashboard<br>• Assigned Students<br>• Journals<br>• Evaluations<br>• Feedback |
| **INDUSTRY SUPERVISOR DASHBOARD** | **A. Assigned Interns**<br>• Intern Name<br>• Schedule<br>• Hours Rendered<br><br>**B. Attendance Validation**<br>• Approve Attendance<br>• Validate DTR<br>• Confirm Hours<br><br>**C. Journal Validation**<br>• Review Daily Logs<br>• Approve/Reject Entries<br><br>**D. Performance Evaluation**<br>• Rate Intern<br>• Add Comments<br>• Submit Evaluation<br><br>**E. Notifications**<br>• Pending Evaluations<br>• Missing Journals | Supervisor logs in<br>• validates attendance<br>• reviews journals<br>• evaluates intern | • Dashboard<br>• Interns<br>• Attendance<br>• Evaluations |
| **PALD DIRECTOR DASHBOARD** | **A. Internship Analytics**<br>• Total Interns<br>• Deployment Rate<br>• Completion Rate<br>• Active Companies<br><br>**B. Company Analytics**<br>• Most Used Companies<br>• Top Performing Companies<br>• Inactive Companies<br><br>**C. Student Analytics**<br>• At-Risk Students<br>• Completed Students<br>• Pending Students<br><br>**D. MOA Monitoring**<br>• Active MOA<br>• Expired MOA<br>• Pending Renewal<br><br>**E. Reports**<br>• Generate Reports<br>• Download Analytics<br>• Export Data | Director logs in<br>• views analytics<br>• monitors internship status<br>• checks reports<br>• tracks university internship performance | • Dashboard<br>• Analytics<br>• Companies<br>• MOA Monitoring<br>• Reports |


WEEKLY FEATURE DEVELOPMENT TABLE
| Week | Dates | Frontend Features (React + Bootstrap) | Backend Features (Laravel API) | Key Deliverable |
|------|--------|----------------------------------------|--------------------------------|----------------|
| **Week 1** | May 19–25 | App layout (Navbar, Sidebar), routing setup, dashboard template UI | Laravel setup, database setup, API structure, Sanctum install | System foundation ready |
| **Week 2** | May 26–June 1 | Login UI, Register UI, Forgot Password UI | Authentication API, role-based access (Student, Coordinator, Faculty, Industry, Director) | Login system working |
| **Week 3** | June 2–8 | Student Dashboard UI (cards, progress bar, stats, notifications) | Student profile API, user data endpoints | Student dashboard functional UI |
| **Week 4** | June 9–15 | Student Profile page, Journal UI, DTR upload UI | Journal API (create, read), file upload API | Student module system |
| **Week 5** | June 16–22 | Document Compliance UI (upload, status tracker), Placement UI | Document API, MOA validation, placement API | Compliance system |
| **Week 6** | June 23–29 | Coordinator Dashboard UI (monitoring tables, charts, student lists) | Student monitoring API, placement tracking API | Coordinator system |
| **Week 7** | June 30–July 6 | Faculty Dashboard UI (assigned students, journal review, evaluation forms) | Journal review API, evaluation API, feedback system | Faculty supervision system |
| **Week 8** | July 7–13 | Industry Dashboard UI (attendance, evaluation, validation tools) | Attendance validation API, industry evaluation API | Industry supervisor system |
| **Week 9** | July 14–19 | PALD Director Dashboard UI (analytics, charts, reports, overview panels) | Analytics API, reporting system, system optimization | Full system completion |



interntrack-client/
├── public/
│   ├── index.html
│   └── assets/                     # images, logos, icons
│
├── src/
│   ├── assets/
│   │   ├── images/
│   │   ├── icons/
│   │   └── styles/                 # global CSS overrides
│   │
│   ├── components/
│   │   ├── layout/
│   │   │   ├── Navbar.jsx
│   │   │   ├── Sidebar.jsx
│   │   │   └── Footer.jsx
│   │   │
│   │   ├── ui/
│   │   │   ├── Card.jsx
│   │   │   ├── Table.jsx
│   │   │   ├── Modal.jsx
│   │   │   ├── Button.jsx
│   │   │   └── Loader.jsx
│   │   │
│   │   └── charts/
│   │       ├── BarChart.jsx
│   │       ├── PieChart.jsx
│   │       └── LineChart.jsx
│   │
│   ├── pages/
│   │   ├── auth/
│   │   │   ├── Login.jsx
│   │   │   ├── Register.jsx
│   │   │   └── ForgotPassword.jsx
│   │   │
│   │   ├── student/
│   │   │   ├── StudentDashboard.jsx
│   │   │   ├── Profile.jsx
│   │   │   ├── Journals.jsx
│   │   │   ├── Documents.jsx
│   │   │   └── Placement.jsx
│   │   │
│   │   ├── coordinator/
│   │   │   ├── CoordinatorDashboard.jsx
│   │   │   ├── Students.jsx
│   │   │   ├── Placements.jsx
│   │   │   ├── Reports.jsx
│   │   │   └── Analytics.jsx
│   │   │
│   │   ├── faculty/
│   │   │   ├── FacultyDashboard.jsx
│   │   │   ├── AssignedStudents.jsx
│   │   │   ├── JournalReview.jsx
│   │   │   └── Evaluation.jsx
│   │   │
│   │   ├── industry/
│   │   │   ├── IndustryDashboard.jsx
│   │   │   ├── Interns.jsx
│   │   │   ├── Attendance.jsx
│   │   │   └── Evaluation.jsx
│   │   │
│   │   └── director/
│   │       ├── DirectorDashboard.jsx
│   │       ├── Analytics.jsx
│   │       ├── Companies.jsx
│   │       └── Reports.jsx
│   │
│   ├── routes/
│   │   └── AppRoutes.jsx
│   │
│   ├── services/
│   │   ├── api.js
│   │   ├── authService.js
│   │   ├── studentService.js
│   │   ├── journalService.js
│   │   └── documentService.js
│   │
│   ├── context/
│   │   └── AuthContext.jsx
│   │
│   ├── hooks/
│   │   └── useAuth.js
│   │
│   ├── utils/
│   │   ├── helpers.js
│   │   └── constants.js
│   │
│   ├── App.jsx
│   ├── main.jsx
│   └── index.css
│
├── package.json
└── vite.config.js

interntrack-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── StudentController.php
│   │   │   ├── JournalController.php
│   │   │   ├── DocumentController.php
│   │   │   ├── PlacementController.php
│   │   │   ├── EvaluationController.php
│   │   │   ├── NotificationController.php
│   │   │   └── ReportController.php
│   │   │
│   │   ├── Middleware/
│   │   │   └── RoleMiddleware.php
│   │   │
│   │   └── Requests/                # validation rules
│   │       ├── LoginRequest.php
│   │       ├── StudentRequest.php
│   │       └── JournalRequest.php
│   │
│   └── Models/
│       ├── User.php
│       ├── Student.php
│       ├── Journal.php
│       ├── Document.php
│       ├── Placement.php
│       ├── Evaluation.php
│       └── Company.php
│
├── database/
│   ├── migrations/
│   │   ├── create_users_table.php
│   │   ├── create_students_table.php
│   │   ├── create_journals_table.php
│   │   ├── create_documents_table.php
│   │   ├── create_placements_table.php
│   │   └── create_evaluations_table.php
│   │
│   └── seeders/
│       ├── UserSeeder.php
│       └── RoleSeeder.php
│
├── routes/
│   ├── api.php                      # ALL API routes
│   └── web.php
│
├── config/
│
├── storage/
│   └── app/
│       └── public/
│           ├── documents/
│           ├── journals/
│           └── profiles/
│
├── public/
│
├── tests/
│
├── .env
└── composer.json
