import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import EmptyState from '../../components/EmptyState'
import PageError from '../../components/PageError'
import HireProgressTracker from '../../components/HireProgressTracker'
import api from '../../services/api'

function profileOf(student) {
  return student?.student_profile || student?.studentProfile || null
}

function badge(status) {
  if (status === 'absorbed') return 'badge bg-success'
  if (status === 'not_hired') return 'badge bg-danger'
  return 'badge bg-warning text-dark'
}

function SupervisorAbsorption() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/absorption')
      .then((res) => setItems(res.data.internships ?? []))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load absorption list.')
        setItems([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  return (
    <Layout title="Intern Absorption" subtitle="Hire outcomes (view only — Director finalizes)" icon="fa-user-check" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="alert alert-info mb-4">
        Final hire outcomes (Absorbed / Not Hired) are recorded by the <strong>PALD Director</strong>.
        This page is for monitoring your completed interns only.
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-route"></i>
          <h6>Hire path (for demos)</h6>
        </div>
        <div className="p-3">
          <HireProgressTracker
            internship={{ status: 'completed', absorption_status: 'pending', total_hours_rendered: 360, target_hours: 360 }}
            showLegend
          />
          <p className="text-muted small mb-0 mt-2">
            Completed interns sit at <strong>75%</strong> until <code>DIR-1001</code> confirms Absorbed or Not Hired (<strong>100%</strong>).
            Demo: DEMO-0075 (pending), DEMO-0100H / DEMO-0100N (finalized by Director).
          </p>
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-user-check"></i>
          <h6>Completed Interns — Hire Status</h6>
        </div>
        {loading ? (
          <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
        ) : items.length === 0 && !error ? (
          <EmptyState
            icon="fa-user-check"
            title="No completed internships yet"
            message="Completed interns and hire outcomes will appear here after their internship ends."
          />
        ) : items.length === 0 ? null : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Intern</th>
                  <th>Company</th>
                  <th>Ended</th>
                  <th>Student declared?</th>
                  <th>Hire progress</th>
                  <th>Outcome</th>
                </tr>
              </thead>
              <tbody>
                {items.map((i) => {
                  const p = profileOf(i.student)
                  const name = p ? `${p.last_name}, ${p.first_name}` : '—'
                  const outcome = i.absorption_status || 'pending'
                  return (
                    <tr key={i.id}>
                      <td className="fw-semibold">{name}</td>
                      <td>{i.company?.company_name || '—'}</td>
                      <td>{i.end_date ? new Date(i.end_date).toLocaleDateString() : '—'}</td>
                      <td>{i.student_declared_hired ? <span className="badge bg-info text-dark">Yes</span> : '—'}</td>
                      <td style={{ minWidth: 220 }}><HireProgressTracker internship={i} compact /></td>
                      <td><span className={badge(outcome)}>{outcome.replace('_', ' ')}</span></td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </Layout>
  )
}

export default SupervisorAbsorption
