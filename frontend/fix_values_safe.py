import os
files = ['frontend/src/pages/admin/MisdSectionMappings.jsx', 'frontend/src/pages/faculty/FacultyAssignedStudents.jsx', 'frontend/src/pages/coordinator/CoordMonitoring.jsx', 'frontend/src/pages/shared/ManageRequirementsTemplates.jsx']
for f in files:
    if not os.path.exists(f): continue
    with open(f, 'r', encoding='utf-8') as file: content = file.read()
    if 'formatYearSection' not in content: content = content.replace("import { useState", "import { formatYearSection } from '../../utils/formatSection'\\nimport { useState")
    content = content.replace('{u.section}', '{formatYearSection(u.section)}')
    content = content.replace('{row.section}', '{formatYearSection(row.section)}')
    content = content.replace('{r.section}', '{formatYearSection(r.section)}')
    content = content.replace('{studentSection(row)}', '{formatYearSection(studentSection(row))}')
    content = content.replace("{sub.section || '—'}", "{formatYearSection(sub.section) || '—'}")
    with open(f, 'w', encoding='utf-8') as file: file.write(content)
