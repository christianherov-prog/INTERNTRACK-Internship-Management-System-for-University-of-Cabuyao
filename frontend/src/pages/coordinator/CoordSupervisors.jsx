import SupervisorsDirectory from '../shared/SupervisorsDirectory'

export default function CoordSupervisors() {
  return (
    <SupervisorsDirectory
      apiBase="/coordinator"
      bodyClass="coordinator-page"
      cacheKey="coordinator:supervisors"
      subtitle="Supervisors for students in your department"
    />
  )
}
