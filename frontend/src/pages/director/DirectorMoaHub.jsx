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
      <div className="card shadow-sm mb-4 border-0">
        <div className="card-header bg-white border-bottom-0 pt-3 pb-0">
          <ul className="nav nav-tabs border-bottom-0">
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'monitoring' ? 'active fw-bold text-primary border-bottom-0' : 'text-muted border-0'}`}
                onClick={() => setActiveTab('monitoring')}
              >
                <i className="fa fa-chart-line me-2"></i>MOA Notary Monitoring
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'management' ? 'active fw-bold text-primary border-bottom-0' : 'text-muted border-0'}`}
                onClick={() => setActiveTab('management')}
              >
                <i className="fa fa-handshake me-2"></i>MOA Updates & Management
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div>
        {activeTab === 'monitoring' ? (
          <div className="tab-embedded"><DirectorMOAMonitoring embedded={true} /></div>
        ) : (
          <div className="tab-embedded"><DirectorMOAManagement embedded={true} /></div>
        )}
      </div>
    </Layout>
  )
}

export default DirectorMoaHub
