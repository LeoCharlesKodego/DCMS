import { useCallback, useEffect, useRef, useState } from 'react'
import { authRequest } from './api'

type Notice = { id: number; title: string; body: string; event_type: string; read_at: string | null; created_at: string }
type Result = { data: Notice[]; unread_count: number }

// Popover API is Baseline Newly available. Without it the panel falls back to a
// fixed, state-driven overlay with its own Escape and outside-click handling.
const POPOVER_SUPPORTED = typeof HTMLElement !== 'undefined' && Object.hasOwn(HTMLElement.prototype, 'popover')

export default function NotificationCenter() {
  const [result, setResult] = useState<Result | null>(null)
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const panelRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)

  const refresh = useCallback(async () => {
    try {
      setResult(await authRequest('/notifications') as Result)
      setError('')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load notifications.')
    }
  }, [])

  useEffect(() => { void refresh() }, [refresh])

  // Keep aria-expanded in sync with the native popover and refresh on open.
  useEffect(() => {
    const panel = panelRef.current
    if (!panel || !POPOVER_SUPPORTED) return

    const handleToggle = (event: Event) => {
      const isOpen = (event as Event & { newState?: string }).newState === 'open'
      setOpen(isOpen)
      if (isOpen) {
        void refresh()
        panel.focus()
        return
      }
      // Chrome restores focus to the invoker; WebKit leaves it on the hidden panel.
      requestAnimationFrame(() => {
        const active = document.activeElement
        if (!active || active === document.body || panel.contains(active)) triggerRef.current?.focus()
      })
    }

    panel.addEventListener('toggle', handleToggle)
    return () => panel.removeEventListener('toggle', handleToggle)
  }, [refresh])

  // Fallback dismissal for browsers without the Popover API.
  useEffect(() => {
    if (POPOVER_SUPPORTED || !open) return

    const handleKey = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return
      setOpen(false)
      triggerRef.current?.focus()
    }
    const handlePointer = (event: PointerEvent) => {
      const target = event.target as Node
      if (!panelRef.current?.contains(target) && !triggerRef.current?.contains(target)) setOpen(false)
    }

    document.addEventListener('keydown', handleKey)
    document.addEventListener('pointerdown', handlePointer)
    return () => {
      document.removeEventListener('keydown', handleKey)
      document.removeEventListener('pointerdown', handlePointer)
    }
  }, [open])

  useEffect(() => {
    if (!POPOVER_SUPPORTED && open) panelRef.current?.focus()
  }, [open])

  function closeNotifications() {
    if (POPOVER_SUPPORTED) {
      panelRef.current?.hidePopover()
      return
    }

    setOpen(false)
    triggerRef.current?.focus()
  }

  async function markRead(id: number) {
    await authRequest(`/notifications/${id}/read`, 'POST', {})
    await refresh()
  }

  const unread = result?.unread_count ?? 0

  return (
    <div className="notification-center">
      <button
        ref={triggerRef}
        className="top-icon-button"
        type="button"
        popoverTarget={POPOVER_SUPPORTED ? 'notifications-panel' : undefined}
        onClick={POPOVER_SUPPORTED ? undefined : () => setOpen((current) => !current)}
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
        aria-expanded={open}
        aria-controls="notifications-panel"
      >
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" /></svg>
        {unread > 0 && <span className="notification-dot" aria-hidden="true" />}
      </button>

      <div
        ref={panelRef}
        id="notifications-panel"
        className={`notification-popover surface-glass${!POPOVER_SUPPORTED && open ? ' is-open' : ''}`}
        popover={POPOVER_SUPPORTED ? 'auto' : undefined}
        tabIndex={-1}
        aria-labelledby="notifications-heading"
      >
        <div className="notification-head">
          <h2 id="notifications-heading">Notifications</h2>
          <button className="notification-close" type="button" onClick={closeNotifications} aria-label="Close notifications">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
          </button>
        </div>

        {error && <p className="notification-empty notification-error" role="alert">{error}</p>}
        {!result ? <p className="notification-empty">Loading notifications…</p> : result.data.length === 0 ? (
          <p className="notification-empty">No notifications.</p>
        ) : (
          <ul className="notification-list" role="list">
            {result.data.map((item) => {
              const isUnread = !item.read_at
              return (
                <li className={isUnread ? 'notification-item is-unread' : 'notification-item'} key={item.id}>
                  <div className="notification-item-head">
                    <strong>{item.title}</strong>
                    {isUnread && <span className="notification-unread-label">Unread</span>}
                  </div>
                  <span>{item.body}</span>
                  <small><time dateTime={item.created_at}>{new Date(item.created_at).toLocaleString()}</time></small>
                  {isUnread && (
                    <button type="button" className="text-button" onClick={() => void markRead(item.id)}>
                      Mark read<span className="sr-only"> {item.title}</span>
                    </button>
                  )}
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </div>
  )
}
