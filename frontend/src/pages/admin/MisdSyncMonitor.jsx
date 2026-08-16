import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

const defaultMockStatus = {
  use_mock: true,
  reachable: true,
  latency_ms: 0,
  cache_ttl: 3600,
  base_url: 'in-process://MockMisdRepository',
  note: 'Mock iEnroll simulation engine.',
}

function MisdSyncMonitor() {
  const [status, setStatus] = useState(defaultMockStatus)
  const [audit, setAudit] = useState([])
  const [provisionLog, setProvisionLog] = useState([])
  const [directory, setDirectory] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [studentNumber, setStudentNumber] = useState('')
  const [preview, setPreview] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const [sRes, aRes, pRes] = await Promise.allSettled([
        api.get('/admin/misd/status'),
        api.get('/admin/audit-log', { params: { per_page: 20 } }),
        api.get('/admin/provisioning-log'),
      ])

      if (sRes.status === 'fulfilled' && sRes.value?.data) {
        setStatus(sRes.value.data)
      } else {
        setStatus(defaultMockStatus)
      }

      if (aRes.status === 'fulfilled' && aRes.value?.data) {
        setAudit(unwrapList(aRes.value.data).items)
      }

      if (pRes.status === 'fulfilled' && pRes.value?.data) {
        setProvisionLog(pRes.value.data?.data || [])
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load sync monitor.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const lookupStudent = async (e) => {
    e.preventDefault()
    const sn = studentNumber.trim().toUpperCase()
    if (!sn) return
    setBusy(true)
    setMessage(null)
    setPreview(null)
    try {
      const res = await api.get(`/admin/misd/students/${encodeURIComponent(sn)}`)
      setPreview(res.data)
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Student not found in MISD.' })
    } finally {
      setBusy(false)
    }
  }

  const syncStudent = async () => {
    if (!preview?.local?.id) {
      setMessage({ type: 'warning', text: 'Student must already exist in INTERNTRACK to sync.' })
      return
    }
    setBusy(true)
    setMessage(null)
    try {
      const res = await api.post(`/admin/misd/sync/student/${preview.local.id}`)
      setMessage({ type: 'success', text: res.data.message + (res.data.changed ? ' (profile changed)' : ' (no changes)') })
      const refreshed = await api.get(`/admin/misd/students/${encodeURIComponent(studentNumber.trim().toUpperCase())}`)
      setPreview(refreshed.data)
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Sync failed.' })
    } finally {
      setBusy(false)
    }
  }

  const loadDirectory = async (type) => {
    setBusy(true)
    setMessage(null)
    try {
      const res = await api.post('/admin/misd/directory', { type })
      setDirectory(res.data)
      setMessage({ type: 'success', text: `Loaded ${res.data.count} ${type} from MISD directory.` })
      await load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Directory fetch failed.' })
    } finally {
      setBusy(false)
    }
  }

  return (
    <Layout title="MISD Sync" subtitle="Integration health, enrollment sync & logs" icon="fa-sync" bodyClass="admin-page">
      {error && <PageError message={error} onRetry={load} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <>
          <div className="row g-4 gx-lg-5 mb-4">
            {/* Health & Engine Status */}
            <div className="col-lg-4">
              <div className="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex align-items-center justify-content-between">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-heart-pulse text-success"></i> Integration Health
                  </h6>
                  <span className="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold px-2 py-0.5" style={{ fontSize: '0.72rem' }}>
                    Mock Engine
                  </span>
                </div>
                <div className="card-body px-4 pt-2 pb-4 d-flex flex-column justify-content-between">
                  <div className="d-flex flex-column">
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Operating Mode</span>
                      <span className="badge bg-warning-subtle text-warning-emphasis fw-bold px-2.5 py-1 rounded-pill" style={{ fontSize: '0.75rem' }}>
                        {status?.use_mock !== false ? 'Mock iEnroll Engine' : 'Live University API'}
                      </span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Connector Reachability</span>
                      <span className={`badge ${status?.reachable !== false ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} fw-bold px-2.5 py-1 rounded-pill`} style={{ fontSize: '0.75rem' }}>
                        {status?.reachable !== false ? 'Reachable (OK)' : 'Unreachable'}
                      </span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Latency</span>
                      <span className="text-dark fw-bold" style={{ fontSize: '0.88rem' }}>{status?.latency_ms != null ? `${status.latency_ms} ms` : '0 ms'}</span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Cache TTL</span>
                      <span className="text-dark fw-bold" style={{ fontSize: '0.88rem' }}>{status?.cache_ttl ?? 3600}s</span>
                    </div>
                    <div className="text-muted mt-4 px-3 py-2 rounded-3 bg-light" style={{ fontSize: '0.78rem', wordBreak: 'break-all' }}>
                      <i className="fa-solid fa-link me-1.5 text-secondary"></i> {status?.base_url || 'in-process://MockMisdRepository'}
                    </div>
                  </div>
                  <button className="btn btn-outline-success btn-sm mt-4 w-100 rounded-3 fw-semibold py-2" onClick={load}>
                    <i className="fa fa-arrows-rotate me-1.5"></i> Recheck Status
                  </button>
                </div>
              </div>
            </div>

            {/* Student Profile Lookup & Sync */}
            <div className="col-lg-8">
              <div className="card border-0 shadow-sm rounded-4 bg-white">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-user-graduate text-success"></i> Sync Student Enrollment Profile
                  </h6>
                </div>
                <div className="card-body px-4 pt-3.5 pb-4">
                  <form onSubmit={lookupStudent} className="mb-4">
                    <label className="form-label fw-semibold text-muted mb-3" style={{ fontSize: '0.82rem' }}>
                      Enter Student Identification Number
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0 text-muted rounded-start-3">
                        <i className="fa fa-id-card"></i>
                      </span>
                      <input
                        className="form-control border-start-0 font-monospace"
                        placeholder="e.g. 2300600 or 2300592"
                        value={studentNumber}
                        onChange={(e) => setStudentNumber(e.target.value)}
                      />
                      <button className="btn btn-success fw-semibold px-3.5 rounded-end-3" type="submit" disabled={busy}>
                        {busy ? <i className="fa fa-spinner fa-spin me-1.5"></i> : <i className="fa fa-search me-1.5"></i>}
                        Lookup Student
                      </button>
                    </div>
                  </form>

                  {preview && (
                    <div className="pt-2">
                      <div className="row g-3" style={{ fontSize: '0.88rem' }}>
                        <div className="col-md-6">
                          <div className="p-3 rounded-3 h-100" style={{ background: '#f8fafc', border: '1px solid #eef2f6' }}>
                            <div className="d-flex align-items-center justify-content-between mb-2">
                              <span className="text-muted fw-bold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                                MISD Simulated Record
                              </span>
                              <span className="badge bg-info-subtle text-info-emphasis fw-semibold px-2 py-0.5 rounded-pill" style={{ fontSize: '0.72rem' }}>
                                Remote Data
                              </span>
                            </div>
                            <div className="fw-bold text-dark mb-1">{preview.misd?.first_name} {preview.misd?.last_name}</div>
                            <div className="text-muted mb-0.5">Section: <code className="bg-white px-1.5 py-0.5 rounded border">{preview.misd?.section || '—'}</code></div>
                            <div className="text-muted mb-0.5">Program: <strong className="text-dark">{(typeof preview.misd?.program === 'string' ? preview.misd?.program : preview.misd?.program?.code || preview.misd?.program?.name) || '—'}</strong></div>
                            <div className="text-muted">Term: {preview.misd?.academic_year || '—'} · Sem {preview.misd?.semester || '—'}</div>
                          </div>
                        </div>
                        <div className="col-md-6">
                          <div className="p-3 rounded-3 h-100" style={{ background: '#f8fafc', border: '1px solid #eef2f6' }}>
                            <div className="d-flex align-items-center justify-content-between mb-2">
                              <span className="text-muted fw-bold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                                INTERNTRACK Local DB
                              </span>
                              <span className="badge bg-success-subtle text-success fw-semibold px-2 py-0.5 rounded-pill" style={{ fontSize: '0.72rem' }}>
                                Local DB
                              </span>
                            </div>
                            {preview.local ? (
                              <>
                                <div className="fw-bold text-dark mb-1">Username: <code>{preview.local.username}</code></div>
                                <div className="text-muted mb-0.5">Section: <code className="bg-white px-1.5 py-0.5 rounded border">{preview.local.section || '—'}</code></div>
                                <div className="text-muted mb-0.5">Program: <strong className="text-dark">{(typeof preview.local.program === 'string' ? preview.local.program : preview.local.program?.code || preview.local.program?.name) || '—'}</strong></div>
                                <div className="text-muted">Last Synced: {preview.local.synced_at ? new Date(preview.local.synced_at).toLocaleString() : 'Never'}</div>
                              </>
                            ) : (
                              <div className="text-muted py-3 text-center">
                                <i className="fa-regular fa-circle-question fa-2x mb-1 d-block opacity-40"></i>
                                Not provisioned in INTERNTRACK yet.
                              </div>
                            )}
                          </div>
                        </div>
                      </div>

                      {preview.drift && (preview.drift.section_changed || preview.drift.program_changed) && (
                        <div className="alert alert-warning mt-3 mb-0 py-2.5 rounded-3 border-0 d-flex align-items-center gap-2" style={{ fontSize: '0.85rem' }}>
                          <i className="fa fa-triangle-exclamation text-warning flex-shrink-0"></i>
                          <span>
                            <strong>Enrollment drift detected:</strong> MISD data differs from local record
                            {preview.drift.section_changed ? ' (Section)' : ''}
                            {preview.drift.program_changed ? ' (Degree Program)' : ''}.
                          </span>
                        </div>
                      )}

                      <button className="btn btn-success rounded-3 fw-semibold px-3.5 py-2 mt-3" onClick={syncStudent} disabled={busy || !preview.local}>
                        <i className={`fa fa-${busy ? 'spinner fa-spin' : 'arrows-rotate'} me-1.5`}></i>
                        Sync Profile from MISD
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Directory Preview */}
          <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                <i className="fa fa-list-check text-success"></i> MISD Directory Inspection
              </h6>
              <div className="btn-group btn-group-sm">
                <button className="btn btn-outline-success fw-semibold px-3" disabled={busy} onClick={() => loadDirectory('students')}>
                  <i className="fa fa-user-graduate me-1"></i>Fetch Students
                </button>
                <button className="btn btn-outline-success fw-semibold px-3" disabled={busy} onClick={() => loadDirectory('faculty')}>
                  <i className="fa fa-chalkboard-user me-1"></i>Fetch Faculty
                </button>
              </div>
            </div>
            <div className="card-body px-4 pt-2 pb-4">
              {!directory ? (
                <p className="text-muted mb-0" style={{ fontSize: '0.88rem' }}>
                  Click <strong>Fetch Students</strong> or <strong>Fetch Faculty</strong> to query the simulated MISD directory stream.
                </p>
              ) : (
                <>
                  <div className="d-flex align-items-center justify-content-between mb-2">
                    <span className="text-muted fw-semibold" style={{ fontSize: '0.85rem' }}>
                      Found <strong className="text-dark">{directory.count}</strong> {directory.type} records in mock payload
                    </span>
                  </div>
                  {directory.count > 0 ? (
                    <pre className="bg-light border rounded-3 p-3 mb-0 font-monospace text-dark" style={{ maxHeight: 220, overflow: 'auto', fontSize: '0.76rem' }}>
                      {JSON.stringify(directory.data?.slice?.(0, 8) ?? directory.data, null, 2)}
                    </pre>
                  ) : (
                    <div className="alert alert-warning mb-0 py-2 rounded-3 border-0" style={{ fontSize: '0.85rem' }}>
                      No records returned.
                    </div>
                  )}
                </>
              )}
            </div>
          </div>

          {/* Audit Logs & Sync Logs */}
          <div className="row g-4 mb-4">
            <div className="col-lg-6">
              <div className="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-history text-success"></i> Integration Log History
                  </h6>
                </div>
                <div className="card-body px-4 pt-2 pb-4">
                  <table className="table table-hover border-top mb-0" style={{ fontSize: '0.82rem' }}>
                    <thead>
                      <tr>
                        <th className="px-3 py-2 text-muted fw-bold">Timestamp</th>
                        <th className="px-3 py-2 text-muted fw-bold">Action</th>
                        <th className="px-3 py-2 text-muted fw-bold">Operator</th>
                      </tr>
                    </thead>
                    <tbody>
                      {audit.length === 0 ? (
                        <tr>
                          <td colSpan="3" className="text-center text-muted py-3">No integration logs yet.</td>
                        </tr>
                      ) : audit.map((row) => (
                        <tr key={row.id}>
                          <td className="px-3 py-2 text-muted">{row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
                          <td className="px-3 py-2">
                            <span className="badge bg-light text-dark border font-monospace" style={{ fontSize: '0.75rem' }}>
                              {row.action}
                            </span>
                          </td>
                          <td className="px-3 py-2 fw-semibold text-dark">{row.actor?.username || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <div className="col-lg-6">
              <div className="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-terminal text-success"></i> Provisioning Log Stream
                  </h6>
                </div>
                <div className="p-3 pt-0">
                  <pre className="bg-light border rounded-3 p-3 mb-0 font-monospace text-dark" style={{ maxHeight: 300, overflowX: 'hidden', overflowY: 'auto', whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '0.72rem' }}>
                    {provisionLog.length === 0 ? 'No MISD provisioning events logged yet.' : provisionLog.join('\n')}
                  </pre>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default MisdSyncMonitor
