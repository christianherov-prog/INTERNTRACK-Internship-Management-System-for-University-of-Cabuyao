# Director Pages - Implementation Guide by Page

## Overview

This guide provides specific implementation recommendations for each director page type. Use these as templates when updating your existing director pages.

---

## 1. Director Dashboard

**Purpose**: High-level overview of all internship programs and key metrics

**Key Components**:
- KPI stat cards (Active Internships, Partner Companies, Completion Rate, Average Rating)
- Performance trend chart
- Recent alerts/notifications
- Quick action buttons

### Recommended Structure

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Director Dashboard - InternTrack</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="director-enhancements.css">
</head>
<body class="page-body director-dashboard">
  
  <div class="sidebar"><!-- Existing sidebar --></div>
  <header class="topbar"><!-- Existing topbar --></header>
  
  <main class="main-content director-page">
    
    <!-- Page Header -->
    <div style="margin-bottom: 2.2rem;">
      <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.4rem;">
        Dashboard Overview
      </h1>
      <p style="color: #7da488; font-size: 0.95rem;">
        Real-time analytics and performance metrics for all internship programs
      </p>
    </div>
    
    <!-- KPI Cards Grid (3 columns) -->
    <h3 style="font-size: 1.05rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      <i class="fas fa-trending-up"></i> Key Metrics
    </h3>
    <div class="director-analytics-grid">
      <div class="director-stat-card">
        <div class="director-stat-value">156</div>
        <div class="director-stat-label">Active Internships</div>
        <div class="director-stat-change positive">
          <i class="fas fa-arrow-up"></i> 12% vs last month
        </div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">48</div>
        <div class="director-stat-label">Partner Companies</div>
        <div class="director-stat-change positive">
          <i class="fas fa-arrow-up"></i> 5 new this month
        </div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">4.6/5</div>
        <div class="director-stat-label">Average Rating</div>
        <div class="director-stat-change positive">
          <i class="fas fa-arrow-up"></i> Excellent performance
        </div>
      </div>
    </div>
    
    <!-- Main Chart Section -->
    <div style="margin-top: 2rem; margin-bottom: 2rem;">
      <div class="director-chart-panel">
        <h3>Program Performance (6-Month Trend)</h3>
        <p class="chart-subtitle">Active internships, completion rate, and satisfaction scores</p>
        
        <div style="height: 300px; background: #f8fcfd; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #aaa;">
          [Chart Placeholder - Multi-line chart]
        </div>
      </div>
    </div>
    
    <!-- Alerts Section -->
    <h3 style="font-size: 1.05rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      <i class="fas fa-bell"></i> Recent Alerts
    </h3>
    
    <div class="director-alert warning">
      <i class="fas fa-exclamation-triangle"></i>
      <div class="director-alert-content">
        <strong>Performance Alert</strong>
        3 interns have performance scores below 3.0. Supervisor follow-up recommended.
      </div>
    </div>
    
    <div class="director-alert info">
      <i class="fas fa-info-circle"></i>
      <div class="director-alert-content">
        <strong>New MOA Pending</strong>
        2 companies awaiting MOA approval. Review required for final processing.
      </div>
    </div>
    
    <!-- Quick Actions -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
      <button class="director-btn-primary">
        <i class="fas fa-file-export"></i> Generate Monthly Report
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-chart-bar"></i> View Analytics
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-cog"></i> Dashboard Settings
      </button>
    </div>
    
  </main>
  
  <script src="script.js"></script>
</body>
</html>
```

---

## 2. Director Analytics

**Purpose**: Detailed analytics with filters and multiple data views

**Key Components**:
- Advanced filter section (4+ filters)
- Navigation tabs (Overview, Details, Trends)
- Data table with sortable columns
- Performance metrics

### Recommended Structure

```html
<!-- Same head as dashboard -->

