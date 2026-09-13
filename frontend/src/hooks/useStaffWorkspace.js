import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { cacheClear } from '../utils/pageCache'

export const STAFF_WORKSPACE_KEY = 'interntrack_staff_workspace'

const WORKSPACE_HOME = {
  coordinator: '/coordinator/monitoring',
  faculty: '/faculty/dashboard',
}

function workspaceFromPath(pathname) {
  if (pathname.startsWith('/faculty')) return 'faculty'
  if (pathname.startsWith('/coordinator')) return 'coordinator'
  return null
}

function storedWorkspace() {
  const stored = sessionStorage.getItem(STAFF_WORKSPACE_KEY)
  return stored === 'faculty' || stored === 'coordinator' ? stored : null
}

/**
 * Coordinators can work in two workspaces with one login:
 * their own Coordinator workspace (department-wide) and the
 * Faculty Supervisor workspace (advisee-scoped, /faculty pages).
 * All other roles have a single fixed workspace equal to their role.
 */
export function useStaffWorkspace() {
  const { user } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  const canSwitch = user?.role === 'coordinator'

  let workspace = user?.role || null
  if (canSwitch) {
    workspace = workspaceFromPath(location.pathname) || storedWorkspace() || 'coordinator'
  }

  // Remember the last-used workspace (also covers direct URLs/bookmarks).
  useEffect(() => {
    if (!canSwitch) return
    const fromPath = workspaceFromPath(location.pathname)
    if (fromPath) sessionStorage.setItem(STAFF_WORKSPACE_KEY, fromPath)
  }, [canSwitch, location.pathname])

  const switchWorkspace = (next) => {
    if (!canSwitch || !WORKSPACE_HOME[next] || next === workspace) return
    sessionStorage.setItem(STAFF_WORKSPACE_KEY, next)
    // Drop cached page payloads so the prior workspace cannot flash unauthorized data.
    cacheClear()
    navigate(WORKSPACE_HOME[next])
  }

  return { workspace, canSwitch, switchWorkspace }
}
