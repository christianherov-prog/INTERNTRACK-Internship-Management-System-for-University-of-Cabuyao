import { useEffect, useId, useRef, useState } from 'react'
import { useStaffWorkspace } from '../hooks/useStaffWorkspace'

const WORKSPACES = [
  {
    id: 'coordinator',
    label: 'Coordinator',
    description: 'Department internship management',
    icon: 'fa-sitemap',
  },
  {
    id: 'faculty',
    label: 'Faculty Supervisor',
    description: 'Assigned student supervision',
    icon: 'fa-chalkboard-user',
  },
]

/**
 * Polished workspace switcher for dual-role staff (Coordinator + Faculty).
 * Authorization still comes from useStaffWorkspace — only authorized options appear.
 */
export default function RoleWorkspaceSwitcher() {
  const { workspace, canSwitch, switchWorkspace } = useStaffWorkspace()
  const [open, setOpen] = useState(false)
  const [switching, setSwitching] = useState(false)
  const rootRef = useRef(null)
  const listId = useId()

  const current = WORKSPACES.find((w) => w.id === workspace) || WORKSPACES[0]

  useEffect(() => {
    if (!open) return undefined
    const onPointer = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false)
    }
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onPointer)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onPointer)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  if (!canSwitch) return null

  const select = (next) => {
    if (next === workspace || switching) {
      setOpen(false)
      return
    }
    setSwitching(true)
    setOpen(false)
    // Navigate to the target workspace home (clears prior-role page tree).
    switchWorkspace(next)
  }

  return (
    <div className={`workspace-switcher${open ? ' is-open' : ''}`} ref={rootRef}>
      <button
        type="button"
        className="workspace-switcher-trigger"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={listId}
        title="Switch between Coordinator and Faculty Supervisor workspaces"
        onClick={() => setOpen((v) => !v)}
        disabled={switching}
      >
        <span className="workspace-switcher-icon" aria-hidden="true">
          <i className={`fa ${current.icon}`} />
        </span>
        <span className="workspace-switcher-label">{current.label}</span>
        <i className={`fa fa-chevron-${open ? 'up' : 'down'} workspace-switcher-caret`} aria-hidden="true" />
      </button>

      <div
        id={listId}
        className="workspace-switcher-menu"
        role="menu"
        hidden={!open}
      >
        <div className="workspace-switcher-heading">Switch workspace</div>
        {WORKSPACES.map((item) => {
          const selected = item.id === workspace
          return (
            <button
              key={item.id}
              type="button"
              role="menuitemradio"
              aria-checked={selected}
              className={`workspace-switcher-option${selected ? ' is-selected' : ''}`}
              onClick={() => select(item.id)}
            >
              <span className="workspace-switcher-option-icon" aria-hidden="true">
                <i className={`fa ${item.icon}`} />
              </span>
              <span className="workspace-switcher-option-copy">
                <span className="workspace-switcher-option-title">
                  {selected && <i className="fa fa-check me-1" aria-hidden="true" />}
                  {item.label}
                </span>
                <span className="workspace-switcher-option-desc">{item.description}</span>
              </span>
            </button>
          )
        })}
      </div>
    </div>
  )
}
