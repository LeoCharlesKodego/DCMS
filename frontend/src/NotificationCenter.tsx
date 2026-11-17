import { useEffect, useState } from 'react'
import { authRequest } from './api'

type Notice = { id: number; title: string; body: string; event_type: string; read_at: string | null; created_at: string }
type Result = { data: Notice[]; unread_count: number }

export default function NotificationCenter() {
  const [result, setResult] = useState<Result | null>(null)
  const [open, setOpen] = useState(false)
  async function refresh() { setResult(await authRequest('/notifications') as Result) }
  useEffect(() => { refresh().catch(() => undefined) }, [])
  async function markRead(id: number) { await authRequest(`/notifications/${id}/read`, 'POST', {}); await refresh() }

  return <div className="notification-center"><button className="top-icon-button" type="button" aria-label={`${result?.unread_count ?? 0} unread notifications`} title="Notifications" onClick={() => setOpen(!open)}><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>{!!result?.unread_count && <i />}</button>{open && <section className="notification-popover"><strong>Notifications</strong>{!result?.data.length ? <small>No notifications yet.</small> : result.data.map((item) => <article className={item.read_at ? '' : 'notification-unread'} key={item.id}><strong>{item.title}</strong><span>{item.body}</span><small>{new Date(item.created_at).toLocaleString()}</small>{!item.read_at && <button type="button" className="text-button" onClick={() => markRead(item.id).catch(() => undefined)}>Mark read</button>}</article>)}</section>}</div>
}
