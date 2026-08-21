import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { formatStudentName } from '../../utils/formatName'
import { useConfirm } from '../../contexts/ConfirmContext'

function CoordHteRequests({ embedded = false }) {
  const confirm = useConfirm()
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/coordinator/hte-requests')
      setRequests(res.data.requests || [])
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load HTE requests.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const updateStatus = async (reqId, newStatus) => {
    if (newStatus === 'approved' && !(await confirm({ message: 'Approving this will add the company to the system for MOA processing. Continue?' }))) {
      return
    }

    setUpdatingId(reqId)
    setSuccessMsg(null)
    setError(null)
    try {
      await api.patch(`/coordinator/hte-requests/${reqId}/status`, { status: newStatus })
      setSuccessMsg('HTE Request status updated.')
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update request.')
    } finally {
      setUpdatingId(null)
    }
  }

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "HTE Requests", subtitle: "Review New Company Requests", icon: "fa-handshake", bodyClass: "coordinator-page" };

  const filtered = requests.filter(r => {
    const name = formatStudentName(r).toLowerCase()
    const status = r.status.toLowerCase()
    return (!search || name.includes(search.toLowerCase()))
      && (statusFilter === "all" || status === statusFilter)
  })

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={load} />}
      {successMsg && (
        <div className="alert alert-success alert-dismissible mb-3">
          {successMsg}
          <button className="btn-close" onClick={() => setSuccessMsg(null)}></button>
        </div>
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by name…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <div className="content-card">
          <div className="content-card-header">
            <i className="fa fa-handshake"></i>
            <h6>HTE Requests</h6>
            <span className="ms-auto badge bg-secondary">{filtered.length} request{filtered.length !== 1 ? "s" : ""}</span>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Requested By</th>
                  <th>Company Name</th>
                  <th>Contact Info</th>
                  <th>Student Remarks</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={6} className="text-center py-4 text-muted">No HTE requests match the selected filters.</td></tr>
                ) : filtered.map(r => (
                  <tr key={r.id}>
                    <td>
                      <div className="fw-semibold">{formatStudentName(r)}</div>
                      <div className="small text-muted">{r.student?.email}</div>
                    </td>
                    <td className="fw-semibold">{r.company_name}</td>
                    <td>
                      <div className="small"><strong>Address:</strong> {r.address || '—'}</div>
                      <div className="small"><strong>Person:</strong> {r.contact_person || '—'}</div>
                      <div className="small"><strong>Email:</strong> {r.contact_email || '—'}</div>
                      <div className="small"><strong>Phone:</strong> {r.contact_number || '—'}</div>
                    </td>
                    <td className="small">{r.remarks || '—'}</td>
                    <td>
                      <span className={`badge ${r.status === 'rejected' ? 'bg-danger' : r.status === 'pending' ? 'bg-warning text-dark' : 'bg-success'}`}>
                        {r.status.toUpperCase()}
                      </span>
                    </td>
                    <td>
                      {r.status === 'pending' && (
                        <div className="d-flex gap-2">
                          <button className="btn btn-sm btn-success" onClick={() => updateStatus(r.id, 'approved')} disabled={updatingId === r.id}>
                            Approve
                          </button>
                          <button className="btn btn-sm btn-danger" onClick={() => updateStatus(r.id, 'rejected')} disabled={updatingId === r.id}>
                            Reject
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </Wrapper>
  )
}

export default CoordHteRequests
