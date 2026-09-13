/** Shared Organization Type options for Director / Student / Coordinator UIs. */

export const ORG_TYPE_SPECIFY = '__specify__'

export const ORGANIZATION_TYPE_OPTIONS = [
  { value: '', label: 'Unspecified' },
  { value: 'industry', label: 'Industry' },
  { value: 'school', label: 'School' },
  { value: 'hospital', label: 'Hospital' },
  { value: 'government_office', label: 'Government Office' },
  { value: 'private_office', label: 'Private Office' },
  { value: 'ngo', label: 'NGO' },
  { value: ORG_TYPE_SPECIFY, label: 'Specify Organization Type' },
]

const PREDEFINED = new Set(
  ORGANIZATION_TYPE_OPTIONS
    .map((o) => o.value)
    .filter((v) => v && v !== ORG_TYPE_SPECIFY)
)

/**
 * Map a stored DB value into select + custom text fields.
 * Legacy "other" opens Specify with empty custom (Needs Specification).
 */
export function splitOrganizationType(stored) {
  const raw = (stored ?? '').toString().trim()
  if (!raw || raw.toLowerCase() === 'unspecified') {
    return { selectValue: '', customType: '' }
  }
  if (raw.toLowerCase() === 'other') {
    return { selectValue: ORG_TYPE_SPECIFY, customType: '' }
  }
  if (PREDEFINED.has(raw.toLowerCase())) {
    return { selectValue: raw.toLowerCase(), customType: '' }
  }
  return { selectValue: ORG_TYPE_SPECIFY, customType: raw }
}

/** Value to send to the API (never "other" or "Specify Organization Type"). */
export function resolveOrganizationTypeForApi(selectValue, customType) {
  if (selectValue === ORG_TYPE_SPECIFY) {
    return (customType || '').trim()
  }
  return selectValue || ''
}

export function validateOrganizationType(selectValue, customType) {
  if (selectValue !== ORG_TYPE_SPECIFY) return null
  const custom = (customType || '').trim()
  if (!custom) return 'Organization Type is required.'
  if (/^other$/i.test(custom)) return 'Please specify the actual type of organization.'
  return null
}

/**
 * Organization Type select + optional custom text field.
 */
export default function OrganizationTypeField({
  id = 'organization-type',
  selectValue,
  customType,
  onSelectChange,
  onCustomChange,
  disabled = false,
  required = false,
  error = null,
  className = '',
}) {
  const showCustom = selectValue === ORG_TYPE_SPECIFY

  return (
    <div className={className}>
      <label className="form-label fw-semibold" htmlFor={id}>
        Organization Type{required ? <span className="text-danger"> *</span> : null}
      </label>
      <select
        id={id}
        className={`form-select${error && !showCustom ? ' is-invalid' : ''}`}
        value={selectValue}
        disabled={disabled}
        onChange={(e) => onSelectChange(e.target.value)}
      >
        {ORGANIZATION_TYPE_OPTIONS.map((opt) => (
          <option key={opt.value || 'unspecified'} value={opt.value}>{opt.label}</option>
        ))}
      </select>
      {showCustom && (
        <div className="mt-2">
          <label className="form-label fw-semibold" htmlFor={`${id}-custom`}>
            Organization Type <span className="text-danger">*</span>
          </label>
          <input
            id={`${id}-custom`}
            type="text"
            className={`form-control${error ? ' is-invalid' : ''}`}
            value={customType}
            disabled={disabled}
            placeholder="Organization Type"
            maxLength={100}
            onChange={(e) => onCustomChange(e.target.value)}
            required
          />
        </div>
      )}
      {error && <div className="invalid-feedback d-block">{error}</div>}
    </div>
  )
}
