import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { ToastProvider } from './contexts/ToastContext'
import ErrorBoundary from './components/ErrorBoundary'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import StudentDashboard from './pages/student/StudentDashboard'
import StudentAttendance from './pages/student/StudentAttendance'
import StudentLogbook from './pages/student/StudentLogbook'
import StudentDocuments from './pages/student/StudentDocuments'
import StudentEvaluations from './pages/student/StudentEvaluations'
import StudentRecords from './pages/student/StudentRecords'
import StudentSettings from './pages/student/StudentSettings'
import PortfolioBuilder from './pages/student/portfolio/PortfolioBuilder'
import PortfolioPreview from './pages/student/portfolio/PortfolioPreview'
import DirectorDashboard from './pages/director/DirectorDashboard'
import DirectorCompanies from './pages/director/DirectorCompanies'
import DirectorMOAMonitoring from './pages/director/DirectorMOAMonitoring'
import DirectorReports from './pages/director/DirectorReports'
import DirectorSettings from './pages/director/DirectorSettings'
import SupervisorDashboard from './pages/supervisor/SupervisorDashboard'
import SupervisorAssignedInterns from './pages/supervisor/SupervisorAssignedInterns'
import SupervisorAttendanceValidation from './pages/supervisor/SupervisorAttendanceValidation'
import SupervisorJournalValidation from './pages/supervisor/SupervisorJournalValidation'
import SupervisorPerformanceEvaluation from './pages/supervisor/SupervisorPerformanceEvaluation'
import SupervisorAbsorption from './pages/supervisor/SupervisorAbsorption'
import SupervisorFeedback from './pages/supervisor/SupervisorFeedback'
import SupervisorNotifications from './pages/supervisor/SupervisorNotifications'
import SupervisorSettings from './pages/supervisor/SupervisorSettings'
import CoordEvaluations from './pages/coordinator/CoordEvaluations'
import FacultyDashboard from './pages/faculty/FacultyDashboard'
import FacultyAssignedStudents from './pages/faculty/FacultyAssignedStudents'
import FacultyJournals from './pages/faculty/FacultyJournals'
import FacultyEvaluations from './pages/faculty/FacultyEvaluations'
import FacultyFeedback from './pages/faculty/FacultyFeedback'
import FacultyDocuments from './pages/faculty/FacultyDocuments'
import FacultyAttendance from './pages/faculty/FacultyAttendance'
import FacultySettings from './pages/faculty/FacultySettings'
import DirectorInternships from './pages/director/DirectorInternships'
import CoordMonitoring from './pages/coordinator/CoordMonitoring'
import CoordAnnouncements from './pages/coordinator/CoordAnnouncements'
import CoordDocApprovals from './pages/coordinator/CoordDocApprovals'
import CoordLogbookReview from './pages/coordinator/CoordLogbookReview'
import CoordRecords from './pages/coordinator/CoordRecords'
import CoordAbsorption from './pages/coordinator/CoordAbsorption'
import CoordReports from './pages/coordinator/CoordReports'
import CoordSettings from './pages/coordinator/CoordSettings'
import CoordSupervisorApprovals from './pages/coordinator/CoordSupervisorApprovals'
import DirectorAbsorption from './pages/director/DirectorAbsorption'
import StudentSupervisorInvite from './pages/student/StudentSupervisorInvite'
import SupervisorRegisterPage from './pages/public/SupervisorRegisterPage'
import StudentMessages from './pages/student/StudentMessages'
import SupervisorMessages from './pages/supervisor/SupervisorMessages'
import FacultyMessages from './pages/faculty/FacultyMessages'
import CoordMessages from './pages/coordinator/CoordMessages'

