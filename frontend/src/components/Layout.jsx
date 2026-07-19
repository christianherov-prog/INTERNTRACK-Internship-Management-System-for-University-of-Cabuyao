import { useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

function Layout({ children, title, subtitle, icon, bodyClass = '' }) {
  const { user } = useAuth()

  useEffect(() => {
    if (user) {
      document.body.className = `page-body ${user.role}-page ${bodyClass}`.trim()
    }

    const overlay = document.querySelector('.sidebar-overlay')
    if (!overlay) {
      const newOverlay = document.createElement('div')
      newOverlay.className = 'sidebar-overlay'
      document.body.appendChild(newOverlay)
    }

    return () => {
      document.body.classList.remove('sidebar-open')
    }
  }, [user, bodyClass])

  return (
    <>
      <Sidebar />
      <Topbar title={title} subtitle={subtitle} icon={icon} />
      <main className="main-content">
        {children}
        <footer className="app-footer">
          &copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span>
        </footer>
      </main>
    </>
  )
}

export default Layout
