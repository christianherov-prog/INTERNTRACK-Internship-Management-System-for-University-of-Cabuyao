import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../../services/api'
import PageError from '../../components/PageError'
import InternTrackLoader from '../../components/InternTrackLoader'
import CCSPortfolioPreview from '../student/portfolio/CCSPortfolioPreview'
import COEPortfolioPreview from '../student/portfolio/COEPortfolioPreview'
import COEDPortfolioPreview from '../student/portfolio/COEDPortfolioPreview'
import PsychologyPortfolioPreview from '../student/portfolio/PsychologyPortfolioPreview'
import NursingPortfolioPreview from '../student/portfolio/NursingPortfolioPreview'
import { resolvePortfolioVariant } from '../../utils/portfolioVariant'

const BACK_TO = '/faculty/assigned-students'
const BACK_LABEL = 'Back to Assigned Students'
const MODE_LABEL = 'Read-only Faculty Preview'

const VARIANTS = {
  nursing: NursingPortfolioPreview,
  psychology: PsychologyPortfolioPreview,
  coed: COEDPortfolioPreview,
  coe: COEPortfolioPreview,
  ccs: CCSPortfolioPreview,
}

/**
 * Read-only preview of an assigned student's Portfolio for the Faculty role.
 *
 * The backend authorizes the request against the internship's faculty_id and
 * returns the same payload the student Portfolio uses. The portfolio layout is
 * chosen from the selected student's program, not from the logged-in faculty.
 * The college preview components render print views only, so no student
 * editing or upload controls are exposed here.
 */
export default function FacultyPortfolioPreview() {
  const { studentId } = useParams()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(() => {
    if (!/^\d+$/.test(String(studentId || ''))) {
      setError('Invalid student reference.')
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    setData(null)
    api.get(`/faculty/students/${studentId}/portfolio`)
      .then((res) => setData(res.data))
      .catch((err) => {
        const status = err.response?.status
        if (status === 403) setError(err.response?.data?.message || 'You are not assigned to this student.')
        else if (status === 404) setError('Student portfolio not found.')
        else setError(err.response?.data?.message || 'Failed to load portfolio.')
      })
      .finally(() => setLoading(false))
  }, [studentId])

  useEffect(() => { load() }, [load])

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <InternTrackLoader />
        <div className="small">Loading portfolio…</div>
      </div>
    )
  }

  if (error || !data?.internship) {
    return (
      <div style={{ background: '#e5e5e5', minHeight: '100vh', padding: '24px' }}>
        <PageError message={error || 'No internship record found for this student.'} onRetry={error ? load : null} />
        <div className="text-center mt-3">
          <Link to={BACK_TO} className="text-muted">
            <i className="fa fa-arrow-left me-2"></i>{BACK_LABEL}
          </Link>
        </div>
      </div>
    )
  }

  const Preview = VARIANTS[resolvePortfolioVariant(data.user)] || CCSPortfolioPreview

  return (
    <Preview
      key={studentId}
      preloadedData={data}
      backTo={BACK_TO}
      backLabel={BACK_LABEL}
      modeLabel={MODE_LABEL}
    />
  )
}
