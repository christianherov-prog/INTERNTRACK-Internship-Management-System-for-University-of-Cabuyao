import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import StatusChangeModal from '../../components/StatusChangeModal'
import StatusHistoryModal from '../../components/StatusHistoryModal'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'
import { formatStudentName } from '../../utils/formatName'


function ChangeSectionModal({ student, onClose, onUpdated }) {
  const [loading, setLoading] = useState(true)
  const [options, setOptions] = useState({ sections: [], section_faculty_map: {} })
  const [form, setForm] = useState({ section: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/coordinator/placement-options')
      .then(res => {
        setOptions(res.data)
        const initialSection = student.student_profile?.section || (res.data.sections?.length > 0 ? res.data.sections[0] : '')
        setForm({ section: initialSection })
      })
      .catch(() => setError('Failed to load options.'))
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    api.patch(`/coordinator/students/${student.id}/section`, form)
      .then(res => onUpdated(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to update section.'))
      .finally(() => setSaving(false))
  }

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Change Section: {student.student_profile?.first_name} {student.student_profile?.last_name}</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? (
                <div className="text-center py-3"><i className="fa fa-spinner fa-spin text-muted fa-2x"></i></div>
              ) : (
                <div className="mb-3">
                  <label className="form-label">Section</label>
                  <select className="form-select" value={form.section} onChange={e => setForm({ section: e.target.value })} required>
                    <option value="">Select Section</option>
                    {options.sections?.map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                  {form.section && (
                    <div className="mt-2 text-muted small">
                      <strong>Assigned Faculty:</strong> {options.section_faculty_map?.[form.section]?.name || (
                        <span className="text-danger fw-bold"><i className="fa fa-warning"></i> No Faculty Assigned to this Section</span>
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={loading || saving}>
                {saving ? 'Saving...' : 'Update Section'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

function BulkChangeSectionModal({ studentIds, onClose, onUpdated }) {
  const [loading, setLoading] = useState(true)
  const [options, setOptions] = useState({ sections: [], section_faculty_map: {} })
  const [form, setForm] = useState({ section: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/coordinator/placement-options')
      .then(res => {
        setOptions(res.data)
        if (res.data.sections?.length > 0) setForm({ section: res.data.sections[0] })
      })
      .catch(() => setError('Failed to load options.'))
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    api.patch(`/coordinator/students/bulk-section`, { student_ids: studentIds, section: form.section })
      .then(res => onUpdated(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to perform bulk update.'))
      .finally(() => setSaving(false))
  }

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Bulk Change Section ({studentIds.length} Students)</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? (
                <div className="text-center py-3"><i className="fa fa-spinner fa-spin text-muted fa-2x"></i></div>
              ) : (
                <div className="mb-3">
                  <label className="form-label">New Section for all selected</label>
                  <select className="form-select" value={form.section} onChange={e => setForm({ section: e.target.value })} required>
                    <option value="">Select Section</option>
                    {options.sections?.map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                  {form.section && (
                    <div className="mt-2 text-muted small">
                      <strong>Assigned Faculty:</strong> {options.section_faculty_map?.[form.section]?.name || (
                        <span className="text-danger fw-bold"><i className="fa fa-warning"></i> No Faculty Assigned to this Section</span>
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={loading || saving}>
                {saving ? 'Saving...' : 'Update Sections'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
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

function CoordRecords() {
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [assigning, setAssigning] = useState(null)
  const [changingSection, setChangingSection] = useState(null)
  const [bulkChangingSection, setBulkChangingSection] = useState(false)
  const [selectedStudents, setSelectedStudents] = useState([])
  const [statusTarget, setStatusTarget] = useState(null)
  const [historyTarget, setHistoryTarget] = useState(null)
  const [message, setMessage] = useState(null)
  const [certLoading, setCertLoading] = useState(null)
  const [archived, setArchived] = useState(false)
  const [archiveBusy, setArchiveBusy] = useState(null)

  const [search, setSearch] = useState("")
  const [programFilter, setProgramFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")
  const [statusFilter, setStatusFilter] = useState("all")

  const fetchRecords = () => {
    setLoading(true)
    api.get('/coordinator/records', { params: { archived: archived ? 1 : 0 } })
      .then(res => setStudents(unwrapList(res.data).items))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchRecords()
  }, [archived])

  const toggleArchive = async (student) => {
    setArchiveBusy(student.id)
    try {
      await api.patch(`/coordinator/students/${student.id}/archive`, { archived: !archived })
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

  const handleSelectAll = (e, currentFiltered) => {
    if (e.target.checked) {
      setSelectedStudents(currentFiltered.map(s => s.id))
    } else {
      setSelectedStudents([])
    }
  }

  const handleSelectOne = (e, id) => {
    if (e.target.checked) {
      setSelectedStudents(prev => [...prev, id])
    } else {
      setSelectedStudents(prev => prev.filter(x => x !== id))
    }
  }

  useEffect(() => {
    setSelectedStudents([])
  }, [search, programFilter, sectionFilter, statusFilter, archived])

  const programs = ["all", ...new Set(students.map(s => s.student_profile?.program?.code || "—").filter(x => x !== "—"))]
  const sections = ["all", ...new Set(students.map(s => formatYearSection(s.student_profile?.section) || "—").filter(x => x !== "—"))]

  const filtered = students.filter(student => {
    const name = formatStudentName(student).toLowerCase()
    const prog = student.student_profile?.program?.code || "—"
    const sec = formatYearSection(student.student_profile?.section) || "—"
    const st = student.active_internship?.status || "none"
    return (!search || name.includes(search.toLowerCase()))
      && (programFilter === "all" || prog === programFilter)
      && (sectionFilter === "all" || sec === sectionFilter)
      && (statusFilter === "all" || st === statusFilter)
  })

  return (
    <Layout title="Records & Placement" subtitle={CURRENT_TERM} icon="fa-folder-open" bodyClass="coordinator-page roster-page">
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
          onAssigned={(internship) => {
            setAssigning(null)
            setMessage({ type: 'success', text: 'Placement assigned successfully.' })
            fetchRecords()
          }}
        />
      )}

      {changingSection && (
        <ChangeSectionModal
          student={changingSection}
          onClose={() => setChangingSection(null)}
          onUpdated={(res) => {
            setChangingSection(null)
            setMessage({ type: 'success', text: `Section updated to ${res.section}.` })
            fetchRecords()
          }}
        />
      )}

      {bulkChangingSection && (
        <BulkChangeSectionModal
          studentIds={selectedStudents}
          onClose={() => setBulkChangingSection(false)}
          onUpdated={(res) => {
            setBulkChangingSection(false)
            setSelectedStudents([])
            setMessage({ type: 'success', text: `Successfully updated sections for ${selectedStudents.length} student(s) to ${res.section}.` })
            fetchRecords()
          }}
        />
      )}

      {statusTarget && (
        <StatusChangeModal
          internshipId={statusTarget.internshipId}
          studentName={statusTarget.studentName}
          currentStatus={statusTarget.status}
          apiBase="coordinator"
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
          apiBase="coordinator"
          onClose={() => setHistoryTarget(null)}
        />
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by name…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 170 }} value={programFilter} onChange={e => setProgramFilter(e.target.value)}>
          {programs.map(p => <option key={p} value={p}>{p === "all" ? "All Departments" : p}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 150 }} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
          {sections.map(s => <option key={s} value={s}>{s === "all" ? "All Sections" : s}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="ongoing">Active / Ongoing</option>
          <option value="completed">Completed</option>
          <option value="suspended">Suspended</option>
          <option value="deferred">Deferred</option>
          <option value="expelled">Expelled</option>
          <option value="pending_placement">Pending Placement</option>
        </select>

        <div className="ms-auto btn-group">
          <button className={`btn btn-sm ${!archived ? "btn-primary" : "btn-outline-secondary"}`} onClick={() => setArchived(false)}>Active</button>
          <button className={`btn btn-sm ${archived ? "btn-secondary" : "btn-outline-secondary"}`} onClick={() => setArchived(true)}>Archived</button>
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>{archived ? 'Archived Students' : 'Student Interns Roster'}</h6>
          {selectedStudents.length > 0 && (
            <button className="btn btn-sm btn-primary ms-3" onClick={() => setBulkChangingSection(true)}>
              <i className="fa fa-users me-1"></i> Change Section ({selectedStudents.length})
            </button>
          )}
          <span className="ms-auto badge bg-secondary">{filtered.length} student{filtered.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            {loading ? (
              <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : filtered.length === 0 ? (
              <div className="text-center py-5 text-muted">No students found matching your criteria.</div>
            ) : (
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style={{ width: '40px' }}>
                      <input type="checkbox" className="form-check-input" checked={selectedStudents.length === filtered.length && filtered.length > 0} onChange={(e) => handleSelectAll(e, filtered)} />
                    </th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(student => {
                    const profile = student.student_profile
                    const internship = student.active_internship
                    const hasInternship = !!internship
                    const name = formatStudentName(student)
                    const st = internship?.status

                    return (
                      <tr key={student.id}>
                        <td>
                          <input type="checkbox" className="form-check-input" checked={selectedStudents.includes(student.id)} onChange={(e) => handleSelectOne(e, student.id)} />
                        </td>
                        <td>{name}</td>
                        <td>{profile?.student_number || student.username}</td>
                        <td>{student.student_profile?.program?.code || "—"}</td>
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
                        <td className="text-center">
                          <button
                            type="button"
                            className={`btn btn-sm ${archived ? 'btn-outline-success' : 'btn-outline-secondary'} me-1`}
                            title={archived ? 'Unarchive' : 'Archive'}
                            disabled={archiveBusy === student.id}
                            onClick={() => toggleArchive(student)}
                          >
                            <i className={`fa ${archived ? 'fa-box-open' : 'fa-box-archive'}`}></i>
                          </button>

                          <button className="btn btn-sm btn-outline-info me-1" onClick={() => setChangingSection(student)}>
                            <i className="fa fa-users me-1"></i> Section
                          </button>
                          {internship?.id ? (
                            <>
                              <button
                                className="btn btn-sm btn-outline-primary me-1"
                                onClick={() => setStatusTarget({
                                  internshipId: internship.id,
                                  studentName: name,
                                  status: st,
                                })}
                              >
                                <i className="fa fa-tag me-1"></i> Status
                              </button>
                              <button
                                className="btn btn-sm btn-outline-secondary me-1"
                                onClick={() => setHistoryTarget({
                                  internshipId: internship.id,
                                  studentName: name,
                                })}
                              >
                                <i className="fa fa-clock-rotate-left me-1"></i> History
                              </button>
                              {(st === 'completed') && (
                                <button
                                  className="btn btn-sm btn-success"
                                  onClick={() => downloadCertificate(internship.id)}
                                  disabled={certLoading === internship.id}
                                >
                                  <i className="fa fa-certificate me-1"></i>
                                  {certLoading === internship.id ? '…' : 'Certificate'}
                                </button>
                              )}
                            </>
                          ) : (
                            <button className="btn btn-sm btn-outline-secondary" disabled>
                              No internship
                            </button>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default CoordRecords
