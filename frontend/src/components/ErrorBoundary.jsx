import { Component } from 'react'

/**
 * Catches unhandled React render errors and shows a recovery UI
 * instead of a blank white screen.
 */
class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }

  componentDidCatch(error, info) {
    if (import.meta.env.DEV) {
      console.error('ErrorBoundary caught:', error, info)
    }
  }

  handleReload = () => {
    window.location.reload()
  }

  handleReset = () => {
    this.setState({ hasError: false, error: null })
  }

  render() {
    if (this.state.hasError) {
      return (
        <div
          className="d-flex align-items-center justify-content-center min-vh-100 p-4"
          style={{ background: '#f8fafc' }}
          role="alert"
        >
          <div className="text-center" style={{ maxWidth: 420 }}>
            <div className="mb-3" style={{ fontSize: '2.5rem', color: '#b45309' }}>
              <i className="fa fa-exclamation-triangle" aria-hidden="true" />
            </div>
            <h1 className="h4 mb-2">Something went wrong</h1>
            <p className="text-muted mb-4">
              An unexpected error occurred while rendering this page. You can try again
              or reload the application.
            </p>
            <div className="d-flex gap-2 justify-content-center flex-wrap">
              <button type="button" className="btn btn-outline-secondary" onClick={this.handleReset}>
                Try again
              </button>
              <button type="button" className="btn btn-success" onClick={this.handleReload}>
                Reload page
              </button>
            </div>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}

export default ErrorBoundary
