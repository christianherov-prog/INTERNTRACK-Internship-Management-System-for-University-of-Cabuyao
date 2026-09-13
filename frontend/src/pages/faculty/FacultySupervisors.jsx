import SupervisorsDirectory from '../shared/SupervisorsDirectory'

export default function FacultySupervisors() {
  return (
    <SupervisorsDirectory
      apiBase="/faculty"
      bodyClass="faculty-page"
      cacheKey="faculty:supervisors"
      subtitle="Supervisors linked to your assigned students' internships"
    />
  )
}
