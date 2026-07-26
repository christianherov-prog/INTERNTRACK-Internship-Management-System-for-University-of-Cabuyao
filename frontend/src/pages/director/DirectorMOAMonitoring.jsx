import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import ReportExportModal from '../../components/modals/ReportExportModal'

function DirectorMOAMonitoring() {
  const [companies, setCompanies] = useState([])
  const [loading, setLoading]     = useState(true)
  const [error, setError]         = useState(null)
  const [exportPreview, setExportPreview] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/director/moa-monitoring')
      .then(res => setCompanies(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load MOA monitoring.')
        setCompanies([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const urgencyColor = (days) => {
    if (days === null || days === undefined) return '#64748b'
    if (days < 0)   return '#dc2626'
    if (days < 30)  return '#ea580c'
    if (days < 90)  return '#d97706'
    return '#15803d'
  }

  const urgencyLabel = (days) => {
    if (days === null || days === undefined) return 'No Expiry Set'
    if (days < 0)   return `Expired ${Math.abs(days)}d ago`
    if (days < 30)  return `Expires in ${days}d`
    if (days < 90)  return `${days}d remaining`
    return 'Valid'
  }

  const moaBadge = { active: 'badge-active', pending: 'badge-pending', expired: 'badge-inactive', for_renewal: 'badge-pending', 'on-process': 'badge-pending' }

  const active   = companies.filter(c => c.moa_status === 'active').length
  const forRenew = companies.filter(c => c.moa_status === 'for_renewal' || (c.expires_in_days !== null && c.expires_in_days < 60)).length
  const expired  = companies.filter(c => c.moa_status === 'expired').length

  const handleExportCsv = () => {
    setExportPreview({
      title: 'MOA Monitoring Report',
      filename: 'moa-monitoring',
      rows: companies.map(c => ({
        Company: c.company_name, Status: c.moa_status, 'Expiry Date': c.moa_expiry_date ?? '—',
        'Urgency (days)': c.expires_in_days ?? '—', Contact: c.contact_person ?? '—', Slots: c.slots_available,
      })),
    })
  }

  return (
    <Layout title="MOA Monitoring" subtitle="Memorandum of Agreement Tracker" icon="fa-file-signature" bodyClass="director-page">
      {error && <PageError message={error} onRetry={load} />}

      {/* Summary — shared .stat-card (same as Student/Coordinator) */}
      <div className="row g-3 mb-4">
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon green"><i className="fa fa-file-signature"></i></div><div><div className="stat-value">{active}</div><div className="stat-label">Active MOAs</div></div></div>
        </div>
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon amber"><i className="fa fa-clock"></i></div><div><div className="stat-value">{forRenew}</div><div className="stat-label">For Renewal</div></div></div>
        </div>
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon blue"><i className="fa fa-triangle-exclamation"></i></div><div><div className="stat-value">{expired}</div><div className="stat-label">Expired</div></div></div>
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-file-signature"></i><h6>MOA Status by Company</h6>
          <button className="btn btn-sm btn-outline-success ms-auto" onClick={handleExportCsv} disabled={companies.length === 0}>
            <i className="fa fa-file-csv me-1"></i>Export CSV
          </button>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead><tr><th>Company</th><th>Status</th><th>Expiry Date</th><th>Urgency</th><th>Contact</th><th>Slots</th></tr></thead>
                <tbody>
                  {companies.length === 0 && !error ? (
                    <tr><td colSpan={6} className="text-center text-muted py-4">No companies found.</td></tr>
                  ) : companies.length === 0 ? null : companies.map(c => (
                    <tr key={c.id}>
                      <td className="fw-semibold">{c.company_name}</td>
                      <td><span className={`badge-status ${moaBadge[c.moa_status] ?? 'badge-pending'}`}>{c.moa_status?.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
                      <td style={{fontSize:'0.82rem'}}>{c.moa_expiry_date ?? '—'}</td>
                      <td>
                        <span style={{fontSize:'0.82rem',fontWeight:600,color:urgencyColor(c.expires_in_days)}}>
                          <i className="fa fa-circle me-1" style={{fontSize:'0.6rem'}}></i>
                          {urgencyLabel(c.expires_in_days)}
                        </span>
                      </td>
                      <td style={{fontSize:'0.82rem'}}>{c.contact_person ?? '—'}</td>
                      <td>{c.slots_available}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
      <ReportExportModal preview={exportPreview} onClose={() => setExportPreview(null)} />
    </Layout>
  )
}

export default DirectorMOAMonitoring
