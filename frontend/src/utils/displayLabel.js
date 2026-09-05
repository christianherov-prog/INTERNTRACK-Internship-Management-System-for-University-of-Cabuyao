/**
 * Safe text for React children when APIs return either a string
 * or a related model ({ name, code, ... }).
 */
export function displayLabel(value, fallback = '') {
  if (value == null || value === '') return fallback
  if (typeof value === 'string' || typeof value === 'number') return String(value)
  if (typeof value === 'object') {
    const label = value.name || value.code || value.label || value.title
    return label ? String(label) : fallback
  }
  return fallback
}
