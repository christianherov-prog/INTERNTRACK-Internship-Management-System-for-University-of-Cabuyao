/** Fixed name-suffix options (Philippine / iEnroll-style). Empty = N/A. */
export const SUFFIX_OPTIONS = ['Jr.', 'Sr.', 'II', 'III', 'IV']

/** Map select value to API payload (N/A / blank → null). */
export function suffixToApi(value) {
  const v = value == null ? '' : String(value).trim()
  if (!v || v.toUpperCase() === 'N/A') return null
  return v
}
