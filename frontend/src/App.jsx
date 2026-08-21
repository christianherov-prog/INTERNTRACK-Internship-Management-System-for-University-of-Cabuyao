import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { ToastProvider } from './contexts/ToastContext'
import { ConfirmProvider } from './contexts/ConfirmContext'
import ErrorBoundary from './components/ErrorBoundary'
import AccessDeniedOverlay from './components/AccessDeniedOverlay'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import StudentDashboard from './pages/student/StudentDashboard'
import StudentAttendanceHub from './pages/student/StudentAttendanceHub'
import StudentLogbook from './pages/student/StudentLogbook'
import StudentDocuments from './pages/student/StudentDocuments'
import PortfolioBuilder from './pages/student/portfolio/PortfolioBuilder'
import PortfolioPreview from './pages/student/portfolio/PortfolioPreview'
import StudentEvaluations from './pages/student/StudentEvaluations'

import StudentRecords from './pages/student/StudentRecords'
import StudentSettings from './pages/student/StudentSettings'
import StudentCompanies from './pages/student/StudentCompanies'

import DirectorDashboard from './pages/director/DirectorDashboard'
import DirectorCompanies from './pages/director/DirectorCompanies'
import DirectorMoaHub from './pages/director/DirectorMoaHub'
import DirectorReports from './pages/director/DirectorReports'
import DirectorSettings from './pages/director/DirectorSettings'
import SupervisorDashboard from './pages/supervisor/SupervisorDashboard'
import SupervisorAssignedInterns from './pages/supervisor/SupervisorAssignedInterns'
import SupervisorAttendanceValidation from './pages/supervisor/SupervisorAttendanceValidation'
import FacultyFeedback from './pages/faculty/FacultyFeedback'
import CoordDocApprovals from './pages/coordinator/CoordDocApprovals'
import CoordLogbookReview from './pages/coordinator/CoordLogbookReview'
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
import FacultyDocuments from './pages/faculty/FacultyDocuments'
import FacultyReports from './pages/faculty/FacultyReports'
import FacultySettings from './pages/faculty/FacultySettings'
import DirectorInternships from './pages/director/DirectorInternships'
import DirectorAbsorption from './pages/director/DirectorAbsorption'
import DirectorHTEEvaluations from './pages/director/DirectorHTEEvaluations'
import CoordMonitoring from './pages/coordinator/CoordMonitoring'
import CoordAnnouncements from './pages/coordinator/CoordAnnouncements'
import CoordRecords from './pages/coordinator/CoordRecords'
import CoordReports from './pages/coordinator/CoordReports'
import CoordSettings from './pages/coordinator/CoordSettings'
import ManageRequirements from './pages/shared/ManageRequirementsTemplates'
import CoordPlacementHub from './pages/coordinator/CoordPlacementHub'
import CoordSupervisorApprovals from './pages/coordinator/CoordSupervisorApprovals'
import CoordAbsorption from './pages/coordinator/CoordAbsorption'
import MeetingsPage from './pages/shared/MeetingsPage'
import StudentSupervisorInvite from './pages/student/StudentSupervisorInvite'
import MisdDashboard from './pages/admin/MisdDashboard'
import MisdDirectors from './pages/admin/MisdDirectors'
import MisdCoordinators from './pages/admin/MisdCoordinators'
import MisdUsers from './pages/admin/MisdUsers'
import MisdSectionMappings from './pages/admin/MisdSectionMappings'
import MisdSyncMonitor from './pages/admin/MisdSyncMonitor'
import MisdSettings from './pages/admin/MisdSettings'
import SupervisorRegisterPage from './pages/public/SupervisorRegisterPage'
import ChangePasswordConfirmPage from './pages/public/ChangePasswordConfirmPage'
import StudentMessages from './pages/student/StudentMessages'
import SupervisorMessages from './pages/supervisor/SupervisorMessages'
import FacultyMessages from './pages/faculty/FacultyMessages'
import CoordMessages from './pages/coordinator/CoordMessages'
import DirectorMessages from './pages/director/DirectorMessages'

