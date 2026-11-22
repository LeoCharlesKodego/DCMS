import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Summary = Record<string, number>
type Event = { id: number; event_type: string; target_type: string; from_state: string | null; to_state: string | null; actor: { name: string } | null; document: { title: string } | null; reason: string | null; occurred_at: string }

const DATE_TIME_FORMAT: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }

function formatDateTime(value: string) {
  return new Date(value).toLocaleString(undefined, DATE_TIME_FORMAT)
}

function readableEvent(value: string) {
  return value.replaceAll('.', ' ').replaceAll('_', ' ')
}

function readableState(value: string | null) {
  return value ? value.replaceAll('_', ' ') : '—'
}

export default function ReportsDashboard() {
  const [summary, setSummary] = useState<Summary | null>(null)
  const [events, setEvents] = useState<Event[] | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const [eventType, setEventType] = useState('')
  const [fromDate, setFromDate] = useState('')
  const [toDate, setToDate] = useState('')

  const load = useCallback(async (filters: { event_type?: string; from?: string; to?: string } = {}) => {
    setBusy(true)
    setError('')
    try {
      const params = new URLSearchParams()
      if (filters.event_type) params.set('event_type', filters.event_type)
      if (filters.from) params.set('from', filters.from)
      if (filters.to) params.set('to', filters.to)
      params.set('limit', '100')
      const [counts, audit] = await Promise.all([
        authRequest('/reports/summary'),
        authRequest(`/reports/audit?${params.toString()}`),
      ])
      setSummary((counts as { data: Summary }).data)
      setEvents((audit as { data: Event[] }).data)
    } catch (reason) {
      setSummary(null)
      setEvents(null)
      setError(reason instanceof Error ? reason.message : 'Could not load authorized reports.')
    } finally {
      setBusy(false)
    }
  }, [])

  useEffect(() => {
    let active = true
    const params = new URLSearchParams()
    params.set('limit', '100')
    Promise.all([authRequest('/reports/summary'), authRequest(`/reports/audit?${params.toString()}`)])
      .then(([counts, audit]) => {
        if (!active) return
        setSummary((counts as { data: Summary }).data)
        setEvents((audit as { data: Event[] }).data)
        setError('')
      })
      .catch((reason: unknown) => {
        if (!active) return
        setSummary(null)
        setEvents(null)
        setError(reason instanceof Error ? reason.message : 'Could not load authorized reports.')
      })
    return () => { active = false }
  }, [])

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    void load({ event_type: eventType || undefined, from: fromDate || undefined, to: toDate || undefined })
  }

  function clearFilters() {
    setEventType('')
    setFromDate('')
    setToDate('')
    void load({})
  }

  const eventTypes = Array.from(new Set((events ?? []).map((item) => item.event_type))).sort()

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error} {events === null && <button className="text-button" type="button" onClick={() => { setError(''); void load({ event_type: eventType || undefined, from: fromDate || undefined, to: toDate || undefined }) }}>Retry</button>}</p>}

    <section className="panel registry-panel" aria-busy={summary === null && !error}>
      <div className="panel-heading"><div><h2>Document control summary</h2><p>Approved starter reports: counts and audit history.</p></div></div>
      {summary === null
        ? (error ? <div className="registry-empty">Summary could not be loaded.</div> : <p className="registry-loading">Loading report summary…</p>)
        : <div className="registry-table-wrap reports-summary-wrap" role="region" tabIndex={0} aria-labelledby="reports-summary-caption">
            <table className="registry-table reports-summary-table">
              <caption className="sr-only" id="reports-summary-caption">Document control summary counts.</caption>
              <thead><tr><th scope="col">Metric</th><th scope="col" className="is-numeric">Count</th></tr></thead>
              <tbody>
                {Object.entries(summary).map(([key, value]) => <tr key={key}>
                  <th scope="row">{key.replaceAll('_', ' ')}</th>
                  <td className="is-numeric">{value}</td>
                </tr>)}
              </tbody>
            </table>
          </div>}
    </section>

    <section className="panel registry-panel" aria-busy={events === null && !error}>
      <div className="panel-heading"><div><h2>Audit history</h2><p>Latest 100 events. Audit entries remain append-only.</p></div></div>

      <form className="registry-actions reports-filters" onSubmit={applyFilters}>
        <label>Event type
          <select value={eventType} onChange={(event) => setEventType(event.target.value)}>
            <option value="">All events</option>
            {eventTypes.map((type) => <option key={type} value={type}>{readableEvent(type)}</option>)}
          </select>
        </label>
        <label>From date<input type="date" value={fromDate} onChange={(event) => setFromDate(event.target.value)} /></label>
        <label>To date<input type="date" value={toDate} onChange={(event) => setToDate(event.target.value)} /></label>
        <div className="reports-filter-actions">
          <button className="button button-primary" type="submit" disabled={busy}>{busy ? 'Loading…' : 'Apply filters'}</button>
          <button className="button button-secondary" type="button" onClick={clearFilters} disabled={busy}>Clear</button>
        </div>
      </form>

      {events === null
        ? (error ? <div className="registry-empty">Audit history could not be loaded.</div> : <p className="registry-loading">Loading audit history…</p>)
        : !events.length
          ? <div className="registry-empty">No audit events recorded yet.</div>
          : <div className="registry-table-wrap reports-audit-wrap" role="region" tabIndex={0} aria-labelledby="reports-audit-caption">
              <table className="registry-table reports-audit-table">
                <caption className="sr-only" id="reports-audit-caption">Audit history — event, actor, target, state change, and when it occurred.</caption>
                <thead>
                  <tr>
                    <th scope="col">Event</th>
                    <th scope="col">Actor</th>
                    <th scope="col">Target</th>
                    <th scope="col">State change</th>
                    <th scope="col" className="is-date">When</th>
                  </tr>
                </thead>
                <tbody>
                  {events.map((item) => <tr key={item.id}>
                    <th scope="row">{readableEvent(item.event_type)}</th>
                    <td>{item.actor?.name ?? 'System'}</td>
                    <td>{item.document?.title ?? `${item.target_type} #${item.id}`}</td>
                    <td>{item.from_state ? `${readableState(item.from_state)} → ${readableState(item.to_state)}` : '—'}{item.reason ? <small>{item.reason}</small> : null}</td>
                    <td className="is-date"><time dateTime={item.occurred_at}>{formatDateTime(item.occurred_at)}</time></td>
                  </tr>)}
                </tbody>
              </table>
            </div>}
    </section>
  </div>
}
