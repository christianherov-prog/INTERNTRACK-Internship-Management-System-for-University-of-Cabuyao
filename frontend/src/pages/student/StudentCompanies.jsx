import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function StudentCompanies() {
  const [activeTab, setActiveTab] = useState('companies')
  const [companies, setCompanies] = useState([])
  const [applications, setApplications] = useState([])
  const [hteRequests, setHteRequests] = useState([])

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)

  // HTE Request Form State
  const [newHte, setNewHte] = useState({
    company_name: '', address: '', contact_person: '', contact_email: '', contact_number: '', remarks: ''
  })
  const [submitting, setSubmitting] = useState(false)

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [compRes, appRes, hteRes] = await Promise.all([
        api.get('/student/companies'),
        api.get('/student/applications'),
        api.get('/student/hte-requests')
      ])
      setCompanies(compRes.data.companies || [])
      setApplications(appRes.data.applications || [])
      setHteRequests(hteRes.data.requests || [])
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load placement data.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { loadData() }, [])

  const applyToCompany = async (companyId) => {
    if (!window.confirm('Are you sure you want to apply to this company?')) return
    setSubmitting(true)
    setSuccessMsg(null)
    setError(null)
    try {
      const res = await api.post('/student/applications', { company_id: companyId })
      setSuccessMsg(res.data.message || 'Application submitted successfully!')
      setActiveTab('applications')
      loadData()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit application.')
    } finally {
      setSubmitting(false)
    }
  }

  const submitHteRequest = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setSuccessMsg(null)
    setError(null)
    try {
      const res = await api.post('/student/hte-requests', newHte)
      setSuccessMsg(res.data.message || 'HTE Request submitted successfully!')
      setNewHte({ company_name: '', address: '', contact_person: '', contact_email: '', contact_number: '', remarks: '' })
      setActiveTab('applications')
      loadData()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit HTE request.')
    } finally {
      setSubmitting(false)
    }
  }

  const getStatusBadge = (status) => {
    if (!status) return 'badge bg-secondary'
    if (status.includes('approved') || status.includes('accepted')) return 'badge bg-success'
    if (status.includes('rejected')) return 'badge bg-danger'
    return 'badge bg-warning text-dark'
  }

  const TABS = [
    { key: 'companies',    icon: 'fa-building',    label: 'Eligible Companies',   count: companies.length },
    { key: 'applications', icon: 'fa-paper-plane', label: 'My Applications',      count: applications.length + hteRequests.length },
    { key: 'request',      icon: 'fa-plus-circle', label: 'Request New HTE',      count: null },
  ]

  return (
    <Layout title="Placement Hub" subtitle="Companies & Applications" icon="fa-building" bodyClass="student-page">
      {error && <PageError message={error} onRetry={loadData} />}
      {successMsg && (
        <div className="alert alert-success alert-dismissible mb-3 d-flex align-items-center gap-2">
          <i className="fa fa-check-circle"></i>
          <span>{successMsg}</span>
          <button className="btn-close ms-auto" onClick={() => setSuccessMsg(null)}></button>
        </div>
      )}

      {/* ── Tab Bar ── */}
      <div className="placement-tabs-bar mb-4">
        {TABS.map(tab => (
          <button
            key={tab.key}
            id={`placement-tab-${tab.key}`}
            className={`placement-tab-btn${activeTab === tab.key ? ' active' : ''}`}
            onClick={() => setActiveTab(tab.key)}
          >
            <i className={`fa ${tab.icon}`}></i>
            <span>{tab.label}</span>
            {tab.count !== null && tab.count > 0 && (
              <span className={`placement-tab-count${activeTab === tab.key ? ' active' : ''}`}>
                {tab.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-5">
          <i className="fa fa-spinner fa-spin fa-2x text-muted"></i>
          <p className="text-muted mt-3 mb-0">Loading placement data…</p>
        </div>
      ) : !error && (
        <>
          {/* ── Eligible Companies ── */}
          {activeTab === 'companies' && (
            <div className="content-card">
              <div className="content-card-header">
                <i className="fa fa-list"></i>
                <h6>Accredited Host Training Establishments</h6>
                <span className="ms-auto badge bg-success-subtle text-success fw-semibold" style={{ fontSize: '0.75rem' }}>
                  {companies.length} available
                </span>
              </div>
              {companies.length === 0 ? (
                <div className="placement-empty-state">
                  <i className="fa fa-building-circle-xmark fa-3x text-muted mb-3"></i>
                  <p className="fw-semibold text-dark mb-1">No eligible companies right now</p>
                  <p className="text-muted small mb-0">Check back later, or use <strong>Request New HTE</strong> to suggest a company.</p>
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Company Name</th>
                        <th>Address</th>
                        <th className="text-center">Available Slots</th>
                        <th className="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {companies.map(c => (
                        <tr key={c.id}>
                          <td>
                            <div className="fw-semibold text-dark">{c.company_name}</div>
                            {c.industry && <div className="text-muted small">{c.industry}</div>}
                          </td>
                          <td className="text-muted">{c.address || '—'}</td>
                          <td className="text-center">
                            <span className={`badge ${c.slots_available > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} fw-semibold`}>
                              {c.slots_available} slot{c.slots_available !== 1 ? 's' : ''}
                            </span>
                          </td>
                          <td className="text-center">
                            <button
                              id={`apply-company-${c.id}`}
                              className="btn btn-sm btn-primary px-3"
                              onClick={() => applyToCompany(c.id)}
                              disabled={submitting || c.slots_available === 0}
                            >
                              {submitting ? <i className="fa fa-spinner fa-spin me-1"></i> : <i className="fa fa-paper-plane me-1"></i>}
                              Apply
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}

          {/* ── My Applications ── */}
          {activeTab === 'applications' && (
            <div className="row g-4">
              <div className="col-lg-6">
                <div className="content-card h-100">
                  <div className="content-card-header">
                    <i className="fa fa-paper-plane"></i>
                    <h6>Company Applications</h6>
                    <span className="ms-auto badge bg-secondary-subtle text-secondary fw-semibold" style={{ fontSize: '0.75rem' }}>
                      {applications.length}
                    </span>
                  </div>
                  {applications.length === 0 ? (
                    <div className="placement-empty-state">
                      <i className="fa fa-inbox fa-2x text-muted mb-2"></i>
                      <p className="text-muted small mb-0">No applications submitted yet.<br />Browse <strong>Eligible Companies</strong> to apply.</p>
                    </div>
                  ) : (
                    <div className="table-responsive">
                      <table className="table table-hover mb-0">
                        <thead>
                          <tr>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Remarks</th>
                          </tr>
                        </thead>
                        <tbody>
                          {applications.map(a => (
                            <tr key={a.id}>
                              <td className="fw-semibold">{a.company?.company_name}</td>
                              <td><span className={getStatusBadge(a.status)}>{a.status.replace(/_/g, ' ').toUpperCase()}</span></td>
                              <td className="small text-muted">{a.coordinator_remarks || '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
              <div className="col-lg-6">
                <div className="content-card h-100">
                  <div className="content-card-header">
                    <i className="fa fa-handshake"></i>
                    <h6>New HTE Requests</h6>
                    <span className="ms-auto badge bg-secondary-subtle text-secondary fw-semibold" style={{ fontSize: '0.75rem' }}>
                      {hteRequests.length}
                    </span>
                  </div>
                  {hteRequests.length === 0 ? (
                    <div className="placement-empty-state">
                      <i className="fa fa-building-circle-check fa-2x text-muted mb-2"></i>
                      <p className="text-muted small mb-0">No HTE requests submitted yet.<br />Use the <strong>Request New HTE</strong> tab.</p>
                    </div>
                  ) : (
                    <div className="table-responsive">
                      <table className="table table-hover mb-0">
                        <thead>
                          <tr>
                            <th>Company Requested</th>
                            <th>Status</th>
                            <th>Remarks</th>
                          </tr>
                        </thead>
                        <tbody>
                          {hteRequests.map(r => (
                            <tr key={r.id}>
                              <td className="fw-semibold">{r.company_name}</td>
                              <td><span className={getStatusBadge(r.status)}>{r.status.toUpperCase()}</span></td>
                              <td className="small text-muted">{r.remarks || '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* ── Request New HTE ── */}
          {activeTab === 'request' && (
            <div className="content-card" style={{ maxWidth: '780px', margin: '0 auto' }}>
              <div className="content-card-header">
                <i className="fa fa-plus-circle"></i>
                <h6>Request a New Host Training Establishment</h6>
              </div>
              <div className="p-4">
                <div className="alert alert-info d-flex gap-3 align-items-start small mb-4 border-0 rounded-3">
                  <i className="fa fa-circle-info fa-lg mt-1 flex-shrink-0"></i>
                  <span>
                    Found a company not on our accredited list? Submit an HTE Request and our coordinator will review it,
                    then process the necessary <strong>Memorandum of Agreement (MOA)</strong> with the company.
                  </span>
                </div>
                <form onSubmit={submitHteRequest}>
                  <div className="mb-3">
                    <label className="form-label fw-semibold">Company Name <span className="text-danger">*</span></label>
                    <input
                      id="hte-company-name"
                      type="text"
                      className="form-control"
                      required
                      value={newHte.company_name}
                      onChange={e => setNewHte({...newHte, company_name: e.target.value})}
                      placeholder="e.g. Acme Technologies Inc."
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label fw-semibold">Company Address</label>
                    <input
                      id="hte-company-address"
                      type="text"
                      className="form-control"
                      value={newHte.address}
                      onChange={e => setNewHte({...newHte, address: e.target.value})}
                      placeholder="e.g. BGC, Taguig City"
                    />
                  </div>
                  <div className="row mb-3 g-3">
                    <div className="col-md-4">
                      <label className="form-label fw-semibold">Contact Person</label>
                      <input id="hte-contact-person" type="text" className="form-control" value={newHte.contact_person} onChange={e => setNewHte({...newHte, contact_person: e.target.value})} placeholder="Full name" />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold">Contact Email</label>
                      <input id="hte-contact-email" type="email" className="form-control" value={newHte.contact_email} onChange={e => setNewHte({...newHte, contact_email: e.target.value})} placeholder="hr@company.com" />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold">Contact Number</label>
                      <input id="hte-contact-number" type="text" className="form-control" value={newHte.contact_number} onChange={e => setNewHte({...newHte, contact_number: e.target.value})} placeholder="09XX-XXX-XXXX" />
                    </div>
                  </div>
                  <div className="mb-4">
                    <label className="form-label fw-semibold">Why do you want to intern here? <span className="text-muted fw-normal">(Remarks)</span></label>
                    <textarea
                      id="hte-remarks"
                      className="form-control"
                      rows="3"
                      value={newHte.remarks}
                      onChange={e => setNewHte({...newHte, remarks: e.target.value})}
                      placeholder="Briefly describe the company and why you want to train there…"
                    ></textarea>
                  </div>
                  <div className="d-flex justify-content-end gap-2">
                    <button
                      type="button"
                      className="btn btn-outline-secondary px-4"
                      onClick={() => setNewHte({ company_name: '', address: '', contact_person: '', contact_email: '', contact_number: '', remarks: '' })}
                    >
                      Clear
                    </button>
                    <button id="hte-submit-btn" type="submit" className="btn btn-primary px-4" disabled={submitting}>
                      {submitting
                        ? <><i className="fa fa-spinner fa-spin me-2"></i>Submitting…</>
                        : <><i className="fa fa-paper-plane me-2"></i>Submit Request</>
                      }
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </>
      )}

      {/* ── Tab Bar Styles ── */}
      <style>{`
        .placement-tabs-bar {
          display: flex;
          gap: 6px;
          background: #f0f4f1;
          padding: 6px;
          border-radius: 12px;
          width: fit-content;
        }
        .placement-tab-btn {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 8px 18px;
          border: none;
          border-radius: 8px;
          background: transparent;
          font-size: 0.875rem;
          font-weight: 500;
          color: #6b7280;
          cursor: pointer;
          transition: background 0.18s, color 0.18s, box-shadow 0.18s;
          white-space: nowrap;
        }
        .placement-tab-btn:hover {
          background: rgba(255,255,255,0.7);
          color: #374151;
        }
        .placement-tab-btn.active {
          background: #fff;
          color: #157938;
          font-weight: 600;
          box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .placement-tab-count {
          background: #e5e7eb;
          color: #6b7280;
          font-size: 0.7rem;
          font-weight: 700;
          padding: 1px 7px;
          border-radius: 20px;
          min-width: 20px;
          text-align: center;
        }
        .placement-tab-count.active {
          background: #dcfce7;
          color: #15803d;
        }
        .placement-empty-state {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 40px 24px;
          text-align: center;
        }
      `}</style>
    </Layout>
  )
}

export default StudentCompanies
