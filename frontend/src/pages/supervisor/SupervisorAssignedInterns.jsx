import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { DEFAULT_TARGET_HOURS } from '../../config/hours'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { useAuth } from '../../contexts/AuthContext'
import { formatStudentName } from '../../utils/formatName'

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
  const [interns, setInterns] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [downloading, setDownloading] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')

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
    setLoading(true)
    setError(null)
    api.get('/supervisor/assigned-students')
      .then(res => setInterns(unwrapList(res.data).items))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load assigned students.')
        setInterns([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  return (
    <Layout title="Assigned Students" subtitle="Industry Supervisor" icon="fa-users" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by name…" value={search} onChange={e => setSearch(e.target.value)} />
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
            <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
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
                      return <tr><td colSpan="8" className="text-center text-muted py-4">No students match the selected filters.</td></tr>
                    }

                    return filtered.map((i) => {
                      const profile = i.student?.student_profile || i.student?.studentProfile
                      const name = formatStudentName(i.student)
                      const hours = parseFloat(i.total_hours_rendered || 0)
                    const target = parseInt(i.target_hours || DEFAULT_TARGET_HOURS, 10)
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
                                  program: profile?.program?.name || profile?.program || profile?.course_name || '—',
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
                                  program: profile?.program?.name || profile?.program || profile?.course_name || '—',
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
    </Layout>
  )
}

export default SupervisorAssignedInterns
