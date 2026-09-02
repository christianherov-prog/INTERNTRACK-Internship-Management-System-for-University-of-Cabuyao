/**
 * Resolve which My Portfolio structure to show from the student's program
 * (and college as a secondary signal). Does not use role.
 */
export function resolvePortfolioVariant(user) {
  const program = normalizeProgram(user)
  const haystack = program.toLowerCase()

  if (haystack.includes('nursing') || haystack.includes('bsn') || haystack.includes('ncm 122')) {
    return 'nursing'
  }
  if (haystack.includes('psychology') || haystack.includes('bspsy')) {
    return 'psychology'
  }
  if (haystack.includes('education') || haystack.includes('coed') || haystack.includes('elementary') || haystack.includes('secondary education')) {
    return 'coed'
  }
  if (haystack.includes('engineering') || /\bcoe\b/.test(haystack) || haystack.includes('civil engineering') || haystack.includes('computer engineering')) {
    return 'coe'
  }
  return 'ccs'
}

function normalizeProgram(user) {
  const program = user?.program
  const fromProgram = typeof program === 'string'
    ? program
    : (program?.name || program?.code || '')
  const dept = user?.department
  const fromDept = typeof dept === 'string'
    ? dept
    : (dept?.name || dept?.code || '')
  const profile = user?.student_profile
  const fromProfile = typeof profile?.program === 'string'
    ? profile.program
    : (profile?.program?.name || profile?.program?.code || '')
  return [fromProgram, fromProfile, user?.program_code, fromDept].filter(Boolean).join(' ')
}
