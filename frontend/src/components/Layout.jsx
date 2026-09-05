import { useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import Sidebar from './Sidebar'
import Topbar from './Topbar'
import { useCurrentTerm } from '../hooks/useCurrentTerm'

function Layout({ children, title, subtitle, icon, bodyClass = '' }) {
  const { user } = useAuth()
  const currentTerm = useCurrentTerm()

  useEffect(() => {
    if (user) {
      document.body.className = `page-body ${user.role}-page ${bodyClass}`.trim()
    }

    return () => {
      document.body.classList.remove('sidebar-open')
    }
  }, [user, bodyClass])

  return (
    <>
      <Sidebar />
      <div className="sidebar-overlay" onClick={() => document.body.classList.remove('sidebar-open')} />
      <Topbar title={title} subtitle={subtitle} icon={icon} />
      <main className="main-content">
        {children}
        <footer className="app-footer">
          &copy; {new Date().getFullYear()} INTERNTRACK <span>{currentTerm}</span>
        </footer>
      </main>
    </>
  )
}

export default Layout
