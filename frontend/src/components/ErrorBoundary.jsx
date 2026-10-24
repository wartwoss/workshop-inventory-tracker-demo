import { Component } from 'react'
import { Alert } from './Alert'

export class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }

  componentDidCatch(error, errorInfo) {
    // eslint-disable-next-line no-console
    console.error('ErrorBoundary caught an error:', error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return (
        <Alert tone="error" title="Something went wrong">
          <div style={{ fontFamily: 'monospace', fontSize: '0.85rem', marginTop: 8 }}>
            {this.state.error?.message || 'Unknown error'}
          </div>
        </Alert>
      )
    }

    return this.props.children
  }
}
