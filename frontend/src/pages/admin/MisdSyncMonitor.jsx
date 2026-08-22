import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function MisdSyncMonitor() {
  const [status, setStatus] = useState(null)
  const [audit, setAudit] = useState([])
  const [provisionLog, setProvisionLog] = useState([])
  const [directory, setDirectory] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [studentNumber, setStudentNumber] = useState('')
  const [preview, setPreview] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      api.get('/admin/misd/status'),
      api.get('/admin/audit-log', { params: { per_page: 20 } }),
      api.get('/admin/provisioning-log'),
    ])
      .then(([s, a, p]) => {
        setStatus(s.data)
        setAudit(unwrapList(a.data).items)
        setProvisionLog(p.data?.data || [])
      })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load sync monitor.'))
      .finally(() => setLoading(false))
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
          <div className="row g-3 mb-4">
            <div className="col-lg-4">
              <div className="content-card h-100">
                <div className="content-card-header"><i className="fa fa-heartbeat"></i><h6>Health</h6></div>
                <div className="p-3">
                  <div className="mb-2 d-flex justify-content-between"><span>Mode</span><span className={`badge ${status?.use_mock ? 'bg-warning text-dark' : 'bg-success'}`}>{status?.use_mock ? 'Mock' : 'Live'}</span></div>
                  <div className="mb-2 d-flex justify-content-between"><span>Reachable</span><span className={`badge ${status?.reachable ? 'bg-success' : 'bg-danger'}`}>{status?.reachable ? 'Yes' : 'No'}</span></div>
                  <div className="mb-2 d-flex justify-content-between"><span>Latency</span><span>{status?.latency_ms != null ? `${status.latency_ms} ms` : '—'}</span></div>
                  <div className="mb-2 d-flex justify-content-between"><span>Cache TTL</span><span>{status?.cache_ttl}s</span></div>
                  <div className="text-muted" style={{ fontSize: '0.78rem', wordBreak: 'break-all' }}>{status?.base_url}</div>
                  {status?.note && <div className="text-muted mt-1" style={{ fontSize: '0.78rem' }}>{status.note}</div>}
                  <button className="btn btn-sm btn-outline-secondary mt-3" onClick={load}><i className="fa fa-redo me-1"></i>Recheck</button>
                </div>
              </div>
            </div>

            <div className="col-lg-8">
              <div className="content-card h-100">
                <div className="content-card-header"><i className="fa fa-user-graduate"></i><h6>Sync Student Profile</h6></div>
                <form className="p-3" onSubmit={lookupStudent}>
                  <div className="input-group">
                    <input
                      className="form-control"
                      placeholder="Student number e.g. 2021-00123 or 2300600"
                      value={studentNumber}
                      onChange={(e) => setStudentNumber(e.target.value)}
                    />
                    <button className="btn btn-primary" type="submit" disabled={busy}>Lookup</button>
                  </div>
                </form>
                {preview && (
                  <div className="px-3 pb-3">
                    <div className="row g-2" style={{ fontSize: '0.9rem' }}>
                      <div className="col-md-6">
                        <div className="border rounded p-2 h-100">
                          <div className="fw-semibold mb-1">MISD</div>
                          <div>{preview.misd?.first_name} {preview.misd?.last_name}</div>
                          <div>Section: <code>{preview.misd?.section || '—'}</code></div>
                          <div>Program: {(typeof preview.misd?.program === 'string' ? preview.misd?.program : preview.misd?.program?.code || preview.misd?.program?.name) || '—'}</div>
                          <div>AY: {preview.misd?.academic_year || '—'} · Sem {preview.misd?.semester || '—'}</div>
                        </div>
                      </div>
                      <div className="col-md-6">
                        <div className="border rounded p-2 h-100">
                          <div className="fw-semibold mb-1">Local</div>
                          {preview.local ? (
                            <>
                              <div>Username: <code>{preview.local.username}</code></div>
                              <div>Section: <code>{preview.local.section || '—'}</code></div>
                              <div>Program: {(typeof preview.local.program === 'string' ? preview.local.program : preview.local.program?.code || preview.local.program?.name) || '—'}</div>
                              <div>Synced: {preview.local.synced_at ? new Date(preview.local.synced_at).toLocaleString() : 'Never'}</div>
                            </>
                          ) : (
                            <div className="text-muted">Not provisioned in INTERNTRACK yet.</div>
                          )}
                        </div>
                      </div>
                    </div>
                    {preview.drift && (preview.drift.section_changed || preview.drift.program_changed) && (
                      <div className="alert alert-warning mt-2 mb-0 py-2">
                        Enrollment drift detected
                        {preview.drift.section_changed ? ' (section)' : ''}
                        {preview.drift.program_changed ? ' (program)' : ''}.
                      </div>
                    )}
                    <button className="btn btn-success btn-sm mt-3" onClick={syncStudent} disabled={busy || !preview.local}>
                      <i className="fa fa-sync me-1"></i>Sync from MISD
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="content-card mb-4">
            <div className="content-card-header d-flex justify-content-between align-items-center">
              <div className="d-flex align-items-center gap-2"><i className="fa fa-list"></i><h6 className="mb-0">MISD Directory Preview</h6></div>
              <div className="btn-group btn-group-sm">
                <button className="btn btn-outline-primary" disabled={busy} onClick={() => loadDirectory('students')}>Students</button>
                <button className="btn btn-outline-primary" disabled={busy} onClick={() => loadDirectory('faculty')}>Faculty</button>
              </div>
            </div>
            <div className="p-3">
              {!directory ? (
                <p className="text-muted mb-0">
                  Click <strong>Students</strong> or <strong>Faculty</strong> to load the mock/live MISD directory.
                </p>
              ) : (
                <>
                  <p className="mb-2">
                    <strong>{directory.count}</strong> {directory.type} returned
                    {directory.count === 0 && (
                      <span className="text-danger ms-2">
                        (empty — if mock mode still fails, restart the API after the latest fix)
                      </span>
                    )}
                  </p>
                  {directory.count > 0 ? (
                    <pre className="bg-light border rounded p-2 mb-0" style={{ maxHeight: 220, overflow: 'auto', fontSize: '0.75rem' }}>
                      {JSON.stringify(directory.data?.slice?.(0, 8) ?? directory.data, null, 2)}
                    </pre>
                  ) : (
                    <div className="alert alert-warning mb-0 py-2">
                      No directory records. Mock mode should return sample students/faculty without calling localhost HTTP.
                    </div>
                  )}
                </>
              )}
            </div>
          </div>

          <div className="row g-3">
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header"><i className="fa fa-history"></i><h6>Admin Audit Log</h6></div>
                <div className="table-responsive" style={{ maxHeight: 320, overflow: 'auto' }}>
                  <table className="table table-sm mb-0" style={{ fontSize: '0.8rem' }}>
                    <thead className="table-light"><tr><th>When</th><th>Action</th><th>Actor</th></tr></thead>
                    <tbody>
                      {audit.length === 0 ? (
                        <tr><td colSpan={3} className="text-center text-muted py-3">No entries.</td></tr>
                      ) : audit.map((row) => (
                        <tr key={row.id}>
                          <td>{row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
                          <td><code>{row.action}</code></td>
                          <td>{row.actor?.username || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header"><i className="fa fa-file-alt"></i><h6>Provisioning Log (Laravel)</h6></div>
                <pre className="bg-light border-0 p-3 mb-0" style={{ maxHeight: 320, overflow: 'auto', fontSize: '0.72rem' }}>
                  {provisionLog.length === 0 ? 'No MISD/provision lines found in laravel.log.' : provisionLog.join('\n')}
                </pre>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default MisdSyncMonitor
