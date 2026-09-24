import { useEffect, useMemo, useState } from 'react'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { formatStudentName } from '../../utils/formatName'

/**
 * Faculty tool to set Weekly Journal deadlines for the students they handle.
 *
 * There is no free-typed week number. For every selected student the server
 * returns that student's OWN journal weeks (GET /faculty/journal-weeks), built by
 * the same rule that numbers Student journals, FO-31, and the Journal Review
 * Queue (week N = internship start + 7(N-1) days). Each student gets a row with a
 * week dropdown, that student's date range, and a deadline, so different
 * internship start dates are never mixed up. The backend re-checks assignment
 * and week validity before saving.
 */
export default function JournalDeadlineManager() {
  const [open, setOpen] = useState(false)
  const [students, setStudents] = useState([])
  const [selected, setSelected] = useState([])
  const [weeksByInternship, setWeeksByInternship] = useState({})
  const [rows, setRows] = useState({}) // internshipId -> { week, dueAt }
  const [loadingWeeks, setLoadingWeeks] = useState(false)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  const loadStudents = () => {
    api.get('/faculty/assigned-students')
      .then(res => setStudents((unwrapList(res.data).items || []).filter(r => r.id && (r.handled_by_faculty ?? r.can_preview_portfolio))))
      .catch(() => setStudents([]))
  }

  const loadWeeks = (ids) => {
    if (!ids.length) return Promise.resolve()
    setLoadingWeeks(true)
    return api.get('/faculty/journal-weeks', { params: { internship_ids: ids } })
      .then(res => {
        const next = {}
        ;(res.data?.data || []).forEach(item => { next[item.internship_id] = item })
        setWeeksByInternship(prev => ({ ...prev, ...next }))
      })
      .catch(err => setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to load journal weeks.' }))
      .finally(() => setLoadingWeeks(false))
  }

  useEffect(() => { if (open) loadStudents() }, [open])

  // Fetch week data only for newly selected students (stable, no refetch loops).
  useEffect(() => {
    const missing = selected.filter(id => !weeksByInternship[id])
    if (missing.length) loadWeeks(missing)
  }, [selected]) // eslint-disable-line react-hooks/exhaustive-deps

  const nameFor = useMemo(() => {
    const map = new Map()
    students.forEach(r => map.set(Number(r.id), formatStudentName(r) || r.student?.student_number || `Internship #${r.id}`))
    return map
  }, [students])

  const toggle = (id) => setSelected(prev => (prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]))

  const setRow = (id, patch) => setRows(prev => ({ ...prev, [id]: { ...(prev[id] || {}), ...patch } }))

  const onWeekChange = (id, weekNumber) => {
    const week = (weeksByInternship[id]?.weeks || []).find(w => String(w.week_number) === String(weekNumber))
    // Pre-fill with the existing deadline for that week (update, not duplicate).
    setRow(id, { week: weekNumber, dueAt: week?.deadline?.due_at_local || rows[id]?.dueAt || '' })
  }

  const copyFirstDeadline = () => {
    const first = selected.map(id => rows[id]?.dueAt).find(Boolean)
    if (!first) return
    setRows(prev => {
      const next = { ...prev }
      selected.forEach(id => { next[id] = { ...(next[id] || {}), dueAt: first } })
      return next
    })
  }

  const save = async (e) => {
    e.preventDefault()
    setMessage(null)
    if (!selected.length) return setMessage({ type: 'danger', text: 'Select at least one student.' })
    const payload = []
    for (const id of selected) {
      const row = rows[id] || {}
      if (!row.week) return setMessage({ type: 'danger', text: `Choose a journal week for ${nameFor.get(Number(id))}.` })
      if (!row.dueAt) return setMessage({ type: 'danger', text: `Choose a deadline for ${nameFor.get(Number(id))}.` })
      payload.push({ internship_id: id, week_number: Number(row.week), due_at: row.dueAt })
    }
    setSaving(true)
    try {
      const res = await api.post('/faculty/journal-deadlines', { deadlines: payload })
      setMessage({ type: 'success', text: res.data?.message || 'Journal deadline saved.' })
      await loadWeeks(selected) // refresh week rows (deadline + journal timing)
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to save the deadline.' })
    } finally {
      setSaving(false)
    }
  }

  const remove = async (deadlineId, internshipId) => {
    setMessage(null)
    try {
      await api.delete(`/faculty/journal-deadlines/${deadlineId}`)
      await loadWeeks([internshipId])
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to remove the deadline.' })
    }
  }

  return (
    <div className="content-card mb-3" data-testid="journal-deadline-manager">
      <div className="content-card-header">
        <i className="fa fa-hourglass-half" aria-hidden="true"></i>
        <h6>Weekly Journal Deadlines</h6>
        <button type="button" className="btn btn-sm btn-outline-primary ms-auto" aria-expanded={open} onClick={() => setOpen(o => !o)}>
          {open ? 'Hide' : 'Manage deadlines'}
        </button>
      </div>
      {open && (
        <div className="p-3">
          {message && <div className={`alert alert-${message.type} py-2`} role="status">{message.text}</div>}

          <div className="mb-3">
            <div className="form-label fw-semibold mb-1">Students you handle</div>
            <div className="border rounded p-2" style={{ maxHeight: 180, overflowY: 'auto' }}>
              {students.length === 0 ? (
                <div className="text-muted small">No assigned students with an internship record.</div>
              ) : students.map(r => (
                <div className="form-check" key={r.id}>
                  <input className="form-check-input" type="checkbox" id={`dl-${r.id}`} checked={selected.includes(r.id)} onChange={() => toggle(r.id)} />
                  <label className="form-check-label small" htmlFor={`dl-${r.id}`}>{nameFor.get(Number(r.id))}</label>
                </div>
              ))}
            </div>
          </div>

          {selected.length > 0 && (
            <form onSubmit={save}>
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-2" style={{ minWidth: 760 }}>
                  <thead>
                    <tr>
                      <th scope="col">Student</th>
                      <th scope="col">Journal week</th>
                      <th scope="col">Date range</th>
                      <th scope="col">Journal</th>
                      <th scope="col">Deadline (Asia/Manila)</th>
                      <th scope="col"><span className="visually-hidden">Actions</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    {selected.map(id => {
                      const info = weeksByInternship[id]
                      const weeks = info?.weeks || []
                      const row = rows[id] || {}
                      const week = weeks.find(w => String(w.week_number) === String(row.week))
                      return (
                        <tr key={id} data-testid={`deadline-row-${id}`}>
                          <td className="fw-semibold">{nameFor.get(Number(id))}</td>
                          <td style={{ minWidth: 230 }}>
                            {!info ? (
                              <span className="text-muted small">{loadingWeeks ? 'Loading weeks…' : '—'}</span>
                            ) : weeks.length === 0 ? (
                              <span className="text-muted small">No journal weeks yet (internship start date not set).</span>
                            ) : (
                              <select
                                className="form-select form-select-sm"
                                aria-label={`Journal week for ${nameFor.get(Number(id))}`}
                                value={row.week || ''}
                                onChange={e => onWeekChange(id, e.target.value)}
                                required
                              >
                                <option value="">Select week…</option>
                                {weeks.map(w => (
                                  <option key={w.week_number} value={w.week_number}>
                                    {w.label}{w.deadline ? ' • has deadline' : ''}
                                  </option>
                                ))}
                              </select>
                            )}
                          </td>
                          <td className="small">{week?.range_display || '—'}</td>
                          <td className="small">
                            {week?.journal ? (
                              <>
                                <span className="text-capitalize">{String(week.journal.status || '').replace(/_/g, ' ')}</span>
                                {week.deadline ? (
                                  <span className={`badge ms-1 ${week.journal.submitted_late ? 'bg-danger' : 'bg-success'}`}>
                                    {week.journal.submitted_late ? 'Late' : 'On time'}
                                  </span>
                                ) : null}
                              </>
                            ) : (week ? <span className="text-muted">Not submitted</span> : '—')}
                          </td>
                          <td style={{ minWidth: 200 }}>
                            <input
                              type="datetime-local"
                              className="form-control form-control-sm"
                              aria-label={`Deadline for ${nameFor.get(Number(id))}`}
                              value={row.dueAt || ''}
                              min="2000-01-01T00:00"
                              max="2099-12-31T23:59"
                              disabled={!row.week}
                              onChange={e => setRow(id, { dueAt: e.target.value })}
                              required
                            />
                          </td>
                          <td className="text-end">
                            {week?.deadline ? (
                              <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => remove(week.deadline.id, id)}>
                                Remove
                              </button>
                            ) : null}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
              <div className="d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" className="btn btn-primary btn-sm" disabled={saving || loadingWeeks}>
                  <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'} me-1`} aria-hidden="true"></i>Save deadlines
                </button>
                {selected.length > 1 && (
                  <button type="button" className="btn btn-outline-secondary btn-sm" onClick={copyFirstDeadline}>
                    Use the first deadline for all rows
                  </button>
                )}
                <span className="form-text">Weeks and date ranges are each student&apos;s own. Late journals are still accepted and marked late.</span>
              </div>
            </form>
          )}
        </div>
      )}
    </div>
  )
}
