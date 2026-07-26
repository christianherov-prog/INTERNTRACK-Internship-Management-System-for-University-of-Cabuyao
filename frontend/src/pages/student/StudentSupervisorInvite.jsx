import { useState, useEffect } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'

function StudentSupervisorInvite() {
  const [invite, setInvite] = useState(null)
  const [hasSupervisor, setHasSupervisor] = useState(false)
  const [supervisor, setSupervisor] = useState(null)
  const [state, setState] = useState('none')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [generating, setGenerating] = useState(false)
  const [copied, setCopied] = useState(false)

  const fetchStatus = () => {
    setLoading(true)
    setError(null)
    api.get('/student/supervisor-invite/status')
      .then(res => {
        setInvite(res.data.invite)
        setHasSupervisor(!!res.data.has_supervisor)
        setSupervisor(res.data.supervisor || null)
        setState(res.data.state || (res.data.has_supervisor ? 'assigned' : 'none'))
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisor invite status.')
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchStatus() }, [])

  const handleGenerate = async () => {
    setGenerating(true)
    try {
      const res = await api.post('/student/supervisor-invite')
      setInvite({
        token: res.data.token,
        register_url: res.data.register_url,
        expires_at: res.data.expires_at,
        status: 'pending',
      })
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to generate invite.')
    } finally {
      setGenerating(false)
    }
  }

  const handleCopy = () => {
    const url = invite?.register_url || `${window.location.origin}/register/supervisor?token=${invite?.token}`
    navigator.clipboard.writeText(url).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    })
  }

  const registerUrl = invite?.register_url || (invite?.token ? `${window.location.origin}/register/supervisor?token=${invite.token}` : '')

  if (loading) {
    return (
      <Layout title="Supervisor Invite" subtitle="QR Code Registration" icon="fa-qrcode" bodyClass="student-page">
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x"></i></div>
      </Layout>
    )
  }

  const statusBadge = (status) => {
    const map = {
      pending: { color: 'warning', icon: 'fa-clock', label: 'Waiting for Supervisor to Register' },
      registered: { color: 'info', icon: 'fa-user-check', label: 'Registered — Awaiting Coordinator Approval' },
      approved: { color: 'success', icon: 'fa-check-circle', label: 'Approved & Assigned' },
      rejected: { color: 'danger', icon: 'fa-times-circle', label: 'Rejected by Coordinator' },
      expired: { color: 'secondary', icon: 'fa-hourglass-end', label: 'Expired' },
    }
    const s = map[status] || map.pending
    return <span className={`badge bg-${s.color}`}><i className={`fa ${s.icon} me-1`}></i>{s.label}</span>
  }

  return (
    <Layout title="Supervisor Invite" subtitle="QR Code Registration" icon="fa-qrcode" bodyClass="student-page">
      {error && <PageError message={error} onRetry={fetchStatus} />}
      {hasSupervisor || state === 'assigned' ? (
        <div className="content-card">
          <div className="content-card-header bg-light">
            <h6 className="mb-0"><i className="fa fa-check-circle text-success me-2"></i>Supervisor Already Assigned</h6>
          </div>
          <div className="p-4 text-center">
            <i className="fa fa-user-tie fa-3x text-success mb-3"></i>
            <p className="mb-1 fw-semibold">{supervisor?.name || 'Your industry supervisor'}</p>
            {(supervisor?.position || supervisor?.email) && (
              <p className="text-muted small mb-2">
                {[supervisor?.position, supervisor?.email].filter(Boolean).join(' · ')}
              </p>
            )}
            <p className="text-muted mb-0">A supervisor has already been assigned to your internship. No further action is needed.</p>
          </div>
        </div>
      ) : (
        <div className="row">
          <div className="col-lg-6 mb-4">
            <div className="content-card h-100">
              <div className="content-card-header bg-light">
                <h6 className="mb-0"><i className="fa fa-qrcode me-2"></i>How It Works</h6>
              </div>
              <div className="p-4">
                <div className="d-flex align-items-start mb-3">
                  <span className="badge bg-primary rounded-circle me-3" style={{width:'28px',height:'28px',lineHeight:'18px',fontSize:'13px'}}>1</span>
                  <div><strong>Generate QR Code</strong><p className="text-muted small mb-0">Click the button below to create a unique invite link for your HTE Supervisor.</p></div>
                </div>
                <div className="d-flex align-items-start mb-3">
                  <span className="badge bg-primary rounded-circle me-3" style={{width:'28px',height:'28px',lineHeight:'18px',fontSize:'13px'}}>2</span>
                  <div><strong>Supervisor Scans & Registers</strong><p className="text-muted small mb-0">Your supervisor scans the QR code with their phone and fills in their details (Name, Email, Position, Company).</p></div>
                </div>
                <div className="d-flex align-items-start mb-3">
                  <span className="badge bg-primary rounded-circle me-3" style={{width:'28px',height:'28px',lineHeight:'18px',fontSize:'13px'}}>3</span>
                  <div><strong>Faculty Reviews & Approves</strong><p className="text-muted small mb-0">Your Faculty Supervisor will verify and approve the supervisor's account.</p></div>
                </div>
                <div className="d-flex align-items-start">
                  <span className="badge bg-success rounded-circle me-3" style={{width:'28px',height:'28px',lineHeight:'18px',fontSize:'13px'}}>4</span>
                  <div><strong>Supervisor Gets Access</strong><p className="text-muted small mb-0">Once approved, the supervisor is automatically assigned to your internship and can log in.</p></div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-lg-6 mb-4">
            <div className="content-card h-100">
              <div className="content-card-header bg-light">
                <h6 className="mb-0"><i className="fa fa-link me-2"></i>Your Invite</h6>
              </div>
              <div className="p-4 text-center">
                {!invite || invite.status === 'expired' || invite.status === 'rejected' ? (
                  <>
                    <i className="fa fa-user-plus fa-3x text-muted mb-3"></i>
                    <p className="text-muted mb-4">Generate a QR code to invite your HTE Supervisor to register on InternTrack.</p>
                    <button className="btn btn-green px-5" onClick={handleGenerate} disabled={generating}>
                      {generating
                        ? <><i className="fa fa-spinner fa-spin me-2"></i>Generating...</>
                        : <><i className="fa fa-qrcode me-2"></i>Generate QR Code</>
                      }
                    </button>
                  </>
                ) : (
                  <>
                    <div className="mb-3">{statusBadge(invite.status)}</div>

                    {invite.status === 'pending' && (
                      <>
                        <div className="bg-white p-3 d-inline-block rounded shadow-sm mb-3" style={{border:'2px solid #dee2e6'}}>
                          <QRCodeSVG value={registerUrl} size={200} level="H" includeMargin />
                        </div>
                        <p className="small text-muted mb-2">Ask your supervisor to scan this QR code, or share the link below:</p>
                        <div className="input-group input-group-sm mb-3">
                          <input type="text" className="form-control form-control-sm" value={registerUrl} readOnly style={{fontSize:'11px'}} />
                          <button className="btn btn-outline-green btn-sm" onClick={handleCopy}>
                            <i className={`fa ${copied ? 'fa-check' : 'fa-copy'} me-1`}></i>{copied ? 'Copied!' : 'Copy'}
                          </button>
                        </div>
                        <p className="text-muted small mb-3">
                          <i className="fa fa-clock me-1"></i>
                          Expires: {new Date(invite.expires_at).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' })}
                        </p>
                        <button className="btn btn-outline-warning btn-sm" onClick={handleGenerate} disabled={generating}>
                          <i className="fa fa-refresh me-1"></i>Regenerate
                        </button>
                      </>
                    )}

                    {invite.status === 'registered' && (
                      <div className="mt-2">
                        <i className="fa fa-user-clock fa-3x text-info mb-3"></i>
                        <p className="mb-1"><strong>{invite.first_name} {invite.last_name}</strong></p>
                        <p className="text-muted small mb-0">{invite.position} &middot; {invite.email}</p>
                        <p className="text-muted small mt-3">Your Faculty Supervisor is reviewing this registration.</p>
                      </div>
                    )}

                    {invite.status === 'approved' && (
                      <div className="mt-2">
                        <i className="fa fa-check-circle fa-3x text-success mb-3"></i>
                        <p className="mb-1"><strong>{invite.first_name} {invite.last_name}</strong> has been approved.</p>
                        <p className="text-muted small">Your supervisor has been assigned to your internship.</p>
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </Layout>
  )
}

export default StudentSupervisorInvite
