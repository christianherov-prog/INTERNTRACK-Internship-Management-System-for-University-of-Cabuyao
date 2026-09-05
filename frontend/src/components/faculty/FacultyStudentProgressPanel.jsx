import { useEffect, useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import PageError from '../../components/PageError'
import api from '../../services/api'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

export default function FacultyStudentProgressPanel({ userId }) {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)
  const [generating, setGenerating] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)

  const { user } = useAuth()

  useEffect(() => {
    if (!userId || !user) return
    api.get(`/${user.role}/students/${userId}/progress`)
      .then(res => setData(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to load student progress.'))
      .finally(() => setLoading(false))
  }, [userId, user])

  const generateJournalPdf = async (weekNumber) => {
    setGenerating(weekNumber)
    try {
      const resp = await api.get('/faculty/journal/generate', {
        params: { internship_id: data.internship.id, week_number: weekNumber },
        responseType: 'blob',
      })
      const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href  = url
      link.download = `Journal_${data.student.name}_Week${weekNumber}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch {
      alert('Failed to generate journal PDF.')
    } finally {
      setGenerating(null)
    }
  }

  const docStatusBadge = (status) => {
    const map = { approved: 'badge-active', rejected: 'badge-inactive', pending: 'badge-pending', submitted: 'badge-pending' }
    return <span className={`badge-status ${map[status] ?? 'badge-pending'}`}>{status || 'pending'}</span>
  }

  const journalStatusBadge = (status) => {
    const map = { approved: 'badge-active', needs_revision: 'badge-inactive', submitted: 'badge-pending' }
    return <span className={`badge-status ${map[status] ?? 'badge-pending'}`}>{(status || '—').replace(/_/g, ' ')}</span>
  }

  if (loading) return <div className="p-4 text-center"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
  if (error) return <div className="p-4"><PageError message={error} /></div>

  const { documents, journals } = data

  return (
    <div className="faculty-progress-panel bg-light p-3 rounded border">
      <div className="row g-4">
        {/* ── Documents Checklist ── */}
        <div className="col-md-12">
          <div className="content-card h-100 mb-0">
            <div className="content-card-header">
              <i className="fa fa-file-alt"></i>
              <h6 className="mb-0">Documents</h6>
              <span className="ms-auto badge bg-secondary">{documents?.approved}/{documents?.total} approved</span>
            </div>
            <div className="table-card p-0">
              {documents?.items?.length === 0 ? (
                <div className="text-center py-4 text-muted">No documents submitted yet.</div>
              ) : documents?.items?.map(doc => (
                <div key={doc.id} className="d-flex justify-content-between align-items-center p-2 border-bottom">
                  <span style={{ fontSize: '0.88rem' }}>{doc.name}</span>
                  {docStatusBadge(doc.status)}
                </div>
              ))}
            </div>
          </div>
        </div>

      </div>

      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
        onDownload={previewModal?.onDownload}
        downloading={previewModal?.downloading}
      />
    </div>
  )
}
