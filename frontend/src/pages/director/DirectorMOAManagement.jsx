import { useEffect, useState } from "react"
import Layout from "../../components/Layout"
import api from "../../services/api"

const MOA_STATUS_OPTIONS = ["active", "pending", "for_renewal", "expired", "on-process"]

const statusColor = s => {
  if (s === "active")      return "badge-active"
  if (s === "expired")     return "badge-inactive"
  if (s === "for_renewal" || s === "on-process") return "badge-warning"
  return "badge-pending"
}

const daysUntilExpiry = dateStr => {
  if (!dateStr) return null
  const diff = Math.ceil((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24))
  return diff
}

function DirectorMOAManagement({ embedded = false }) {
  const [companies, setCompanies]   = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState(null)
  const [search, setSearch]         = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [editId, setEditId]         = useState(null)
  const [editStatus, setEditStatus] = useState("")
  const [editExpiry, setEditExpiry] = useState("")
  const [saving, setSaving]         = useState(false)
  const [saveMsg, setSaveMsg]       = useState(null)

  const load = () => {
    setLoading(true)
    api.get("/director/companies").then(res => {
      setCompanies(res.data.data ?? res.data)
    }).catch(() => setError("Failed to load companies.")).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [])

  const filtered = companies.filter(c => {
    const matchSearch = !search || c.company_name?.toLowerCase().includes(search.toLowerCase()) || c.industry?.toLowerCase().includes(search.toLowerCase())
    const matchStatus = !statusFilter || c.moa_status === statusFilter
    return matchSearch && matchStatus
  })

  const startEdit = (c) => {
    setEditId(c.id); setEditStatus(c.moa_status); setEditExpiry(c.moa_expiry_date ?? ""); setSaveMsg(null)
  }

  const saveEdit = async () => {
    setSaving(true); setSaveMsg(null)
    try {
      await api.put(`/director/companies/${editId}`, { moa_status: editStatus, moa_expiry_date: editExpiry || null })
      setSaveMsg("MOA status updated successfully.")
      setEditId(null)
      load()
    } catch (err) {
      setSaveMsg(err.response?.data?.message || "Update failed.")
    } finally {
      setSaving(false)
    }
  }

  const expiryAlert = (c) => {
    const days = daysUntilExpiry(c.moa_expiry_date)
    if (days === null) return null
    if (days < 0)   return <span className="badge bg-danger ms-1">Expired {Math.abs(days)}d ago</span>
    if (days <= 30) return <span className="badge bg-warning text-dark ms-1">Expires in {days}d</span>
    if (days <= 90) return <span className="badge bg-info text-dark ms-1">Expires in {days}d</span>
    return null
  }

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "MOA Management", subtitle: "Monitor and update HTE partnership agreements", icon: "fa-file-signature", bodyClass: "director-page" };

  return (
    <Wrapper {...wrapperProps}>
      {saveMsg && <div className="alert alert-success mb-3"><i className="fa fa-check-circle me-2"></i>{saveMsg}</div>}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-filter"></i><h6>Filters</h6>
        </div>
        <div className="p-4">
          <div className="row g-3">
            <div className="col-md-6">
              <input className="form-control" placeholder="Search company name or industry..." value={search} onChange={e => setSearch(e.target.value)} />
            </div>
            <div className="col-md-4">
              <select className="form-select" value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
                <option value="">All MOA Statuses</option>
                {MOA_STATUS_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div className="col-md-2">
              <button className="btn btn-outline-secondary w-100" onClick={() => { setSearch(""); setStatusFilter("") }}>
                <i className="fa fa-times me-1"></i>Clear
              </button>
            </div>
          </div>
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-handshake"></i>
          <h6>HTE Partnership List</h6>
          <span className="ms-auto badge bg-secondary">{filtered.length} companies</span>
        </div>
        <div className="table-responsive">
          {loading
            ? <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            : (
              <table className="table table-hover table-sm mb-0">
                <thead className="table-light">
                  <tr>
                    <th>#</th>
                    <th>Company Name</th>
                    <th>Industry</th>
                    <th>MOA Status</th>
                    <th>MOA Start</th>
                    <th>MOA Expiry</th>
                    <th>Interns</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.length === 0
                    ? <tr><td colSpan={8} className="text-center py-4 text-muted">No companies found.</td></tr>
                    : filtered.map((c, i) => (
                      <tr key={c.id} className={c.moa_status === "expired" ? "table-danger" : c.moa_status === "for_renewal" ? "table-warning" : ""}>
                        <td>{i + 1}</td>
                        <td className="fw-semibold">
                          {c.company_name}
                          {expiryAlert(c)}
                        </td>
                        <td>{c.industry ?? "—"}</td>
                        <td>
                          {editId === c.id
                            ? <select className="form-select form-select-sm" value={editStatus} onChange={e => setEditStatus(e.target.value)}>
                                {MOA_STATUS_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
                              </select>
                            : <span className={`badge-status ${statusColor(c.moa_status)}`}>{c.moa_status}</span>}
                        </td>
                        <td>{c.moa_start_date ?? "—"}</td>
                        <td>
                          {editId === c.id
                            ? <input type="date" className="form-control form-control-sm" value={editExpiry} onChange={e => setEditExpiry(e.target.value)} />
                            : c.moa_expiry_date ?? "—"}
                        </td>
                        <td className="text-center">{c.internships_count ?? "—"}</td>
                        <td>
                          {editId === c.id
                            ? <div className="d-flex gap-1">
                                <button className="btn btn-success btn-sm" onClick={saveEdit} disabled={saving}>
                                  {saving ? <i className="fa fa-spinner fa-spin"></i> : <><i className="fa fa-save me-1"></i>Save</>}
                                </button>
                                <button className="btn btn-outline-secondary btn-sm" onClick={() => setEditId(null)}>Cancel</button>
                              </div>
                            : <button className="btn btn-outline-primary btn-sm" onClick={() => startEdit(c)}>
                                <i className="fa fa-edit me-1"></i>Update MOA
                              </button>}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            )}
        </div>
      </div>
    </Wrapper>
  )
}

export default DirectorMOAManagement
