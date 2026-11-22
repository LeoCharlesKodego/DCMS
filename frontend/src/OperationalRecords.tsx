import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Schedule = { id: number; record_type: string; retention_duration: number | null; retention_unit: string | null; trigger_event: string; legal_basis: string | null; disposition_method: string | null; is_active: boolean }
type RecordRow = { id: number; title: string; record_type: string; reference_number: string | null; status: string; retention_triggered_on: string | null; schedule: Schedule | null; holds: { id: number; reason: string; released_at: string | null }[] }
type Data = { records: RecordRow[]; schedules: Schedule[]; disposition_requests: { id: number; status: string; reason: string }[] }

const testIds = {
  loading: 'records-loading',
  empty: 'records-empty',
  table: 'records-table',
}

function loadFailure(reason: unknown) {
  return reason instanceof Error ? reason.message : 'Could not load records.'
}

function readableStatus(value: string) {
  const label = value.replaceAll('_', ' ')

  return label.charAt(0).toUpperCase() + label.slice(1)
}

function statusTone(value: string) {
  if (value === 'active') return 'released'
  if (['on_hold', 'pending', 'pending_disposition'].includes(value)) return 'pending'
  return 'neutral'
}

function formatDate(value: string | null) {
  if (!value) return '—'

  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

function retentionLabel(schedule: Schedule | null) {
  if (!schedule) return 'No schedule'
  if (!schedule.retention_duration || !schedule.retention_unit) return `${schedule.record_type} · period not set`

  return `${schedule.record_type} · ${schedule.retention_duration} ${schedule.retention_unit}${schedule.is_active ? '' : ' · inactive'}`
}

export default function OperationalRecords() {
  const [data, setData] = useState<Data | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  const refresh = useCallback(async () => {
    try {
      setData(await authRequest('/operational-records') as Data)
      setError('')
    } catch (reason) {
      setData(null)
      setError(loadFailure(reason))
    }
  }, [])

  useEffect(() => {
    let active = true
    authRequest('/operational-records')
      .then((result) => {
        if (!active) return
        setData(result as Data)
        setError('')
      })
      .catch((reason: unknown) => {
        if (!active) return
        setData(null)
        setError(loadFailure(reason))
      })

    return () => { active = false }
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>, path: string, success: string) {
    event.preventDefault(); const form = new FormData(event.currentTarget); setBusy(true); setError(''); setNotice('')
    const body = Object.fromEntries(form.entries())
    try { await authRequest(path, 'POST', body); await refresh(); setNotice(success) }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save the record.') }
    finally { setBusy(false) }
  }

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error} {!data && <button className="text-button" type="button" onClick={() => { setError(''); void refresh() }}>Retry</button>}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    <div className="registry-form-grid">
      <form className="panel registry-create" onSubmit={(event) => submit(event, '/retention-schedules', 'Draft retention schedule saved.')}>
        <h2>Retention schedule draft</h2>
        <p>Schedules are inactive until institution-specific periods and bases are approved.</p>
        <label>Record type<input name="record_type" required maxLength={255} autoComplete="off" /></label>
        <label>Trigger event<input name="trigger_event" required maxLength={100} autoComplete="off" placeholder="creation, closure, or another approved event" /></label>
        <label>Retention duration<input name="retention_duration" type="number" min="1" max="65535" inputMode="numeric" autoComplete="off" /></label>
        <label>Time unit<select name="retention_unit"><option value="">Not set</option><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option></select></label>
        <label>Legal or policy basis<textarea name="legal_basis" maxLength={4000} rows={2} /></label>
        <label>Disposition method<input name="disposition_method" maxLength={255} autoComplete="off" /></label>
        <input type="hidden" name="is_active" value="false" />
        <button className="button button-secondary" disabled={busy}>{busy ? 'Saving…' : 'Save inactive schedule'}</button>
      </form>

      <form className="panel registry-create" onSubmit={(event) => submit(event, '/operational-records', 'Operational record metadata saved.')}>
        <h2>Register operational record</h2>
        <p>Do not enter patient charts or clinical patient data.</p>
        <label>Record type<input name="record_type" required maxLength={255} autoComplete="off" /></label>
        <label>Title<input name="title" required maxLength={500} autoComplete="off" /></label>
        <label>Reference number<input name="reference_number" maxLength={255} autoComplete="off" /></label>
        <label>Retention schedule<select name="retention_schedule_id"><option value="">No schedule</option>{data?.schedules.map((schedule) => <option key={schedule.id} value={schedule.id}>{schedule.record_type}{schedule.is_active ? '' : ' · inactive'}</option>)}</select></label>
        <label>Record date<input name="created_on" type="date" autoComplete="off" /></label>
        <label>Retention trigger date<input name="retention_triggered_on" type="date" autoComplete="off" /></label>
        <button className="button button-primary" disabled={busy}>{busy ? 'Saving…' : 'Save record metadata'}</button>
      </form>
    </div>

    <section className="panel registry-panel" aria-busy={data === null && !error}>
      <div className="panel-heading"><div><h2>Operational records</h2><p>Holds block disposition. Expiry never deletes a record automatically.</p></div></div>

      {data === null
        ? (error
          ? <div className="registry-empty" data-testid={testIds.empty}>Records could not be loaded. Use Retry above.</div>
          : <p className="registry-loading" data-testid={testIds.loading}>Loading operational records…</p>)
        : !data.records.length
          ? <div className="registry-empty" data-testid={testIds.empty}>No operational records recorded yet.</div>
          : <div className="registry-table-wrap records-table-wrap" role="region" tabIndex={0} aria-labelledby="records-table-caption">
            <table className="registry-table records-table" data-testid={testIds.table}>
              <caption className="sr-only" id="records-table-caption">Operational records — retention schedule, retention trigger date, holds, and the actions available for each record.</caption>
              <thead>
                <tr>
                  <th scope="col">Record</th>
                  <th scope="col">Status</th>
                  <th scope="col">Retention schedule</th>
                  <th scope="col" className="is-date">Retention triggered</th>
                  <th scope="col">Holds</th>
                  <th scope="col"><span className="sr-only">Row actions</span></th>
                </tr>
              </thead>
              <tbody>
                {data.records.map((record) => <tr key={record.id}>
                  <th scope="row">
                    <strong>{record.title}</strong>
                    <small>{record.record_type}{record.reference_number ? ` · ${record.reference_number}` : ''}</small>
                  </th>
                  <td><span className={`stamp stamp-${statusTone(record.status)}`}>{readableStatus(record.status)}</span></td>
                  <td>{retentionLabel(record.schedule)}<small>{record.schedule ? `Trigger: ${record.schedule.trigger_event}` : 'Choose a schedule when registering the record'}</small></td>
                  <td className="is-date"><time dateTime={record.retention_triggered_on ?? undefined}>{formatDate(record.retention_triggered_on)}</time></td>
                  <td>
                    {record.holds.length === 0 ? <span className="records-none">No holds</span> : <ul className="records-holds">
                      {record.holds.map((hold) => <li key={hold.id}>{hold.reason} · {hold.released_at ? `released ${formatDate(hold.released_at)}` : 'active'}</li>)}
                    </ul>}
                  </td>
                  <td className="registry-row-actions">
                    <div className="records-actions">
                      <form onSubmit={(event) => submit(event, `/operational-records/${record.id}/holds`, 'Record hold placed.')}>
                        <input name="reason" required maxLength={255} autoComplete="off" placeholder="Hold reason" aria-label={`Hold reason for ${record.title}`} />
                        <button className="button button-secondary" disabled={busy}>{busy ? 'Placing…' : 'Place hold'}</button>
                      </form>
                      <form onSubmit={(event) => submit(event, `/operational-records/${record.id}/disposition-requests`, 'Disposition request submitted.')}>
                        <input name="reason" required maxLength={255} autoComplete="off" placeholder="Disposition request reason" aria-label={`Disposition request reason for ${record.title}`} />
                        <button className="button button-secondary" disabled={busy}>{busy ? 'Submitting…' : 'Request disposition review'}</button>
                      </form>
                    </div>
                  </td>
                </tr>)}
              </tbody>
            </table>
          </div>}
    </section>
  </div>
}
