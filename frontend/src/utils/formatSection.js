/**
 * Format a raw section code like "4ITD", "4IT-D", "4 - 4IT-D", "4IT - D"
 * into the canonical display form "4IT - D".
 */
export function formatYearSection(section, yearLevel = null) {
  let raw = String(section ?? '').trim()
  if (!raw) {
    return yearLevel != null && String(yearLevel).trim() !== ''
      ? String(yearLevel)
      : null
  }

  // Strip redundant duplicate year prefix if present (e.g. "4 - 4IT-D" -> "4IT-D")
  raw = raw.replace(/^(\d+)\s*-\s*(?=\1)/, '')

  // Matches 4ITD, 4IT-D, 4IT - D, 4_IT_D, 4CPEA, 4CPE-A, etc.
  const match = /^(\d+)[\s\-_]*([A-Za-z]+?)[\s\-_]*([A-Za-z])$/.exec(raw)
  if (match) {
    return `${match[1]}${match[2].toUpperCase()} - ${match[3].toUpperCase()}`
  }

  // Matches ITD, IT-D, IT - D with separate yearLevel
  const matchNoYear = /^([A-Za-z]+?)[\s\-_]*([A-Za-z])$/.exec(raw)
  if (matchNoYear) {
    const yr = yearLevel != null && String(yearLevel).trim() !== '' ? String(yearLevel).trim() : ''
    return `${yr}${matchNoYear[1].toUpperCase()} - ${matchNoYear[2].toUpperCase()}`
  }

  return raw
}
