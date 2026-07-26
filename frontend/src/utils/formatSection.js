/**
 * Format a raw section code like "4ITD" into the shared display form "4 - IT D"
 * (same convention as AuthController::formatStudentSubtitle).
 */
export function formatYearSection(section, yearLevel = null) {
  const raw = String(section ?? '').trim()
  if (!raw) {
    return yearLevel != null && String(yearLevel).trim() !== ''
      ? String(yearLevel)
      : null
  }

  const match = /^(\d+)\s*([A-Za-z]+?)([A-Za-z])$/.exec(raw)
  if (match) {
    return `${match[1]} - ${match[2].toUpperCase()} ${match[3].toUpperCase()}`
  }

  if (yearLevel != null && String(yearLevel).trim() !== '') {
    return `${yearLevel} - ${raw}`
  }

  return raw
}
