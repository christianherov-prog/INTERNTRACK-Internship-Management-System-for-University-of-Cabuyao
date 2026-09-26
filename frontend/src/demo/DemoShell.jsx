import { useEffect } from 'react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import InternTrackLogo from '../components/InternTrackLogo'
import { CCS_DEMO } from './ccsMockData'
import { DEMO_MODULES } from './demoModules'

export default function DemoShell() {
  useEffect(() => {
    const previous = document.body.className
    document.body.className = 'page-body demo-page'
    return () => {
      document.body.className = previous
    }
  }, [])

  return (
    <>
      <aside className="sidebar" style={{ width: '270px' }}>
        <div className="sidebar-brand">
          <InternTrackLogo
            variant="dark"
            showSubtitle
            className="app-logo"
            markClassName="app-logo-mark"
            subtitleClassName="app-logo-sub"
          />
        </div>
        <nav className="sidebar-nav">
          <div className="nav-section-label">DEMO</div>
          <NavLink to="/demo" end className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-layer-group"></i>
            <span>Demo hub</span>
          </NavLink>
          <div className="nav-section-label">MODULES</div>
          {DEMO_MODULES.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
            >
              <i className={`fa ${item.icon}`}></i>
              <span>{item.text}</span>
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="sidebar-overlay" onClick={() => document.body.classList.remove('sidebar-open')} />
      <header className="topbar">
        <div className="topbar-left">
          <button
            type="button"
            className="btn btn-link d-lg-none p-0 me-1"
            onClick={() => document.body.classList.toggle('sidebar-open')}
            aria-label="Open menu"
          >
            <i className="fa fa-bars"></i>
          </button>
          <div className="topbar-page-icon"><i className="fa fa-flask"></i></div>
          <div className="topbar-title-group">
            <div className="topbar-title">InternTrack demo</div>
            <div className="topbar-subtitle">{CCS_DEMO.term} · {CCS_DEMO.department.code} only · no login</div>
          </div>
        </div>
        <Link to="/" className="btn btn-sm btn-outline-secondary">Live app login</Link>
      </header>
      <main className="main-content">
        <Outlet />
      </main>
    </>
  )
}
