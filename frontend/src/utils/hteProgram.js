/**
 * Program-defined HTE placement count.
 * Single-HTE programs should not show a deployment switcher.
 */

const MULTI_HTE_CODES = ['BSED', 'BEED', 'BSN', 'BSPSY']

const MULTI_HTE_NAME_MARKERS = [
  'nursing',
  'psychology',
  'secondary education',
  'elementary education',
]

export function isMultiHteProgram(user) {
  const code = String(user?.program_code || user?.program?.code || '').toUpperCase()
  if (MULTI_HTE_CODES.includes(code)) return true

  const name = [
    typeof user?.program === 'string' ? user.program : user?.program?.name,
    user?.student_profile?.program?.name,
    user?.studentProfile?.program?.name,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

  return MULTI_HTE_NAME_MARKERS.some((marker) => name.includes(marker))
}
