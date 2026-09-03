import { useState } from 'react'
import Layout from '../../components/Layout'
import CoordPlacements from './CoordPlacements'
import CoordHteRequests from './CoordHteRequests'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

function CoordPlacementHub() {
  const currentTerm = useCurrentTerm()
  const [activeTab, setActiveTab] = useState('placements')

  return (
    <Layout title="Internship Management" subtitle={currentTerm} icon="fa-briefcase" bodyClass="coordinator-page">
      <div className="card shadow-sm mb-4 border-0">
        <div className="card-header bg-white border-bottom-0 pt-3 pb-0">
          <ul className="nav nav-tabs border-bottom-0">
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'placements' ? 'active fw-bold text-primary border-bottom-0' : 'text-muted border-0'}`}
                onClick={() => setActiveTab('placements')}
              >
                <i className="fa fa-paper-plane me-2"></i>Applications & Placements
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'hte' ? 'active fw-bold text-primary border-bottom-0' : 'text-muted border-0'}`}
                onClick={() => setActiveTab('hte')}
              >
                <i className="fa fa-handshake me-2"></i>HTE Requests
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div>
        {activeTab === 'placements' ? (
          <div className="tab-embedded"><CoordPlacements embedded={true} /></div>
        ) : (
          <div className="tab-embedded"><CoordHteRequests embedded={true} /></div>
        )}
      </div>
    </Layout>
  )
}

export default CoordPlacementHub
