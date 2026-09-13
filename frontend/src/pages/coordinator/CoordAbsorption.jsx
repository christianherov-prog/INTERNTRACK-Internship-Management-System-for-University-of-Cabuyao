import RoleAbsorption from '../../components/RoleAbsorption'

function CoordAbsorption() {
  return (
    <RoleAbsorption
      apiBase="coordinator"
      bodyClass="coordinator-page"
      showSupervisorColumn
      emptyMessage="No completed internships yet."
    />
  )
}

export default CoordAbsorption
