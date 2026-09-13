import { useLocation } from 'react-router-dom'
import { CCS_DEMO, displayPersonName } from './ccsMockData'
import { DEMO_MODULES } from './demoModules'

export default function DemoPlaceholder() {
  const location = useLocation()
  const module = DEMO_MODULES.find((item) => item.to === location.pathname)

  return (
    <div className="content-card">
      <div className="content-card-header">
        <i className={`fa ${module?.icon || 'fa-file'}`}></i>
        <h6>{module?.text || 'Demo module'}</h6>
      </div>
      <div className="p-4">
        <p className="mb-2">This module will be built in a later pass using the shared CCS dataset.</p>
        <p className="text-muted small mb-0">
          Sample student already wired: {displayPersonName(CCS_DEMO.student)} ({CCS_DEMO.student.student_number})
          at {CCS_DEMO.company.company_name}, supervised by {displayPersonName(CCS_DEMO.supervisor)}.
        </p>
      </div>
    </div>
  )
}
