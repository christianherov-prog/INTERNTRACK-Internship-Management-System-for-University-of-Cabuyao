import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'

import StudentSupervisorInvite from './StudentSupervisorInvite'
import StudentAttendance from './StudentAttendance'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import api from '../../services/api'
import PageError from '../../components/PageError'
import { unwrapList } from '../../utils/apiList'
import { useCachedPage } from '../../hooks/useCachedPage'
import { prefetchPage } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'

function StudentAttendanceHub() {
  const currentTerm = useCurrentTerm()
  const { loading, seed, run } = useCachedPage('student:attendance-hub')
  const [activeTab, setActiveTab] = useState('attendance')
  const [statusData, setStatusData] = useState(seed ?? null)
  const [error, setError] = useState(null)

  const fetchStatus = () => {
    setError(null)
    run(() => api.get('/student/supervisor-invite/status').then(res => res.data))
      .then((next) => {
        if (next) {
          setStatusData(next)
          if (next.state === 'assigned' || next.has_supervisor) {
            prefetchPage('student:attendance', async () => {
              const [attRes, corrRes] = await Promise.all([
                api.get('/student/attendance'),
                api.get('/student/attendance/corrections').catch(() => ({ data: { data: [] } })),
              ])
              return {
                data: attRes.data,
                corrections: unwrapList(corrRes.data).items,
              }
            })
          }
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisor status.')
      })
  }

  useEffect(() => { fetchStatus() }, [])

  if (loading && !statusData) {
    return (
      <Layout title="Attendance & Supervisor" subtitle={currentTerm} icon="fa-user-clock" bodyClass="student-page">
        <div className="text-center py-5"><InternTrackLoader /></div>
      </Layout>
    )
  }

  if (error && !statusData) {
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
      {isApproved && (
        <div className="nav-tabs-wrapper mb-4">
          <ul className="nav nav-tabs custom-tabs">
            <li className="nav-item">
              <button className={`nav-link ${activeTab === 'attendance' ? 'active' : ''}`} onClick={() => setActiveTab('attendance')}>
                <i className="fa fa-clock me-2"></i>Attendance
              </button>
            </li>
            <li className="nav-item">
              <button className={`nav-link ${activeTab === 'supervisor' ? 'active' : ''}`} onClick={() => setActiveTab('supervisor')}>
                <i className="fa fa-user-tie me-2"></i>Supervisor Details
              </button>
            </li>
          </ul>
        </div>
      )}

      <div>
        {(!isApproved || activeTab === 'supervisor') && (
          <div className="tab-embedded" hidden={isApproved && activeTab !== 'supervisor'}>
            <StudentSupervisorInvite
              embedded={true}
              initialStatusData={statusData}
              onStatusChange={fetchStatus}
            />
          </div>
        )}
        {isApproved && (
          <div className="tab-embedded" hidden={activeTab !== 'attendance'}>
            <StudentAttendance embedded={true} />
          </div>
        )}
      </div>
    </Layout>
  )
}

export default StudentAttendanceHub
