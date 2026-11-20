import { useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Schedule = { id: number; record_type: string; retention_duration: number | null; retention_unit: string | null; trigger_event: string; legal_basis: string | null; disposition_method: string | null; is_active: boolean }
type RecordRow = { id: number; title: string; record_type: string; reference_number: string | null; status: string; retention_triggered_on: string | null; schedule: Schedule | null; holds: { id: number; reason: string; released_at: string | null }[] }
type Data = { records: RecordRow[]; schedules: Schedule[]; disposition_requests: { id: number; status: string; reason: string }[] }

export default function OperationalRecords() {
  const [data, setData] = useState<Data | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  async function refresh() { setData(await authRequest('/operational-records') as Data) }
  useEffect(() => { refresh().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load records.')) }, [])

  async function submit(event: FormEvent<HTMLFormElement>, path: string, success: string) {
    event.preventDefault(); const form = new FormData(event.currentTarget); setBusy(true); setError(''); setNotice('')
    const body = Object.fromEntries(form.entries())
    try { await authRequest(path, 'POST', body); await refresh(); setNotice(success) }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save the record.') }
    finally { setBusy(false) }
  }

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error}</p>}{notice && <p className="registry-message registry-success" role="status">{notice}</p>}
    <div className="registry-form-grid">
      <form className="panel registry-create" onSubmit={(event) => submit(event, '/retention-schedules', 'Draft retention schedule saved.') }><h2>Retention schedule draft</h2><p>Schedules are inactive until institution-specific periods and bases are approved.</p><label>Record type<input name="record_type" required maxLength={255} /></label><label>Trigger event<input name="trigger_event" required maxLength={100} placeholder="creation, closure, or another approved event" /></label><label>Retention duration<input name="retention_duration" type="number" min="1" max="65535" /></label><label>Time unit<select name="retention_unit"><option value="">Not set</option><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option></select></label><label>Legal or policy basis<textarea name="legal_basis" maxLength={4000} /></label><label>Disposition method<input name="disposition_method" maxLength={255} /></label><input type="hidden" name="is_active" value="false" /><button className="button button-secondary" disabled={busy}>Save inactive schedule</button></form>
      <form className="panel registry-create" onSubmit={(event) => submit(event, '/operational-records', 'Operational record metadata saved.') }><h2>Register operational record</h2><p>Do not enter patient charts or clinical patient data.</p><label>Record type<input name="record_type" required maxLength={255} /></label><label>Title<input name="title" required maxLength={500} /></label><label>Reference number<input name="reference_number" maxLength={255} /></label><label>Retention schedule<select name="retention_schedule_id"><option value="">No schedule</option>{data?.schedules.map((schedule) => <option key={schedule.id} value={schedule.id}>{schedule.record_type}{schedule.is_active ? '' : ' · inactive'}</option>)}</select></label><label>Record date<input name="created_on" type="date" /></label><label>Retention trigger date<input name="retention_triggered_on" type="date" /></label><button className="button button-primary" disabled={busy}>Save record metadata</button></form>
    </div>
    <section className="panel registry-panel"><div className="panel-heading"><div><h2>Operational records</h2><p>Holds block disposition. Expiry never deletes a record automatically.</p></div></div>{!data?.records.length ? <div className="registry-empty">No operational records recorded yet.</div> : data.records.map((record) => <article className="version-card" key={record.id}><strong>{record.title}</strong><span>{record.record_type} · {record.status.replaceAll('_', ' ')}{record.reference_number ? ` · ${record.reference_number}` : ''}</span><small>Schedule: {record.schedule?.record_type ?? 'none'} · trigger {record.retention_triggered_on ?? 'not recorded'}</small>{record.holds.map((hold) => <small key={hold.id}>Hold: {hold.reason} · {hold.released_at ? 'released' : 'active'}</small>)}<div className="registry-actions"><form onSubmit={(event) => submit(event, `/operational-records/${record.id}/holds`, 'Record hold placed.')}><input name="reason" required placeholder="Hold reason" /><button className="button button-secondary" disabled={busy}>Place hold</button></form><form onSubmit={(event) => submit(event, `/operational-records/${record.id}/disposition-requests`, 'Disposition request submitted.')}><input name="reason" required placeholder="Disposition request reason" /><button className="button button-secondary" disabled={busy}>Request disposition review</button></form></div></article>)}</section>
  </div>
}
