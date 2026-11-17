import { useEffect, useState } from 'react'
import { authRequest } from './api'

type Summary = Record<string, number>
type Event = { id: number; event_type: string; target_type: string; from_state: string | null; to_state: string | null; actor: { name: string } | null; document: { title: string } | null; reason: string | null; occurred_at: string }

export default function ReportsDashboard() {
  const [summary, setSummary] = useState<Summary | null>(null)
  const [events, setEvents] = useState<Event[]>([])
  const [error, setError] = useState('')
  useEffect(() => {
    Promise.all([authRequest('/reports/summary'), authRequest('/reports/audit?limit=100')])
      .then(([counts, audit]) => { setSummary((counts as { data: Summary }).data); setEvents((audit as { data: Event[] }).data) })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load authorized reports.'))
  }, [])
  return <div className="registry-layout">{error && <p className="registry-message registry-error" role="alert">{error}</p>}<section className="panel registry-panel"><div className="panel-heading"><div><h2>Document control summary</h2><p>Approved starter reports: JSON summary and audit history.</p></div></div><div className="registry-actions">{Object.entries(summary ?? {}).map(([key, value]) => <span className="registry-status" key={key}>{key.replaceAll('_', ' ')}: {value}</span>)}</div></section><section className="panel registry-panel"><div className="panel-heading"><div><h2>Audit history</h2><p>Latest 100 events. Audit entries remain append-only.</p></div></div>{!events.length ? <div className="registry-empty">No audit events are available.</div> : <div className="audit-timeline">{events.map((item) => <article className="audit-event" key={item.id}><span className="audit-event-mark"/><div><strong>{item.event_type.replaceAll('.', ' ').replaceAll('_', ' ')}</strong><small>{item.actor?.name ?? 'System'}{item.document?.title ? ` · ${item.document.title}` : ` · ${item.target_type} #${item.id}`}</small>{item.from_state && <small>{item.from_state} → {item.to_state}</small>}{item.reason && <p>{item.reason}</p>}</div><time>{new Date(item.occurred_at).toLocaleString()}</time></article>)}</div>}</section></div>
}
