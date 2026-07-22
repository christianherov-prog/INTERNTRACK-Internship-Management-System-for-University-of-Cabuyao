import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function studentName(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  if (p) return `${p.first_name || ''} ${p.last_name || ''}`.trim()
  return row?.student?.username || '—'
}

function FacultyAssignedStudents() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [archived, setArchived] = useState(false)
  const [busyId, setBusyId] = useState(null)
  const [message, setMessage] = useState(null)

  const fetchStudents = () => {
    setLoading(true)
    setError(null)
    api.get('/faculty/assigned-students', { params: { archived: archived ? 1 : 0 } })
      .then((res) => {
        setRows(unwrapList(res.data).items)
      })
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
      setMessage({
        type: 'success',
        text: archived ? 'Student restored to Active.' : 'Student archived.',
      })
      fetchStudents()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Archive action failed.' })
    } finally {
      setBusyId(null)
    }
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

      <div className="btn-group mb-3" role="group" aria-label="Active or archived">
        <button type="button" className={`btn btn-sm ${!archived ? 'btn-success' : 'btn-outline-success'}`} onClick={() => setArchived(false)}>Active</button>
        <button type="button" className={`btn btn-sm ${archived ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={() => setArchived(true)}>Archived</button>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>{archived ? 'Archived Students' : 'My Assigned Students'}</h6>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="text-center text-muted py-4">
                        {archived ? 'No archived students.' : 'No assigned students yet.'}
                      </td>
                    </tr>
                  ) : rows.map((row) => {
                    const profile = row.student?.student_profile || row.student?.studentProfile
                    return (
                      <tr key={row.id}>
                        <td className="fw-semibold">{studentName(row)}</td>
                        <td>{row.student?.username || profile?.student_number || '—'}</td>
                        <td>{row.program || profile?.program || profile?.course_name || '—'}</td>
                        <td>{row.company?.company_name || '—'}</td>
                        <td>
                          <span className={`badge-status ${row.status === 'ongoing' ? 'badge-active' : 'badge-pending'}`}>
                            {(row.status || '—').replace(/_/g, ' ')}
                          </span>
                        </td>
                        <td className="d-flex gap-1">
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
          )}
        </div>
      </div>
    </Layout>
  )
}

export default FacultyAssignedStudents
