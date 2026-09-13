import { Navigate, Route, Routes } from 'react-router-dom'
import DemoShell from './DemoShell'
import DemoHome from './DemoHome'
import DemoPlaceholder from './DemoPlaceholder'

export default function DemoApp() {
  return (
    <Routes>
      <Route element={<DemoShell />}>
        <Route index element={<DemoHome />} />
        <Route path="dashboard" element={<DemoPlaceholder />} />
        <Route path="attendance" element={<DemoPlaceholder />} />
        <Route path="logbook" element={<DemoPlaceholder />} />
        <Route path="documents" element={<DemoPlaceholder />} />
        <Route path="evaluations" element={<DemoPlaceholder />} />
        <Route path="analytics" element={<DemoPlaceholder />} />
        <Route path="invite-supervisor" element={<DemoPlaceholder />} />
        <Route path="portfolio" element={<DemoPlaceholder />} />
        <Route path="reports" element={<DemoPlaceholder />} />
        <Route path="manage-requirements" element={<DemoPlaceholder />} />
        <Route path="doc-approvals" element={<DemoPlaceholder />} />
        <Route path="logbook-review" element={<DemoPlaceholder />} />
        <Route path="settings" element={<DemoPlaceholder />} />
        <Route path="*" element={<Navigate to="/demo" replace />} />
      </Route>
    </Routes>
  )
}
