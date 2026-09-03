import React, { useState, useEffect } from 'react'

export default function AccessDeniedOverlay() {
  const [deniedMessage, setDeniedMessage] = useState(null)

  useEffect(() => {
    const handleAccessDenied = (e) => {
      setDeniedMessage(e.detail || 'Access Denied: You do not have permission to access this department\'s resources.')
    }

    window.addEventListener('access-denied', handleAccessDenied)
    return () => window.removeEventListener('access-denied', handleAccessDenied)
  }, [])

  if (!deniedMessage) return null

  return (
    <div style={{
      position: 'fixed',
      top: 0, left: 0, right: 0, bottom: 0,
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      backdropFilter: 'blur(10px)',
      zIndex: 99999,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: '#fff',
      padding: '20px'
    }}>
      <div style={{
        background: 'rgba(30, 41, 59, 0.8)',
        border: '1px solid rgba(255, 50, 50, 0.3)',
        borderRadius: '16px',
        padding: '40px',
        maxWidth: '500px',
        textAlign: 'center',
        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(239, 68, 68, 0.1)'
      }}>
        <div style={{
          width: '64px',
          height: '64px',
          borderRadius: '50%',
          background: 'rgba(239, 68, 68, 0.1)',
          color: '#ef4444',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          margin: '0 auto 20px',
          fontSize: '32px'
        }}>
          <i className="bi bi-shield-lock-fill"></i>
        </div>
        <h2 style={{ margin: '0 0 16px', fontSize: '24px', fontWeight: '600' }}>Access Restricted</h2>
        <p style={{ margin: '0 0 24px', color: '#94a3b8', lineHeight: '1.6' }}>{deniedMessage}</p>
        <button 
          onClick={() => {
            setDeniedMessage(null)
            window.history.back()
          }}
          style={{
            background: '#ef4444',
            color: '#fff',
            border: 'none',
            padding: '12px 24px',
            borderRadius: '8px',
            fontWeight: '500',
            cursor: 'pointer',
            transition: 'background 0.2s'
          }}
          onMouseOver={(e) => e.target.style.background = '#dc2626'}
          onMouseOut={(e) => e.target.style.background = '#ef4444'}
        >
          Go Back
        </button>
      </div>
    </div>
  )
}
