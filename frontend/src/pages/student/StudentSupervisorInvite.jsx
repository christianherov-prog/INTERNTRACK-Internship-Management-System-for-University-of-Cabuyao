import { useState, useEffect } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import api from '../../services/api'
import PageError from '../../components/PageError'

function StudentSupervisorInvite({ embedded = false, initialStatusData = null, onStatusChange = () => { } }) {
  const [invite, setInvite] = useState(initialStatusData?.invite || null)
  const [supervisor, setSupervisor] = useState(initialStatusData?.supervisor || null)
  const [state, setState] = useState(initialStatusData?.state || 'none')
  const [loading, setLoading] = useState(!initialStatusData)
  const [error, setError] = useState(null)
  const [generating, setGenerating] = useState(false)
  const [copied, setCopied] = useState(false)

  const fetchStatus = () => {
    setLoading(true)
    setError(null)
    api.get('/student/supervisor-invite/status')
      .then(res => {
        setInvite(res.data.invite)
        setSupervisor(res.data.supervisor || null)
        setState(res.data.state || (res.data.has_supervisor ? 'assigned' : 'none'))
        onStatusChange(res.data)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisor invite status.')
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    if (!initialStatusData) {
      fetchStatus()
    } else {
      setInvite(initialStatusData.invite)
      setSupervisor(initialStatusData.supervisor)
      setState(initialStatusData.state || (initialStatusData.has_supervisor ? 'assigned' : 'none'))
      setLoading(false)
    }
  }, [initialStatusData])

  const handleGenerate = async () => {
    setGenerating(true)
    try {
      const res = await api.post('/student/supervisor-invite')
      const updatedInvite = {
        token: res.data.token,
        register_url: res.data.register_url,
        expires_at: res.data.expires_at,
        status: 'pending',
        created_at: new Date().toISOString()
      }
      setInvite(updatedInvite)
      setState('invite_pending')
      onStatusChange({ state: 'invite_pending', invite: updatedInvite })
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
    return <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
  }




  // Determine current step index based on state
  // Steps: 1: Invite, 2: Registers, 3: Faculty Approval, 4: Attendance Access
  let stepIndex = 1;
  if (state === 'invite_pending') stepIndex = 2;
  if (state === 'pending_approval') stepIndex = 3;
  if (state === 'assigned') stepIndex = 4;
  if (state === 'rejected') stepIndex = 1; // back to step 1

  const renderProgress = () => {
    const steps = [
      { num: 1, label: 'Invite Supervisor', active: stepIndex >= 1, current: stepIndex === 1 && state !== 'rejected' },
      { num: 2, label: 'Supervisor Registers', active: stepIndex >= 2, current: stepIndex === 2 },
      { num: 3, label: 'Faculty Approval', active: stepIndex >= 3, current: stepIndex === 3 },
      { num: 4, label: 'Attendance Access', active: stepIndex >= 4, current: stepIndex === 4 },
    ]

    return (
      <div className="d-flex justify-content-between align-items-center position-relative mb-4 pb-2" style={{ maxWidth: '600px', margin: '0 auto' }}>
        <div className="position-absolute w-100" style={{ height: '3px', background: '#e9ecef', top: '15px', zIndex: 0 }}></div>
        <div className="position-absolute" style={{ height: '3px', background: '#198754', top: '15px', zIndex: 0, width: `${(Math.max(1, stepIndex) - 1) * 33.33}%`, transition: 'width 0.4s ease' }}></div>
        
        {steps.map((step, idx) => {
          let icon = <span className="fw-bold">{step.num}</span>
          if (step.active && !step.current && state !== 'rejected') {
             icon = <i className="fa fa-check"></i>
          }
          if (step.current && state === 'rejected') {
             icon = <i className="fa fa-times"></i>
          }
          
          let circleClass = "bg-light text-muted border border-2 border-light";
          if (step.active) circleClass = "bg-success text-white border border-2 border-success";
          if (step.current && state !== 'rejected') circleClass = "bg-primary text-white border border-2 border-primary";
          if (step.current && state === 'rejected') circleClass = "bg-danger text-white border border-2 border-danger";

          return (
            <div key={idx} className="position-relative z-1 d-flex flex-column align-items-center" style={{ width: '80px' }}>
              <div className={`rounded-circle d-flex align-items-center justify-content-center shadow-sm ${circleClass}`} style={{ width: '32px', height: '32px', fontSize: '14px', transition: 'all 0.3s' }}>
                {icon}
              </div>
              <div className="mt-2 text-center" style={{ fontSize: '11px', lineHeight: '1.2', fontWeight: step.current ? '600' : '400', color: step.active || step.current ? '#212529' : '#6c757d' }}>
                {step.label}
              </div>
            </div>
          )
        })}
      </div>
    )
  }

  return (
    <div>
      {error && <PageError message={error} onRetry={fetchStatus} />}
      {/* STATE 1: NOT_INVITED */}
      {(state === 'none' || (invite && invite.status === 'expired')) && (
        <div className="content-card border-0 shadow-sm text-center">
          <div className="p-5">
            <i className="fa fa-user-plus fa-4x text-muted mb-4"></i>
            <h4 className="fw-bold mb-3">Invite Your HTE Supervisor</h4>
            <p className="text-muted mb-4 mx-auto" style={{ maxWidth: '500px' }}>
              Before you can record your internship attendance, you need to invite an HTE Supervisor and have them approved by your faculty supervisor.
            </p>
            <button className="btn btn-primary btn-lg px-5 shadow-sm rounded-pill mb-3" onClick={handleGenerate} disabled={generating}>
              {generating ? <><i className="fa fa-spinner fa-spin me-2"></i>Generating...</> : <><i className="fa fa-qrcode me-2"></i>Generate Supervisor Invite</>}
            </button>
            <p className="text-muted small mt-2">
              Your supervisor will scan the QR code, provide their details, and wait for faculty approval.
            </p>
          </div>
        </div>
      )}

      {/* STATE 2: INVITED */}
      {state === 'invite_pending' && (
        <div className="content-card border-0 shadow-sm text-center">
          <div className="content-card-header bg-white border-bottom pt-4 pb-3">
            <span className="badge bg-primary px-3 py-2 rounded-pill mb-2 shadow-sm"><i className="fa fa-paper-plane me-2"></i>Invitation Sent</span>
            <h5 className="mb-0 fw-bold mt-2">Supervisor Invitation</h5>
          </div>
          <div className="p-4">
            <div className="bg-light p-3 d-inline-block rounded-4 shadow-sm mb-4">
              <QRCodeSVG value={registerUrl} size={220} level="H" includeMargin />
            </div>
            <p className="text-muted mb-4 mx-auto" style={{ maxWidth: '400px' }}>
              Ask your HTE Supervisor to scan this QR code using their phone to register.
            </p>

            <div className="bg-light rounded p-3 text-start mx-auto mb-4" style={{ maxWidth: '500px' }}>
              <div className="d-flex justify-content-between align-items-center mb-2">
                <span className="text-muted small">Invited: {new Date(invite?.created_at || Date.now()).toLocaleDateString()}</span>
                <span className="text-muted small"><i className="fa fa-clock me-1"></i>Waiting for registration</span>
              </div>
              <div className="input-group">
                <input type="text" className="form-control bg-white" value={registerUrl} readOnly />
                <button className="btn btn-primary" onClick={handleCopy}>
                  <i className={`fa ${copied ? 'fa-check' : 'fa-copy'} me-1`}></i>{copied ? 'Copied' : 'Copy'}
                </button>
              </div>
            </div>

            <button className="btn btn-outline-secondary btn-sm rounded-pill" onClick={handleGenerate} disabled={generating}>
              <i className="fa fa-refresh me-1"></i>Regenerate QR Code
            </button>
          </div>
        </div>
      )}

      {/* STATE 3: REGISTERED / PENDING_APPROVAL */}
      {state === 'pending_approval' && (
        <div className="content-card border-0 shadow-sm text-center">
          <div className="content-card-header bg-white border-bottom pt-4 pb-3">
            <span className="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2 shadow-sm"><i className="fa fa-clock me-2"></i>Awaiting Faculty Approval</span>
            <h5 className="mb-0 fw-bold mt-2">Supervisor Details</h5>
          </div>
          <div className="p-5">
            <div className="mb-4">
              <i className="fa fa-user-tie fa-4x text-info mb-3"></i>
              <h4 className="fw-bold mb-1">{invite?.first_name} {invite?.last_name}</h4>
              <p className="text-muted mb-0">{invite?.position}</p>
              <p className="text-muted">{invite?.email}</p>
            </div>

            <div className="alert alert-warning border-warning-subtle text-start mx-auto d-flex align-items-center shadow-sm" style={{ maxWidth: '600px' }}>
              <i className="fa fa-info-circle fa-2x me-3 text-warning"></i>
              <div>
                <strong>Your supervisor has registered successfully.</strong><br />
                Your faculty supervisor must review and approve the supervisor before attendance tracking becomes available.
              </div>
            </div>
          </div>
        </div>
      )}

      {/* STATE 4: REJECTED */}
      {state === 'rejected' && (
        <div className="content-card border-0 shadow-sm text-center border-top border-danger border-4">
          <div className="p-5">
            <i className="fa fa-times-circle fa-4x text-danger mb-4"></i>
            <h4 className="fw-bold mb-3 text-danger">Supervisor Approval Required</h4>
            <span className="badge bg-danger px-3 py-2 rounded-pill mb-4 shadow-sm">Supervisor Rejected</span>

            <p className="text-muted mb-4 mx-auto" style={{ maxWidth: '500px' }}>
              Your faculty supervisor did not approve this supervisor. Please review any feedback and submit another supervisor invitation.
            </p>

            <button className="btn btn-danger btn-lg px-5 shadow-sm rounded-pill" onClick={handleGenerate} disabled={generating}>
              {generating ? <><i className="fa fa-spinner fa-spin me-2"></i>Generating...</> : <><i className="fa fa-user-plus me-2"></i>Invite Another Supervisor</>}
            </button>
          </div>
        </div>
      )}

      {/* STATE 5: APPROVED / ASSIGNED */}
      {state === 'assigned' && (
        <div className="content-card border-0 shadow-sm text-center">
          <div className="p-5">
            <i className="fa fa-check-circle fa-4x text-success mb-4"></i>
            <h4 className="fw-bold mb-3 text-success">Your Supervisor Has Been Approved</h4>
            <p className="text-muted mb-4 mx-auto" style={{ maxWidth: '500px' }}>
              Your HTE Supervisor has been approved by your faculty supervisor. Attendance tracking is now available.
            </p>

            <div className="card bg-light border-0 mx-auto mb-4" style={{ maxWidth: '400px' }}>
              <div className="card-body p-4 text-start d-flex align-items-center">
                <div className="bg-white rounded-circle shadow-sm d-flex justify-content-center align-items-center me-3" style={{ width: '50px', height: '50px' }}>
                  <i className="fa fa-user-tie text-primary fa-lg"></i>
                </div>
                <div>
                  <h6 className="mb-0 fw-bold">{supervisor?.name}</h6>
                  <div className="text-muted small">{(supervisor?.position || supervisor?.email) || 'Industry Supervisor'}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

    </div>
  )
}

export default StudentSupervisorInvite
