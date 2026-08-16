export function formatStudentName(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile || row?.student_profile || row?.studentProfile || row;
  
  if (p && (p.first_name || p.last_name)) {
    const last = (p.last_name || "").trim();
    const first = (p.first_name || "").trim();
    const middle = (p.middle_name || "").trim();
    const suffix = (p.suffix || "").trim();
    
    let name = "";
    if (last) name += last;
    if (first) name += (name ? ", " : "") + first;
    if (middle) name += " " + middle;
    if (suffix) name += ", " + suffix;
    
    return name || row?.student?.student_number || row?.student_number || row?.student?.email || row?.email || "—";
  }
  return row?.student?.student_number || row?.student_number || row?.student?.email || row?.email || "—";
}
