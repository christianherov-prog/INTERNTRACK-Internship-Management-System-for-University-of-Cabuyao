import { useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import Sidebar from './Sidebar'
import Topbar from './Topbar'
import { useCurrentTerm } from '../hooks/useCurrentTerm'
import { useStaffWorkspace } from '../hooks/useStaffWorkspace'

function Layout({ children, title, subtitle, icon, bodyClass = '' }) {
  const { user } = useAuth()
  const currentTerm = useCurrentTerm()
  const { workspace } = useStaffWorkspace()

  useEffect(() => {
    if (user) {
      // Body class follows the active workspace (coordinators can use the
      // Faculty Supervisor workspace) so existing per-role CSS keeps working.
      document.body.className = `page-body ${workspace || user.role}-page ${bodyClass}`.trim()
    }

    return () => {
      document.body.classList.remove('sidebar-open')
    }
  }, [user, workspace, bodyClass])

  return (
    <>
      <Sidebar />
      <div className="sidebar-overlay" onClick={() => document.body.classList.remove('sidebar-open')} />
      <Topbar title={title} subtitle={subtitle} icon={icon} />
      <main className="main-content">
        {children}
        <footer className="app-footer">
          &copy; {new Date().getFullYear()} InternTrack <span>{currentTerm}</span>
        </footer>
      </main>
    </>
  )
}

export default Layout
