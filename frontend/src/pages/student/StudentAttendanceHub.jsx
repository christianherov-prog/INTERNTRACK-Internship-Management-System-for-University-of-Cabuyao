import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'

import StudentSupervisorInvite from './StudentSupervisorInvite'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import api from '../../services/api'
import PageError from '../../components/PageError'

function StudentAttendanceHub() {
  const currentTerm = useCurrentTerm()
  const [activeTab, setActiveTab] = useState('attendance')
  const [statusData, setStatusData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetchStatus = () => {
    setLoading(true)
    setError(null)
    api.get('/student/supervisor-invite/status')
      .then(res => {
        setStatusData(res.data)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisor status.')
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchStatus() }, [])

  if (loading) {
    return (
      <Layout title="Attendance & Supervisor" subtitle={currentTerm} icon="fa-user-clock" bodyClass="student-page">
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </Layout>
    )
  }

  if (error) {
    return (
      <Layout title="Attendance & Supervisor" subtitle={currentTerm} icon="fa-user-clock" bodyClass="student-page">
        <PageError message={error} onRetry={fetchStatus} />
      </Layout>
    )
  }

  const state = statusData?.state || (statusData?.has_supervisor ? 'assigned' : 'none')
  const isApproved = state === 'assigned'





  return (
    <Layout title="Attendance & Supervisor" subtitle={currentTerm} icon="fa-user-clock" bodyClass="student-page">

      <div>
        <div className="tab-embedded">
          <StudentSupervisorInvite
            embedded={true}
            initialStatusData={statusData}
            onStatusChange={fetchStatus}
          />
        </div>
      </div>
    </Layout >
  )
}

export default StudentAttendanceHub
