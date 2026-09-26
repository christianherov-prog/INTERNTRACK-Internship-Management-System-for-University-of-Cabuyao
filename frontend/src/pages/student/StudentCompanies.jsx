import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import ConfirmModal from '../../components/modals/ConfirmModal'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { useCachedPage } from '../../hooks/useCachedPage'
import { cacheDelete } from '../../utils/pageCache'
import MoaFilePicker, { validateMoaFile } from '../../components/MoaFilePicker'
import OrganizationTypeField, {
  ORG_TYPE_SPECIFY,
  resolveOrganizationTypeForApi,
  validateOrganizationType,
} from '../../components/OrganizationTypeField'
import InternTrackLoader from '../../components/InternTrackLoader'

const EMPTY_HTE = {
  company_name: '',
  address: '',
  organization_type_select: '',
  organization_type_custom: '',
  contact_person: '',
  contact_email: '',
  contact_number: '',
  remarks: '',
}

function applicationForCompany(applications, companyId) {
  return applications.find((a) =>
    Number(a.company_id) === Number(companyId) || Number(a.company?.id) === Number(companyId)
  )
}

function sendButtonState(app) {
  if (!app) return { label: 'Send', disabled: false }
  const status = String(app.status || '').toLowerCase()
  if (status.includes('rejected') || status.includes('withdrawn')) return { label: 'Send', disabled: false }
  if (status.includes('pending') || status.includes('approved') || status.includes('accepted')) {
    return { label: 'Sent', disabled: true }
  }
  return { label: 'Send', disabled: false }
}

/** Why other companies are locked, from the authoritative placement_lock.source. */
function lockNotice(source) {
  if (source === 'pending_application') return 'You already have an active company application. Choose Change Company to apply elsewhere.'
  if (source === 'completed') return 'Your internship placement is already completed.'
  return 'You already have an active internship placement.'
}