function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <ToastProvider>
          <BrowserRouter>
            <Routes>
          <Route path="/" element={<LoginPage />} />
          <Route path="/register/supervisor" element={<SupervisorRegisterPage />} />
          
          <Route path="/student/dashboard" element={<ProtectedRoute role="student"><StudentDashboard /></ProtectedRoute>} />
          <Route path="/student/attendance" element={<ProtectedRoute role="student"><StudentAttendance /></ProtectedRoute>} />
          <Route path="/student/logbook" element={<ProtectedRoute role="student"><StudentLogbook /></ProtectedRoute>} />
          <Route path="/student/documents" element={<ProtectedRoute role="student"><StudentDocuments /></ProtectedRoute>} />
          <Route path="/student/evaluations" element={<ProtectedRoute role="student"><StudentEvaluations /></ProtectedRoute>} />
          <Route path="/student/portfolio" element={<ProtectedRoute role="student"><PortfolioBuilder /></ProtectedRoute>} />
          <Route path="/student/portfolio/preview" element={<ProtectedRoute role="student"><PortfolioPreview /></ProtectedRoute>} />
          <Route path="/student/records" element={<ProtectedRoute role="student"><StudentRecords /></ProtectedRoute>} />
          <Route path="/student/messages" element={<ProtectedRoute role="student"><StudentMessages /></ProtectedRoute>} />
          <Route path="/student/supervisor-invite" element={<ProtectedRoute role="student"><StudentSupervisorInvite /></ProtectedRoute>} />
          <Route path="/student/settings" element={<ProtectedRoute role="student"><StudentSettings /></ProtectedRoute>} />
          
          <Route path="/director/dashboard" element={<ProtectedRoute role="director"><DirectorDashboard /></ProtectedRoute>} />
          <Route path="/director/analytics" element={<Navigate to="/director/dashboard" replace />} />
          <Route path="/director/companies" element={<ProtectedRoute role="director"><DirectorCompanies /></ProtectedRoute>} />
          <Route path="/director/moa-monitoring" element={<ProtectedRoute role="director"><DirectorMOAMonitoring /></ProtectedRoute>} />
          <Route path="/director/reports" element={<ProtectedRoute role="director"><DirectorReports /></ProtectedRoute>} />
          <Route path="/director/internships" element={<ProtectedRoute role="director"><DirectorInternships /></ProtectedRoute>} />
          <Route path="/director/absorption" element={<ProtectedRoute role="director"><DirectorAbsorption /></ProtectedRoute>} />
          <Route path="/director/settings" element={<ProtectedRoute role="director"><DirectorSettings /></ProtectedRoute>} />
          
          <Route path="/supervisor/dashboard" element={<ProtectedRoute role="supervisor"><SupervisorDashboard /></ProtectedRoute>} />
          <Route path="/supervisor/assigned-interns" element={<ProtectedRoute role="supervisor"><SupervisorAssignedInterns /></ProtectedRoute>} />
          <Route path="/supervisor/attendance-validation" element={<ProtectedRoute role="supervisor"><SupervisorAttendanceValidation /></ProtectedRoute>} />
          <Route path="/supervisor/journal-validation" element={<ProtectedRoute role="supervisor"><SupervisorJournalValidation /></ProtectedRoute>} />
          <Route path="/supervisor/performance-evaluation" element={<ProtectedRoute role="supervisor"><SupervisorPerformanceEvaluation /></ProtectedRoute>} />
          <Route path="/supervisor/absorption" element={<ProtectedRoute role="supervisor"><SupervisorAbsorption /></ProtectedRoute>} />
          <Route path="/supervisor/feedback" element={<ProtectedRoute role="supervisor"><SupervisorFeedback /></ProtectedRoute>} />
          <Route path="/supervisor/notifications" element={<ProtectedRoute role="supervisor"><SupervisorNotifications /></ProtectedRoute>} />
          <Route path="/supervisor/messages" element={<ProtectedRoute role="supervisor"><SupervisorMessages /></ProtectedRoute>} />
          <Route path="/supervisor/settings" element={<ProtectedRoute role="supervisor"><SupervisorSettings /></ProtectedRoute>} />
          
          <Route path="/faculty/dashboard" element={<ProtectedRoute role="faculty"><FacultyDashboard /></ProtectedRoute>} />
          <Route path="/faculty/assigned-students" element={<ProtectedRoute role="faculty"><FacultyAssignedStudents /></ProtectedRoute>} />
          <Route path="/faculty/journals" element={<ProtectedRoute role="faculty"><FacultyJournals /></ProtectedRoute>} />
          <Route path="/faculty/evaluations" element={<ProtectedRoute role="faculty"><FacultyEvaluations /></ProtectedRoute>} />
          <Route path="/faculty/feedback" element={<ProtectedRoute role="faculty"><FacultyFeedback /></ProtectedRoute>} />
          <Route path="/faculty/documents" element={<ProtectedRoute role="faculty"><FacultyDocuments /></ProtectedRoute>} />
          <Route path="/faculty/attendance" element={<ProtectedRoute role="faculty"><FacultyAttendance /></ProtectedRoute>} />
          <Route path="/faculty/messages" element={<ProtectedRoute role="faculty"><FacultyMessages /></ProtectedRoute>} />
          <Route path="/faculty/settings" element={<ProtectedRoute role="faculty"><FacultySettings /></ProtectedRoute>} />
          
          <Route path="/coordinator/monitoring" element={<ProtectedRoute role="coordinator"><CoordMonitoring /></ProtectedRoute>} />
          <Route path="/coordinator/announcements" element={<ProtectedRoute role="coordinator"><CoordAnnouncements /></ProtectedRoute>} />
          <Route path="/coordinator/doc-approvals" element={<ProtectedRoute role="coordinator"><CoordDocApprovals /></ProtectedRoute>} />
          <Route path="/coordinator/logbook-review" element={<ProtectedRoute role="coordinator"><CoordLogbookReview /></ProtectedRoute>} />
          <Route path="/coordinator/records" element={<ProtectedRoute role="coordinator"><CoordRecords /></ProtectedRoute>} />
          <Route path="/coordinator/absorption" element={<ProtectedRoute role="coordinator"><CoordAbsorption /></ProtectedRoute>} />
          <Route path="/coordinator/reports" element={<ProtectedRoute role="coordinator"><CoordReports /></ProtectedRoute>} />
          <Route path="/coordinator/evaluations" element={<ProtectedRoute role="coordinator"><CoordEvaluations /></ProtectedRoute>} />
          <Route path="/coordinator/messages" element={<ProtectedRoute role="coordinator"><CoordMessages /></ProtectedRoute>} />
          <Route path="/coordinator/supervisor-approvals" element={<ProtectedRoute role="coordinator"><CoordSupervisorApprovals /></ProtectedRoute>} />
          <Route path="/coordinator/settings" element={<ProtectedRoute role="coordinator"><CoordSettings /></ProtectedRoute>} />
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
          </BrowserRouter>
        </ToastProvider>
      </AuthProvider>
    </ErrorBoundary>
  )
}

export default App