<body class="page-body director-analytics">
  
  <div class="sidebar"><!-- Existing --></div>
  <header class="topbar"><!-- Existing --></header>
  
  <main class="main-content director-page">
    
    <!-- Page Header -->
    <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.3rem;">
      Analytics & Insights
    </h1>
    <p style="color: #7da488; margin-bottom: 1.5rem; font-size: 0.95rem;">
      In-depth analysis of internship programs, performance trends, and engagement metrics
    </p>
    
    <!-- Filter Section -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      <i class="fas fa-filter"></i> Filters
    </h3>
    <div class="director-filter-section">
      <div class="director-filter-item">
        <label class="director-filter-label">Status</label>
        <select class="director-filter-select">
          <option>All Statuses</option>
          <option>Active</option>
          <option>Completed</option>
          <option>At Risk</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Department</label>
        <select class="director-filter-select">
          <option>All Departments</option>
          <option>IT</option>
          <option>HR</option>
          <option>Finance</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Date Range</label>
        <select class="director-filter-select">
          <option>Last 30 Days</option>
          <option>Last 90 Days</option>
          <option>Last Year</option>
          <option>Custom</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Performance Level</label>
        <select class="director-filter-select">
          <option>All Levels</option>
          <option>Excellent (4.5-5)</option>
          <option>Good (3.5-4.4)</option>
          <option>Satisfactory (2.5-3.4)</option>
        </select>
      </div>
    </div>
    
    <!-- Navigation Tabs -->
    <div class="director-nav-tabs" style="margin-top: 2rem; margin-bottom: 1.5rem;">
      <button class="director-nav-tab active">
        <i class="fas fa-eye"></i> Overview
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-table"></i> Detailed View
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-chart-line"></i> Trends
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-download"></i> Export
      </button>
    </div>
    
    <!-- Summary Stats -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      Summary
    </h3>
    <div class="director-analytics-grid">
      <div class="director-stat-card">
        <div class="director-stat-value">142</div>
        <div class="director-stat-label">Filtered Results</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">4.2</div>
        <div class="director-stat-label">Avg Performance</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">89%</div>
        <div class="director-stat-label">Completion Rate</div>
      </div>
    </div>
    
    <!-- Data Table -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Internship Records
    </h3>
    <table class="director-data-table">
      <thead>
        <tr>
          <th>Intern ID</th>
          <th>Name</th>
          <th>Company</th>
          <th>Department</th>
          <th>Status</th>
          <th>Performance</th>
          <th>Progress</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>#IN-001</td>
          <td>John Anderson</td>
          <td>Tech Corp</td>
          <td>Engineering</td>
          <td><span class="director-badge success"><i class="fas fa-check"></i> Active</span></td>
          <td>4.8/5.0</td>
          <td>
            <div class="director-progress-bar">
              <div class="director-progress-fill" style="width: 85%"></div>
            </div>
          </td>
        </tr>
        <!-- More rows -->
      </tbody>
    </table>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
      <button class="director-btn-primary">
        <i class="fas fa-file-export"></i> Export Data
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-print"></i> Print Report
      </button>
    </div>
    
  </main>
  
  <script src="script.js"></script>
</body>
```

---

## 3. Director Reports

**Purpose**: Pre-built and custom reports with generation and export capabilities

**Key Components**:
- Report type selector
- Report parameters/configuration
- Generated report display
- Export options

### Recommended Structure

```html
<!-- Same head as dashboard -->

<body class="page-body director-reports">
  
  <div class="sidebar"><!-- Existing --></div>
  <header class="topbar"><!-- Existing --></header>
  
  <main class="main-content director-page">
    
    <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.3rem;">
      Reports & Documentation
    </h1>
    <p style="color: #7da488; margin-bottom: 1.5rem; font-size: 0.95rem;">
      Generate and review comprehensive reports on internship programs, performance, and partnerships
    </p>
    
    <!-- Report Selector Tabs -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      Select Report Type
    </h3>
    <div class="director-nav-tabs">
      <button class="director-nav-tab active">
        <i class="fas fa-chart-bar"></i> Performance Report
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-users"></i> Intern Summary
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-handshake"></i> Company MOA
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-calendar"></i> Schedule Report
      </button>
    </div>
    
    <!-- Report Configuration -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 1.5rem;">
      Report Configuration
    </h3>
    <div class="director-filter-section">
      <div class="director-filter-item">
        <label class="director-filter-label">Date Range</label>
        <select class="director-filter-select">
          <option>Last Month</option>
          <option>Last Quarter</option>
          <option>This Year</option>
          <option>Custom</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Department Filter</label>
        <select class="director-filter-select">
          <option>All Departments</option>
          <option>IT</option>
          <option>HR</option>
          <option>Finance</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Include</label>
        <select class="director-filter-select">
          <option>All Data</option>
          <option>Active Only</option>
          <option>Completed Only</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Format</label>
        <select class="director-filter-select">
          <option>PDF</option>
          <option>Excel</option>
          <option>CSV</option>
        </select>
      </div>
    </div>
    
    <!-- Key Metrics Preview -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Report Summary
    </h3>
    <div class="director-analytics-grid">
      <div class="director-stat-card">
        <div class="director-stat-value">156</div>
        <div class="director-stat-label">Total Records</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">4.5</div>
        <div class="director-stat-label">Avg Rating</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">92%</div>
        <div class="director-stat-label">Completion</div>
      </div>
    </div>
    
    <!-- Report Preview -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Report Preview
    </h3>
    <div class="director-chart-panel">
      <h3>Performance Distribution</h3>
      <p class="chart-subtitle">Breakdown of intern performance ratings</p>
      
      <div style="height: 300px; background: #f8fcfd; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #aaa;">
        [Chart Placeholder]
      </div>
    </div>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
      <button class="director-btn-primary">
        <i class="fas fa-refresh"></i> Generate Report
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-download"></i> Download
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-print"></i> Print
      </button>
    </div>
    
  </main>
  
  <script src="script.js"></script>
