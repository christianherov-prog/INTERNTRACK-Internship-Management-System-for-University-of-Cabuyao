import { useState, useEffect } from 'react'
import Layout from './Layout'
import PageError from './PageError'
import api from '../services/api'
import { useCachedPage } from '../hooks/useCachedPage'
import InternTrackLoader from './InternTrackLoader'
import AppModal from './modals/AppModal'
import { formatDisplayDate } from '../utils/manilaTime'

function profileOf(student) {
  return student?.student_profile || student?.studentProfile || null
}

function AbsorptionModal({ internship, apiBase, onClose, onSaved, declaredHiredExtra }) {
  const [status, setStatus] = useState('absorbed')
  const [absorbedAt, setAbsorbedAt] = useState(new Date().toISOString().slice(0, 10))
  const [jobTitle, setJobTitle] = useState('')
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const p = profileOf(internship.student)
  const name = p ? `${p.last_name}, ${p.first_name}` : 'Intern'

  const submit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    api.patch(`/${apiBase}/internships/${internship.id}/absorption`, {
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
    <AppModal
      onClose={onClose}
      size="md"
      title={`Confirm Absorption — ${name}`}
      icon="fa-user-check"
      busy={saving}
      onSubmit={submit}
      footer={(
        <>
          <button type="button" className="btn btn-secondary" onClick={onClose} disabled={saving}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save Outcome'}</button>
        </>
      )}
    >
      {error && <div className="alert alert-danger">{error}</div>}
      {internship.student_declared_hired && (
        <div className="alert alert-info py-2">
          Student declared they were hired
          {internship.student_declaration_notes ? `: ${internship.student_declaration_notes}` : '.'}
          {declaredHiredExtra ? ` ${declaredHiredExtra}` : ''}
        </div>
      )}
      <div className="it-form-grid">
        <div className="it-span-2">
          <label className="form-label fw-semibold" htmlFor="absorption-status">Was this intern hired / absorbed?</label>
          <select id="absorption-status" className="form-select" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="absorbed">Yes — Absorbed / Hired</option>
            <option value="not_hired">No — Not Hired</option>
          </select>
        </div>
        {status === 'absorbed' && (
          <>
            <div>
              <label className="form-label" htmlFor="absorption-date">Hire date</label>
              <input id="absorption-date" type="date" className="form-control" value={absorbedAt} onChange={(e) => setAbsorbedAt(e.target.value)} required />
            </div>
            <div>
              <label className="form-label" htmlFor="absorption-title">Job title (optional)</label>
              <input id="absorption-title" maxLength={255} className="form-control" value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} placeholder="Job Title" />
            </div>
          </>
        )}
        <div className="it-span-2">
          <label className="form-label" htmlFor="absorption-notes">Notes (optional)</label>
          <textarea id="absorption-notes" maxLength={2000} className="form-control" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
        </div>
      </div>
    </AppModal>
  )
}

function badge(status) {
  if (status === 'absorbed') return 'badge bg-success'
  if (status === 'not_hired') return 'badge bg-danger'
  return 'badge bg-warning text-dark'
}

/**
 * Shared absorption list + confirm modal for supervisor and coordinator.
 * Props preserve each role's existing columns/copy — no redesign.
 * canRecord=false renders a read-only list: only the PALD Director may
 * finalize absorption (AbsorptionService::recordOutcome), so roles without
 * that right see each outcome but get no Confirm/Update action.
 */
function RoleAbsorption({
  apiBase,
  bodyClass,
  showEndedColumn = false,
  showSupervisorColumn = false,
  emptyMessage = 'No completed internships yet.',
  declaredHiredExtra = '',
  canRecord = true,
}) {
  const { loading, seed, run } = useCachedPage(`${apiBase}:absorption`)
  const [items, setItems] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [modal, setModal] = useState(null)

  const load = () => {
    setError(null)
    run(() => api.get(`/${apiBase}/absorption`).then((res) => res.data.internships ?? []))
      .then((next) => { if (next) setItems(next) })
      .catch((err) => {
        setItems([])
        setError(err.response?.data?.message || 'Failed to load absorption records.')
      })
  }

  useEffect(() => { load() }, [apiBase])

  return (
    <Layout
      title="Intern Absorption"
      subtitle={canRecord ? 'Confirm hire outcomes for completed interns' : 'Hire outcomes for completed interns'}
      icon="fa-user-check"
      bodyClass={bodyClass}
    >
      {error && <PageError message={error} onRetry={load} />}
      {!canRecord && (
        <div className="alert alert-info d-flex align-items-center gap-2 mb-3" role="note" data-testid="absorption-read-only-note">
          <i className="fa fa-circle-info"></i>
          <span>Absorption outcomes are finalized by the PALD Director. This page shows each intern&apos;s current outcome.</span>
        </div>
      )}
      {canRecord && modal && (
        <AbsorptionModal
          internship={modal}
          apiBase={apiBase}
          declaredHiredExtra={declaredHiredExtra}
          onClose={() => setModal(null)}
          onSaved={() => { setModal(null); load() }}
        />
      )}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-user-check"></i>
          <h6>{canRecord ? 'Completed Interns — Hire Confirmation' : 'Completed Interns — Hire Outcomes'}</h6>
        </div>
        {loading ? (
          <div className="text-center py-5"><InternTrackLoader /></div>
        ) : error ? null : items.length === 0 ? (
          <div className="text-center py-5 text-muted">{emptyMessage}</div>
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Intern</th>
                  <th>Company</th>
                  {showEndedColumn && <th>Ended</th>}
                  {showSupervisorColumn && <th>Supervisor</th>}
                  <th>Student declared?</th>
                  <th>Outcome</th>
                  {canRecord && <th></th>}
                </tr>
              </thead>
              <tbody>
                {items.map((i) => {
                  const p = profileOf(i.student)
                  const name = p ? `${p.last_name}, ${p.first_name}` : '—'
                  const sp = i.supervisor?.supervisor_profile || i.supervisor?.supervisorProfile
                  const supervisorName = sp ? `${sp.last_name}, ${sp.first_name}` : (i.supervisor?.username || '—')
                  const outcome = i.absorption_status || 'pending'
                  return (
                    <tr key={i.id}>
                      <td className="fw-semibold">{name}</td>
                      <td>{i.company?.company_name || '—'}</td>
                      {showEndedColumn && (
                        <td>{formatDisplayDate(i.end_date, { month: 'short', day: 'numeric', year: 'numeric' }) || '—'}</td>
                      )}
                      {showSupervisorColumn && <td>{supervisorName}</td>}
                      <td>{i.student_declared_hired ? <span className="badge bg-info text-dark">Yes</span> : '—'}</td>
                      <td><span className={badge(outcome)}>{outcome.replace('_', ' ')}</span></td>
                      {canRecord && (
                        <td>
                          <button className="btn btn-sm btn-primary" onClick={() => setModal(i)}>
                            {outcome === 'pending' || !i.absorption_status ? 'Confirm' : 'Update'}
                          </button>
                        </td>
                      )}
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

export default RoleAbsorption
