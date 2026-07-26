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
import ConfirmModal from './modals/ConfirmModal'
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
    director: 'Director',
  }
  return map[role] || role || 'Stakeholder'
}

const ATTACH_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/gif,image/webp,application/pdf'
const ATTACH_MAX_BYTES = 10 * 1024 * 1024
const ATTACH_EXT_OK = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'])
const POLL_MS = 12000
const LAST_THREAD_KEY = (userId) => `interntrack_msg_last_${userId || 'anon'}`

function fileExt(name) {
  const parts = String(name || '').toLowerCase().split('.')
  return parts.length > 1 ? parts.pop() : ''
}

function isImageFile(fileOrMeta) {
  if (!fileOrMeta) return false
  if (fileOrMeta.is_image != null) return Boolean(fileOrMeta.is_image)
  const mime = (fileOrMeta.type || fileOrMeta.mime || '').toLowerCase()
  if (mime.startsWith('image/')) return true
  return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt(fileOrMeta.name || fileOrMeta.filename))
}

function fileTypeIcon(filename) {
  const ext = fileExt(filename)
  if (ext === 'pdf') return 'fa-file-pdf'
  if (['doc', 'docx'].includes(ext)) return 'fa-file-word'
  if (['xls', 'xlsx'].includes(ext)) return 'fa-file-excel'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'fa-file-image'
  return 'fa-file'
}

