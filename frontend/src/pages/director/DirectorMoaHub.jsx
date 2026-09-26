import { useState } from 'react'
import Layout from '../../components/Layout'
import DirectorMOAMonitoring from './DirectorMOAMonitoring'
import DirectorMOAManagement from './DirectorMOAManagement'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

function DirectorMoaHub() {
  const currentTerm = useCurrentTerm()
  const [activeTab, setActiveTab] = useState('monitoring')

  return (
    <Layout title="MOA Management" subtitle={currentTerm} icon="fa-file-signature" bodyClass="director-page">
      <div className="nav-tabs-wrapper mb-4">
        <div>
          <ul className="nav nav-tabs custom-tabs" role="tablist">
            <li className="nav-item">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'monitoring'}
                className={`nav-link ${activeTab === 'monitoring' ? 'active' : ''}`}
                onClick={() => setActiveTab('monitoring')}
              >
                <i className="fa fa-chart-line me-2"></i>MOA Notary Monitoring
              </button>
            </li>
            <li className="nav-item">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'management'}
                className={`nav-link ${activeTab === 'management' ? 'active' : ''}`}
                onClick={() => setActiveTab('management')}
              >
                <i className="fa fa-handshake me-2"></i>MOA Updates & Management
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div>
        <div className="tab-embedded" hidden={activeTab !== 'monitoring'}><DirectorMOAMonitoring embedded={true} /></div>
        <div className="tab-embedded" hidden={activeTab !== 'management'}><DirectorMOAManagement embedded={true} /></div>
      </div>
    </Layout>
  )
}

export default DirectorMoaHub
