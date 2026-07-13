import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const PAGE_META = {
  '/': { title: 'Dashboard', icon: 'fa-tachometer-alt' },
  '/attendance': { title: 'Attendance', icon: 'fa-calendar-check' },
  '/logbook': { title: 'Logbook', icon: 'fa-book-open' },
  '/documents': { title: 'Documents', icon: 'fa-file-alt' },
  '/evaluations': { title: 'Evaluations', icon: 'fa-star' },
  '/settings': { title: 'Settings', icon: 'fa-cog' },
};

function initialsOf(name) {
  return (name || '')
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase() || 'ST';
}

export default function StudentLayout() {
  const { student, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const meta = PAGE_META[location.pathname] || PAGE_META['/'];

  // The prototype CSS targets body.page-body / .student-page, so mirror the
  // original <body class="page-body student-page"> on the real body element.
  useEffect(() => {
    document.body.classList.add('page-body', 'student-page');
    return () => document.body.classList.remove('page-body', 'student-page', 'sidebar-open');
  }, []);

  useEffect(() => {
    document.body.classList.toggle('sidebar-open', sidebarOpen);
  }, [sidebarOpen]);

  // Close the mobile sidebar when navigating.
  useEffect(() => {
    setSidebarOpen(false);
  }, [location.pathname]);

  const handleLogout = async (e) => {
    e.preventDefault();
    await logout();
    navigate('/login');
  };

  return (
    <>
      {/* SIDEBAR */}
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="app-logo">
            <img src="/logo.png" alt="Logo" className="app-logo-img" />
            <div className="app-logo-text">
              <div className="app-logo-main">INTERNTRACK</div>
              <div className="app-logo-sub">INTERNSHIP SYSTEM</div>
            </div>
          </div>
        </div>
        <nav className="sidebar-nav">
          <div className="nav-section-label">MAIN</div>
          <NavLink to="/" end className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-tachometer-alt"></i><span>Dashboard</span>
          </NavLink>
          <NavLink to="/attendance" className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-calendar-check"></i><span>Attendance</span>
          </NavLink>
          <NavLink to="/logbook" className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-book-open"></i><span>Logbook</span>
          </NavLink>
          <NavLink to="/documents" className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-file-alt"></i><span>Documents</span>
          </NavLink>
          <NavLink to="/evaluations" className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-star"></i><span>Evaluations</span>
          </NavLink>
          <div className="nav-section-label">ACCOUNT</div>
          <NavLink to="/settings" className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}>
            <i className="fa fa-cog"></i><span>Settings</span>
          </NavLink>
          <a href="/login" className="sidebar-link logout-link" onClick={handleLogout}>
            <i className="fa fa-sign-out-alt"></i><span>Logout</span>
          </a>
        </nav>
      </aside>

      {/* TOPBAR */}
      <header className="topbar">
        <div className="topbar-left">
          <button
            type="button"
            className="btn-hamburger"
            aria-label="Open sidebar"
            onClick={() => setSidebarOpen((open) => !open)}
          >
            <i className="fa fa-bars"></i>
          </button>
          <div className="topbar-page-icon"><i className={`fa ${meta.icon}`}></i></div>
          <div className="topbar-title-group">
            <div className="topbar-title">{meta.title}</div>
            <div className="topbar-subtitle">AY 2024-2025, Sem 2</div>
          </div>
        </div>
        <div className="topbar-right">
          <span className="role-badge"><i className="fa fa-user-shield"></i> Student</span>
          <div className="topbar-avatar" title="Student profile">{initialsOf(student?.full_name)}</div>
        </div>
      </header>

      <div className="sidebar-overlay" onClick={() => setSidebarOpen(false)}></div>

      <Outlet />
    </>
  );
}
