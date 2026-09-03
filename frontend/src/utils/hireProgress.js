/**
 * Hire / absorption progress milestones for demos and records UI.
 * Separate from hours-only progress: 0 → 25 → 50 → 75 → 100 (hired or not).
 */

export const HIRE_MILESTONES = [
  { percent: 0, key: 'placed', short: 'Placed', label: 'Just placed / starting' },
  { percent: 25, key: 'early', short: '25%', label: 'Early internship progress' },
  { percent: 50, key: 'mid', short: '50%', label: 'Mid internship' },
  { percent: 75, key: 'pending', short: '75%', label: 'Completed — awaiting hire confirmation' },
  { percent: 100, key: 'final', short: '100%', label: 'Hire outcome recorded' },
]

/**
 * @param {object|null} internship
 * @returns {{ percent: number, stage: string, label: string, outcome: 'absorbed'|'not_hired'|null, hoursPercent: number }}
 */
export function computeHireProgress(internship) {
  if (!internship) {
    return {
      percent: 0,
      stage: 'none',
      label: 'No internship yet',
      outcome: null,
      hoursPercent: 0,
    }
  }

  const status = String(internship.status || '').toLowerCase()
  const absorption = internship.absorption_status || null
  const hours = Number(internship.total_hours_rendered) || 0
  const target = Number(internship.target_hours) || 360
  const hoursPercent = target > 0 ? Math.min(100, Math.round((hours / target) * 100)) : 0

  if (absorption === 'absorbed') {
    return {
      percent: 100,
      stage: 'absorbed',
      label: 'Absorbed / Hired',
      outcome: 'absorbed',
      hoursPercent,
    }
  }

  if (absorption === 'not_hired') {
    return {
      percent: 100,
      stage: 'not_hired',
      label: 'Not Hired',
      outcome: 'not_hired',
      hoursPercent,
    }
  }

  if (status === 'completed' || absorption === 'pending') {
    return {
      percent: 75,
      stage: 'pending_absorption',
      label: 'Completed — awaiting hire confirmation',
      outcome: null,
      hoursPercent,
    }
  }

  if (
    status === 'pending_placement' ||
    (!internship.company_id && !internship.company)
  ) {
    return {
      percent: 0,
      stage: 'not_placed',
      label: 'Pending placement',
      outcome: null,
      hoursPercent,
    }
  }

  // Active / placed / for_evaluation — snap hours to 0 / 25 / 50 until completion.
  if (hoursPercent < 12.5) {
    return {
      percent: 0,
      stage: 'placed',
      label: 'Just placed / starting',
      outcome: null,
      hoursPercent,
    }
  }
  if (hoursPercent < 37.5) {
    return {
      percent: 25,
      stage: 'early',
      label: 'Early internship progress (~25% hours)',
      outcome: null,
      hoursPercent,
    }
  }

  return {
    percent: 50,
    stage: 'mid',
    label: 'Mid internship (~50%+ hours)',
    outcome: null,
    hoursPercent,
  }
}
