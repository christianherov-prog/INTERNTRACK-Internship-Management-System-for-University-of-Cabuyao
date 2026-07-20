import RoleAbsorption from '../../components/RoleAbsorption'

function SupervisorAbsorption() {
  return (
    <RoleAbsorption
      apiBase="supervisor"
      bodyClass="supervisor-page"
      showEndedColumn
      emptyMessage="No completed internships yet. Absorption is only available after completion."
      declaredHiredExtra="Confirm Yes or No below."
    />
  )
}

export default SupervisorAbsorption
