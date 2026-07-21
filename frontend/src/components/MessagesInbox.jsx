import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import Layout from './Layout'
import PageError from './PageError'
import api from '../services/api'
import { unwrapList } from '../utils/apiList'
import { useAuth } from '../contexts/AuthContext'

function roleLabel(role) {
  const map = {
    student: 'Student',
    supervisor: 'Industry Supervisor',
    faculty: 'Faculty Supervisor',
    coordinator: 'Coordinator',
  }
  return map[role] || role
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

/**
 * Internship-scoped inbox shared by student / supervisor / faculty / coordinator.
 */
function MessagesInbox({ titleSubtitle, bodyClass }) {
  const { user } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [archived, setArchived] = useState(false)
  const [threads, setThreads] = useState([])
  const [listMeta, setListMeta] = useState(null)
  const [listPage, setListPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [active, setActive] = useState(null) // { internship_id, peer }
  const [messages, setMessages] = useState([])
  const [threadMeta, setThreadMeta] = useState(null)
  const [threadPage, setThreadPage] = useState(1)
  const [threadLoading, setThreadLoading] = useState(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [sendError, setSendError] = useState(null)
  const deepLinkHandled = useRef(false)
  const threadEndRef = useRef(null)

  const loadThreads = useCallback((page = 1, append = false) => {
    setLoading(!append)
    setError(null)
    api.get('/messages/conversations', {
      params: { archived: archived ? 1 : 0, page, per_page: 20 },
    })
      .then((res) => {
        const { items, meta } = unwrapList(res.data)
        setThreads((prev) => (append ? [...prev, ...items] : items))
        setListMeta(meta)
        setListPage(page)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load conversations.')
        if (!append) setThreads([])
      })
      .finally(() => setLoading(false))
  }, [archived])

  useEffect(() => {
    deepLinkHandled.current = false
    setActive(null)
    setMessages([])
    loadThreads(1, false)
  }, [loadThreads])

  const fetchThreadPage = async (internshipId, peerId, page, { prepend = false } = {}) => {
    const res = await api.get(`/messages/conversations/${internshipId}/${peerId}`, {
      params: { page, per_page: 50 },
    })
    const chunk = res.data.messages || []
    setThreadMeta(res.data)
    setThreadPage(page)
    setMessages((prev) => (prepend ? [...chunk, ...prev] : chunk))
    return res.data
  }

  const openThread = async (thread, { replaceUrl = true } = {}) => {
    setActive({ internship_id: thread.internship_id, peer: thread.peer })
    setThreadLoading(true)
    setSendError(null)
    setDraft('')
    try {
      await fetchThreadPage(thread.internship_id, thread.peer.id, 1, { prepend: false })
      loadThreads(1, false)
      if (replaceUrl) {
        setSearchParams({
          internship_id: String(thread.internship_id),
          peer_id: String(thread.peer.id),
        })
      }
      requestAnimationFrame(() => {
        threadEndRef.current?.scrollIntoView({ block: 'end' })
      })
    } catch (err) {
      setSendError(err.response?.data?.message || 'Failed to open conversation.')
      setMessages([])
    } finally {
      setThreadLoading(false)
    }
  }

  // Deep-link from notification: ?internship_id=&peer_id=
  useEffect(() => {
    if (deepLinkHandled.current || loading || threads.length === 0) return
    const internshipId = Number(searchParams.get('internship_id'))
    const peerId = Number(searchParams.get('peer_id'))
    if (!internshipId || !peerId) return

    const match = threads.find(
      (t) => t.internship_id === internshipId && t.peer?.id === peerId
    )
    deepLinkHandled.current = true
    if (match) {
      openThread(match, { replaceUrl: false })
    } else {
      // Thread may be on another page / archived — open directly
      openThread(
        { internship_id: internshipId, peer: { id: peerId, name: '…', role: '' } },
        { replaceUrl: false }
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading, threads, searchParams])

  const loadOlderMessages = async () => {
    if (!active || !threadMeta?.meta) return
    const next = threadPage + 1
    if (next > threadMeta.meta.last_page) return
    setLoadingOlder(true)
    try {
      await fetchThreadPage(active.internship_id, active.peer.id, next, { prepend: true })
    } catch (err) {
      setSendError(err.response?.data?.message || 'Failed to load earlier messages.')
    } finally {
      setLoadingOlder(false)
    }
  }

  const handleSend = async (e) => {
    e?.preventDefault?.()
    if (!active || draft.trim().length < 1) return
    setSending(true)
    setSendError(null)
    try {
      const res = await api.post('/messages', {
        internship_id: active.internship_id,
        recipient_id: active.peer.id,
        body: draft.trim(),
      })
      const created = res.data.data
      setMessages((prev) => [...prev, created])
      setDraft('')
      loadThreads(1, false)
      requestAnimationFrame(() => {
        threadEndRef.current?.scrollIntoView({ block: 'end' })
      })
    } catch (err) {
      const status = err.response?.status
      const msg = err.response?.data?.message
      if (status === 429) {
        setSendError(msg || 'Too many messages sent. Please wait a moment before sending again.')
      } else {
        setSendError(msg || 'Failed to send message.')
      }
    } finally {
      setSending(false)
    }
  }

  const onComposerKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend(e)
    }
  }

  const hasOlder = threadMeta?.meta && threadPage < threadMeta.meta.last_page
  const canLoadMoreThreads = listMeta && listPage < listMeta.last_page

  return (
    <Layout title="Messages" subtitle={titleSubtitle} icon="fa-envelope" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={() => loadThreads(1, false)} />}

      <div className="d-flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Inbox views">
        <button
          type="button"
          className={`btn btn-sm ${!archived ? 'btn-success' : 'btn-outline-secondary'}`}
          aria-selected={!archived}
          onClick={() => setArchived(false)}
        >
          Active
        </button>
        <button
          type="button"
          className={`btn btn-sm ${archived ? 'btn-success' : 'btn-outline-secondary'}`}
          aria-selected={archived}
          onClick={() => setArchived(true)}
        >
          Archived
        </button>
      </div>

      <div className="row g-3 messages-inbox-row">
        <div className="col-lg-4 col-12">
          <div className="content-card h-100">
            <div className="content-card-header">
              <i className="fa fa-inbox" aria-hidden="true"></i>
              <h6>{archived ? 'Archived conversations' : 'Conversations'}</h6>
            </div>
            <div
              className="table-card messages-thread-list"
              style={{ maxHeight: 520, overflowY: 'auto' }}
              role="list"
              aria-label="Conversation list"
            >
              {loading ? (
                <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted" aria-hidden="true"></i></div>
              ) : threads.length === 0 ? (
                <div className="text-center text-muted py-5 px-3">
                  <i className="fa fa-comments fa-2x mb-2 d-block" aria-hidden="true"></i>
                  {archived
                    ? 'No archived conversations yet.'
                    : 'No conversations yet. You can message people linked to your internship once they are assigned.'}
                </div>
              ) : (
                <>
                  <div className="list-group list-group-flush">
                    {threads.map((t) => {
                      const key = `${t.internship_id}-${t.peer.id}`
                      const isActive = active
                        && active.internship_id === t.internship_id
                        && active.peer.id === t.peer.id
                      return (
                        <button
                          type="button"
                          key={key}
                          role="listitem"
                          className={`list-group-item list-group-item-action text-start ${isActive ? 'active' : ''}`}
                          onClick={() => openThread(t)}
                          aria-label={`Conversation with ${t.peer.name}, ${roleLabel(t.peer.role)}`}
                        >
                          <div className="d-flex justify-content-between align-items-start gap-2">
                            <div>
                              <strong>{t.peer.name}</strong>
                              <div className="small opacity-75">{roleLabel(t.peer.role)}</div>
                              <div className="small opacity-75">{t.student_name} · {t.internship_term}</div>
                            </div>
                            {t.unread_count > 0 && (
                              <span className="badge bg-danger" aria-label={`${t.unread_count} unread`}>{t.unread_count}</span>
                            )}
                          </div>
                          {t.last_message && (
                            <div className="small mt-1 text-truncate" style={{ maxWidth: '100%' }}>
                              {t.last_message.body}
                              <span className="ms-1 opacity-75">· {timeAgo(t.last_message.created_at)}</span>
                            </div>
                          )}
                        </button>
                      )
                    })}
                  </div>
                  {canLoadMoreThreads && (
                    <div className="p-2 text-center">
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        onClick={() => loadThreads(listPage + 1, true)}
                      >
                        Load more conversations
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-8 col-12">
          <div className="content-card h-100 d-flex flex-column">
            <div className="content-card-header">
              <i className="fa fa-comments" aria-hidden="true"></i>
              <h6>
                {active
                  ? `${active.peer.name || 'Conversation'} (${roleLabel(active.peer.role) || '…'})`
                  : 'Select a conversation'}
              </h6>
            </div>

            {!active ? (
              <div className="text-center text-muted py-5">
                Choose a conversation from the list to read and send messages.
              </div>
            ) : threadLoading ? (
              <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted" aria-hidden="true"></i></div>
            ) : (
              <>
                {threadMeta?.internship?.term && (
                  <div className="px-3 pt-2 small text-muted">
                    Internship: {threadMeta.internship.term}
                    {threadMeta.internship.status ? ` · ${threadMeta.internship.status}` : ''}
                  </div>
                )}
                {hasOlder && (
                  <div className="px-3 pt-2">
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-secondary w-100"
                      onClick={loadOlderMessages}
                      disabled={loadingOlder}
                    >
                      {loadingOlder ? 'Loading…' : 'Load earlier messages'}
                    </button>
                  </div>
                )}
                <div
                  className="flex-grow-1 px-3 py-3 messages-thread-pane"
                  style={{ maxHeight: 380, overflowY: 'auto', background: 'rgba(0,0,0,0.02)' }}
                  role="log"
                  aria-live="polite"
                  aria-label="Message thread"
                >
                  {messages.length === 0 ? (
                    <div className="text-center text-muted py-4">No messages yet. Say hello.</div>
                  ) : (
                    messages.map((m) => {
                      const mine = m.sender_id === user?.id
                      return (
                        <div
                          key={m.id}
                          className={`mb-2 d-flex ${mine ? 'justify-content-end' : 'justify-content-start'}`}
                        >
                          <div
                            className={`rounded px-3 py-2 ${mine ? 'bg-success text-white' : 'bg-white border'}`}
                            style={{ maxWidth: '85%', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}
                          >
                            <div>{m.body}</div>
                            <div className={`small mt-1 ${mine ? 'text-white-50' : 'text-muted'}`}>
                              {timeAgo(m.created_at)}
                              {!mine && m.read_at ? ' · read' : ''}
                            </div>
                          </div>
                        </div>
                      )
                    })
                  )}
                  <div ref={threadEndRef} />
                </div>

                {sendError && (
                  <div className="alert alert-danger mx-3 mb-2 py-2" role="alert">{sendError}</div>
                )}

                <form onSubmit={handleSend} className="px-3 pb-3">
                  <label className="form-label visually-hidden" htmlFor="message-composer">
                    Message body
                  </label>
                  <div className="input-group">
                    <textarea
                      id="message-composer"
                      className="form-control"
                      rows={2}
                      placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                      value={draft}
                      onChange={(e) => setDraft(e.target.value)}
                      onKeyDown={onComposerKeyDown}
                      maxLength={5000}
                      required
                      aria-label="Message body"
                    />
                    <button
                      type="submit"
                      className="btn btn-success"
                      disabled={sending || draft.trim().length < 1}
                      aria-label="Send message"
                    >
                      {sending ? <i className="fa fa-spinner fa-spin" aria-hidden="true"></i> : <i className="fa fa-paper-plane" aria-hidden="true"></i>}
                    </button>
                  </div>
                  {archived && (
                    <div className="small text-muted mt-1">
                      Viewing an archived (ended) internship conversation.
                    </div>
                  )}
                </form>
              </>
            )}
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default MessagesInbox
