import SupervisorsDirectory from '../shared/SupervisorsDirectory'

export default function DirectorSupervisors() {
  return (
    <SupervisorsDirectory
      apiBase="/director"
      bodyClass="director-page"
      cacheKey="director:supervisors"
      subtitle="University-wide HTE supervisor directory"
    />
  )
}
