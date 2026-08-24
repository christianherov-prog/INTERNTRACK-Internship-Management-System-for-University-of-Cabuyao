import os, glob, re

files = [
    'frontend/src/components/MessagesInbox.jsx',
    'frontend/src/pages/coordinator/CoordMonitoring.jsx',
    'frontend/src/pages/coordinator/CoordPlacements.jsx',
    'frontend/src/pages/coordinator/CoordRecords.jsx',
    'frontend/src/pages/director/DirectorInternships.jsx',
    'frontend/src/pages/faculty/FacultyAssignedStudents.jsx',
    'frontend/src/pages/faculty/FacultyEvaluations.jsx'
]

for f in files:
    if not os.path.exists(f): continue
    with open(f, 'r', encoding='utf8') as file:
        c = file.read()
    
    # We want to make sure the mapping inside new Set applies formatYearSection
    # For CoordMonitoring:
    # const sections = ['all', ...new Set(allRows.map(r => r.section).filter(s => s && s !== '—'))]
    # We should replace `r.section` with `formatYearSection(r.section)` inside the map!
    
    # Let's just find and print the lines first to be safe
    lines = c.split('\n')
    for i, line in enumerate(lines):
        if 'const sections =' in line and 'new Set' in line:
            print(f"{f}:{i+1}: {line.strip()}")
            