</body>
```

---

## 4. Director MOA Monitoring

**Purpose**: Track and manage memoranda of agreement (MOA) with partner companies

**Key Components**:
- MOA status overview
- Company partnership statistics
- MOA expiration alerts
- Document management

### Recommended Structure

```html
<!-- Same head as dashboard -->

<body class="page-body director-moa-monitoring">
  
  <div class="sidebar"><!-- Existing --></div>
  <header class="topbar"><!-- Existing --></header>
  
  <main class="main-content director-page">
    
    <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.3rem;">
      MOA Monitoring & Management
    </h1>
    <p style="color: #7da488; margin-bottom: 1.5rem; font-size: 0.95rem;">
      Track and manage memoranda of agreement with all partner companies
    </p>
    
    <!-- MOA Status Cards -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      Partnership Overview
    </h3>
    <div class="director-analytics-grid">
      <div class="director-stat-card">
        <div class="director-stat-value">48</div>
        <div class="director-stat-label">Active MOAs</div>
        <div class="director-stat-change positive">
          <i class="fas fa-check"></i> All Current
        </div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">5</div>
        <div class="director-stat-label">Expiring Soon</div>
        <div class="director-stat-change warning">
          <i class="fas fa-clock"></i> &lt; 90 days
        </div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">2</div>
        <div class="director-stat-label">Pending Approval</div>
        <div class="director-stat-change warning">
          <i class="fas fa-hourglass"></i> Action needed
        </div>
      </div>
    </div>
    
    <!-- Alerts -->
    <div class="director-alert warning" style="margin-top: 1.5rem;">
      <i class="fas fa-exclamation-triangle"></i>
      <div class="director-alert-content">
        <strong>MOA Expiration Alert</strong>
        Tech Corp Inc. MOA expires in 30 days. Renewal process should begin immediately.
      </div>
    </div>
    
    <!-- Filter & Navigation -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      MOA Status Filters
    </h3>
    <div class="director-filter-section">
      <div class="director-filter-item">
        <label class="director-filter-label">Status</label>
        <select class="director-filter-select">
          <option>All Status</option>
          <option>Active</option>
          <option>Pending</option>
          <option>Expired</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Industry</label>
        <select class="director-filter-select">
          <option>All Industries</option>
          <option>Technology</option>
          <option>Finance</option>
          <option>Healthcare</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Expiration</label>
        <select class="director-filter-select">
          <option>All Dates</option>
          <option>Expires &lt; 30 days</option>
          <option>Expires 30-90 days</option>
          <option>Expires &gt; 90 days</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Sort By</label>
        <select class="director-filter-select">
          <option>Expiration Date</option>
          <option>Company Name</option>
          <option>Date Created</option>
        </select>
      </div>
    </div>
    
    <!-- MOA Table -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Partnership Agreements
    </h3>
    <table class="director-data-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Industry</th>
          <th>Status</th>
          <th>Expiration Date</th>
          <th>Days Remaining</th>
          <th>Interns</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Tech Corp Inc.</td>
          <td>Technology</td>
          <td><span class="director-badge success">Active</span></td>
          <td>2024-02-15</td>
          <td>30</td>
          <td>12</td>
          <td>
            <button class="director-btn-secondary" style="padding: 0.4rem 0.7rem; font-size: 0.75rem;">
              <i class="fas fa-edit"></i> Renew
            </button>
          </td>
        </tr>
        <!-- More rows -->
      </tbody>
    </table>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
      <button class="director-btn-primary">
        <i class="fas fa-plus"></i> Add New MOA
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-file-download"></i> Download MOAs
      </button>
    </div>
    
  </main>
  
  <script src="script.js"></script>
