/**
 * Display a profile value, or N/A when missing / not applicable.
 * Never store the string "N/A" in the API — only use it in the UI.
 */
export function displayOrNA(value) {
  if (value == null) return 'N/A'
  const v = String(value).trim()
  return v !== '' ? v : 'N/A'
}
