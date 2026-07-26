import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import ClassListUploadModal from '../../components/ClassListUploadModal'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function studentName(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  if (p) return `${p.first_name || ''} ${p.last_name || ''}`.trim()
  return row?.student?.username || '—'
}

function studentSection(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  return p?.section || row?.program || '—'
}

function FacultyAssignedStudents() {
  const [rows, setRows]             = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState(null)
  const [archived, setArchived]     = useState(false)
  const [busyId, setBusyId]         = useState(null)
  const [message, setMessage]       = useState(null)
  const [search, setSearch]         = useState('')
  const [showUpload, setShowUpload] = useState(false)
  const [sectionFilter, setSectionFilter] = useState('all')
  const [sexFilter, setSexFilter] = useState('all')
  const [placementFilter, setPlacementFilter] = useState('all')

  const fetchStudents = () => {
    setLoading(true)
    setError(null)
    api.get('/faculty/assigned-students', { params: { archived: archived ? 1 : 0 } })
      .then((res) => setRows(unwrapList(res.data).items))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load assigned students.')
        setRows([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchStudents() }, [archived])

  const toggleArchive = async (row) => {
    const studentId = row.student?.id
    if (!studentId) return
    setBusyId(studentId)
    setMessage(null)
    try {
      await api.patch(`/faculty/students/${studentId}/archive`, { archived: !archived })
      setMessage({ type: 'success', text: archived ? 'Student restored to Active.' : 'Student archived.' })
      fetchStudents()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Archive action failed.' })
    } finally {
      setBusyId(null)
    }
  }

  // â”€â”€ Computed values â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const sections = ['all', ...new Set(rows.map(r => studentSection(r)).filter(s => s !== '—'))]

  const filtered = rows.filter(r => {
    const name = studentName(r).toLowerCase()
    const sec  = studentSection(r)
    const sex  = r.student?.sex || '—'
    const isPlaced = !!r.company && r.status !== 'unplaced'

    const matchSearch  = !search || name.includes(search.toLowerCase())
    const matchSection = sectionFilter === 'all' || sec === sectionFilter
    const matchSex     = sexFilter === 'all' || sex.toLowerCase() === sexFilter.toLowerCase()
    const matchPlacement = placementFilter === 'all'
      || (placementFilter === 'placed' && isPlaced)
      || (placementFilter === 'unplaced' && !isPlaced)

    return matchSearch && matchSection && matchSex && matchPlacement
  })

  // Group by section for a cleaner view
  const grouped = filtered.reduce((acc, row) => {
    const sec = studentSection(row)
    if (!acc[sec]) acc[sec] = []
    acc[sec].push(row)
    return acc
  }, {})

  const statusBadge = (status) => {
    const map = {
      ongoing:           'badge-active',
      active:            'badge-active',
      for_evaluation:    'badge-pending',
      completed:         'badge-active',
      placed:            'badge-pending',
      pending_placement: 'badge-pending',
      unplaced:          'badge-inactive',
    }
    return (
      <span className={`badge-status ${map[status] ?? 'badge-inactive'}`}>
        {(status || '—').replace(/_/g, ' ')}
      </span>
    )
  }

  return (
    <Layout title="Assigned Students" subtitle={CURRENT_TERM} icon="fa-users" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={fetchStudents} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {/* Toolbar */}
      <div className="d-flex flex-wrap gap-2 align-items-center mb-3 justify-content-between">
        <div className="d-flex gap-2 flex-wrap align-items-center">
          <div className="btn-group" role="group">
            <button className={`btn btn-sm ${!archived ? 'btn-success' : 'btn-outline-success'}`} onClick={() => setArchived(false)}>
              Active
            </button>
            <button className={`btn btn-sm ${archived ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={() => setArchived(true)}>
              Archived
            </button>
          </div>
          <input
            className="form-control form-control-sm"
            style={{ width: 200 }}
            placeholder="Search by name…"
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
          <select
            className="form-select form-select-sm"
            style={{ width: 150 }}
            value={sectionFilter}
            onChange={e => setSectionFilter(e.target.value)}
          >
            {sections.map(s => (
              <option key={s} value={s}>{s === 'all' ? 'All Sections' : s}</option>
            ))}
          </select>
          <select
            className="form-select form-select-sm"
            style={{ width: 120 }}
            value={sexFilter}
            onChange={e => setSexFilter(e.target.value)}
          >
            <option value="all">All Sexes</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
          <select
            className="form-select form-select-sm"
            style={{ width: 140 }}
            value={placementFilter}
            onChange={e => setPlacementFilter(e.target.value)}
          >
            <option value="all">All Status</option>
            <option value="placed">Placed</option>
            <option value="unplaced">Unplaced</option>
          </select>
        </div>

        <button className="btn btn-sm btn-success" onClick={() => setShowUpload(true)}>
          <i className="fa fa-file-excel me-2"></i>Upload Class List
        </button>
      </div>

      {/* Info banner for unplaced students */}
      <div className="alert alert-info mb-3 py-2" style={{ fontSize: '0.88rem' }}>
        <i className="fa fa-circle-info me-2"></i>
        <strong>Tip:</strong> Students uploaded via class list appear here even if they haven't applied to a company yet.
        Status will show as <em>not placed</em> until they are assigned to an internship.
      </div>

      {/* Grouped by Section */}
      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : Object.keys(grouped).length === 0 ? (
        <div className="content-card">
          <div className="text-center py-5 text-muted">
            <i className="fa fa-users fa-3x mb-3 d-block opacity-25"></i>
            {archived ? 'No archived students.' : 'No students assigned yet. Upload a class list to get started.'}
          </div>
        </div>
      ) : (
        Object.entries(grouped).map(([section, sectionRows]) => (
          <div key={section} className="content-card mb-4">
            <div className="content-card-header">
              <i className="fa fa-layer-group"></i>
              <h6>Section: {section}</h6>
              <span className="ms-auto badge bg-secondary">{sectionRows.length} student{sectionRows.length !== 1 ? 's' : ''}</span>
            </div>
            <div className="table-card">
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Student ID</th>
                      <th>Program</th>
                      <th>Company</th>
                      <th>OJT Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sectionRows.map((row) => {
                      const profile = row.student?.student_profile || row.student?.studentProfile
                      const hasInternship = !!row.company
                      return (
                        <tr key={row.id}>
                          <td className="fw-semibold">{studentName(row)}</td>
                          <td>{row.student?.username || profile?.student_number || '—'}</td>
                          <td>{row.program || profile?.program || '—'}</td>
                          <td>
                            {hasInternship
                              ? row.company?.company_name || row.company?.name || '—'
                              : <span className="text-muted fst-italic">Not yet placed</span>
                            }
                          </td>
                          <td>{statusBadge(row.status)}</td>
                          <td className="d-flex gap-1">
                            <Link
                              to={`/faculty/students/${row.student?.id}/progress`}
                              className="btn btn-sm btn-outline-green"
                              title="View progress"
                            >
                              <i className="fa fa-chart-line"></i>
                            </Link>
                            <Link to="/faculty/journals" className="btn btn-sm btn-outline-success" title="Review journals">
                              <i className="fa fa-eye"></i>
                            </Link>
                            <button
                              type="button"
                              className={`btn btn-sm ${archived ? 'btn-outline-success' : 'btn-outline-secondary'}`}
                              title={archived ? 'Unarchive' : 'Archive'}
                              disabled={busyId === row.student?.id}
                              onClick={() => toggleArchive(row)}
                            >
                              <i className={`fa ${archived ? 'fa-box-open' : 'fa-box-archive'} ${busyId === row.student?.id ? 'fa-spin' : ''}`}></i>
                            </button>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        ))
      )}

      {/* Class List Upload Modal */}
      {showUpload && (
        <ClassListUploadModal
          onClose={() => setShowUpload(false)}
          onSuccess={(msg) => {
            setMessage({ type: 'success', text: msg })
            fetchStudents()
          }}
        />
      )}
    </Layout>
  )
}

export default FacultyAssignedStudents