</body>
```

---

## 5. Director Companies

**Purpose**: Manage partner company information and relationships

**Key Components**:
- Company search/filter
- Company listing with details
- Company status indicators
- Contact and MOA information

### Recommended Structure

```html
<!-- Same head as dashboard -->

<body class="page-body director-companies">
  
  <div class="sidebar"><!-- Existing --></div>
  <header class="topbar"><!-- Existing --></header>
  
  <main class="main-content director-page">
    
    <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.3rem;">
      Partner Companies
    </h1>
    <p style="color: #7da488; margin-bottom: 1.5rem; font-size: 0.95rem;">
      Manage and monitor all partner companies and their internship programs
    </p>
    
    <!-- Company Stats -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem;">
      Partnership Overview
    </h3>
    <div class="director-analytics-grid">
      <div class="director-stat-card">
        <div class="director-stat-value">48</div>
        <div class="director-stat-label">Total Companies</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">42</div>
        <div class="director-stat-label">Active Partners</div>
      </div>
      
      <div class="director-stat-card">
        <div class="director-stat-value">156</div>
        <div class="director-stat-label">Current Interns</div>
      </div>
    </div>
    
    <!-- Search & Filter -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Search & Filter
    </h3>
    <div class="director-filter-section">
      <div class="director-filter-item">
        <label class="director-filter-label">Industry</label>
        <select class="director-filter-select">
          <option>All Industries</option>
          <option>Technology</option>
          <option>Finance</option>
          <option>Healthcare</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Status</label>
        <select class="director-filter-select">
          <option>All Status</option>
          <option>Active</option>
          <option>Inactive</option>
          <option>Prospective</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Region</label>
        <select class="director-filter-select">
          <option>All Regions</option>
          <option>Metro Manila</option>
          <option>North Luzon</option>
          <option>South Luzon</option>
        </select>
      </div>
      
      <div class="director-filter-item">
        <label class="director-filter-label">Sort By</label>
        <select class="director-filter-select">
          <option>Company Name</option>
          <option>Number of Interns</option>
          <option>Date Added</option>
        </select>
      </div>
    </div>
    
    <!-- Company Table -->
    <h3 style="font-size: 1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1rem; margin-top: 2rem;">
      Company Directory
    </h3>
    <table class="director-data-table">
      <thead>
        <tr>
          <th>Company Name</th>
          <th>Industry</th>
          <th>Status</th>
          <th>Interns</th>
          <th>Rating</th>
          <th>Contact</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Tech Corp Inc.</strong></td>
          <td>Technology</td>
          <td><span class="director-badge success">Active</span></td>
          <td>12</td>
          <td>4.8/5.0</td>
          <td>
            <a href="mailto:hr@techcorp.com" style="color: #1a5f94; text-decoration: none;">
              <i class="fas fa-envelope"></i> Email
            </a>
          </td>
          <td>
            <button class="director-btn-secondary" style="padding: 0.4rem 0.7rem; font-size: 0.75rem;">
              <i class="fas fa-eye"></i> View
            </button>
          </td>
        </tr>
        <!-- More rows -->
      </tbody>
    </table>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
      <button class="director-btn-primary">
        <i class="fas fa-plus"></i> Add Company
      </button>
      <button class="director-btn-secondary">
        <i class="fas fa-file-import"></i> Import Companies
      </button>
    </div>
    
  </main>
  
  <script src="script.js"></script>
</body>
```

---

## 6. Director Settings

**Purpose**: Configure director-level preferences and system settings

**Key Components**:
- Settings tabs (General, Notifications, Preferences)
- Form controls and toggles
- Save/Reset buttons
- Settings groups

### Recommended Structure

```html
<!-- Same head as dashboard -->

