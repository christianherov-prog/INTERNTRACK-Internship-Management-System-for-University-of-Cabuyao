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
      <div className="nav-tabs-wrapper mb-4">
        <div>
          <ul className="nav nav-tabs custom-tabs" role="tablist">
            <li className="nav-item">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'placements'}
                className={`nav-link ${activeTab === 'placements' ? 'active' : ''}`}
                onClick={() => setActiveTab('placements')}
              >
                <i className="fa fa-paper-plane me-2"></i>Applications & Placements
              </button>
            </li>
            <li className="nav-item">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'hte'}
                className={`nav-link ${activeTab === 'hte' ? 'active' : ''}`}
                onClick={() => setActiveTab('hte')}
              >
                <i className="fa fa-handshake me-2"></i>HTE Requests
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div>
        <div className="tab-embedded" hidden={activeTab !== 'placements'}><CoordPlacements embedded={true} /></div>
        <div className="tab-embedded" hidden={activeTab !== 'hte'}><CoordHteRequests embedded={true} /></div>
      </div>
    </Layout>
  )
}

export default CoordPlacementHub
