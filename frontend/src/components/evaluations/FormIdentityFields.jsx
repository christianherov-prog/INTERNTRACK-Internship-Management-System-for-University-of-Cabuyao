import { identityValue, MISSING_IDENTITY } from '../../utils/formIdentity'

export default function FormIdentityFields({ identity, fields = [] }) {
  if (!fields.length) return null

  return (
    <div className="border rounded-3 p-3 mb-4" style={{ background: '#f8fafc' }}>
      <div className="row g-3">
        {fields.map((field) => {
          const value = identityValue(identity, field.key)
          return (
            <div key={field.key} className={field.wide ? 'col-12' : 'col-md-6'}>
              <div
                className="text-muted text-uppercase fw-semibold"
                style={{ fontSize: '0.72rem', letterSpacing: '0.04em' }}
              >
                {field.label}
              </div>
              {value ? (
                <div className="fw-semibold text-dark" style={{ fontSize: '0.95rem' }}>{value}</div>
              ) : (
                <div className="small text-danger">{MISSING_IDENTITY}</div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
