import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import api from '../../services/api'

function profileOf(student) {
  return student?.student_profile || student?.studentProfile || null
}

function AbsorptionModal({ internship, onClose, onSaved }) {
  const [status, setStatus] = useState('absorbed')
  const [absorbedAt, setAbsorbedAt] = useState(new Date().toISOString().slice(0, 10))
  const [jobTitle, setJobTitle] = useState('')
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const p = profileOf(internship.student)
  const name = p ? `${p.first_name} ${p.last_name}` : 'Intern'

  const submit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    api.patch(`/coordinator/internships/${internship.id}/absorption`, {
      absorption_status: status,
      absorbed_at: status === 'absorbed' ? absorbedAt : null,
      job_title: status === 'absorbed' ? jobTitle : null,
      absorption_notes: notes || null,
    })
      .then(() => onSaved())
      .catch((err) => setError(err.response?.data?.message || 'Failed to save.'))
      .finally(() => setSaving(false))
  }

  return (
    <div className="modal show d-block" style={{ background: 'rgba(0,0,0,0.45)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <form onSubmit={submit}>
            <div className="modal-header">
              <h5 className="modal-title">Confirm Absorption — {name}</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {internship.student_declared_hired && (
                <div className="alert alert-info py-2">
                  Student declared they were hired
                  {internship.student_declaration_notes ? `: ${internship.student_declaration_notes}` : '.'}
                </div>
              )}
              <div className="mb-3">
                <label className="form-label fw-semibold">Was this intern hired / absorbed?</label>
                <select className="form-select" value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="absorbed">Yes — Absorbed / Hired</option>
                  <option value="not_hired">No — Not Hired</option>
                </select>
              </div>
              {status === 'absorbed' && (
                <>
                  <div className="mb-3">
                    <label className="form-label">Hire date</label>
                    <input type="date" className="form-control" value={absorbedAt} onChange={(e) => setAbsorbedAt(e.target.value)} required />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Job title (optional)</label>
                    <input className="form-control" value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} placeholder="e.g. Junior Developer" />
                  </div>
                </>
              )}
              <div className="mb-2">
                <label className="form-label">Notes (optional)</label>
                <textarea className="form-control" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save Outcome'}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

function badge(status) {
  if (status === 'absorbed') return 'badge bg-success'
  if (status === 'not_hired') return 'badge bg-danger'
  return 'badge bg-warning text-dark'
}

function CoordAbsorption() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)

  const load = () => {
    setLoading(true)
    api.get('/coordinator/absorption')
      .then((res) => setItems(res.data.internships ?? []))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  return (
    <Layout title="Intern Absorption" subtitle="Confirm hire outcomes for completed interns" icon="fa-user-check" bodyClass="coordinator-page">
      {modal && (
        <AbsorptionModal
          internship={modal}
          onClose={() => setModal(null)}
          onSaved={() => { setModal(null); load() }}
        />
      )}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-user-check"></i>
          <h6>Completed Interns — Hire Confirmation</h6>
        </div>
        {loading ? (
          <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
        ) : items.length === 0 ? (
          <div className="text-center py-5 text-muted">No completed internships yet.</div>
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Intern</th>
                  <th>Company</th>
                  <th>Supervisor</th>
                  <th>Student declared?</th>
                  <th>Outcome</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {items.map((i) => {
                  const p = profileOf(i.student)
                  const name = p ? `${p.first_name} ${p.last_name}` : '—'
                  const sp = i.supervisor?.supervisor_profile || i.supervisor?.supervisorProfile
                  const supervisorName = sp ? `${sp.first_name} ${sp.last_name}` : (i.supervisor?.username || '—')
                  const outcome = i.absorption_status || 'pending'
                  return (
                    <tr key={i.id}>
                      <td className="fw-semibold">{name}</td>
                      <td>{i.company?.company_name || '—'}</td>
                      <td>{supervisorName}</td>
                      <td>{i.student_declared_hired ? <span className="badge bg-info text-dark">Yes</span> : '—'}</td>
                      <td><span className={badge(outcome)}>{outcome.replace('_', ' ')}</span></td>
                      <td>
                        <button className="btn btn-sm btn-primary" onClick={() => setModal(i)}>
                          {outcome === 'pending' || !i.absorption_status ? 'Confirm' : 'Update'}
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
    </Layout>
  )
}

export default CoordAbsorption
