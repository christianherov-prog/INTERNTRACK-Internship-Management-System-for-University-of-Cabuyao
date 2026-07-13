# INTERNTRACK Unification Guide

## Common Layout

All internal pages use the same shell:

```html
<body class="page-body [role]-page">
  <aside class="sidebar">...</aside>
  <header class="topbar">...</header>
  <main class="main-content">...</main>
  <script src="script.js"></script>
</body>
```

The shared sidebar is fixed at `--sidebar-w` and the topbar is fixed at `--topbar-h`. Main content uses `margin-left: var(--sidebar-w)` on desktop and fills the screen on mobile.

## Sidebar

Use the same classes on every role page:

- `.sidebar`
- `.sidebar-brand`
- `.app-logo`, `.app-logo-img`, `.app-logo-text`
- `.sidebar-nav`
- `.nav-section-label`
- `.sidebar-link`
- `.sidebar-link.active`
- `.logout-link`

The active state is based on the link whose `href` matches the current filename. `script.js` also recalculates active links on load, so copied pages stay accurate after renaming.

## Topbar

Use this structure:

```html
<header class="topbar">
  <div class="topbar-left">
    <button type="button" class="btn-hamburger" id="sidebarToggle" aria-label="Open sidebar">
      <i class="fa fa-bars"></i>
    </button>
    <div class="topbar-page-icon"><i class="fa fa-chart-line"></i></div>
    <div class="topbar-title-group">
      <div class="topbar-title">Page Title</div>
      <div class="topbar-subtitle">AY 2024-2025, Sem 2</div>
    </div>
  </div>
  <div class="topbar-right">
    <span class="role-badge"><i class="fa fa-user-shield"></i> Role</span>
    <div class="topbar-avatar">IN</div>
  </div>
</header>
```

Logout belongs in the sidebar under `ACCOUNT`, not in the topbar.

## Components

Use these shared classes for consistent UI:

- Cards: `.content-card`, `.sp-card`, `.stat-card`, `.sp-stat-card`, `.settings-card`
- Tables: wrap tables in `.table-responsive` or `.sp-table-wrap`
- Status badges: `.badge-at-risk`, `.badge-on-track`, `.badge-completed`, `.badge-pending`
- Alternate status names: `.status-at-risk`, `.status-on-track`, `.status-completed`, `.status-pending`
- Footer: `.app-footer`

## Adding A New Page

1. Copy an existing page for the same role.
2. Update `<title>`, `.topbar-title`, `.topbar-page-icon`, and the body content.
3. Add the new sidebar link to the role menu in every page for that role.
4. Set only one link to `.active`; `script.js` will also correct it at runtime.
5. Keep `styles.css` and `script.js` linked.

## Batch Updating Pages

Run the included script from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\normalize-layout.ps1
```

The script normalizes all internal HTML pages in the root folder. It does not modify `index.html` or files inside `backup/`.

## Current Filename Map

The active project uses these filenames:

- Coordinator: `coord-monitoring.html`, `coord-announcements.html`, `coord-doc-approvals.html`, `coord-logbook-review.html`, `coord-records.html`, `coord-reports.html`, `coord-settings.html`
- Director: `director-dashboard.html`, `director-analytics.html`, `director-companies.html`, `director-moa-monitoring.html`, `director-reports.html`, `director-settings.html`
- Faculty: `faculty-dashboard.html`, `faculty-assigned-students.html`, `faculty-journals.html`, `faculty-evaluations.html`, `faculty-feedback.html`, `faculty-settings.html`
- Student: `student-dashboard.html`, `student-attendance.html`, `student-logbook.html`, `student-documents.html`, `student-evaluations.html`, `student-records.html`, `student-settings.html`
- Supervisor: `supervisor-dashboard.html`, `supervisor-assigned-interns.html`, `supervisor-attendance-validation.html`, `supervisor-journal-validation.html`, `supervisor-performance-evaluation.html`, `supervisor-notifications.html`, `supervisor-settings.html`
