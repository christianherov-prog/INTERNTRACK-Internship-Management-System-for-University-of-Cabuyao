import { createContext, useContext, useState, useCallback, useRef } from 'react'
import ConfirmModal from '../components/modals/ConfirmModal'

const ConfirmContext = createContext(null)

export const useConfirm = () => {
  const context = useContext(ConfirmContext)
  if (!context) {
    throw new Error('useConfirm must be used within a ConfirmProvider')
  }
  return context
}

export const ConfirmProvider = ({ children }) => {
  const [modalState, setModalState] = useState({
    open: false,
    title: 'Confirm action',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'primary',
    loading: false,
    loadingLabel: 'Working…',
    error: null,
    children: null,
  })

  const resolverRef = useRef(null)
  const runRef = useRef(null)

  const closeAndResolve = useCallback((value) => {
    setModalState((prev) => ({
      ...prev,
      open: false,
      loading: false,
      error: null,
      children: null,
    }))
    if (resolverRef.current) {
      resolverRef.current(value)
      resolverRef.current = null
    }
    runRef.current = null
  }, [])

  /**
   * Open a confirmation dialog.
   * - confirm(options) → Promise<boolean>
   * - confirm({ ..., run }) → Promise<boolean>; keeps modal open with loading
   *   while `run` executes; only resolves true after `run` succeeds.
   */
  const confirm = useCallback((options) => {
    const config = typeof options === 'string' ? { message: options } : (options || {})

    setModalState({
      open: true,
      title: config.title || 'Confirm action',
      message: config.message || '',
      confirmLabel: config.confirmLabel || 'Confirm',
      cancelLabel: config.cancelLabel || 'Cancel',
      variant: config.variant || 'primary',
      loading: false,
      loadingLabel: config.loadingLabel || 'Working…',
      error: null,
      children: config.children || null,
    })

    runRef.current = typeof config.run === 'function' ? config.run : null

    return new Promise((resolve) => {
      resolverRef.current = resolve
    })
  }, [])

  const handleConfirm = useCallback(async () => {
    const run = runRef.current
    if (!run) {
      closeAndResolve(true)
      return
    }

    setModalState((prev) => ({ ...prev, loading: true, error: null }))
    try {
      await run()
      closeAndResolve(true)
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        'Action failed. Please try again.'
      setModalState((prev) => ({ ...prev, loading: false, error: message }))
    }
  }, [closeAndResolve])

  const handleCancel = useCallback(() => {
    if (modalState.loading) return
    closeAndResolve(false)
  }, [closeAndResolve, modalState.loading])

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <ConfirmModal
        open={modalState.open}
        title={modalState.title}
        message={modalState.message}
        confirmLabel={modalState.confirmLabel}
        cancelLabel={modalState.cancelLabel}
        variant={modalState.variant}
        loading={modalState.loading}
        loadingLabel={modalState.loadingLabel}
        error={modalState.error}
        onConfirm={handleConfirm}
        onCancel={handleCancel}
      >
        {modalState.children}
      </ConfirmModal>
    </ConfirmContext.Provider>
  )
}
