import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { DEFAULT_TARGET_HOURS } from '../../config/hours'

function absorptionBadge(status) {
  if (status === 'absorbed') return 'badge bg-success'
  if (status === 'not_hired') return 'badge bg-danger'
  return 'badge bg-warning text-dark'
}

function StudentRecords() {
  const [profile, setProfile] = useState(null)
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [declareMsg, setDeclareMsg] = useState(null)
  const [declaringId, setDeclaringId] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/student/records')
      .then((res) => {
        setProfile(res.data.profile ?? null)
        setHistory(unwrapList(res.data).items)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load your internship records.')
        setProfile(null)
        setHistory([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const active = history.find((h) =>
    ['ongoing', 'placed', 'active', 'for_evaluation', 'pending_placement'].includes(h.status)
  )
  const completed = history.filter((h) => h.status === 'completed')
  const totalHours = history.reduce((sum, h) => sum + (Number(h.total_hours_rendered) || 0), 0)
  const totalDays = history.reduce((sum, h) => sum + (Number(h.validated_days) || 0), 0)

  const declareHired = (internshipId) => {
    setDeclaringId(internshipId)
    setDeclareMsg(null)
    api.post('/student/absorption/declare', { internship_id: internshipId })
      .then((res) => {
        setDeclareMsg({ type: 'success', text: res.data.message || 'Declaration submitted.' })
        load()
      })
      .catch((err) => {
        setDeclareMsg({
          type: 'danger',
          text: err.response?.data?.message || 'Could not submit hire declaration.',
        })
      })
      .finally(() => setDeclaringId(null))
  }

  return (
    <Layout title="My Records" subtitle="Internship History" icon="fa-folder-open" bodyClass="student-page">
      {error && <PageError message={error} onRetry={load} />}
      {declareMsg && (
        <div className={`alert alert-${declareMsg.type} alert-dismissible mb-3`}>
          {declareMsg.text}
          <button className="btn-close" onClick={() => setDeclareMsg(null)}></button>
        </div>
      )}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card">
                <div className="stat-icon teal"><i className="fa fa-clock"></i></div>
                <div>
                  <div className="stat-value">{totalHours}</div>
                  <div className="stat-label">Total Hours</div>
                </div>
              </div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card">
                <div className="stat-icon green"><i className="fa fa-calendar-check"></i></div>
                <div>
                  <div className="stat-value">{totalDays}</div>
                  <div className="stat-label">Validated Days</div>
                </div>
              </div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card">
                <div className="stat-icon amber"><i className="fa fa-briefcase"></i></div>
                <div>
                  <div className="stat-value">{history.length}</div>
                  <div className="stat-label">Placements</div>
                </div>
              </div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card">
                <div className="stat-icon blue"><i className="fa fa-user-graduate"></i></div>
                <div>
                  <div className="stat-value" style={{ fontSize: '1.1rem' }}>
                    {profile?.program?.name || profile?.program?.code || (typeof profile?.program === 'string' ? profile?.program : null) || profile?.course_name || '—'}
                  </div>
                  <div className="stat-label">Program</div>
                </div>
              </div>
            </div>
          </div>

          {active && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-circle-play"></i>
                <h6>Current Internship</h6>
              </div>
              <div className="p-3">
                <div className="fw-semibold">{active.company?.company_name || 'No company assigned'}</div>
                <div className="text-muted small">
                  {(active.status || '').replace(/_/g, ' ')} · {active.term || '—'} ·{' '}
                  {active.total_hours_rendered || 0}/{active.target_hours || DEFAULT_TARGET_HOURS} hrs
                </div>
              </div>
            </div>
          )}

          {completed.length > 0 && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-user-check"></i>
                <h6>Post-Completion Hire / Absorption</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Company</th>
                      <th>Outcome</th>
                      <th>Declared?</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {completed.map((row) => {
                      const outcome = row.absorption_status || 'pending'
                      const alreadyDeclared = !!row.student_declared_hired
                      return (
                        <tr key={`abs-${row.id}`}>
                          <td className="fw-semibold">{row.company?.company_name || '—'}</td>
                          <td>
                            <span className={absorptionBadge(outcome)}>
                              {String(outcome).replace('_', ' ')}
                            </span>
                            {row.job_title ? (
                              <div className="small text-muted mt-1">{row.job_title}</div>
                            ) : null}
                          </td>
                          <td>{alreadyDeclared ? <span className="badge bg-info text-dark">Yes</span> : '—'}</td>
                          <td>
                            {!alreadyDeclared && (outcome === 'pending' || !row.absorption_status) && (
                              <button
                                className="btn btn-sm btn-outline-primary"
                                disabled={declaringId === row.id}
                                onClick={() => declareHired(row.id)}
                              >
                                {declaringId === row.id ? 'Submitting…' : 'Declare I was hired'}
                              </button>
                            )}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
              <div className="p-3 small text-muted border-top">
                Declaring tells your supervisor/coordinator you were hired. Final confirmation is recorded by them.
              </div>
            </div>
          )}

          <div className="content-card">
            <div className="content-card-header">
              <i className="fa fa-folder-open"></i>
              <h6>Internship History</h6>
            </div>
            <div className="table-card">
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Company</th>
                      <th>Term</th>
                      <th>Status</th>
                      <th>Hours</th>
                      <th>Validated Days</th>
                    </tr>
                  </thead>
                  <tbody>
                    {history.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="text-center text-muted py-4">
                          No internship records yet.
                        </td>
                      </tr>
                    ) : history.map((row) => (
                      <tr key={row.id}>
                        <td className="fw-semibold">{row.company?.company_name || '—'}</td>
                        <td>{row.term || '—'}</td>
                        <td>
                          <span className={`badge-status ${row.status === 'ongoing' || row.status === 'active' ? 'badge-active' : 'badge-pending'}`}>
                            {(row.status || '—').replace(/_/g, ' ')}
                          </span>
                        </td>
                        <td>{row.total_hours_rendered || 0}/{row.target_hours || DEFAULT_TARGET_HOURS}</td>
                        <td>{row.validated_days ?? 0}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default StudentRecords