function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <ToastProvider>
          <ConfirmProvider>
            <AccessDeniedOverlay />
            <BrowserRouter>
            <Routes>
              <Route path="/" element={<LoginPage />} />
              <Route path="/register/supervisor" element={<SupervisorRegisterPage />} />
              <Route path="/change-password-confirm" element={<ChangePasswordConfirmPage />} />

              <Route path="/student/dashboard" element={<ProtectedRoute role="student"><StudentDashboard /></ProtectedRoute>} />
              <Route path="/student/attendance" element={<ProtectedRoute role="student"><StudentAttendanceHub /></ProtectedRoute>} />
              <Route path="/student/logbook" element={<ProtectedRoute role="student"><StudentLogbook /></ProtectedRoute>} />
              <Route path="/student/documents" element={<ProtectedRoute role="student"><StudentDocuments /></ProtectedRoute>} />
              <Route path="/student/portfolio" element={<ProtectedRoute role="student"><PortfolioBuilder /></ProtectedRoute>} />
              <Route path="/student/companies" element={<ProtectedRoute role="student"><StudentCompanies /></ProtectedRoute>} />
              <Route path="/student/portfolio/preview" element={<ProtectedRoute role="student"><PortfolioPreview /></ProtectedRoute>} />
              <Route path="/student/evaluations" element={<ProtectedRoute allowedRoles={['student']}><StudentEvaluations /></ProtectedRoute>} />


              <Route path="/student/records" element={<ProtectedRoute role="student"><StudentRecords /></ProtectedRoute>} />
              <Route path="/student/messages" element={<ProtectedRoute role="student"><StudentMessages /></ProtectedRoute>} />
              <Route path="/student/meetings" element={<ProtectedRoute role="student"><MeetingsPage bodyClass="student-page" /></ProtectedRoute>} />
              <Route path="/student/settings" element={<ProtectedRoute role="student"><StudentSettings /></ProtectedRoute>} />

              <Route path="/director/dashboard" element={<ProtectedRoute role="director"><DirectorDashboard /></ProtectedRoute>} />
              <Route path="/director/analytics" element={<Navigate to="/director/dashboard" replace />} />
              <Route path="/director/companies" element={<ProtectedRoute role="director"><DirectorCompanies /></ProtectedRoute>} />
              <Route path="/director/moa" element={<ProtectedRoute role="director"><DirectorMoaHub /></ProtectedRoute>} />
              <Route path="/director/reports" element={<ProtectedRoute role="director"><DirectorReports /></ProtectedRoute>} />
              <Route path="/director/hte-evaluations" element={<ProtectedRoute role="director"><DirectorHTEEvaluations /></ProtectedRoute>} />
              <Route path="/director/internships" element={<ProtectedRoute role="director"><DirectorInternships /></ProtectedRoute>} />
              <Route path="/director/absorption" element={<ProtectedRoute role="director"><DirectorAbsorption /></ProtectedRoute>} />
              <Route path="/director/messages" element={<ProtectedRoute role="director"><DirectorMessages /></ProtectedRoute>} />
              <Route path="/director/announcements" element={<ProtectedRoute role="director"><CoordAnnouncements apiBase="/director" bodyClass="director-page" /></ProtectedRoute>} />
              <Route path="/director/meetings" element={<ProtectedRoute role="director"><MeetingsPage bodyClass="director-page" canCreate /></ProtectedRoute>} />
              <Route path="/director/settings" element={<ProtectedRoute role="director"><DirectorSettings /></ProtectedRoute>} />

              <Route path="/supervisor/dashboard" element={<ProtectedRoute role="supervisor"><SupervisorDashboard /></ProtectedRoute>} />
              <Route path="/supervisor/assigned-interns" element={<ProtectedRoute role="supervisor"><SupervisorAssignedInterns /></ProtectedRoute>} />
              <Route path="/supervisor/attendance-validation" element={<ProtectedRoute role="supervisor"><SupervisorAttendanceValidation /></ProtectedRoute>} />
              {/* Journal validation removed from supervisor role — faculty handles all journal reviews.
              <Route path="/supervisor/journal-validation" element={<ProtectedRoute role="supervisor"><SupervisorJournalValidation /></ProtectedRoute>} />
              */}
              <Route path="/supervisor/performance-evaluation" element={<ProtectedRoute role="supervisor"><SupervisorPerformanceEvaluation /></ProtectedRoute>} />
              <Route path="/supervisor/absorption" element={<ProtectedRoute role="supervisor"><SupervisorAbsorption /></ProtectedRoute>} />
              <Route path="/supervisor/feedback" element={<ProtectedRoute role="supervisor"><SupervisorFeedback /></ProtectedRoute>} />
              <Route path="/supervisor/notifications" element={<ProtectedRoute role="supervisor"><SupervisorNotifications /></ProtectedRoute>} />
              <Route path="/supervisor/messages" element={<ProtectedRoute role="supervisor"><SupervisorMessages /></ProtectedRoute>} />
              <Route path="/supervisor/meetings" element={<ProtectedRoute role="supervisor"><MeetingsPage bodyClass="supervisor-page" /></ProtectedRoute>} />
              <Route path="/supervisor/settings" element={<ProtectedRoute role="supervisor"><SupervisorSettings /></ProtectedRoute>} />

              <Route path="/faculty/dashboard" element={<ProtectedRoute role="faculty"><FacultyDashboard /></ProtectedRoute>} />
              <Route path="/faculty/assigned-students" element={<ProtectedRoute role="faculty"><FacultyAssignedStudents /></ProtectedRoute>} />
              <Route path="/faculty/journals" element={<ProtectedRoute role="faculty"><FacultyJournals /></ProtectedRoute>} />
              <Route path="/faculty/evaluations" element={<ProtectedRoute role="faculty"><FacultyEvaluations /></ProtectedRoute>} />
              <Route path="/faculty/requirements" element={<ProtectedRoute role="faculty"><ManageRequirements /></ProtectedRoute>} />
              <Route path="/faculty/documents" element={<ProtectedRoute role="faculty"><FacultyDocuments /></ProtectedRoute>} />
              <Route path="/faculty/feedback" element={<ProtectedRoute role="faculty"><FacultyFeedback /></ProtectedRoute>} />
              <Route path="/faculty/reports" element={<ProtectedRoute role="faculty"><FacultyReports /></ProtectedRoute>} />
              <Route path="/faculty/messages" element={<ProtectedRoute role="faculty"><FacultyMessages /></ProtectedRoute>} />
              <Route path="/faculty/meetings" element={<ProtectedRoute role="faculty"><MeetingsPage bodyClass="faculty-page" canCreate /></ProtectedRoute>} />
              <Route path="/faculty/supervisor-approvals" element={<ProtectedRoute role="faculty"><CoordSupervisorApprovals apiBase="/faculty" bodyClass="faculty-page" /></ProtectedRoute>} />
              <Route path="/faculty/settings" element={<ProtectedRoute role="faculty"><FacultySettings /></ProtectedRoute>} />

              <Route path="/coordinator/monitoring" element={<ProtectedRoute role="coordinator"><CoordMonitoring /></ProtectedRoute>} />
              <Route path="/coordinator/internship-management" element={<ProtectedRoute role="coordinator"><CoordPlacementHub /></ProtectedRoute>} />
              <Route path="/coordinator/announcements" element={<ProtectedRoute role="coordinator"><CoordAnnouncements /></ProtectedRoute>} />
              <Route path="/coordinator/records" element={<ProtectedRoute role="coordinator"><CoordRecords /></ProtectedRoute>} />
              <Route path="/coordinator/absorption" element={<ProtectedRoute role="coordinator"><CoordAbsorption /></ProtectedRoute>} />
              <Route path="/coordinator/doc-approvals" element={<ProtectedRoute role="coordinator"><CoordDocApprovals /></ProtectedRoute>} />
              <Route path="/coordinator/logbook" element={<ProtectedRoute role="coordinator"><CoordLogbookReview /></ProtectedRoute>} />
              <Route path="/coordinator/reports" element={<ProtectedRoute role="coordinator"><CoordReports /></ProtectedRoute>} />
              <Route path="/coordinator/evaluations" element={<ProtectedRoute role="coordinator"><CoordEvaluations /></ProtectedRoute>} />
              <Route path="/coordinator/messages" element={<ProtectedRoute role="coordinator"><CoordMessages /></ProtectedRoute>} />
              <Route path="/coordinator/meetings" element={<ProtectedRoute role="coordinator"><MeetingsPage bodyClass="coordinator-page" canCreate /></ProtectedRoute>} />
              <Route path="/coordinator/requirements" element={<ProtectedRoute role="coordinator"><ManageRequirements /></ProtectedRoute>} />
              <Route path="/coordinator/settings" element={<ProtectedRoute role="coordinator"><CoordSettings /></ProtectedRoute>} />

              <Route path="/admin/dashboard" element={<ProtectedRoute role="admin"><MisdDashboard /></ProtectedRoute>} />
              <Route path="/admin/directors" element={<ProtectedRoute role="admin"><MisdDirectors /></ProtectedRoute>} />
              <Route path="/admin/coordinators" element={<ProtectedRoute role="admin"><MisdCoordinators /></ProtectedRoute>} />
              <Route path="/admin/users" element={<ProtectedRoute role="admin"><MisdUsers /></ProtectedRoute>} />
              <Route path="/admin/section-mappings" element={<ProtectedRoute role="admin"><MisdSectionMappings /></ProtectedRoute>} />
                <Route path="/supervisor/performance-evaluation" element={<ProtectedRoute role="supervisor"><SupervisorPerformanceEvaluation /></ProtectedRoute>} />
                <Route path="/supervisor/absorption" element={<ProtectedRoute role="supervisor"><SupervisorAbsorption /></ProtectedRoute>} />
                <Route path="/supervisor/feedback" element={<ProtectedRoute role="supervisor"><SupervisorFeedback /></ProtectedRoute>} />
                <Route path="/supervisor/notifications" element={<ProtectedRoute role="supervisor"><SupervisorNotifications /></ProtectedRoute>} />
                <Route path="/supervisor/messages" element={<ProtectedRoute role="supervisor"><SupervisorMessages /></ProtectedRoute>} />
                <Route path="/supervisor/meetings" element={<ProtectedRoute role="supervisor"><MeetingsPage bodyClass="supervisor-page" /></ProtectedRoute>} />
                <Route path="/supervisor/settings" element={<ProtectedRoute role="supervisor"><SupervisorSettings /></ProtectedRoute>} />

                <Route path="/faculty/dashboard" element={<ProtectedRoute role="faculty"><FacultyDashboard /></ProtectedRoute>} />
                <Route path="/faculty/assigned-students" element={<ProtectedRoute role="faculty"><FacultyAssignedStudents /></ProtectedRoute>} />
                <Route path="/faculty/journals" element={<ProtectedRoute role="faculty"><FacultyJournals /></ProtectedRoute>} />
                <Route path="/faculty/evaluations" element={<ProtectedRoute role="faculty"><FacultyEvaluations /></ProtectedRoute>} />
                <Route path="/faculty/requirements" element={<ProtectedRoute role="faculty"><ManageRequirements /></ProtectedRoute>} />
                <Route path="/faculty/documents" element={<ProtectedRoute role="faculty"><FacultyDocuments /></ProtectedRoute>} />
                <Route path="/faculty/feedback" element={<ProtectedRoute role="faculty"><FacultyFeedback /></ProtectedRoute>} />
                <Route path="/faculty/reports" element={<ProtectedRoute role="faculty"><FacultyReports /></ProtectedRoute>} />
                <Route path="/faculty/messages" element={<ProtectedRoute role="faculty"><FacultyMessages /></ProtectedRoute>} />
                <Route path="/faculty/meetings" element={<ProtectedRoute role="faculty"><MeetingsPage bodyClass="faculty-page" canCreate /></ProtectedRoute>} />
                <Route path="/faculty/supervisor-approvals" element={<ProtectedRoute role="faculty"><CoordSupervisorApprovals apiBase="/faculty" bodyClass="faculty-page" /></ProtectedRoute>} />
                <Route path="/faculty/settings" element={<ProtectedRoute role="faculty"><FacultySettings /></ProtectedRoute>} />

                <Route path="/coordinator/monitoring" element={<ProtectedRoute role="coordinator"><CoordMonitoring /></ProtectedRoute>} />
                <Route path="/coordinator/internship-management" element={<ProtectedRoute role="coordinator"><CoordPlacementHub /></ProtectedRoute>} />
                <Route path="/coordinator/announcements" element={<ProtectedRoute role="coordinator"><CoordAnnouncements /></ProtectedRoute>} />
                <Route path="/coordinator/records" element={<ProtectedRoute role="coordinator"><CoordRecords /></ProtectedRoute>} />
                <Route path="/coordinator/absorption" element={<ProtectedRoute role="coordinator"><CoordAbsorption /></ProtectedRoute>} />
                <Route path="/coordinator/doc-approvals" element={<ProtectedRoute role="coordinator"><CoordDocApprovals /></ProtectedRoute>} />
                <Route path="/coordinator/logbook" element={<ProtectedRoute role="coordinator"><CoordLogbookReview /></ProtectedRoute>} />
                <Route path="/coordinator/reports" element={<ProtectedRoute role="coordinator"><CoordReports /></ProtectedRoute>} />
                <Route path="/coordinator/evaluations" element={<ProtectedRoute role="coordinator"><CoordEvaluations /></ProtectedRoute>} />
                <Route path="/coordinator/messages" element={<ProtectedRoute role="coordinator"><CoordMessages /></ProtectedRoute>} />
                <Route path="/coordinator/meetings" element={<ProtectedRoute role="coordinator"><MeetingsPage bodyClass="coordinator-page" canCreate /></ProtectedRoute>} />
                <Route path="/coordinator/requirements" element={<ProtectedRoute role="coordinator"><ManageRequirements /></ProtectedRoute>} />
                <Route path="/coordinator/settings" element={<ProtectedRoute role="coordinator"><CoordSettings /></ProtectedRoute>} />

                <Route path="/admin/dashboard" element={<ProtectedRoute role="admin"><MisdDashboard /></ProtectedRoute>} />
                <Route path="/admin/directors" element={<ProtectedRoute role="admin"><MisdDirectors /></ProtectedRoute>} />
                <Route path="/admin/coordinators" element={<ProtectedRoute role="admin"><MisdCoordinators /></ProtectedRoute>} />
                <Route path="/admin/users" element={<ProtectedRoute role="admin"><MisdUsers /></ProtectedRoute>} />
                <Route path="/admin/section-mappings" element={<ProtectedRoute role="admin"><MisdSectionMappings /></ProtectedRoute>} />
                <Route path="/admin/sync" element={<ProtectedRoute role="admin"><MisdSyncMonitor /></ProtectedRoute>} />
                <Route path="/admin/settings" element={<ProtectedRoute role="admin"><MisdSettings /></ProtectedRoute>} />

                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </BrowserRouter>
          </ConfirmProvider>
        </ToastProvider>
      </AuthProvider>
    </ErrorBoundary>
  )
}

export default App
