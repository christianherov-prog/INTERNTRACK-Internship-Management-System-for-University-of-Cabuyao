/**
 * Official FO-31 DATE cell: compact same-month range (August 24–28, 2026).
 * Parses YYYY-MM-DD as calendar dates (not local TZ midnight).
 */
export function formatFo31DateRange(start, end) {
  const parse = (value) => {
    if (!value) return null
    const raw = String(value)
    if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
      const [y, m, d] = raw.slice(0, 10).split('-').map(Number)
      return { y, m, d }
    }
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return null
    return {
      y: parsed.getUTCFullYear(),
      m: parsed.getUTCMonth() + 1,
      d: parsed.getUTCDate(),
    }
  }

  const monthName = (m) =>
    new Date(Date.UTC(2000, m - 1, 1)).toLocaleDateString('en-US', { month: 'long', timeZone: 'UTC' })

  const longDate = (p) => (p ? `${monthName(p.m)} ${p.d}, ${p.y}` : '')

  const a = parse(start)
  const b = parse(end)
  if (!a && !b) return ''
  if (!b || (a && b && a.y === b.y && a.m === b.m && a.d === b.d)) return longDate(a || b)
  if (a && b && a.y === b.y && a.m === b.m) return `${monthName(a.m)} ${a.d}–${b.d}, ${a.y}`
  if (a && b && a.y === b.y) return `${monthName(a.m)} ${a.d}–${monthName(b.m)} ${b.d}, ${a.y}`
  return `${longDate(a)}–${longDate(b)}`
}

export function formatFo31WeekLabel(weekNumber) {
  if (weekNumber === null || weekNumber === undefined || weekNumber === '') return ''
  return `Week ${weekNumber}`
}
