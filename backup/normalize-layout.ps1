$roleMenus = @{
  coord = @{ Role='Coordinator'; Avatar='CO'; Body='coordinator-page'; Items=@(
    @('MAIN','coord-monitoring.html','fa-chart-line','Dashboard'), @($null,'coord-announcements.html','fa-bullhorn','Announcements'), @($null,'coord-doc-approvals.html','fa-file-circle-check','Document Approvals'), @($null,'coord-logbook-review.html','fa-book-open','Logbook Review'), @($null,'coord-records.html','fa-folder-open','Records'), @($null,'coord-reports.html','fa-chart-bar','Reports'), @('ACCOUNT','coord-settings.html','fa-cog','Settings'), @($null,'index.html','fa-sign-out-alt','Logout')) }
  director = @{ Role='Director'; Avatar='PD'; Body='director-page'; Items=@(
    @('MAIN','director-dashboard.html','fa-chart-pie','Dashboard'), @($null,'director-analytics.html','fa-chart-line','Analytics'), @($null,'director-companies.html','fa-building','Companies'), @($null,'director-moa-monitoring.html','fa-file-signature','MOA Monitoring'), @($null,'director-reports.html','fa-chart-bar','Reports'), @('ACCOUNT','director-settings.html','fa-cog','Settings'), @($null,'index.html','fa-sign-out-alt','Logout')) }
  faculty = @{ Role='Faculty'; Avatar='FS'; Body='faculty-page'; Items=@(
    @('MAIN','faculty-dashboard.html','fa-chart-line','Dashboard'), @($null,'faculty-assigned-students.html','fa-users','Assigned Students'), @('MANAGEMENT','faculty-journals.html','fa-book','Journals'), @($null,'faculty-evaluations.html','fa-star','Evaluations'), @($null,'faculty-feedback.html','fa-comment-dots','Feedback'), @('ACCOUNT','faculty-settings.html','fa-cog','Settings'), @($null,'index.html','fa-sign-out-alt','Logout')) }
  student = @{ Role='Student'; Avatar='JS'; Body='student-page'; Items=@(
    @('MAIN','student-dashboard.html','fa-tachometer-alt','Dashboard'), @($null,'student-attendance.html','fa-calendar-check','Attendance'), @($null,'student-logbook.html','fa-book-open','Logbook'), @($null,'student-documents.html','fa-file-alt','Documents'), @($null,'student-evaluations.html','fa-star','Evaluations'), @($null,'student-records.html','fa-folder-open','Records'), @('ACCOUNT','student-settings.html','fa-cog','Settings'), @($null,'index.html','fa-sign-out-alt','Logout')) }
  supervisor = @{ Role='Supervisor'; Avatar='MS'; Body='supervisor-page'; Items=@(
    @('MAIN','supervisor-dashboard.html','fa-chart-line','Dashboard'), @($null,'supervisor-assigned-interns.html','fa-users','Assigned Interns'), @($null,'supervisor-attendance-validation.html','fa-calendar-check','Attendance Validation'), @($null,'supervisor-journal-validation.html','fa-book','Journal Review'), @($null,'supervisor-performance-evaluation.html','fa-star','Evaluations'), @($null,'supervisor-notifications.html','fa-bell','Notifications'), @('ACCOUNT','supervisor-settings.html','fa-cog','Settings'), @($null,'index.html','fa-sign-out-alt','Logout')) }
}
$pageMeta = @{
 'coord-monitoring.html'=@('Progress Monitoring & Supervision','fa-chart-line'); 'coord-announcements.html'=@('Announcements','fa-bullhorn'); 'coord-doc-approvals.html'=@('Document Approvals','fa-file-circle-check'); 'coord-logbook-review.html'=@('Logbook Review','fa-book-open'); 'coord-records.html'=@('Student Records','fa-folder-open'); 'coord-reports.html'=@('Reports','fa-chart-bar'); 'coord-settings.html'=@('Settings','fa-cog')
 'director-dashboard.html'=@('PALD Director Dashboard','fa-chart-pie'); 'director-analytics.html'=@('Analytics','fa-chart-line'); 'director-companies.html'=@('Companies','fa-building'); 'director-moa-monitoring.html'=@('MOA Monitoring','fa-file-signature'); 'director-reports.html'=@('Reports','fa-chart-bar'); 'director-settings.html'=@('Settings','fa-cog')
 'faculty-dashboard.html'=@('Faculty Dashboard','fa-chart-line'); 'faculty-assigned-students.html'=@('Assigned Students','fa-users'); 'faculty-journals.html'=@('Journals','fa-book'); 'faculty-evaluations.html'=@('Evaluations','fa-star'); 'faculty-feedback.html'=@('Feedback','fa-comment-dots'); 'faculty-settings.html'=@('Settings','fa-cog')
 'student-dashboard.html'=@('Dashboard','fa-tachometer-alt'); 'student-attendance.html'=@('Attendance','fa-calendar-check'); 'student-logbook.html'=@('Logbook','fa-book-open'); 'student-documents.html'=@('Documents','fa-file-alt'); 'student-evaluations.html'=@('Evaluations','fa-star'); 'student-records.html'=@('Student Records','fa-folder-open'); 'student-settings.html'=@('Settings','fa-cog')
 'supervisor-dashboard.html'=@('Progress Monitoring & Supervision','fa-chart-line'); 'supervisor-assigned-interns.html'=@('Assigned Interns','fa-users'); 'supervisor-attendance-validation.html'=@('Attendance Validation','fa-calendar-check'); 'supervisor-journal-validation.html'=@('Journal Review','fa-book'); 'supervisor-performance-evaluation.html'=@('Performance Evaluations','fa-star'); 'supervisor-notifications.html'=@('Notifications','fa-bell'); 'supervisor-settings.html'=@('Settings','fa-cog')
}
function Get-Role($name) {
  if ($name.StartsWith('coord-')) { return 'coord' }
  foreach ($r in 'director','faculty','student','supervisor') {
    if ($name.StartsWith("$r-")) { return $r }
  }
  return $null
}
function New-Sidebar($name, $cfg) {
  $lines = New-Object System.Collections.Generic.List[string]
  foreach ($line in @('<aside class="sidebar">','  <div class="sidebar-brand">','    <div class="app-logo">','      <img src="logo.jpg" alt="Logo" class="app-logo-img">','      <div class="app-logo-text">','        <div class="app-logo-main">INTERNTRACK</div>','        <div class="app-logo-sub">INTERNSHIP SYSTEM</div>','      </div>','    </div>','  </div>','  <nav class="sidebar-nav">')) { $lines.Add($line) }
  foreach ($it in $cfg.Items) {
    if ($it[0]) { $lines.Add(('    <div class="nav-section-label">{0}</div>' -f $it[0])) }
    $cls='sidebar-link'
    if ($it[1] -eq $name) { $cls += ' active' }
    if ($it[3] -eq 'Logout') { $cls += ' logout-link' }
    $lines.Add(('    <a href="{0}" class="{1}"><i class="fa {2}"></i><span>{3}</span></a>' -f $it[1], $cls, $it[2], $it[3]))
  }
  $lines.Add('  </nav>')
  $lines.Add('</aside>')
  return ($lines -join "`n")
}
function New-Topbar($name, $cfg, $meta) {
  $title = $meta[$name][0]
  $icon = $meta[$name][1]
@"
<header class="topbar">
  <div class="topbar-left">
    <button type="button" class="btn-hamburger" id="sidebarToggle" aria-label="Open sidebar"><i class="fa fa-bars"></i></button>
    <div class="topbar-page-icon"><i class="fa $icon"></i></div>
    <div class="topbar-title-group">
      <div class="topbar-title">$title</div>
      <div class="topbar-subtitle">AY 2024-2025, Sem 2</div>
    </div>
  </div>
  <div class="topbar-right">
    <span class="role-badge"><i class="fa fa-user-shield"></i> $($cfg.Role)</span>
    <div class="topbar-avatar" title="$($cfg.Role) profile">$($cfg.Avatar)</div>
  </div>
</header>
"@.Trim()
}
Get-ChildItem -File -Filter '*.html' | Where-Object { $_.Name -ne 'index.html' } | ForEach-Object {
  $name = $_.Name
  $role = Get-Role $name
  if (-not $role) { return }
  $cfg = $roleMenus[$role]
  $s = Get-Content -Raw -LiteralPath $_.FullName
  if ($s -notmatch 'styles\.css') { $s = $s -replace '</head>', "  <link rel=""stylesheet"" href=""styles.css""/>`n</head>" }
  $s = [regex]::Replace($s, '\s*<link rel="stylesheet" href="coordinator-fix\.css"/>', '')
  $s = [regex]::Replace($s, '<body([^>]*)>', {
    param($m)
    $attrs = $m.Groups[1].Value
    $classes = New-Object System.Collections.Generic.List[string]
    $cm = [regex]::Match($attrs, 'class\s*=\s*"([^"]*)"')
    if ($cm.Success) {
      foreach ($c in ($cm.Groups[1].Value -split '\s+')) { if ($c) { $classes.Add($c) } }
      $attrs = $attrs.Remove($cm.Index, $cm.Length)
    }
    foreach ($c in @('page-body', $cfg.Body)) { if (-not $classes.Contains($c)) { $classes.Add($c) } }
    '<body class="' + ($classes -join ' ') + '"' + $attrs + '>'
  }, 1)
  $s = [regex]::Replace($s, '(?s)<aside class="sidebar">.*?</aside>', (New-Sidebar $name $cfg), 1)
  $tb = New-Topbar $name $cfg $pageMeta
  $pattern = '(?s)<header class="(?:topbar|coord-topbar|sp-topbar|page-topbar-hero)">.*?</header>'
  if ([regex]::IsMatch($s, $pattern)) {
    $s = [regex]::Replace($s, $pattern, $tb, 1)
  } else {
    $s = [regex]::Replace($s, '</aside>', ("</aside>`n`n" + $tb), 1)
  }
  $s = [regex]::Replace($s, '<main class="sp-page">', '<main class="main-content">', 1)
  $s = [regex]::Replace($s, '<main class="sp-page ([^"]+)">', '<main class="main-content $1">', 1)
  if ($s -notmatch 'app-footer' -and $s -match '</main>') {
    $s = $s -replace '</main>', "`n  <footer class=""app-footer"">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>`n</main>"
  }
  if ($s -notmatch 'script\.js') { $s = $s -replace '</body>', "<script src=""script.js""></script>`n</body>" }
  Set-Content -LiteralPath $_.FullName -Value $s -Encoding UTF8
  Write-Output "normalized $name"
}
