import {
  memo,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import { useSearchParams } from 'react-router-dom'
import Layout from './Layout'
import PageError from './PageError'
import api from '../services/api'
import { unwrapList } from '../utils/apiList'
import { useAuth } from '../contexts/AuthContext'
import '../styles/messages.css'

function roleLabel(role) {
  const map = {
    student: 'Student',
    supervisor: 'Industry Supervisor',
    faculty: 'Faculty Supervisor',
    coordinator: 'Coordinator',
  }
  return map[role] || role || 'Stakeholder'
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (diff < 0) return 'just now'
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function initials(name) {
  if (!name || name === '…') return '?'
  const parts = String(name).trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
}

function threadKey(internshipId, peerId) {
  return `${internshipId}-${peerId}`
}

function ConversationRow({ thread, isActive, onSelect }) {
  const preview = thread.last_message?.body || 'No messages yet'
  const when = thread.last_message?.created_at

  return (
    <button
      type="button"
      role="listitem"
      className={`msg-conv-item ${isActive ? 'is-active' : ''} ${thread.unread_count > 0 ? 'has-unread' : ''}`}
      onClick={() => onSelect(thread)}
      aria-current={isActive ? 'true' : undefined}
      aria-label={`Conversation with ${thread.peer.name}, ${roleLabel(thread.peer.role)}`}
    >
      <div className="msg-conv-avatar" aria-hidden="true">
        {initials(thread.peer.name)}
      </div>
      <div className="msg-conv-body">
        <div className="msg-conv-top">
          <span className="msg-conv-name">{thread.peer.name}</span>
          {when && <span className="msg-conv-time">{timeAgo(when)}</span>}
        </div>
        <div className="msg-conv-meta">
          {roleLabel(thread.peer.role)}
          {thread.student_name ? ` · ${thread.student_name}` : ''}
        </div>
        <div className="msg-conv-preview">{preview}</div>
      </div>
      {thread.unread_count > 0 && (
        <span className="msg-unread-badge" aria-label={`${thread.unread_count} unread`}>
          {thread.unread_count > 99 ? '99+' : thread.unread_count}
        </span>
      )}
    </button>
  )
}

const MemoConversationRow = memo(ConversationRow)

function MessageBubble({ message, mine }) {
  const pending = Boolean(message._pending)
  const failed = Boolean(message._failed)

  return (
    <div
      className={`msg-bubble-row ${mine ? 'is-mine' : 'is-theirs'} ${pending ? 'is-pending' : ''} ${failed ? 'is-failed' : ''}`}
    >
      <div className={`msg-bubble ${mine ? 'msg-bubble-mine' : 'msg-bubble-theirs'}`}>
        <div className="msg-bubble-text">{message.body}</div>
        <div className="msg-bubble-meta">
          {pending ? 'Sending…' : failed ? 'Failed to send' : timeAgo(message.created_at)}
          {!mine && !pending && message.read_at ? ' · Read' : ''}
        </div>
      </div>
    </div>
  )
}

const MemoMessageBubble = memo(MessageBubble)

function ListSkeleton() {
  return (
    <div className="msg-skeleton-list" aria-hidden="true">
      {[0, 1, 2, 3, 4].map((i) => (
        <div key={i} className="msg-skeleton-row">
          <div className="msg-skeleton-avatar" />
          <div className="msg-skeleton-lines">
            <div className="msg-skeleton-line w-60" />
            <div className="msg-skeleton-line w-40" />
            <div className="msg-skeleton-line w-80" />
          </div>
        </div>
      ))}
    </div>
  )
}

function ThreadSkeleton() {
  return (
    <div className="msg-skeleton-thread" aria-hidden="true">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className={`msg-skeleton-bubble ${i % 2 === 0 ? 'left' : 'right'}`}>
          <div className="msg-skeleton-line" />
          <div className="msg-skeleton-line w-40" />
        </div>
      ))}
    </div>
  )
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
  const [listLoading, setListLoading] = useState(true)
  const [listRefreshing, setListRefreshing] = useState(false)
  const [error, setError] = useState(null)
  const [active, setActive] = useState(null)
  const [messages, setMessages] = useState([])
  const [threadMeta, setThreadMeta] = useState(null)
  const [threadPage, setThreadPage] = useState(1)
  const [threadLoading, setThreadLoading] = useState(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [sendError, setSendError] = useState(null)

  const deepLinkHandled = useRef(false)
  const paneRef = useRef(null)
  const stickToBottomRef = useRef(true)
  const activeRef = useRef(null)
  const tempIdRef = useRef(0)

  useEffect(() => {
    activeRef.current = active
  }, [active])

  const isNearBottom = useCallback(() => {
    const el = paneRef.current
    if (!el) return true
    return el.scrollHeight - el.scrollTop - el.clientHeight < 80
  }, [])

  const scrollToBottom = useCallback((behavior = 'auto') => {
    const el = paneRef.current
    if (!el) return
    requestAnimationFrame(() => {
      el.scrollTo({ top: el.scrollHeight, behavior })
    })
  }, [])

  const onPaneScroll = () => {
    stickToBottomRef.current = isNearBottom()
  }

  const loadThreads = useCallback((page = 1, { append = false, silent = false } = {}) => {
    if (!silent && !append) setListLoading(true)
    if (silent) setListRefreshing(true)
    setError(null)
    return api.get('/messages/conversations', {
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
      .finally(() => {
        setListLoading(false)
        setListRefreshing(false)
      })
  }, [archived])

  useEffect(() => {
    deepLinkHandled.current = false
    setActive(null)
    setMessages([])
    setThreadMeta(null)
    stickToBottomRef.current = true
    loadThreads(1, { append: false, silent: false })
  }, [loadThreads])

  const fetchThreadPage = async (internshipId, peerId, page, { prepend = false } = {}) => {
    const res = await api.get(`/messages/conversations/${internshipId}/${peerId}`, {
      params: { page, per_page: 50 },
    })
    const chunk = res.data.messages || []
    setThreadMeta(res.data)
    setThreadPage(page)
    setMessages((prev) => {
      if (prepend) return [...chunk, ...prev]
      // Keep optimistic pending/failed locals that aren't on the server yet
      const locals = prev.filter((m) => m._pending || m._failed)
      const ids = new Set(chunk.map((m) => m.id))
      const keepLocals = locals.filter((m) => !ids.has(m.id) && !ids.has(m._serverId))
      return [...chunk, ...keepLocals]
    })
    return res.data
  }

  const openThread = async (thread, { replaceUrl = true } = {}) => {
    const same =
      active
      && active.internship_id === thread.internship_id
      && active.peer.id === thread.peer.id

    if (same && messages.length > 0 && !threadLoading) {
      // Already open — soft refresh without clearing UI
      try {
        await fetchThreadPage(thread.internship_id, thread.peer.id, 1, { prepend: false })
        if (stickToBottomRef.current) scrollToBottom('smooth')
        loadThreads(1, { silent: true })
      } catch {
        /* ignore soft refresh errors */
      }
      return
    }

    setActive({ internship_id: thread.internship_id, peer: thread.peer })
    setThreadLoading(true)
    setSendError(null)
    if (!same) setDraft('')
    stickToBottomRef.current = true

    try {
      await fetchThreadPage(thread.internship_id, thread.peer.id, 1, { prepend: false })
      loadThreads(1, { silent: true })
      if (replaceUrl) {
        setSearchParams({
          internship_id: String(thread.internship_id),
          peer_id: String(thread.peer.id),
        }, { replace: true })
      }
      scrollToBottom('auto')
    } catch (err) {
      setSendError(err.response?.data?.message || 'Failed to open conversation.')
      setMessages([])
    } finally {
      setThreadLoading(false)
    }
  }

  // Deep-link from notification: ?internship_id=&peer_id=
  useEffect(() => {
    if (deepLinkHandled.current || listLoading || threads.length === 0) return
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
      openThread(
        { internship_id: internshipId, peer: { id: peerId, name: '…', role: '' } },
        { replaceUrl: false }
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [listLoading, threads, searchParams])

  const loadOlderMessages = async () => {
    if (!active || !threadMeta?.meta) return
    const next = threadPage + 1
    if (next > threadMeta.meta.last_page) return
    const el = paneRef.current
    const prevHeight = el?.scrollHeight || 0
    const prevTop = el?.scrollTop || 0
    setLoadingOlder(true)
    try {
      await fetchThreadPage(active.internship_id, active.peer.id, next, { prepend: true })
      requestAnimationFrame(() => {
        if (!paneRef.current) return
        const delta = paneRef.current.scrollHeight - prevHeight
        paneRef.current.scrollTop = prevTop + delta
      })
    } catch (err) {
      setSendError(err.response?.data?.message || 'Failed to load earlier messages.')
    } finally {
      setLoadingOlder(false)
    }
  }

  const handleSend = async (e) => {
    e?.preventDefault?.()
    if (!active || draft.trim().length < 1 || sending) return

    const body = draft.trim()
    const tempId = `tmp-${++tempIdRef.current}`
    const optimistic = {
      id: tempId,
      internship_id: active.internship_id,
      sender_id: user?.id,
      recipient_id: active.peer.id,
      body,
      created_at: new Date().toISOString(),
      read_at: null,
      _pending: true,
    }

    setDraft('')
    setSendError(null)
    setSending(true)
    setMessages((prev) => [...prev, optimistic])
    stickToBottomRef.current = true
    scrollToBottom('smooth')

    try {
      const res = await api.post('/messages', {
        internship_id: active.internship_id,
        recipient_id: active.peer.id,
        body,
      })
      const created = res.data.data
      setMessages((prev) => prev.map((m) => (m.id === tempId ? created : m)))
      loadThreads(1, { silent: true })
      if (stickToBottomRef.current) scrollToBottom('smooth')
    } catch (err) {
      const status = err.response?.status
      const msg = err.response?.data?.message
      setMessages((prev) => prev.map((m) => (
        m.id === tempId ? { ...m, _pending: false, _failed: true } : m
      )))
      if (status === 429) {
        setSendError(msg || 'Too many messages sent. Please wait a moment before sending again.')
      } else {
        setSendError(msg || 'Failed to send message.')
      }
      // Restore draft so the user can retry
      setDraft((d) => (d ? d : body))
    } finally {
      setSending(false)
    }
  }

  // Soft-poll active thread for new messages without resetting draft/scroll
  useEffect(() => {
    if (!active) return undefined
    const tick = async () => {
      const cur = activeRef.current
      if (!cur) return
      try {
        const res = await api.get(`/messages/conversations/${cur.internship_id}/${cur.peer.id}`, {
          params: { page: 1, per_page: 50 },
        })
        const chunk = res.data.messages || []
        const shouldStick = stickToBottomRef.current
        setMessages((prev) => {
          const pendingOrFailed = prev.filter((m) => m._pending || m._failed)
          const confirmedTemps = pendingOrFailed.filter((local) =>
            chunk.some(
              (c) => c.sender_id === local.sender_id && c.body === local.body
            )
          )
          const stillLocal = pendingOrFailed.filter((local) => !confirmedTemps.includes(local))
          const next = [...chunk, ...stillLocal].sort((a, b) => {
            const ta = new Date(a.created_at).getTime()
            const tb = new Date(b.created_at).getTime()
            if (ta !== tb) return ta - tb
            return String(a.id).localeCompare(String(b.id))
          })
          if (
            next.length === prev.length
            && next.every((m, i) => m.id === prev[i].id && m.read_at === prev[i].read_at && m._pending === prev[i]._pending)
          ) {
            return prev
          }
          return next
        })
        setThreadMeta((prev) => (prev ? { ...prev, ...res.data, messages: undefined } : res.data))
        if (shouldStick) scrollToBottom('smooth')
        loadThreads(1, { silent: true })
      } catch {
        /* ignore poll errors */
      }
    }
    const id = window.setInterval(tick, 12000)
    return () => window.clearInterval(id)
  }, [active?.internship_id, active?.peer?.id, loadThreads, scrollToBottom])

  const onComposerKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend(e)
    }
  }

  const hasOlder = threadMeta?.meta && threadPage < threadMeta.meta.last_page
  const canLoadMoreThreads = listMeta && listPage < listMeta.last_page
  const activeKey = active ? threadKey(active.internship_id, active.peer.id) : null

  const headerPeer = useMemo(() => {
    if (!active) return null
    return active.peer
  }, [active])

  return (
    <Layout title="Messages" subtitle={titleSubtitle} icon="fa-envelope" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={() => loadThreads(1, { silent: false })} />}

      <div className="msg-inbox">
        <div className="msg-inbox-toolbar" role="tablist" aria-label="Inbox views">
          <button
            type="button"
            className={`msg-tab ${!archived ? 'is-active' : ''}`}
            aria-selected={!archived}
            onClick={() => setArchived(false)}
          >
            Active
          </button>
          <button
            type="button"
            className={`msg-tab ${archived ? 'is-active' : ''}`}
            aria-selected={archived}
            onClick={() => setArchived(true)}
          >
            Archived
          </button>
          {listRefreshing && (
            <span className="msg-refresh-hint" aria-live="polite">
              <i className="fa fa-sync fa-spin" aria-hidden="true" /> Updating
            </span>
          )}
        </div>

        <div className="msg-inbox-grid">
          <section className="msg-panel msg-panel-list" aria-label="Conversations">
            <header className="msg-panel-header">
              <i className="fa fa-inbox" aria-hidden="true" />
              <h6>{archived ? 'Archived' : 'Conversations'}</h6>
            </header>

            <div className="msg-conv-list" role="list">
              {listLoading ? (
                <ListSkeleton />
              ) : threads.length === 0 ? (
                <div className="msg-empty">
                  <i className="fa fa-comments" aria-hidden="true" />
                  <p className="msg-empty-title">
                    {archived ? 'No archived conversations yet' : 'No conversations yet'}
                  </p>
                  <p className="msg-empty-sub">
                    {archived
                      ? 'Ended internships will appear here when available.'
                      : 'People linked to your internship will show up here once they are assigned.'}
                  </p>
                </div>
              ) : (
                <>
                  {threads.map((t) => {
                    const key = threadKey(t.internship_id, t.peer.id)
                    return (
                      <MemoConversationRow
                        key={key}
                        thread={t}
                        isActive={activeKey === key}
                        onSelect={openThread}
                      />
                    )
                  })}
                  {canLoadMoreThreads && (
                    <div className="msg-list-more">
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        onClick={() => loadThreads(listPage + 1, { append: true, silent: true })}
                      >
                        Load more conversations
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>
          </section>

          <section className="msg-panel msg-panel-thread" aria-label="Message thread">
            <header className="msg-panel-header">
              <i className="fa fa-comments" aria-hidden="true" />
              <h6 className="msg-thread-title">
                {headerPeer
                  ? (
                    <>
                      <span className="msg-thread-name">{headerPeer.name || 'Conversation'}</span>
                      <span className="msg-thread-role">{roleLabel(headerPeer.role)}</span>
                    </>
                    )
                  : 'Select a conversation'}
              </h6>
            </header>

            {!active ? (
              <div className="msg-empty msg-empty-thread">
                <i className="fa fa-paper-plane" aria-hidden="true" />
                <p className="msg-empty-title">Select a conversation to start messaging</p>
                <p className="msg-empty-sub">Choose someone from the list to view the thread and send a message.</p>
              </div>
            ) : (
              <>
                {threadMeta?.internship?.term && (
                  <div className="msg-thread-context">
                    {threadMeta.internship.term}
                    {threadMeta.internship.status
                      ? ` · ${String(threadMeta.internship.status).replace(/_/g, ' ')}`
                      : ''}
                  </div>
                )}

                {threadLoading && messages.length === 0 ? (
                  <ThreadSkeleton />
                ) : (
                  <>
                    {hasOlder && (
                      <div className="msg-load-older">
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-secondary"
                          onClick={loadOlderMessages}
                          disabled={loadingOlder}
                        >
                          {loadingOlder ? 'Loading…' : 'Load earlier messages'}
                        </button>
                      </div>
                    )}

                    <div
                      ref={paneRef}
                      className={`msg-thread-pane ${threadLoading ? 'is-loading' : ''}`}
                      role="log"
                      aria-live="polite"
                      aria-label="Message thread"
                      onScroll={onPaneScroll}
                    >
                      {messages.length === 0 ? (
                        <div className="msg-empty msg-empty-inline">
                          <p className="msg-empty-title">No messages yet</p>
                          <p className="msg-empty-sub">Say hello to start the conversation.</p>
                        </div>
                      ) : (
                        messages.map((m) => (
                          <MemoMessageBubble
                            key={m.id}
                            message={m}
                            mine={m.sender_id === user?.id}
                          />
                        ))
                      )}
                    </div>
                  </>
                )}

                {sendError && (
                  <div className="alert alert-danger msg-send-error py-2" role="alert">{sendError}</div>
                )}

                <form onSubmit={handleSend} className="msg-composer">
                  <label className="visually-hidden" htmlFor="message-composer">Message body</label>
                  <textarea
                    id="message-composer"
                    className="msg-composer-input form-control"
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
                    className="btn btn-success msg-composer-send"
                    disabled={sending || draft.trim().length < 1}
                    aria-label="Send message"
                  >
                    {sending
                      ? <i className="fa fa-spinner fa-spin" aria-hidden="true" />
                      : <i className="fa fa-paper-plane" aria-hidden="true" />}
                    <span>Send</span>
                  </button>
                  {archived && (
                    <div className="msg-composer-hint">
                      Viewing an archived (ended) internship conversation.
                    </div>
                  )}
                </form>
              </>
            )}
          </section>
        </div>
      </div>
    </Layout>
  )
}

export default MessagesInbox
