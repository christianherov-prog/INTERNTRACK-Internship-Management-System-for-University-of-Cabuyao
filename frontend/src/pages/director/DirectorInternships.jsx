import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import StatusChangeModal from '../../components/StatusChangeModal'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function DirectorInternships() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [statusTarget, setStatusTarget] = useState(null)
  const [message, setMessage] = useState(null)
  const [certLoading, setCertLoading] = useState(null)

  const fetchRows = () => {
    setLoading(true)
    setLoadError(null)
    api.get('/director/internships')
      .then(res => setRows(unwrapList(res.data).items))
      .catch(err => {
        setLoadError(err.response?.data?.message || 'Failed to load internships.')
        setRows([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchRows() }, [])

  const downloadCertificate = async (internshipId) => {
    setCertLoading(internshipId)
    try {
      const res = await api.get(`/director/internships/${internshipId}/certificate`, {
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      const a = document.createElement('a')
      a.href = url
      a.download = `completion-certificate-${internshipId}.pdf`
      a.click()
      window.URL.revokeObjectURL(url)
      setMessage({ type: 'success', text: 'Certificate PDF downloaded.' })
    } catch (err) {
      let text = 'Could not generate certificate.'
      if (err.response?.data instanceof Blob) {
        try {
          const j = JSON.parse(await err.response.data.text())
          text = j.message || text
        } catch { /* ignore */ }
      } else if (err.response?.data?.message) {
        text = err.response.data.message
      }
      setMessage({ type: 'danger', text })
    } finally {
      setCertLoading(null)
    }
  }

  return (
    <Layout title="Internship Status" subtitle="Director" icon="fa-tags" bodyClass="director-page">
      {loadError && <PageError message={loadError} onRetry={fetchRows} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {statusTarget && (
        <StatusChangeModal
          internshipId={statusTarget.id}
          studentName={statusTarget.student?.name || 'Student'}
          currentStatus={statusTarget.status}
          apiBase="director"
          onClose={() => setStatusTarget(null)}
          onSaved={() => {
            setStatusTarget(null)
            setMessage({ type: 'success', text: 'Status updated with reason recorded.' })
            fetchRows()
          }}
        />
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>Internship Roster & Status Tagging</h6>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr><td colSpan="6" className="text-center py-4 text-muted">No internships found.</td></tr>
                  ) : rows.map(r => (
                    <tr key={r.id}>
                      <td>
                        <div className="fw-semibold">{r.student?.name}</div>
                        <div className="text-muted" style={{ fontSize: '0.8rem' }}>{r.student?.student_number}</div>
                      </td>
                      <td>{r.student?.program || '—'}</td>
                      <td>{r.company_name || '—'}</td>
                      <td><span className="badge-status badge-active">{r.status_label || r.status}</span></td>
                      <td style={{ maxWidth: 220, fontSize: '0.82rem' }}>{r.status_reason || '—'}</td>
                      <td className="text-center">
                        <button className="btn btn-sm btn-outline-primary me-1" onClick={() => setStatusTarget(r)}>
                          Change Status
                        </button>
                        {r.status === 'completed' && (
                          <button
                            className="btn btn-sm btn-success"
                            onClick={() => downloadCertificate(r.id)}
                            disabled={certLoading === r.id}
                          >
                            {certLoading === r.id ? 'Generating…' : 'Certificate'}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default DirectorInternships
