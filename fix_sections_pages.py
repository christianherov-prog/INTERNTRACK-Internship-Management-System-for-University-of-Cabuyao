import os, glob

files = [
    'frontend/src/pages/coordinator/CoordMonitoring.jsx',
    'frontend/src/pages/coordinator/CoordPlacements.jsx',
    'frontend/src/pages/coordinator/CoordRecords.jsx',
    'frontend/src/pages/director/DirectorInternships.jsx'
]

for f in files:
    if not os.path.exists(f): continue
    with open(f, 'r', encoding='utf8') as file:
        content = file.read()
    
    # Needs formatYearSection import?
    if 'formatYearSection' not in content:
        content = "import { formatYearSection } from '../../utils/formatSection'\n" + content
    
    if f == 'frontend/src/pages/coordinator/CoordMonitoring.jsx':
        content = content.replace(
            "const sections = ['all', ...new Set(allRows.map(r => r.section).filter(s => s && s !== '—'))]",
            "const sections = ['all', ...new Set(allRows.map(r => formatYearSection(r.section)).filter(s => s && s !== '—'))]"
        )
        content = content.replace(
            "const matchSection = sectionFilter === 'all' || r.section === sectionFilter",
            "const matchSection = sectionFilter === 'all' || formatYearSection(r.section) === sectionFilter"
        )
    
    if f == 'frontend/src/pages/coordinator/CoordPlacements.jsx':
        content = content.replace(
            'const sections = ["all", ...new Set(applications.map(a => a.student?.student_profile?.section || "—").filter(s => s !== "—"))]',
            'const sections = ["all", ...new Set(applications.map(a => formatYearSection(a.student?.student_profile?.section) || "—").filter(s => s !== "—"))]'
        )
        content = content.replace(
            'const sec = app.student?.student_profile?.section || "—"',
            'const sec = formatYearSection(app.student?.student_profile?.section) || "—"'
        )
    
    if f == 'frontend/src/pages/coordinator/CoordRecords.jsx':
        content = content.replace(
            'const sections = ["all", ...new Set(students.map(s => s.student_profile?.section || "—").filter(x => x !== "—"))]',
            'const sections = ["all", ...new Set(students.map(s => formatYearSection(s.student_profile?.section) || "—").filter(x => x !== "—"))]'
        )
        content = content.replace(
            'const sec = student.student_profile?.section || "—"',
            'const sec = formatYearSection(student.student_profile?.section) || "—"'
        )
        
    if f == 'frontend/src/pages/director/DirectorInternships.jsx':
        content = content.replace(
            'const sections = ["all", ...new Set(students.map(s => s.student_profile?.section || "—").filter(x => x !== "—"))]',
            'const sections = ["all", ...new Set(students.map(s => formatYearSection(s.student_profile?.section) || "—").filter(x => x !== "—"))]'
        )
        content = content.replace(
            'const sec = student.student_profile?.section || "—"',
            'const sec = formatYearSection(student.student_profile?.section) || "—"'
        )
    
    with open(f, 'w', encoding='utf8') as file:
        file.write(content)

print("Fixed sections")