function formatBytes(n) {
  const size = Number(n) || 0
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function validateAttachFile(file) {
  if (!file) return 'No file selected.'
  const ext = fileExt(file.name)
  if (!ATTACH_EXT_OK.has(ext)) {
    return 'Unsupported file type. Use images (jpg, png, gif, webp) or documents (pdf, doc, docx, xls, xlsx).'
  }
  if (file.size > ATTACH_MAX_BYTES) {
    return 'File is too large. Maximum size is 10 MB.'
  }
  return null
}

function conversationPreview(thread) {
  if (thread.last_message?.is_unsent) return 'This message was unsent'
  const body = (thread.last_message?.body || '').trim()
  if (body) return body
  if (thread.last_message?.has_attachment) return 'Attachment'
  return 'No messages yet'
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

function sameThread(a, b) {
  if (!a || !b) return false
  return a.internship_id === b.internship_id && a.peer?.id === b.peer?.id
}

/** Photo when available; initials fallback (and onError for broken URLs). */
function PeerAvatar({ peer, className = 'msg-conv-avatar', size = 40 }) {
  const [broken, setBroken] = useState(false)
  const label = peer?.avatar || initials(peer?.name)
  const src = peer?.avatarUrl && !broken ? peer.avatarUrl : null

  useEffect(() => {
    setBroken(false)
  }, [peer?.avatarUrl, peer?.id])

  return (
    <div
      className={className}
      style={{ width: size, height: size }}
      aria-hidden="true"
    >
      {src ? (
        <img
          src={src}
          alt=""
          className="msg-avatar-img"
          onError={() => setBroken(true)}
        />
      ) : (
        label
      )}
    </div>
  )
}

function ConversationRow({ thread, isActive, onSelect, onToggleArchive, archiveBusy }) {
  const preview = conversationPreview(thread)
  const when = thread.last_message?.created_at
  const contextParts = []
  if (thread.student_name) contextParts.push(`Re: ${thread.student_name}`)
  if (thread.internship_term) contextParts.push(thread.internship_term)
  if (thread.internship_status) {
    contextParts.push(String(thread.internship_status).replace(/_/g, ' '))
  }
  const contextLine = contextParts.join(' · ')
  const ariaCtx = contextLine ? `, ${contextLine}` : ''
  const isUserArchived = Boolean(thread.user_archived)

  return (
    <div
      role="listitem"
      className={`msg-conv-item ${isActive ? 'is-active' : ''} ${thread.unread_count > 0 ? 'has-unread' : ''}`}
    >
      <button
        type="button"
        className="msg-conv-main"
        onClick={() => onSelect(thread)}
        aria-current={isActive ? 'true' : undefined}
        aria-label={`Conversation with ${thread.peer.name}, ${roleLabel(thread.peer.role)}${ariaCtx}`}
      >
        <PeerAvatar peer={thread.peer} />
        <div className="msg-conv-body">
          <div className="msg-conv-top">
            <span className="msg-conv-name">{thread.peer.name}</span>
            {when && <span className="msg-conv-time">{timeAgo(when)}</span>}
          </div>
          <div className="msg-conv-meta">
            {roleLabel(thread.peer.role)}
          </div>
          {contextLine && (
            <div className="msg-conv-context" title={contextLine}>
              {contextLine}
            </div>
          )}
          <div className={`msg-conv-preview ${thread.last_message?.is_unsent ? 'is-unsent' : ''}`}>
            {preview}
          </div>
        </div>
        {thread.unread_count > 0 && (
          <span className="msg-unread-badge" aria-label={`${thread.unread_count} unread`}>
            {thread.unread_count > 99 ? '99+' : thread.unread_count}
          </span>
        )}
      </button>
      <button
        type="button"
        className="msg-conv-archive-btn"
        title={isUserArchived ? 'Move to Active' : 'Archive conversation'}
        aria-label={isUserArchived ? 'Unarchive conversation' : 'Archive conversation'}
        disabled={archiveBusy}
        onClick={(e) => {
          e.stopPropagation()
          onToggleArchive(thread, !isUserArchived)
        }}
      >
        <i className={`fa ${isUserArchived ? 'fa-inbox' : 'fa-archive'}`} aria-hidden="true" />
      </button>
    </div>
  )
}

const MemoConversationRow = memo(ConversationRow)

/** Local optimistic preview URL (blob:) or authenticated storage path. */
function attachmentLocalUrl(attachment) {
  const url = attachment?.url
  if (!url || typeof url !== 'string') return null
  if (url.startsWith('blob:') || url.startsWith('data:')) return url
  return null
}

async function fetchAttachmentBlobUrl(path) {
  const res = await api.get('/files/download', {
    params: { path },
    responseType: 'blob',
  })
  return URL.createObjectURL(res.data)
}

function MessageAttachment({ attachment, onOpenImage }) {
  const [broken, setBroken] = useState(false)
  const [blobUrl, setBlobUrl] = useState('')
  const [busy, setBusy] = useState(false)
  const localUrl = attachmentLocalUrl(attachment)
  const storagePath = attachment?.path || null
  const displaySrc = localUrl || blobUrl

  useEffect(() => {
    setBroken(false)
  }, [attachment?.path, attachment?.url])

  useEffect(() => {
    let active = true
    let objectUrl = ''
    if (localUrl || !storagePath) {
      setBlobUrl('')
      return undefined
    }
    fetchAttachmentBlobUrl(storagePath)
      .then((url) => {
        if (!active) {
          URL.revokeObjectURL(url)
          return
        }
        objectUrl = url
        setBlobUrl(url)
      })
      .catch(() => {
        if (active) {
          setBlobUrl('')
          setBroken(true)
        }
      })
    return () => {
      active = false
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [localUrl, storagePath])

  if (!attachment) return null

  const openBlob = async (e) => {
    e?.preventDefault?.()
    e?.stopPropagation?.()
    if (busy) return
    if (localUrl) {
      window.open(localUrl, '_blank', 'noopener,noreferrer')
      return
    }
    if (!storagePath) return
    setBusy(true)
    try {
      const url = await fetchAttachmentBlobUrl(storagePath)
      window.open(url, '_blank', 'noopener,noreferrer')
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch {
      setBroken(true)
    } finally {
      setBusy(false)
    }
  }

  if (attachment.is_image) {
    if (broken || (!localUrl && !storagePath)) {
      return (
        <div className="msg-attach-unavailable" role="status">
          <i className="fa fa-image" aria-hidden="true" />
          <span>Image unavailable</span>
        </div>
      )
    }
    if (!displaySrc) {
      return (
        <div className="msg-attach-unavailable" role="status">
          <i className="fa fa-spinner fa-spin" aria-hidden="true" />
          <span>Loading…</span>
        </div>
      )
    }
    return (
      <button
        type="button"
        className="msg-attach-image-btn"
        onClick={() => onOpenImage?.(attachment)}
        aria-label={`View image ${attachment.filename || ''}`}
      >
        <img
          src={displaySrc}
          alt={attachment.filename || 'Attached image'}
          className="msg-attach-thumb"
          onError={() => setBroken(true)}
        />
      </button>
    )
  }

  // Non-image: path (auth download), local blob URL, or optimistic filename-only while sending
  if (broken && !attachment.filename) {
    return (
      <div className="msg-attach-unavailable" role="status">
        <i className="fa fa-file" aria-hidden="true" />
        <span>File unavailable</span>
      </div>
    )
  }

  const canOpen = Boolean(localUrl || storagePath)

  return (
    <div className="msg-attach-file">
      <div className="msg-attach-file-icon" aria-hidden="true">
        <i className={`fa ${fileTypeIcon(attachment.filename)}`} />
      </div>
      <div className="msg-attach-file-meta">
        <div className="msg-attach-file-name" title={attachment.filename}>
          {attachment.filename || 'Attachment'}
        </div>
        <div className="msg-attach-file-sub">
          {formatBytes(attachment.size)}
        </div>
      </div>
      {canOpen && (
        <button
          type="button"
          className="btn btn-sm btn-outline-secondary msg-attach-download"
          onClick={openBlob}
          disabled={busy}
          aria-busy={busy}
        >
          <i className={`fa ${busy ? 'fa-spinner fa-spin' : 'fa-download'}`} aria-hidden="true" />
          <span>{busy ? 'Opening…' : 'Open'}</span>
        </button>
      )}
    </div>
  )
}

function MessageBubble({ message, mine, onUnsend, onOpenImage }) {
  const pending = Boolean(message._pending)
  const failed = Boolean(message._failed)
  const unsent = Boolean(message.is_unsent)
  const attachment = !unsent ? (message.attachment || message._localAttachment) : null
  const text = (message.body || '').trim()
  const showText = unsent || text.length > 0

  return (
    <div
      className={`msg-bubble-row ${mine ? 'is-mine' : 'is-theirs'} ${pending ? 'is-pending' : ''} ${failed ? 'is-failed' : ''} ${unsent ? 'is-unsent' : ''}`}
    >
      <div className={`msg-bubble ${mine ? 'msg-bubble-mine' : 'msg-bubble-theirs'} ${unsent ? 'msg-bubble-unsent' : ''}`}>
        {attachment && (
          <div className="msg-bubble-attach">
            <MessageAttachment attachment={attachment} onOpenImage={onOpenImage} />
          </div>
        )}
        {showText && (
          <div className={`msg-bubble-text ${unsent ? 'is-unsent' : ''}`}>{message.body}</div>
        )}
        <div className="msg-bubble-meta">
          {pending ? 'Sending…' : failed ? 'Failed to send' : unsent ? 'Unsent' : timeAgo(message.created_at)}
          {!mine && !pending && !unsent && message.read_at ? ' · Read' : ''}
        </div>
        {mine && !pending && !failed && !unsent && typeof message.id === 'number' && (
          <button
            type="button"
            className="msg-unsend-btn"
            onClick={() => onUnsend(message)}
            aria-label="Unsend message"
            title="Unsend"
          >
            Unsend
          </button>
        )}
      </div>
    </div>
  )
}

const MemoMessageBubble = memo(MessageBubble)

/** Composer keeps draft locally so typing does not re-render the conversation list. */
const MessageComposer = memo(function MessageComposer({
  disabled,
  sending,
  showArchivedHint,
  onSend,
}) {
  const [draft, setDraft] = useState('')
  const [attachFile, setAttachFile] = useState(null)
  const [attachPreviewUrl, setAttachPreviewUrl] = useState(null)
  const [localError, setLocalError] = useState(null)
  const fileInputRef = useRef(null)
  const inputRef = useRef(null)
  const sendingLockRef = useRef(false)

  const resizeComposer = useCallback(() => {
    const el = inputRef.current
    if (!el) return
    el.style.height = '0px'
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`
  }, [])

  useEffect(() => {
    resizeComposer()
  }, [draft, resizeComposer])

  const clearAttachment = useCallback(() => {
    setAttachFile(null)
    setAttachPreviewUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return null
    })
    if (fileInputRef.current) fileInputRef.current.value = ''
  }, [])

  const onPickAttachment = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const err = validateAttachFile(file)
    if (err) {
      setLocalError(err)
      e.target.value = ''
      return
    }
    setLocalError(null)
    setAttachPreviewUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return isImageFile(file) ? URL.createObjectURL(file) : null
    })
    setAttachFile(file)
  }

  const submit = async (e) => {
    e?.preventDefault?.()
    if (disabled || sending || sendingLockRef.current) return
    const body = draft.trim()
    if (body.length < 1 && !attachFile) return

    sendingLockRef.current = true
    const fileSnapshot = attachFile
    const previewSnapshot = attachPreviewUrl
    const draftSnapshot = draft
    setDraft('')
    setAttachFile(null)
    setAttachPreviewUrl(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
    setLocalError(null)

    try {
      const result = await onSend({
        body,
        file: fileSnapshot,
        previewUrl: previewSnapshot,
      })

      if (result?.ok === false) {
        setDraft((d) => (d ? d : draftSnapshot))
        if (fileSnapshot) {
          setAttachFile(fileSnapshot)
          if (previewSnapshot) {
            setAttachPreviewUrl(previewSnapshot)
          } else if (isImageFile(fileSnapshot)) {
            setAttachPreviewUrl(URL.createObjectURL(fileSnapshot))
          }
        }
        if (result.error) setLocalError(result.error)
      } else if (previewSnapshot) {
        URL.revokeObjectURL(previewSnapshot)
      }
    } finally {
      sendingLockRef.current = false
    }
  }

  const onComposerKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      if (disabled || sending || sendingLockRef.current) return
      if (draft.trim().length < 1 && !attachFile) return
      submit(e)
    }
  }

  const busy = sending || disabled
  const canSend = !busy && (draft.trim().length >= 1 || Boolean(attachFile))

  return (
    <>
      {localError && (
        <div className="alert alert-danger msg-send-error py-2" role="alert">{localError}</div>
      )}
      {attachFile && (
        <div className="msg-attach-preview" aria-live="polite">
          {attachPreviewUrl ? (
            <img src={attachPreviewUrl} alt="" className="msg-attach-preview-thumb" />
          ) : (
            <div className="msg-attach-preview-file">
              <i className={`fa ${fileTypeIcon(attachFile.name)}`} aria-hidden="true" />
              <div>
                <div className="msg-attach-preview-name">{attachFile.name}</div>
                <div className="msg-attach-preview-sub">{formatBytes(attachFile.size)}</div>
              </div>
            </div>
          )}
          <button
            type="button"
            className="btn btn-sm btn-outline-danger msg-attach-preview-remove"
            onClick={clearAttachment}
            disabled={busy}
            aria-label="Remove attachment"
          >
            <i className="fa fa-times" aria-hidden="true" />
            Remove
          </button>
        </div>
      )}
      <form onSubmit={submit} className="msg-composer">
        <input
          ref={fileInputRef}
          type="file"
          className="visually-hidden"
          accept={ATTACH_ACCEPT}
          onChange={onPickAttachment}
          tabIndex={-1}
          aria-hidden="true"
        />
        <button
          type="button"
          className="btn btn-outline-secondary msg-composer-attach"
          onClick={() => fileInputRef.current?.click()}
          disabled={busy}
          title="Attach file or image"
          aria-label="Attach file or image"
        >
          <i className="fa fa-paperclip" aria-hidden="true" />
        </button>
        <label className="visually-hidden" htmlFor="message-composer">Message body</label>
        <textarea
          ref={inputRef}
          id="message-composer"
          className="msg-composer-input form-control"
          rows={1}
          placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={onComposerKeyDown}
          maxLength={5000}
          aria-label="Message body"
          disabled={busy}
        />
        <button
          type="submit"
          className="btn btn-success msg-composer-send"
          disabled={!canSend}
          aria-label="Send message"
        >
          {sending
            ? <i className="fa fa-spinner fa-spin" aria-hidden="true" />
            : <i className="fa fa-paper-plane" aria-hidden="true" />}
          <span className="msg-composer-send-label">{sending ? 'Sending…' : 'Send'}</span>
        </button>
        {showArchivedHint && (
          <div className="msg-composer-hint">
            Viewing an archived conversation. You can still send messages; unarchive anytime to move it back to Active.
          </div>
        )}
      </form>
    </>
  )
})

const ConversationList = memo(function ConversationList({
  listLoading,
  threads,
  archived,
  activeKey,
  archiveBusyKey,
  canLoadMoreThreads,
  listPage,
  onSelect,
  onToggleArchive,
  onLoadMore,
}) {
  if (listLoading) return <ListSkeleton />
  if (threads.length === 0) {
    return (
      <div className="msg-empty">
        <i className="fa fa-comments" aria-hidden="true" />
        <p className="msg-empty-title">
          {archived ? 'No archived conversations yet' : 'No conversations yet'}
        </p>
        <p className="msg-empty-sub">
          {archived
            ? 'Archive a conversation to move it here, or check ended internships.'
            : 'People linked to your internship will show up here once they are assigned.'}
        </p>
      </div>
    )
  }

  return (
    <>
      {threads.map((t) => {
        const key = threadKey(t.internship_id, t.peer.id)
        return (
          <MemoConversationRow
            key={key}
            thread={t}
            isActive={activeKey === key}
            onSelect={onSelect}
            onToggleArchive={onToggleArchive}
            archiveBusy={archiveBusyKey === key}
          />
        )
      })}
      {canLoadMoreThreads && (
        <div className="msg-list-more">
          <button
            type="button"
            className="btn btn-sm btn-outline-secondary"
            onClick={() => onLoadMore(listPage + 1)}
          >
            Load more conversations
          </button>
        </div>
      )}
    </>
  )
})

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
  const [sending, setSending] = useState(false)
  const [sendError, setSendError] = useState(null)
  const [archiveBusyKey, setArchiveBusyKey] = useState(null)
  const [clearConfirmOpen, setClearConfirmOpen] = useState(false)
  const [clearBusy, setClearBusy] = useState(false)
  const [clearError, setClearError] = useState(null)
  const [actionError, setActionError] = useState(null)
  const [lightbox, setLightbox] = useState(null)

  const deepLinkHandled = useRef(false)
  const restoreHandled = useRef(false)
  const paneRef = useRef(null)
  const stickToBottomRef = useRef(true)
  const activeRef = useRef(null)
  const tempIdRef = useRef(0)
  const threadReqIdRef = useRef(0)
  const threadAbortRef = useRef(null)
  const listAbortRef = useRef(null)
  const listCacheRef = useRef({
    false: null,
    true: null,
  })
  const scrollPosRef = useRef(new Map())
  const archivedRef = useRef(archived)

  useEffect(() => {
    activeRef.current = active
  }, [active])

  useEffect(() => {
    archivedRef.current = archived
  }, [archived])

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

  const rememberScroll = useCallback(() => {
    const cur = activeRef.current
    const el = paneRef.current
    if (!cur || !el) return
    const key = threadKey(cur.internship_id, cur.peer.id)
    scrollPosRef.current.set(key, {
      top: el.scrollTop,
      stick: stickToBottomRef.current,
    })
  }, [])

  const restoreScroll = useCallback((key, { preferBottom = false } = {}) => {
    requestAnimationFrame(() => {
      const el = paneRef.current
      if (!el) return
      const saved = scrollPosRef.current.get(key)
      if (preferBottom || !saved || saved.stick) {
        stickToBottomRef.current = true
        el.scrollTo({ top: el.scrollHeight, behavior: 'auto' })
        return
      }
      stickToBottomRef.current = false
      el.scrollTop = saved.top
    })
  }, [])

  const persistLastThread = useCallback((thread, tabArchived) => {
    if (!user?.id || !thread) return
    try {
      sessionStorage.setItem(LAST_THREAD_KEY(user.id), JSON.stringify({
        internship_id: thread.internship_id,
        peer_id: thread.peer.id,
        archived: Boolean(tabArchived),
      }))
    } catch {
      /* ignore quota */
    }
  }, [user?.id])

  const onPaneScroll = () => {
    stickToBottomRef.current = isNearBottom()
  }

  const loadThreads = useCallback((page = 1, { append = false, silent = false, tab = null } = {}) => {
    const forArchived = tab == null ? archivedRef.current : Boolean(tab)
    if (!silent && !append) setListLoading(true)
    if (silent) setListRefreshing(true)
    setError(null)

    if (listAbortRef.current && !append && !silent) {
      listAbortRef.current.abort()
    }
    const controller = new AbortController()
    if (!append) listAbortRef.current = controller

    return api.get('/messages/conversations', {
      params: { archived: forArchived ? 1 : 0, page, per_page: 20 },
      signal: controller.signal,
    })
      .then((res) => {
        if (forArchived !== archivedRef.current && !append) return null
        const { items, meta } = unwrapList(res.data)
        setThreads((prev) => (append ? [...prev, ...items] : items))
        setListMeta(meta)
        setListPage(page)
        if (!append) {
          listCacheRef.current[forArchived] = { threads: items, meta, page }
        }
        return { items, meta }
      })
      .catch((err) => {
        if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null
        if (forArchived !== archivedRef.current) return null
        setError(err.response?.data?.message || 'Failed to load conversations.')
        if (!append) setThreads([])
        return null
      })
      .finally(() => {
        if (forArchived === archivedRef.current) {
          setListLoading(false)
          setListRefreshing(false)
        }
      })
  }, [])

  // Initial load + tab changes (with cache for instant Active/Archived switch)
  useEffect(() => {
    const cached = listCacheRef.current[archived]
    if (cached?.threads) {
      setThreads(cached.threads)
      setListMeta(cached.meta)
      setListPage(cached.page)
      setListLoading(false)
      loadThreads(1, { silent: true, tab: archived })
    } else {
      setThreads([])
      setListMeta(null)
      setListPage(1)
      loadThreads(1, { silent: false, tab: archived })
    }
    // Keep open thread visible across tab switches (no blank flash).
  }, [archived, loadThreads])

  const applyThreadPayload = useCallback((internshipId, peerId, data, { prepend = false, reqId, page = 1 } = {}) => {
    if (reqId != null && reqId !== threadReqIdRef.current) return false
    const cur = activeRef.current
    if (!cur || cur.internship_id !== internshipId || cur.peer?.id !== peerId) return false

    const chunk = data.messages || []
    setThreadMeta(data)
    if (data.peer) {
      setActive((prev) => (
        prev
        && prev.internship_id === internshipId
        && prev.peer?.id === peerId
          ? { ...prev, peer: { ...prev.peer, ...data.peer } }
          : prev
      ))
    }
    setThreadPage(page)
    setMessages((prev) => {
      if (prepend) return [...chunk, ...prev]
      const locals = prev.filter((m) => m._pending || m._failed)
      const ids = new Set(chunk.map((m) => m.id))
      const keepLocals = locals.filter((m) => !ids.has(m.id) && !ids.has(m._serverId))
      return [...chunk, ...keepLocals]
    })
    return true
  }, [])

  const fetchThreadPage = useCallback(async (internshipId, peerId, page, {
    prepend = false,
    signal,
    reqId,
  } = {}) => {
    const res = await api.get(`/messages/conversations/${internshipId}/${peerId}`, {
      params: { page, per_page: 50 },
      signal,
    })
    applyThreadPayload(internshipId, peerId, res.data, { prepend, reqId, page })
    return res.data
  }, [applyThreadPayload])

  const closeThread = useCallback(() => {
    rememberScroll()
    setActive(null)
    activeRef.current = null
    setMessages([])
    setThreadMeta(null)
    setThreadPage(1)
    setThreadLoading(false)
    setSendError(null)
    setSearchParams({}, { replace: true })
  }, [rememberScroll, setSearchParams])

  const openThread = useCallback(async (thread, { replaceUrl = true } = {}) => {
    const same = sameThread(activeRef.current, thread)

    if (same) {
      try {
        const reqId = ++threadReqIdRef.current
        threadAbortRef.current?.abort()
        const controller = new AbortController()
        threadAbortRef.current = controller
        await fetchThreadPage(thread.internship_id, thread.peer.id, 1, {
          prepend: false,
          signal: controller.signal,
          reqId,
        })
        if (stickToBottomRef.current) scrollToBottom('smooth')
        loadThreads(1, { silent: true })
      } catch (err) {
        if (err?.code !== 'ERR_CANCELED' && err?.name !== 'CanceledError') {
          /* soft refresh — ignore other errors */
        }
      }
      return
    }

    rememberScroll()

    const nextActive = {
      internship_id: thread.internship_id,
      peer: thread.peer,
      student_name: thread.student_name || null,
      internship_term: thread.internship_term || null,
      internship_status: thread.internship_status || null,
    }
    setActive(nextActive)
    activeRef.current = nextActive
    setMessages([])
    setThreadMeta(null)
    setThreadPage(1)
    setThreadLoading(true)
    setSendError(null)
    stickToBottomRef.current = true

    const reqId = ++threadReqIdRef.current
    threadAbortRef.current?.abort()
    const controller = new AbortController()
    threadAbortRef.current = controller

    try {
      await fetchThreadPage(thread.internship_id, thread.peer.id, 1, {
        prepend: false,
        signal: controller.signal,
        reqId,
      })
      if (reqId !== threadReqIdRef.current) return
      loadThreads(1, { silent: true })
      if (replaceUrl) {
        setSearchParams({
          internship_id: String(thread.internship_id),
          peer_id: String(thread.peer.id),
        }, { replace: true })
      }
      persistLastThread(thread, archivedRef.current)
      const key = threadKey(thread.internship_id, thread.peer.id)
      restoreScroll(key, { preferBottom: true })
    } catch (err) {
      if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return
      if (reqId !== threadReqIdRef.current) return
      setSendError(err.response?.data?.message || 'Failed to open conversation.')
      setMessages([])
    } finally {
      if (reqId === threadReqIdRef.current) {
        setThreadLoading(false)
      }
    }
  }, [
    fetchThreadPage,
    loadThreads,
    persistLastThread,
    rememberScroll,
    restoreScroll,
    scrollToBottom,
    setSearchParams,
  ])

  // Deep-link from notification OR restore last-viewed conversation
  useEffect(() => {
    if (listLoading) return

    const internshipId = Number(searchParams.get('internship_id'))
    const peerId = Number(searchParams.get('peer_id'))

    if (internshipId && peerId) {
      if (deepLinkHandled.current) return
      const match = threads.find(
        (t) => t.internship_id === internshipId && t.peer?.id === peerId
      )
      deepLinkHandled.current = true
      restoreHandled.current = true
      if (match) {
        openThread(match, { replaceUrl: false })
      } else {
        openThread(
          { internship_id: internshipId, peer: { id: peerId, name: '…', role: '' } },
          { replaceUrl: false }
        )
      }
      return
    }

    if (restoreHandled.current || activeRef.current || threads.length === 0) return
    restoreHandled.current = true
    try {
      const raw = sessionStorage.getItem(LAST_THREAD_KEY(user?.id))
      if (!raw) return
      const saved = JSON.parse(raw)
      if (Boolean(saved.archived) !== archived) return
      const match = threads.find(
        (t) => t.internship_id === saved.internship_id && t.peer?.id === saved.peer_id
      )
      if (match) openThread(match, { replaceUrl: true })
    } catch {
      /* ignore */
    }
  }, [listLoading, threads, searchParams, archived, openThread, user?.id])

  const loadOlderMessages = useCallback(async () => {
    const cur = activeRef.current
    if (!cur || !threadMeta?.meta) return
    const next = threadPage + 1
    if (next > threadMeta.meta.last_page) return
    const el = paneRef.current
    const prevHeight = el?.scrollHeight || 0
    const prevTop = el?.scrollTop || 0
    setLoadingOlder(true)
    const reqId = threadReqIdRef.current
    try {
      await fetchThreadPage(cur.internship_id, cur.peer.id, next, {
        prepend: true,
        reqId,
      })
      // After prepend, keep viewport stable (not jump to bottom)
      stickToBottomRef.current = false
      requestAnimationFrame(() => {
        if (!paneRef.current || reqId !== threadReqIdRef.current) return
        const delta = paneRef.current.scrollHeight - prevHeight
        paneRef.current.scrollTop = prevTop + delta
      })
    } catch (err) {
      if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return
      setSendError(err.response?.data?.message || 'Failed to load earlier messages.')
    } finally {
      setLoadingOlder(false)
    }
  }, [fetchThreadPage, threadMeta, threadPage])

  const handleSend = useCallback(async ({ body, file, previewUrl }) => {
    const cur = activeRef.current
    if (!cur || sending) return { ok: false, error: 'No conversation selected.' }

    const tempId = `tmp-${++tempIdRef.current}`
    const localAttachment = file
      ? {
          url: isImageFile(file) && previewUrl ? previewUrl : null,
          filename: file.name,
          mime: file.type,
          size: file.size,
          is_image: isImageFile(file),
        }
      : null

    const optimistic = {
      id: tempId,
      internship_id: cur.internship_id,
      sender_id: user?.id,
      recipient_id: cur.peer.id,
      body,
      created_at: new Date().toISOString(),
      read_at: null,
      _pending: true,
      _localAttachment: localAttachment,
      attachment: localAttachment,
    }

    setSendError(null)
    setSending(true)
    setMessages((prev) => [...prev, optimistic])
    stickToBottomRef.current = true
    scrollToBottom('smooth')

    try {
      let res
      if (file) {
        const form = new FormData()
        form.append('internship_id', String(cur.internship_id))
        form.append('recipient_id', String(cur.peer.id))
        form.append('body', body)
        form.append('attachment', file)
        res = await api.post('/messages', form)
      } else {
        res = await api.post('/messages', {
          internship_id: cur.internship_id,
          recipient_id: cur.peer.id,
          body,
        })
      }
      if (!sameThread(activeRef.current, cur)) {
        return { ok: true }
      }
      const created = res.data.data
      setMessages((prev) => prev.map((m) => (m.id === tempId ? created : m)))
      loadThreads(1, { silent: true })
      if (stickToBottomRef.current) scrollToBottom('smooth')
      return { ok: true }
    } catch (err) {
      const status = err.response?.status
      const data = err.response?.data
      const fieldMsg = data?.errors?.attachment?.[0]
        || data?.errors?.body?.[0]
        || data?.message
      if (sameThread(activeRef.current, cur)) {
        setMessages((prev) => prev.map((m) => (
          m.id === tempId ? { ...m, _pending: false, _failed: true } : m
        )))
      }
      let error = fieldMsg || 'Failed to send message.'
      if (status === 429) {
        error = fieldMsg || 'Too many messages sent. Please wait a moment before sending again.'
      } else if (status === 413) {
        error = fieldMsg || 'File is too large. Maximum size is 10 MB.'
      }
      setSendError(error)
      return { ok: false, error }
    } finally {
      setSending(false)
    }
  }, [loadThreads, scrollToBottom, sending, user?.id])

  // Soft-poll active thread — never resets draft (composer is local) or scroll when reading history
  useEffect(() => {
    if (!active) return undefined
    let cancelled = false
    const tick = async () => {
      const cur = activeRef.current
      if (!cur || cancelled) return
      const reqId = threadReqIdRef.current
      try {
        const res = await api.get(`/messages/conversations/${cur.internship_id}/${cur.peer.id}`, {
          params: { page: 1, per_page: 50 },
        })
        if (cancelled || reqId !== threadReqIdRef.current) return
        if (!sameThread(activeRef.current, cur)) return

        const chunk = res.data.messages || []
        const shouldStick = stickToBottomRef.current
        let changed = false
        setMessages((prev) => {
          const pendingOrFailed = prev.filter((m) => m._pending || m._failed)
          const confirmedTemps = pendingOrFailed.filter((local) =>
            chunk.some(
              (c) => c.sender_id === local.sender_id
                && c.body === local.body
                && Boolean(c.attachment?.filename) === Boolean(local.attachment?.filename || local._localAttachment?.filename)
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
            && next.every((m, i) => (
              m.id === prev[i].id
              && m.read_at === prev[i].read_at
              && m._pending === prev[i]._pending
              && Boolean(m.is_unsent) === Boolean(prev[i].is_unsent)
              && m.body === prev[i].body
              && (m.attachment?.url || m.attachment?.path || '')
                === (prev[i].attachment?.url || prev[i].attachment?.path || '')
            ))
          ) {
            return prev
          }
          changed = true
          return next
        })
        setThreadMeta((prev) => (prev ? { ...prev, ...res.data, messages: undefined } : res.data))
        if (shouldStick) scrollToBottom('smooth')
        // Refresh conversation list only when thread content changed (avoids list flicker)
        if (changed) loadThreads(1, { silent: true })
      } catch {
        /* ignore poll errors */
      }
    }
    const id = window.setInterval(tick, POLL_MS)
    return () => {
      cancelled = true
      window.clearInterval(id)
    }
  }, [active?.internship_id, active?.peer?.id, loadThreads, scrollToBottom])

  const toggleArchive = useCallback(async (thread, nextArchived) => {
    const key = threadKey(thread.internship_id, thread.peer.id)
    setArchiveBusyKey(key)
    setActionError(null)
    setThreads((prev) => prev.filter(
      (t) => !(t.internship_id === thread.internship_id && t.peer.id === thread.peer.id)
    ))
    // Invalidate caches for both tabs
    listCacheRef.current.false = null
    listCacheRef.current.true = null

    const wasActive = sameThread(activeRef.current, thread)
    if (wasActive) {
      rememberScroll()
      setActive(null)
      activeRef.current = null
      setMessages([])
      setThreadMeta(null)
      setSearchParams({}, { replace: true })
    }
    try {
      await api.post(`/messages/conversations/${thread.internship_id}/${thread.peer.id}/archive`, {
        archived: nextArchived,
      })
      await loadThreads(1, { silent: true })
    } catch (err) {
      setActionError(err.response?.data?.message || 'Failed to update archive status.')
      await loadThreads(1, { silent: false })
    } finally {
      setArchiveBusyKey(null)
    }
  }, [loadThreads, rememberScroll, setSearchParams])

  const handleUnsend = useCallback(async (message) => {
    if (!message?.id || typeof message.id !== 'number') return
    const prevBody = message.body
    setMessages((prev) => prev.map((m) => (
      m.id === message.id
        ? { ...m, is_unsent: true, body: 'This message was unsent', unsent_at: new Date().toISOString() }
        : m
    )))
    setActionError(null)
    try {
      const res = await api.post(`/messages/${message.id}/unsend`)
      const updated = res.data?.data
      if (updated) {
        setMessages((prev) => prev.map((m) => (m.id === message.id ? updated : m)))
      }
      loadThreads(1, { silent: true })
    } catch (err) {
      setMessages((prev) => prev.map((m) => (
        m.id === message.id ? { ...m, is_unsent: false, body: prevBody, unsent_at: null } : m
      )))
      setActionError(err.response?.data?.message || 'Failed to unsend message.')
    }
  }, [loadThreads])

  const confirmClearConversation = async () => {
    if (!active) return
    setClearBusy(true)
    setClearError(null)
    try {
      await api.post(`/messages/conversations/${active.internship_id}/${active.peer.id}/clear`)
      setMessages([])
      setThreadMeta((prev) => (prev ? { ...prev, messages: [] } : prev))
      setClearConfirmOpen(false)
      loadThreads(1, { silent: true })
    } catch (err) {
      setClearError(err.response?.data?.message || 'Failed to clear conversation.')
    } finally {
      setClearBusy(false)
    }
  }

  const openLightbox = useCallback(async (attachment) => {
    if (!attachment?.is_image) return
    const localUrl = attachmentLocalUrl(attachment)
    if (localUrl) {
      setLightbox({ url: localUrl, filename: attachment.filename || 'Image', revokeOnClose: false })
      return
    }
    if (!attachment.path) return
    try {
      const url = await fetchAttachmentBlobUrl(attachment.path)
      setLightbox({ url, filename: attachment.filename || 'Image', revokeOnClose: true })
    } catch {
      /* ignore — thumb already shows error state */
    }
  }, [])

  const onSelectTab = useCallback((nextArchived) => {
    if (nextArchived === archived) return
    rememberScroll()
    setArchived(nextArchived)
  }, [archived, rememberScroll])

  const onLoadMoreThreads = useCallback((page) => {
    loadThreads(page, { append: true, silent: true })
  }, [loadThreads])

  const hasOlder = threadMeta?.meta && threadPage < threadMeta.meta.last_page
  const canLoadMoreThreads = listMeta && listPage < listMeta.last_page
  const activeKey = active ? threadKey(active.internship_id, active.peer.id) : null
  const activeUserArchived = Boolean(
    threadMeta?.user_archived
    ?? threads.find((t) => threadKey(t.internship_id, t.peer.id) === activeKey)?.user_archived
  )

  const headerPeer = useMemo(() => {
    if (!active) return null
    return active.peer
  }, [active])

  // Abort in-flight requests when leaving the page
  useEffect(() => () => {
    threadAbortRef.current?.abort()
    listAbortRef.current?.abort()
  }, [])

  return (
    <Layout title="Messages" subtitle={titleSubtitle} icon="fa-envelope" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={() => loadThreads(1, { silent: false })} />}

      <ConfirmModal
        open={clearConfirmOpen}
        title="Clear conversation?"
        message="This clears the message history from your view only. The other person will still see the full conversation. New messages after this will appear normally."
        confirmLabel="Clear my view"
        cancelLabel="Cancel"
        variant="danger"
        loading={clearBusy}
        error={clearError}
        onCancel={() => {
          if (clearBusy) return
          setClearConfirmOpen(false)
          setClearError(null)
        }}
        onConfirm={confirmClearConversation}
      />

      <div className="msg-inbox">
        <div className="msg-inbox-toolbar" role="tablist" aria-label="Inbox views">
          <button
            type="button"
            className={`msg-tab ${!archived ? 'is-active' : ''}`}
            aria-selected={!archived}
            onClick={() => onSelectTab(false)}
          >
            Active
          </button>
          <button
            type="button"
            className={`msg-tab ${archived ? 'is-active' : ''}`}
            aria-selected={archived}
            onClick={() => onSelectTab(true)}
          >
            Archived
          </button>
          {listRefreshing && (
            <span className="msg-refresh-hint" aria-live="polite">
              <i className="fa fa-sync fa-spin" aria-hidden="true" /> Updating
            </span>
          )}
        </div>

        {actionError && (
          <div className="alert alert-danger py-2 mb-2" role="alert">{actionError}</div>
        )}

        <div className={`msg-inbox-grid${active ? ' has-active-thread' : ''}`}>
          <section className="msg-panel msg-panel-list" aria-label="Conversations">
            <header className="msg-panel-header">
              <i className="fa fa-inbox" aria-hidden="true" />
              <h6>{archived ? 'Archived' : 'Conversations'}</h6>
            </header>

            <div className="msg-conv-list" role="list">
              <ConversationList
                listLoading={listLoading}
                threads={threads}
                archived={archived}
                activeKey={activeKey}
                archiveBusyKey={archiveBusyKey}
                canLoadMoreThreads={canLoadMoreThreads}
                listPage={listPage}
                onSelect={openThread}
                onToggleArchive={toggleArchive}
                onLoadMore={onLoadMoreThreads}
              />
            </div>
          </section>

          <section className="msg-panel msg-panel-thread" aria-label="Message thread">
            <header className="msg-panel-header msg-panel-header-thread">
              <button
                type="button"
                className="msg-thread-back"
                onClick={closeThread}
                aria-label="Back to conversations"
              >
                <i className="fa fa-arrow-left" aria-hidden="true" />
                <span>Back</span>
              </button>
              <i className="fa fa-comments msg-thread-header-icon" aria-hidden="true" />
              <h6 className="msg-thread-title">
                {headerPeer
                  ? (
                    <>
                      <PeerAvatar peer={headerPeer} className="msg-thread-avatar" size={32} />
                      <span className="msg-thread-name">{headerPeer.name || 'Conversation'}</span>
                      <span className="msg-thread-role">{roleLabel(headerPeer.role)}</span>
                    </>
                    )
                  : 'Select a conversation'}
              </h6>
              {active && (
                <div className="msg-thread-actions">
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-secondary"
                    onClick={() => toggleArchive(
                      {
                        internship_id: active.internship_id,
                        peer: active.peer,
                        user_archived: activeUserArchived,
                      },
                      !activeUserArchived
                    )}
                    disabled={archiveBusyKey === activeKey}
                    title={activeUserArchived ? 'Move to Active' : 'Archive'}
                  >
                    <i className={`fa ${activeUserArchived ? 'fa-inbox' : 'fa-archive'}`} aria-hidden="true" />
                    <span>{activeUserArchived ? 'Unarchive' : 'Archive'}</span>
                  </button>
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-danger"
                    onClick={() => {
                      setClearError(null)
                      setClearConfirmOpen(true)
                    }}
                    title="Clear conversation from your view"
                  >
                    <i className="fa fa-eraser" aria-hidden="true" />
                    <span>Clear</span>
                  </button>
                </div>
              )}
            </header>

            {!active ? (
              <div className="msg-empty msg-empty-thread">
                <i className="fa fa-paper-plane" aria-hidden="true" />
                <p className="msg-empty-title">Select a conversation to start messaging</p>
                <p className="msg-empty-sub">Choose someone from the list to view the thread and send a message.</p>
              </div>
            ) : (
              <>
                {(threadMeta?.internship || active?.student_name || active?.internship_term) && (
                  <div className="msg-thread-context">
                    {[
                      active?.student_name ? `Re: ${active.student_name}` : null,
                      threadMeta?.internship?.term || active?.internship_term || null,
                      (threadMeta?.internship?.status || active?.internship_status)
                        ? String(threadMeta?.internship?.status || active.internship_status).replace(/_/g, ' ')
                        : null,
                    ].filter(Boolean).join(' · ')}
                  </div>
                )}

                {threadLoading ? (
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
                      className="msg-thread-pane"
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
                            onUnsend={handleUnsend}
                            onOpenImage={openLightbox}
                          />
                        ))
                      )}
                    </div>
                  </>
                )}

                {sendError && (
                  <div className="alert alert-danger msg-send-error py-2" role="alert">{sendError}</div>
                )}

                <MessageComposer
                  key={activeKey}
                  disabled={!active}
                  sending={sending}
                  showArchivedHint={archived}
                  onSend={handleSend}
                />
              </>
            )}
          </section>
        </div>
      </div>

      {lightbox && (
        <div
          className="msg-lightbox"
          role="dialog"
          aria-modal="true"
          aria-label={lightbox.filename}
          onClick={() => {
            if (lightbox.revokeOnClose && lightbox.url) URL.revokeObjectURL(lightbox.url)
            setLightbox(null)
          }}
          onKeyDown={(e) => {
            if (e.key === 'Escape') {
              if (lightbox.revokeOnClose && lightbox.url) URL.revokeObjectURL(lightbox.url)
              setLightbox(null)
            }
          }}
        >
          <button
            type="button"
            className="msg-lightbox-close"
            aria-label="Close image"
            onClick={() => {
              if (lightbox.revokeOnClose && lightbox.url) URL.revokeObjectURL(lightbox.url)
              setLightbox(null)
            }}
          >
            <i className="fa fa-times" aria-hidden="true" />
          </button>
          <img
            src={lightbox.url}
            alt={lightbox.filename}
            className="msg-lightbox-img"
            onClick={(e) => e.stopPropagation()}
            onError={() => {
              if (lightbox.revokeOnClose && lightbox.url) URL.revokeObjectURL(lightbox.url)
              setLightbox(null)
            }}
          />
          <div className="msg-lightbox-caption">{lightbox.filename}</div>
        </div>
      )}
    </Layout>
  )
}

export default MessagesInbox