<body class="page-body director-settings">
  
  <div class="sidebar"><!-- Existing --></div>
  <header class="topbar"><!-- Existing --></header>
  
  <main class="main-content director-page">
    
    <h1 style="font-size: 1.8rem; font-weight: 800; color: #1a5f94; margin-bottom: 0.3rem;">
      Settings & Preferences
    </h1>
    <p style="color: #7da488; margin-bottom: 1.5rem; font-size: 0.95rem;">
      Manage your director profile, notifications, and system preferences
    </p>
    
    <!-- Settings Tabs -->
    <div class="director-nav-tabs">
      <button class="director-nav-tab active">
        <i class="fas fa-user"></i> Profile
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-bell"></i> Notifications
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-sliders-h"></i> Preferences
      </button>
      <button class="director-nav-tab">
        <i class="fas fa-lock"></i> Security
      </button>
    </div>
    
    <!-- Settings Content -->
    <section style="margin-top: 2rem;">
      <!-- Profile Settings Section -->
      <h3 style="font-size: 1.1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1.2rem;">
        Director Profile
      </h3>
      
      <form style="max-width: 600px;">
        <div style="margin-bottom: 1.5rem;">
          <label style="display: block; font-weight: 700; color: #1a5f94; margin-bottom: 0.5rem;">
            Full Name
          </label>
          <input type="text" value="Dr. Maria Santos" 
                 class="director-filter-input"
                 style="width: 100%;">
        </div>
        
        <div style="margin-bottom: 1.5rem;">
          <label style="display: block; font-weight: 700; color: #1a5f94; margin-bottom: 0.5rem;">
            Email Address
          </label>
          <input type="email" value="maria.santos@university.edu"
                 class="director-filter-input"
                 style="width: 100%;">
        </div>
        
        <div style="margin-bottom: 1.5rem;">
          <label style="display: block; font-weight: 700; color: #1a5f94; margin-bottom: 0.5rem;">
            Department
          </label>
          <select class="director-filter-select" style="width: 100%;">
            <option>College of Engineering</option>
            <option>College of Business</option>
            <option>College of Science</option>
          </select>
        </div>
        
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <button type="button" class="director-btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
          <button type="button" class="director-btn-secondary">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>
      
      <!-- Notifications Section -->
      <h3 style="font-size: 1.1rem; font-weight: 700; color: #1a5f94; margin-bottom: 1.2rem; margin-top: 3rem;">
        Notification Settings
      </h3>
      
      <div style="max-width: 600px;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #e6f2f9; margin-bottom: 1rem;">
          <div>
            <strong style="color: #1a5f94;">Performance Alerts</strong>
            <p style="color: #7da488; font-size: 0.85rem; margin: 0.3rem 0 0;">Notify when intern performance drops below threshold</p>
          </div>
          <label style="position: relative; display: inline-block; width: 50px; height: 24px;">
            <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #1a5f94; border-radius: 24px; transition: 0.3s;"></span>
          </label>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #e6f2f9; margin-bottom: 1rem;">
          <div>
            <strong style="color: #1a5f94;">New MOA Requests</strong>
            <p style="color: #7da488; font-size: 0.85rem; margin: 0.3rem 0 0;">Notify of new partnership requests</p>
          </div>
          <label style="position: relative; display: inline-block; width: 50px; height: 24px;">
            <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #1a5f94; border-radius: 24px; transition: 0.3s;"></span>
          </label>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; margin-bottom: 1rem;">
          <div>
            <strong style="color: #1a5f94;">Weekly Reports</strong>
            <p style="color: #7da488; font-size: 0.85rem; margin: 0.3rem 0 0;">Receive weekly system summaries</p>
          </div>
          <label style="position: relative; display: inline-block; width: 50px; height: 24px;">
            <input type="checkbox" style="opacity: 0; width: 0; height: 0;">
            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; border-radius: 24px; transition: 0.3s;"></span>
          </label>
        </div>
      </div>
      
      <!-- Save Button -->
      <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem;">
        <button class="director-btn-primary">
          <i class="fas fa-save"></i> Save Settings
        </button>
      </div>
    </section>
    
  </main>
  
  <script src="script.js"></script>
</body>
```

---

## General Implementation Notes

### For All Pages:

1. **Include Enhancement CSS**: Add the `<link>` tag to every director page
2. **Use Director Classes**: Apply `.director-page` to the main content container
3. **Maintain Consistency**: Use the same components across all pages
4. **Test Responsiveness**: Verify layout works on mobile, tablet, desktop
5. **Validate Accessibility**: Ensure all interactive elements are keyboard accessible

### Customization Points:

- Modify `/root` CSS variables for colors
- Adjust grid column numbers in media queries
- Add page-specific styles in `<style>` tags
- Enhance with JavaScript for interactivity

### Performance Optimization:

- Lazy-load large charts using IntersectionObserver
- Minimize HTTP requests
- Use CSS containment on large components
- Optimize image assets

---

**Next Steps**: 
1. Copy the structure for your specific director page
2. Replace placeholder content with actual data
3. Test layout and responsiveness
4. Verify all interactive elements work correctly
5. Deploy to production
