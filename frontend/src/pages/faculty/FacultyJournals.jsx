import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { useCachedPage } from '../../hooks/useCachedPage'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { formatStudentName } from '../../utils/formatName'
import InternTrackLoader from '../../components/InternTrackLoader'
import { loadFacultyFo31Preview, openOfficialFo31 } from '../../utils/officialForm'
import { formatFo31DateRange } from '../../utils/fo31DateRange'
import { formatManilaDateTime } from '../../utils/manilaTime'
import JournalDeadlineManager from '../../components/faculty/JournalDeadlineManager'

function journalStudentNumber(journal) {
  const student = journal?.internship?.student
  const profile = student?.student_profile || student?.studentProfile
  return journal?.student_number || profile?.student_number || student?.student_number || ''
}

function FacultyJournals() {
  const currentTerm = useCurrentTerm()
  const { loading, seed, run } = useCachedPage('faculty:journals')
  const [journals, setJournals]     = useState(() => seed ?? [])
  const [error, setError]           = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage]       = useState(null)
  const [reviewJournal, setReviewJournal] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [historyModal, setHistoryModal] = useState(null)
  const [historyData, setHistoryData] = useState([])
  const [loadingHistory, setLoadingHistory] = useState(false)
  const [historyError, setHistoryError] = useState(null)

  const fetchJournals = () => {
    setError(null)
    run(() => api.get('/faculty/journals').then(res => unwrapList(res.data).items || []))
      .then((next) => { if (next) setJournals(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load journals.')
      })
  }

  useEffect(() => { fetchJournals() }, [])

  const closePreview = () => {
    setPreviewModal(null)
    setReviewJournal(null)
    setPreviewLoading(false)
  }

  const handleReview = async (id, action, feedback) => {
    setProcessing(true)
    try {
      await api.patch(`/faculty/journals/${id}/review`, { action, feedback })
      setMessage({ type: action === 'approved' ? 'success' : 'info', text: `Journal ${action === 'approved' ? 'approved' : 'returned for revision'}.` })
      closePreview()
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Review failed.' })
    } finally { setProcessing(false) }
  }

  const openHistory = (studentId, studentName) => {
    setHistoryModal({ studentId, studentName })
    setLoadingHistory(true)
    setHistoryError(null)
    api.get(`/faculty/students/${studentId}/journals`)
      .then(res => setHistoryData(Array.isArray(res.data) ? res.data : (res.data?.data || [])))
      .catch(() => {
        setHistoryData([])
        setHistoryError('Unable to load journal history. Try again.')
      })
      .finally(() => setLoadingHistory(false))
  }

  const handlePreview = (j) => {
    setReviewJournal(null)
    const internshipId = j.internship_id || j.internship?.id
    if (!internshipId) return
    openOfficialFo31(internshipId, {
      studentName: j.student_display_name,
      program: j.program_name,
      companyName: j.internship?.company?.company_name,
      weekNumber: j.week_number ?? j.entry_number,
      date: j.date,
      endDate: j.end_date,
      accomplishment: j.activities_summary,
      difficulties: j.challenges,
      insights: j.learnings,
      studentSignaturePath: j.student_signature_path,
    }, setPreviewModal).catch((err) => alert(err.response?.data?.message || 'Unable to load FO-31 preview.'))
  }

  const openReview = (j) => {
    setReviewJournal(j)
    setPreviewLoading(true)
    setPreviewModal({ type: 'journal', data: {} })
    loadFacultyFo31Preview(j, setPreviewModal)
      .catch((err) => {
        alert(err.response?.data?.message || 'Unable to load FO-31 preview.')
        closePreview()
      })
      .finally(() => setPreviewLoading(false))
  }

  return (
    <Layout title="Journals" subtitle={currentTerm} icon="fa-book" bodyClass="faculty-page">
      <JournalDeadlineManager />
      {error && <PageError message={error} onRetry={fetchJournals} />}

      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={closePreview}
        type={previewModal?.type}
        data={previewModal?.data || {}}
        onDownload={previewModal?.onDownload}
        loading={previewLoading}
        review={reviewJournal ? {
          journal: reviewJournal,
          processing,
          onSubmit: (action, feedback) => handleReview(reviewJournal.id, action, feedback),
        } : null}
      />
      {historyModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Journal History — {historyModal.studentName}</h5>
                <button className="btn-close" onClick={() => setHistoryModal(null)}></button>
              </div>
              <div className="modal-body p-0">
                {loadingHistory ? (
                  <div className="p-5 text-center"><InternTrackLoader /></div>
                ) : historyError ? (
                  <div className="p-4 text-center text-danger">{historyError}</div>
                ) : historyData.length === 0 ? (
                  <div className="p-4 text-center text-muted">No past journals found.</div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {historyData.map(h => (
                      <li key={h.id} className="list-group-item p-3">
                        <div className="d-flex justify-content-between">
                          <div className="fw-semibold text-primary">Week {h.week_number ?? h.entry_number}</div>
                          <span className={`badge ${h.status === 'approved' ? 'bg-success' : h.status === 'needs_revision' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                            {h.status}
                          </span>
                        </div>
                        <div className="text-muted small mb-2">{formatFo31DateRange(h.date, h.end_date) || h.date}</div>
                        {h.faculty_reviewed_at ? (
                          <div className="text-muted small mb-2">Reviewed {formatManilaDateTime(h.faculty_reviewed_at)}</div>
                        ) : null}
                        {h.score != null && <div className="text-success small fw-bold"><i className="fa fa-check-circle me-1"></i>Score: {h.score}/100</div>}
                        {h.faculty_feedback && (
                          <div className="bg-light p-2 rounded small mt-2">
                            <strong>Feedback:</strong> {h.faculty_feedback}
                          </div>
                        )}
                        <button
                          className="btn btn-sm btn-outline-secondary mt-2"
                          onClick={() => handlePreview({
                            ...h,
                            student_display_name: historyModal.studentName,
                            internship_id: h.internship_id || h.internship?.id,
                            internship: h.internship || reviewJournal?.internship,
                          })}
                        >
                          <i className="fa fa-eye me-1"></i>Preview Form
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-book"></i>
          <h6>Pending Journal Reviews</h6>
          <span className="ms-auto badge bg-warning text-dark">{journals.length} pending</span>
        </div>
        <div className="table-card">
          {loading && journals.length === 0 ? (
            <div className="text-center py-4"><InternTrackLoader /></div>
          ) : journals.length === 0 && !error ? (
            <div className="text-center py-4 text-muted">
              <i className="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
              All journals reviewed!
            </div>
          ) : journals.length === 0 ? null : journals.map(j => {
            const name = j.student_display_name || formatStudentName(j.internship)
            return (
              <div key={j.id} className="p-3 border-bottom d-flex align-items-start justify-content-between">
                <div>
                  <div className="fw-semibold mb-1">
                    {name} · <span className="text-primary">Week {j.week_number ?? j.entry_number}</span>
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.82rem' }}>
                    {formatFo31DateRange(j.date, j.end_date) || j.date}
                    {journalStudentNumber(j) ? ` · ${journalStudentNumber(j)}` : ''}
                  </div>
                  {j.notes && <p className="mt-1 mb-0 text-muted" style={{ fontSize: '0.85rem' }}>{j.notes?.substring(0, 100)}…</p>}
                  <span className={`badge mt-1 ${j.status === 'approved' ? 'bg-success' : j.status === 'needs_revision' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                    {j.status}
                  </span>
                  {j.deadline_display ? (
                    <span className={`badge mt-1 ms-1 ${j.submitted_late ? 'bg-danger' : 'bg-success'}`} title={`Deadline: ${j.deadline_display}`}>
                      {j.submitted_late ? 'Late' : 'On time'}
                    </span>
                  ) : null}
                  {(j.range_display || j.deadline_display || j.submitted_at_display) ? (
                    <div className="text-muted mt-1" style={{ fontSize: '0.78rem' }}>
                      {[j.range_display, j.deadline_display ? `Deadline: ${j.deadline_display}` : null, j.submitted_at_display ? `Submitted: ${j.submitted_at_display}` : null].filter(Boolean).join(' · ')}
                    </div>
                  ) : null}
                </div>
                <div className="d-flex align-items-center gap-2 ms-3 flex-shrink-0">
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => openHistory(j.internship?.student_id, name)}>
                    <i className="fa fa-history me-1"></i>History
                  </button>
                  <button className="btn btn-sm btn-primary" onClick={() => openReview(j)}>
                    <i className="fa fa-pen me-1"></i>Review
                  </button>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </Layout>
  )
}

export default FacultyJournals