function StudentCompanies() {
  const { pending, seed, run } = useCachedPage('student:companies')
  const [activeTab, setActiveTab] = useState('companies')
  const [companies, setCompanies] = useState(() => seed?.companies ?? [])
  const [applications, setApplications] = useState(() => seed?.applications ?? [])
  // Authoritative lock from the API: once a placement is accepted, no new applications.
  const [placementLock, setPlacementLock] = useState(() => seed?.placementLock ?? null)
  // Institutional rule from the API: no Faculty adviser, no application / HTE request.
  const [adviser, setAdviser] = useState(() => seed?.adviser ?? null)
  const adviserMissing = adviser ? !adviser.assigned : false
  const [hteRequests, setHteRequests] = useState(() => seed?.hteRequests ?? [])
  const [error, setError] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)

  const [newHte, setNewHte] = useState({ ...EMPTY_HTE })
  const [hteMoa, setHteMoa] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [applyTarget, setApplyTarget] = useState(null)
  const [applyMoa, setApplyMoa] = useState(null)
  const [showChangeCompany, setShowChangeCompany] = useState(false)
  const [withdrawing, setWithdrawing] = useState(false)

  const loadData = async () => {
    setError(null)
    try {
      const payload = await run(async () => {
        const [compRes, appRes, hteRes] = await Promise.all([
          api.get('/student/companies'),
          api.get('/student/applications'),
          api.get('/student/hte-requests')
        ])
        return {
          companies: compRes.data.companies || [],
          applications: appRes.data.applications || [],
          placementLock: appRes.data.placement_lock || null,
          adviser: appRes.data.adviser || null,
          hteRequests: hteRes.data.requests || [],
        }
      })
      if (payload) {
        setCompanies(payload.companies)
        setApplications(payload.applications)
        setPlacementLock(payload.placementLock)
        setAdviser(payload.adviser)
        setHteRequests(payload.hteRequests)
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load placement data.')
    }
  }

  useEffect(() => { loadData() }, [])

  const setMoaFile = (file, target = 'apply') => {
    if (!file) {
      if (target === 'hte') setHteMoa(null)
      else setApplyMoa(null)
      return
    }
    const invalid = validateMoaFile(file)
    if (invalid) {
      setError(invalid)
      return
    }
    setError(null)
    if (target === 'hte') setHteMoa(file)
    else setApplyMoa(file)
  }

  const clearHteForm = () => {
    setNewHte({ ...EMPTY_HTE })
    setHteMoa(null)
  }

  const applyToCompany = async () => {
    if (!applyTarget) return
    setSubmitting(true)
    setSuccessMsg(null)
    setError(null)
    try {
      const form = new FormData()
      form.append('company_id', applyTarget.id)
      if (applyMoa) form.append('moa', applyMoa)
      const res = await api.post('/student/applications', form)
      setSuccessMsg(res.data.message || 'Application sent for coordinator review.')
      setApplyTarget(null)
      setApplyMoa(null)
      setActiveTab('applications')
      cacheDelete('student:companies')
      cacheDelete('coordinator:applications')
      loadData()
    } catch (err) {
      if (err.response?.status === 409 && err.response?.data?.placement_lock) {
        setPlacementLock(err.response.data.placement_lock)
        setApplyTarget(null)
      }
      setError(err.response?.data?.message || err.response?.data?.errors?.moa?.[0] || 'Failed to send application.')
    } finally {
      setSubmitting(false)
    }
  }

  const withdrawCurrentApplication = async () => {
    if (!placementLock?.application_id) return
    setWithdrawing(true)
    setError(null)
    try {
      const res = await api.post(`/student/applications/${placementLock.application_id}/withdraw`)
      setSuccessMsg(res.data.message || 'Application withdrawn.')
      setPlacementLock(res.data.placement_lock || null)
      setShowChangeCompany(false)
      cacheDelete('student:companies')
      loadData()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to withdraw application.')
    } finally {
      setWithdrawing(false)
    }
  }

  const submitHteRequest = async (e) => {
    e.preventDefault()
    if (submitting) return
    const name = (newHte.company_name || '').trim()
    if (!name || !(newHte.address || '').trim()) {
      setError('Company name and address are required.')
      return
    }
    const orgErr = validateOrganizationType(newHte.organization_type_select, newHte.organization_type_custom)
    if (orgErr) {
      setError(orgErr)
      return
    }
    const accredited = companies.some(
      (c) => String(c.company_name || '').trim().toLowerCase() === name.toLowerCase()
    )
    if (accredited) {
      setError('This HTE already exists in the accredited company list.')
      return
    }
    if (hteMoa) {
      const invalid = validateMoaFile(hteMoa)
      if (invalid) {
        setError(invalid)
        return
      }
    }
    setSubmitting(true)
    setSuccessMsg(null)
    setError(null)
    try {
      const form = new FormData()
      form.append('company_name', newHte.company_name ?? '')
      form.append('address', newHte.address ?? '')
      form.append(
        'organization_type',
        resolveOrganizationTypeForApi(newHte.organization_type_select, newHte.organization_type_custom) || ''
      )
      form.append('contact_person', newHte.contact_person ?? '')
      form.append('contact_email', newHte.contact_email ?? '')
      form.append('contact_number', newHte.contact_number ?? '')
      form.append('remarks', newHte.remarks ?? '')
      if (hteMoa) form.append('moa', hteMoa)
      await api.post('/student/hte-requests', form)
      setSuccessMsg('Request Sent')
      clearHteForm()
      setActiveTab('applications')
      cacheDelete('student:companies')
      cacheDelete('coordinator:hte-requests')
      loadData()
    } catch (err) {
      setError(err.response?.data?.message || err.response?.data?.errors?.organization_type?.[0] || err.response?.data?.errors?.moa?.[0] || err.response?.data?.errors?.contact_email?.[0] || err.response?.data?.errors?.contact_number?.[0] || 'Failed to submit HTE request.')
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

      {adviserMissing && (
        <div className="alert alert-warning d-flex align-items-center gap-2 mb-3" role="status" data-testid="adviser-required-banner">
          <i className="fa fa-user-clock"></i>
          <span>{adviser.message}</span>
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

      {!error && (
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
              {pending && companies.length === 0 ? (
                <div className="placement-empty-state">
                  <InternTrackLoader />
                </div>
              ) : companies.length === 0 ? (
                <div className="placement-empty-state">
                  <i className="fa fa-building-circle-xmark fa-3x text-muted mb-3"></i>
                  <p className="fw-semibold text-dark mb-1">No eligible companies right now</p>
                  <p className="text-muted small mb-0">Check back later, or use <strong>Request New HTE</strong> to suggest a company.</p>
                </div>
              ) : (
                <div className="table-responsive">
                  {placementLock?.locked && (
                    <div className="alert alert-info d-flex align-items-center gap-2 m-3 mb-2 flex-wrap" role="status" data-testid="placement-lock-banner">
                      <i className="fa fa-lock"></i>
                      <span className="flex-grow-1">
                        {lockNotice(placementLock.source)}
                        {placementLock.company_name ? <> Current company: <strong>{placementLock.company_name}</strong>.</> : null}
                      </span>
                      {placementLock.source === 'pending_application' && (
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-primary"
                          data-testid="change-company-btn"
                          onClick={() => setShowChangeCompany(true)}
                        >
                          <i className="fa fa-right-left me-1"></i>Change Company
                        </button>
                      )}
                    </div>
                  )}
                  <table className="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Company Name</th>
                        <th>Organization</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th className="text-center">Available Slots</th>
                        <th className="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {companies.map(c => {
                        const existing = applicationForCompany(applications, c.id)
                        const send = sendButtonState(existing)
                        const noSlots = c.slots_available === 0
                        const locked = Boolean(placementLock?.locked)
                        const isCurrentCompany = locked && Number(placementLock.company_id) === Number(c.id)
                        return (
                        <tr key={c.id}>
                          <td>
                            <div className="fw-semibold text-dark">{c.company_name}</div>
                            {c.industry && <div className="text-muted small">{c.industry}</div>}
                          </td>
                          <td className="text-muted small">
                            {c.organization_type_label || c.organization_type || '—'}
                          </td>
                          <td className="small">
                            <div className="fw-semibold text-dark">{c.contact_person || '—'}</div>
                            {c.contact_email && <div className="text-muted">{c.contact_email}</div>}
                            {c.contact_number && <div className="text-muted">{c.contact_number}</div>}
                          </td>
                          <td className="text-muted">{c.address || '—'}</td>
                          <td className="text-center">
                            <span className={`badge ${c.slots_available > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} fw-semibold`}>
                              {c.slots_available} slot{c.slots_available !== 1 ? 's' : ''}
                            </span>
                          </td>
                          <td className="text-center">
                            {isCurrentCompany ? (
                              placementLock.source === 'pending_application' ? (
                                <span className="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" data-testid={`pending-company-${c.id}`}>
                                  <i className="fa fa-hourglass-half me-1"></i>Applied / Pending
                                </span>
                              ) : placementLock.source === 'completed' ? (
                                <span className="badge bg-secondary" data-testid={`completed-company-${c.id}`}>
                                  <i className="fa fa-flag-checkered me-1"></i>Completed Placement
                                </span>
                              ) : (
                                <span className="badge bg-success" data-testid={`accepted-company-${c.id}`}>
                                  <i className="fa fa-circle-check me-1"></i>Accepted / Current Placement
                                </span>
                              )
                            ) : !locked && adviserMissing ? (
                              <button
                                id={`apply-company-${c.id}`}
                                className="btn btn-sm px-3 btn-outline-secondary"
                                disabled
                                title={adviser.message}
                                aria-label={`Apply to ${c.company_name} unavailable: ${adviser.message}`}
                              >
                                <i className="fa fa-user-clock me-1"></i>Adviser required
                              </button>
                            ) : locked ? (
                              <button
                                id={`apply-company-${c.id}`}
                                className="btn btn-sm px-3 btn-outline-secondary"
                                disabled
                                title={lockNotice(placementLock.source)}
                                aria-label={`Apply to ${c.company_name} unavailable: you already have an active application or placement.`}
                              >
                                <i className="fa fa-lock me-1"></i>Locked
                              </button>
                            ) : (
                              <button
                                id={`apply-company-${c.id}`}
                                className={`btn btn-sm px-3 ${send.disabled ? 'btn-outline-secondary' : 'btn-primary'}`}
                                onClick={() => { setApplyTarget(c); setApplyMoa(null); setError(null) }}
                                disabled={submitting || noSlots || send.disabled}
                              >
                                <i className={`fa ${send.disabled ? 'fa-check' : 'fa-paper-plane'} me-1`}></i>
                                {send.label}
                              </button>
                            )}
                          </td>
                        </tr>
                        )
                      })}
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
                  {pending && applications.length === 0 ? (
                    <div className="placement-empty-state">
                      <InternTrackLoader />
                    </div>
                  ) : applications.length === 0 ? (
                    <div className="placement-empty-state">
                      <i className="fa fa-inbox fa-2x text-muted mb-2"></i>
                      <p className="text-muted small mb-0">No applications sent yet.<br />Browse <strong>Eligible Companies</strong> to send an application.</p>
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
                              <td className="small text-muted">
                                {a.coordinator_remarks || '—'}
                                {a.has_moa && a.moa_path && (
                                  <div>
                                    <AuthenticatedFileLink path={a.moa_path} className="small">View MOA</AuthenticatedFileLink>
                                  </div>
                                )}
                              </td>
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
                  {pending && hteRequests.length === 0 ? (
                    <div className="placement-empty-state">
                      <InternTrackLoader />
                    </div>
                  ) : hteRequests.length === 0 ? (
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
                              <td className="small text-muted">
                                {r.coordinator_remarks || r.remarks || '—'}
                                {r.has_moa && r.moa_path && (
                                  <div>
                                    <AuthenticatedFileLink path={r.moa_path} className="small">View MOA</AuthenticatedFileLink>
                                  </div>
                                )}
                              </td>
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
                <div className="hte-info-banner">
                  Request an HTE that is not yet listed. The Coordinator will review the request and its MOA before approval.
                </div>
                {adviserMissing && (
                  <div className="alert alert-warning d-flex align-items-center gap-2 mb-3" role="status">
                    <i className="fa fa-user-clock"></i>
                    <span>{adviser.message}</span>
                  </div>
                )}
                {placementLock?.locked && (
                  <div className="alert alert-info d-flex align-items-center gap-2 mb-3" role="status">
                    <i className="fa fa-lock"></i>
                    <span>{placementLock.message}</span>
                  </div>
                )}
                <form onSubmit={submitHteRequest}>
                  <div className="hte-form-section">
                    <div className="hte-form-section-title">Company information</div>
                    <div className="mb-3">
                      <label className="form-label fw-semibold" htmlFor="hte-company-name">Company Name <span className="text-danger">*</span></label>
                      <input
                        id="hte-company-name"
                        type="text"
                        className="form-control"
                        required
                        value={newHte.company_name}
                        onChange={e => setNewHte({...newHte, company_name: e.target.value})}
                        placeholder="Company Name"
                        maxLength={255}
                        disabled={submitting || placementLock?.locked}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label fw-semibold" htmlFor="hte-company-address">Company Address <span className="text-danger">*</span></label>
                      <input
                        id="hte-company-address"
                        type="text"
                        className="form-control"
                        required
                        value={newHte.address}
                        onChange={e => setNewHte({...newHte, address: e.target.value})}
                        placeholder="Company Address"
                        maxLength={255}
                        disabled={submitting || placementLock?.locked}
                      />
                    </div>
                    <div className="mb-0">
                      <OrganizationTypeField
                        id="hte-org-type"
                        selectValue={newHte.organization_type_select}
                        customType={newHte.organization_type_custom}
                        onSelectChange={(v) => setNewHte({
                          ...newHte,
                          organization_type_select: v,
                          organization_type_custom: v === ORG_TYPE_SPECIFY ? newHte.organization_type_custom : '',
                        })}
                        onCustomChange={(v) => setNewHte({ ...newHte, organization_type_custom: v })}
                        disabled={submitting}
                      />
                    </div>
                  </div>

                  <div className="hte-form-section">
                    <div className="hte-form-section-title">Contact information</div>
                    <div className="row g-3">
                      <div className="col-md-4">
                        <label className="form-label fw-semibold" htmlFor="hte-contact-person">Contact Person <span className="text-danger">*</span></label>
                        <input id="hte-contact-person" type="text" className="form-control" required value={newHte.contact_person} onChange={e => setNewHte({...newHte, contact_person: e.target.value})} placeholder="Contact Person" maxLength={255} disabled={submitting || placementLock?.locked} />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label fw-semibold" htmlFor="hte-contact-email">Contact Email <span className="text-danger">*</span></label>
                        <input id="hte-contact-email" type="email" className="form-control" required value={newHte.contact_email} onChange={e => setNewHte({...newHte, contact_email: e.target.value})} placeholder="Contact Email" maxLength={255} disabled={submitting || placementLock?.locked} />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label fw-semibold" htmlFor="hte-contact-number">Contact Number <span className="text-danger">*</span></label>
                        <input id="hte-contact-number" type="text" className="form-control" required value={newHte.contact_number} onChange={e => setNewHte({...newHte, contact_number: e.target.value})} placeholder="Contact Number" minLength={7} maxLength={50} pattern="[0-9+\-\s\(\)]{7,50}" title="7 to 50 characters: digits, spaces, +, -, ( )" disabled={submitting || placementLock?.locked} />
                      </div>
                    </div>
                  </div>

                  <div className="hte-form-section">
                    <div className="hte-form-section-title">Request details</div>
                    <label className="form-label fw-semibold" htmlFor="hte-remarks">Reason / Remarks</label>
                    <textarea
                      id="hte-remarks"
                      className="form-control"
                      rows="3"
                      value={newHte.remarks}
                      onChange={e => setNewHte({...newHte, remarks: e.target.value})}
                      placeholder="Reason for Request"
                      maxLength={2000}
                      disabled={submitting || placementLock?.locked}
                    ></textarea>
                    <div className="form-text text-end">{(newHte.remarks || '').length}/2000</div>
                  </div>

                  <div className="hte-form-section">
                    <div className="hte-form-section-title">Memorandum of Agreement</div>
                    <MoaFilePicker
                      id="hte-moa"
                      file={hteMoa}
                      onChange={(file) => setMoaFile(file, 'hte')}
                      onClear={() => setHteMoa(null)}
                      disabled={submitting}
                    />
                  </div>

                  <div className="d-flex flex-wrap justify-content-end gap-2">
                    <button
                      type="button"
                      className="btn btn-outline-secondary px-4"
                      disabled={submitting}
                      onClick={clearHteForm}
                    >
                      Clear
                    </button>
                    <button id="hte-submit-btn" type="submit" className="btn btn-primary px-4" disabled={submitting || placementLock?.locked || adviserMissing}>
                      {submitting
                        ? <><i className="fa fa-spinner fa-spin me-2"></i>Sending...</>
                        : <><i className="fa fa-paper-plane me-2"></i>Send Request</>
                      }
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </>
      )}

      <ConfirmModal
        open={!!applyTarget}
        title={applyTarget ? `Send Application to ${applyTarget.company_name}` : 'Send Application'}
        message="Send your application for coordinator review."
        confirmLabel="Send Application"
        loadingLabel="Sending..."
        cancelLabel="Cancel"
        variant="primary"
        loading={submitting}
        onCancel={() => { if (!submitting) { setApplyTarget(null); setApplyMoa(null) } }}
        onConfirm={applyToCompany}
      >
        <MoaFilePicker
          id="apply-moa"
          file={applyMoa}
          onChange={(file) => setMoaFile(file, 'apply')}
          onClear={() => setApplyMoa(null)}
          disabled={submitting}
        />
      </ConfirmModal>

      <ConfirmModal
        open={showChangeCompany}
        title="Change Company"
        message={`Your current application to ${placementLock?.company_name || 'this company'} will be withdrawn and cannot be undone. You will then be able to apply to a different company. Continue?`}
        confirmLabel="Withdraw & Change Company"
        loadingLabel="Withdrawing..."
        cancelLabel="Keep Current Application"
        variant="danger"
        loading={withdrawing}
        onCancel={() => { if (!withdrawing) setShowChangeCompany(false) }}
        onConfirm={withdrawCurrentApplication}
      />
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
        @media (max-width: 576px) {
          .placement-tabs-bar {
            width: 100%;
            flex-wrap: wrap;
          }
        }
      `}</style>
    </Layout>
  )
}

export default StudentCompanies
