import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import StatusChangeModal from '../../components/StatusChangeModal'
import StatusHistoryModal from '../../components/StatusHistoryModal'
import ModalPortal from '../../components/modals/ModalPortal'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function AssignPlacementModal({ student, onClose, onAssigned }) {
  const [loading, setLoading] = useState(true)
  const [options, setOptions] = useState({ companies: [], faculty: [], supervisors: [] })
  const [form, setForm] = useState({ company_id: '', faculty_id: '', supervisor_id: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/director/placement-options')
      .then(res => {
        setOptions(res.data)
        if (res.data.companies.length > 0) setForm(f => ({ ...f, company_id: res.data.companies[0].id }))
        if (res.data.faculty.length > 0) setForm(f => ({ ...f, faculty_id: res.data.faculty[0].id }))
        if (res.data.supervisors.length > 0) setForm(f => ({ ...f, supervisor_id: res.data.supervisors[0].id }))
      })
      .catch(() => setError('Failed to load placement options.'))
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)

    const internshipId = student.active_internship?.id
    if (!internshipId) {
      setError('Student does not have a pending internship record.')
      setSaving(false)
      return
    }

    api.post(`/director/internships/${internshipId}/place`, form)
      .then(res => {
        onAssigned(res.data.internship)
      })
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to assign placement.')
      })
      .finally(() => setSaving(false))
  }

  return (
    <ModalPortal>
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Assign Placement: {student.student_profile?.first_name} {student.student_profile?.last_name}</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}

              {loading ? (
                <div className="text-center py-3"><i className="fa fa-spinner fa-spin text-muted fa-2x"></i></div>
              ) : (
                <>
                  <div className="mb-3">
                    <label className="form-label">Host Training Establishment (Company)</label>
                    <select className="form-select" value={form.company_id} onChange={e => setForm({ ...form, company_id: e.target.value })} required>
                      {options.companies.map(c => <option key={c.id} value={c.id}>{c.company_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Faculty Supervisor</label>
                    <select className="form-select" value={form.faculty_id} onChange={e => setForm({ ...form, faculty_id: e.target.value })} required>
                      {options.faculty.map(f => <option key={f.id} value={f.id}>{f.faculty_profile?.first_name} {f.faculty_profile?.last_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Industry Supervisor</label>
                    <select className="form-select" value={form.supervisor_id} onChange={e => setForm({ ...form, supervisor_id: e.target.value })} required>
                      {options.supervisors.map(s => <option key={s.id} value={s.id}>{s.supervisor_profile?.first_name} {s.supervisor_profile?.last_name}</option>)}
                    </select>
                  </div>
                </>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn btn-green" disabled={loading || saving}>
                {saving ? 'Assigning...' : 'Authorize Deployment'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    </ModalPortal>
  )
}

function statusBadgeClass(status) {
  const s = status === 'ongoing' ? 'active' : status
  if (s === 'active' || s === 'placed') return 'badge-active'
  if (s === 'completed') return 'badge-completed'
  if (s === 'pending_placement') return 'badge-pending'
  if (s === 'suspended' || s === 'deferred' || s === 'expelled') return 'badge-inactive'
  return 'badge-inactive'
}

function statusLabel(status) {
  if (!status) return 'No Active Internship'
  const map = {
    ongoing: 'Active',
    active: 'Active',
    completed: 'Completed',
    suspended: 'Suspended',
    deferred: 'Deferred',
    expelled: 'Expelled',
    pending_placement: 'Pending Placement',
  }
  return map[status] || String(status).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function DirectorInternships() {
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [assigning, setAssigning] = useState(null)
  const [statusTarget, setStatusTarget] = useState(null)
  const [historyTarget, setHistoryTarget] = useState(null)
  const [message, setMessage] = useState(null)
  const [certLoading, setCertLoading] = useState(null)
  const [archived, setArchived] = useState(false)
  const [archiveBusy, setArchiveBusy] = useState(null)
  const [programSearch, setProgramSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')

  const fetchRecords = () => {
    setLoading(true)
    api.get('/director/records', { params: { archived: archived ? 1 : 0, program: programSearch } })
      .then(res => setStudents(unwrapList(res.data).items))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      fetchRecords()
    }, 400)
    return () => clearTimeout(delayDebounceFn)
  }, [archived, programSearch])

  const toggleArchive = async (student) => {
    setArchiveBusy(student.id)
    try {
      await api.patch(`/director/students/${student.id}/archive`, { archived: !archived })
      setMessage({
        type: 'success',
        text: archived ? 'Student restored to Active.' : 'Student archived.',
      })
      fetchRecords()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Archive action failed.' })
    } finally {
      setArchiveBusy(null)
    }
  }

  const downloadCertificate = async (internshipId) => {
    setCertLoading(internshipId)
    try {
      // NOTE: Certificate generation is typically Coordinator, but we'll try Director if they have access.
      const res = await api.get(`/coordinator/internships/${internshipId}/certificate`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      const a = document.createElement('a')
      a.href = url
      a.download = `completion-certificate-${internshipId}.pdf`
      a.click()
      window.URL.revokeObjectURL(url)
      setMessage({ type: 'success', text: 'Certificate PDF generated from student data.' })
    } catch (err) {
      let text = 'Could not generate certificate (status must be Completed).'
      if (err.response?.data instanceof Blob) {
        try {
          const j = JSON.parse(await err.response.data.text())
          text = j.message || text
        } catch { /* ignore */ }
      }
      setMessage({ type: 'danger', text })
    } finally {
      setCertLoading(null)
    }
  }
  
  const filteredStudents = students.filter(s => {
    if (!searchInput) return true;
    const q = searchInput.toLowerCase();
    const name = s.student_profile ? `${s.student_profile.first_name} ${s.student_profile.last_name}` : s.username;
    return name.toLowerCase().includes(q) || s.username.toLowerCase().includes(q);
  })

  return (
    <Layout title="Student Roster & Placements" subtitle={CURRENT_TERM} icon="fa-folder-open" bodyClass="director-page roster-page">
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {assigning && (
        <AssignPlacementModal
          student={assigning}
          onClose={() => setAssigning(null)}
          onAssigned={() => {
            setAssigning(null)
            fetchRecords()
          }}
        />
      )}

      {statusTarget && (
        <StatusChangeModal
          internshipId={statusTarget.internshipId}
          studentName={statusTarget.studentName}
          currentStatus={statusTarget.status}
          apiBase="director"
          onClose={() => setStatusTarget(null)}
          onSaved={() => {
            setStatusTarget(null)
            setMessage({ type: 'success', text: 'Internship status updated; reason saved to history.' })
            fetchRecords()
          }}
        />
      )}

      {historyTarget && (
        <StatusHistoryModal
          internshipId={historyTarget.internshipId}
          studentName={historyTarget.studentName}
          apiBase="director"
          onClose={() => setHistoryTarget(null)}
        />
      )}

      <div className="d-flex justify-content-between align-items-center mb-3">
        <div className="btn-group" role="group" aria-label="Active or archived">
          <button type="button" className={`btn btn-sm ${!archived ? 'btn-success' : 'btn-outline-success'}`} onClick={() => setArchived(false)}>Active</button>
          <button type="button" className={`btn btn-sm ${archived ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={() => setArchived(true)}>Archived</button>
        </div>
        <div className="d-flex gap-2">
            <input 
              type="text" 
              className="form-control form-control-sm" 
              placeholder="Filter by Department/Program (e.g. BSIT)" 
              value={programSearch} 
              onChange={e => setProgramSearch(e.target.value)} 
              style={{ width: '250px' }} 
            />
            <input 
              type="text" 
              className="form-control form-control-sm" 
              placeholder="Search Student..." 
              value={searchInput} 
              onChange={e => setSearchInput(e.target.value)} 
              style={{ width: '200px' }} 
            />
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>{archived ? 'Archived Students' : 'Student Interns Roster (Cross-Department)'}</h6>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            {loading ? (
              <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : (
              <table className="table table-hover mb-0 dir-roster-table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th className="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredStudents.length === 0 ? (
                    <tr><td colSpan="6" className="text-center py-4 text-muted">No students found.</td></tr>
                  ) : (
                    filteredStudents.map(s => {
                      const profile = s.student_profile
                      const internship = s.active_internship
                      const name = profile ? `${profile.first_name} ${profile.last_name}` : s.username
                      const st = internship?.status
                      const canAssign = st === 'pending_placement'

                      return (
                        <tr key={s.id}>
                          <td>{name}</td>
                          <td>{profile?.student_number || s.username}</td>
                          <td>{profile?.course_name || profile?.program || '—'}</td>
                          <td>{internship?.company?.company_name || '—'}</td>
                          <td>
                            <span className={`badge-status ${statusBadgeClass(st)}`}>
                              {statusLabel(st)}
                            </span>
                            {internship?.status_reason && (
                              <div className="text-muted mt-1" style={{ fontSize: '0.75rem', maxWidth: 180 }} title={internship.status_reason}>
                                {internship.status_reason.length > 48
                                  ? `${internship.status_reason.slice(0, 48)}…`
                                  : internship.status_reason}
                              </div>
                            )}
                          </td>
                          <td className="text-end">
                            <div className="dir-roster-actions" role="group" aria-label={`Actions for ${name}`}>
                              <button
                                type="button"
                                className={`btn btn-sm ${archived ? 'btn-outline-success' : 'btn-outline-secondary'}`}
                                title={archived ? 'Unarchive' : 'Archive'}
                                disabled={archiveBusy === s.id}
                                onClick={() => toggleArchive(s)}
                              >
                                <i className={`fa ${archived ? 'fa-box-open' : 'fa-box-archive'}`}></i>
                              </button>
                              <button
                                type="button"
                                className={`btn btn-sm dir-roster-assign ${canAssign ? 'btn-green' : 'btn-outline-secondary'}`}
                                disabled={!canAssign}
                                title={canAssign ? 'Assign placement' : 'Assign available when status is Pending Placement'}
                                onClick={() => canAssign && setAssigning(s)}
                              >
                                <i className="fa fa-map-pin me-1"></i>Assign
                              </button>
                              {internship?.id ? (
                                <>
                                  <button
                                    type="button"
                                    className="btn btn-sm btn-outline-green"
                                    onClick={() => setStatusTarget({
                                      internshipId: internship.id,
                                      studentName: name,
                                      status: st,
                                    })}
                                  >
                                    <i className="fa fa-tag me-1"></i>Status
                                  </button>
                                  <button
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => setHistoryTarget({
                                      internshipId: internship.id,
                                      studentName: name,
                                    })}
                                  >
                                    <i className="fa fa-clock-rotate-left me-1"></i>History
                                  </button>
                                </>
                              ) : (
                                <>
                                  <button type="button" className="btn btn-sm btn-outline-secondary" disabled title="No internship">
                                    <i className="fa fa-tag me-1"></i>Status
                                  </button>
                                  <button type="button" className="btn btn-sm btn-outline-secondary" disabled title="No internship">
                                    <i className="fa fa-clock-rotate-left me-1"></i>History
                                  </button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default DirectorInternships
