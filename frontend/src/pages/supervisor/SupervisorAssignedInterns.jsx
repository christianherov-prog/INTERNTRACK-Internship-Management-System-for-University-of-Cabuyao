import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { resolveTargetHours } from '../../config/hours'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { useAuth } from '../../contexts/AuthContext'
import { formatStudentName } from '../../utils/formatName'
import { displayLabel } from '../../utils/displayLabel'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'

function statusBadge(status) {
  const s = status === 'ongoing' ? 'active' : status
  if (s === 'active' || s === 'placed') return 'badge-active'
  if (s === 'completed') return 'badge-completed'
  if (s === 'pending_placement') return 'badge-pending'
  if (s === 'suspended' || s === 'deferred' || s === 'expelled') return 'badge-inactive'
  return 'badge-pending'
}

function SupervisorAssignedInterns() {
  const { user } = useAuth()
  const { loading, seed, run } = useCachedPage('supervisor:assigned-interns')
  const [interns, setInterns] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [downloading, setDownloading] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [endingId, setEndingId] = useState(null)
  const [endReason, setEndReason] = useState('')
  const [ending, setEnding] = useState(false)

  const downloadPdf = async (docType, internshipId, studentName) => {
    setDownloading(true);
    try {
      const endpoint = docType === 'dtr' ? '/supervisor/dtr/generate' : '/supervisor/journal/generate';
      const params = docType === 'dtr' 
        ? { internship_id: internshipId, month: new Date().toISOString().slice(0, 7) }
        : { internship_id: internshipId };
      const res = await api.get(endpoint, { params, responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = `${docType === 'dtr' ? 'DTR' : 'Journal'}_${studentName}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      alert(`Failed to download ${docType === 'dtr' ? 'DTR' : 'Journal'} PDF.`);
    } finally {
      setDownloading(false);
    }
  };

  const load = () => {
    setError(null)
    run(() => api.get('/supervisor/assigned-students').then(res => unwrapList(res.data).items))
      .then((next) => { if (next) setInterns(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load assigned students.')
        setInterns([])
      })
  }

  useEffect(() => { load() }, [])

  return (
    <Layout title="Assigned Students" subtitle="Industry Supervisor" icon="fa-users" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="ongoing">Active / Ongoing</option>
          <option value="completed">Completed</option>
          <option value="suspended">Suspended</option>
          <option value="deferred">Deferred</option>
          <option value="expelled">Expelled</option>
          <option value="pending_placement">Pending Placement</option>
        </select>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>My Assigned Interns</h6>
        </div>
        <p className="px-3 pt-3 mb-0 text-muted" style={{ fontSize: '0.85rem' }}>
          Status is official (set by Coordinator/Director). You can view it and submit evaluations — you cannot change it.
        </p>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-5"><InternTrackLoader /></div>
          ) : interns.length === 0 ? (
            <EmptyState
              icon="fa-user-slash"
              title="No students assigned yet"
              message="A student invite plus coordinator approval is required before interns appear here."
            />
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Term</th>
                    <th>Hours</th>
                    <th>Official Status</th>
                    <th>Documents</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {(() => {
                    const filtered = interns.filter(i => {
                      const name = formatStudentName(i.student).toLowerCase()
                      const st = i.status || 'none'
                      return (!search || name.includes(search.toLowerCase()))
                        && (statusFilter === 'all' || st === statusFilter)
                    })

                    if (filtered.length === 0) {
                      return <tr><td colSpan="9" className="text-center text-muted py-4">No students match the selected filters.</td></tr>
                    }

                    return filtered.map((i) => {
                      const profile = i.student?.student_profile || i.student?.studentProfile
                      const name = formatStudentName(i.student)
                      const hours = parseFloat(i.total_hours_rendered || 0)
                    const target = resolveTargetHours(i.target_hours)
                    return (
                      <tr key={i.id}>
                        <td className="fw-semibold">{name}</td>
                        <td>{profile?.student_number || i.student?.username}</td>
                        <td style={{ fontSize: '0.85rem' }}>{profile?.program?.name || profile?.program?.code || (typeof profile?.program === 'string' ? profile?.program : null) || profile?.course_name || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{i.company?.company_name || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{i.term || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{hours} / {target}</td>
                        <td>
                          <span className={`badge-status ${statusBadge(i.status)}`}>
                            {i.status_label || i.status}
                          </span>
                          {i.status_reason && (
                            <div className="text-muted mt-1" style={{ fontSize: '0.72rem', maxWidth: 160 }} title={i.status_reason}>
                              {i.status_reason.length > 40 ? `${i.status_reason.slice(0, 40)}…` : i.status_reason}
                            </div>
                          )}
                        </td>
                        <td>
                          <div className="d-flex gap-1 flex-wrap">
                            <button
                              type="button"
                              className="btn btn-xs btn-outline-primary"
                              style={{ fontSize: '0.78rem', padding: '2px 8px' }}
                              onClick={() => setPreviewModal({
                                type: 'dtr',
                                data: {
                                  studentName: name,
                                  program: displayLabel(profile?.program || profile?.course_name, '—'),
                                  companyName: i.company?.company_name || '—',
                                  companyLogoPath: i.company?.company_logo_path || '',
                                  supervisorName: user?.username,
                                  logs: i.attendance_logs || [],
                                  month: new Date().toISOString().slice(0, 7)
                                },
                                onDownload: () => downloadPdf('dtr', i.id, name),
                              })}
                            >
                              <i className="fa fa-eye me-1"></i>DTR (FO-30)
                            </button>
                            <button
                              type="button"
                              className="btn btn-xs btn-outline-secondary"
                              style={{ fontSize: '0.78rem', padding: '2px 8px' }}
                              onClick={() => setPreviewModal({
                                type: 'journal',
                                data: {
                                  studentName: name,
                                  program: displayLabel(profile?.program || profile?.course_name, '—'),
                                  companyName: i.company?.company_name || '—',
                                  companyLogoPath: i.company?.company_logo_path || '',
                                },
                                onDownload: () => downloadPdf('journal', i.id, name),
                              })}
                            >
                              <i className="fa fa-eye me-1"></i>Journal (FO-31)
                            </button>
                          </div>
                        </td>
                        <td>
                          {['completed', 'expelled', 'terminated'].includes(i.status) ? null : (
                            <button
                              type="button"
                              className="btn btn-outline-danger btn-sm"
                              style={{ fontSize: '0.78rem' }}
                              onClick={() => { setEndingId(i.id); setEndReason('') }}
                            >
                              End supervision
                            </button>
                          )}
                        </td>
                      </tr>
                    )
                  })})()}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
        onDownload={previewModal?.onDownload}
        downloading={downloading}
      />

      {endingId && (
        <div className="modal d-block" style={{ background: 'rgba(0,0,0,0.45)' }} role="dialog">
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">End supervision of this intern?</h5>
                <button type="button" className="btn-close" onClick={() => setEndingId(null)} aria-label="Close"></button>
              </div>
              <div className="modal-body">
                <p className="text-muted" style={{ fontSize: '0.9rem' }}>
                  This only unlinks you from this intern. Your account and any other assigned internships stay intact. The student can invite a new supervisor.
                </p>
                <label className="form-label small fw-semibold">Reason (optional)</label>
                <textarea className="form-control" rows={3} value={endReason} onChange={(e) => setEndReason(e.target.value)} />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setEndingId(null)} disabled={ending}>Cancel</button>
                <button
                  type="button"
                  className="btn btn-danger"
                  disabled={ending}
                  onClick={async () => {
                    setEnding(true)
                    try {
                      await api.post(`/supervisor/internships/${endingId}/end-supervision`, { reason: endReason || null })
                      setEndingId(null)
                      load()
                    } catch (err) {
                      alert(err.response?.data?.message || 'Failed to end supervision.')
                    } finally {
                      setEnding(false)
                    }
                  }}
                >
                  {ending ? 'Ending…' : 'End supervision'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </Layout>
  )
}

export default SupervisorAssignedInterns
