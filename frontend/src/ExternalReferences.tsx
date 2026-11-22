import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Reference = { id: number; title: string; issuing_body: string | null; reference_number: string | null; url: string | null; published_at: string | null; last_reviewed_at: string | null; review_due_at: string | null; status: string; notes: string | null; documents: { id: number; title: string }[] }

const DATE_FORMAT: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' }

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(undefined, DATE_FORMAT)
}

function readableStatus(value: string) {
  return value.replaceAll('_', ' ').replace(/^\w/, (char) => char.toUpperCase())
}

function statusTone(value: string) {
  if (value === 'active') return 'released'
  if (['pending', 'due', 'overdue'].includes(value)) return 'pending'
  return 'neutral'
}

export default function ExternalReferences() {
  const [rows, setRows] = useState<Reference[] | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const result = await authRequest('/external-references') as { data: Reference[] }
      setRows(result.data)
      setError('')
    } catch (reason) {
      setRows(null)
      setError(reason instanceof Error ? reason.message : 'Could not load external references.')
    }
  }, [])

  useEffect(() => {
    let active = true
    authRequest('/external-references')
      .then((result) => {
        if (!active) return
        setRows((result as { data: Reference[] }).data)
        setError('')
      })
      .catch((reason: unknown) => {
        if (!active) return
        setRows(null)
        setError(reason instanceof Error ? reason.message : 'Could not load external references.')
      })
    return () => { active = false }
  }, [])

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const formElement = event.currentTarget; const form = new FormData(formElement); setBusy(true); setError(''); setNotice('')
    try {
      await authRequest('/external-references', 'POST', {
        title: String(form.get('title') ?? ''), issuing_body: String(form.get('issuing_body') || '') || null,
        reference_number: String(form.get('reference_number') || '') || null, url: String(form.get('url') || '') || null,
        published_at: String(form.get('published_at') || '') || null, last_reviewed_at: String(form.get('last_reviewed_at') || '') || null,
        review_due_at: String(form.get('review_due_at') || '') || null, status: 'active', notes: String(form.get('notes') || '') || null,
      })
      formElement.reset(); await refresh(); setNotice('External reference added.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save the reference.') }
    finally { setBusy(false) }
  }

  async function linkDocument(event: FormEvent<HTMLFormElement>, referenceId: number) {
    event.preventDefault(); const form = new FormData(event.currentTarget); const documentId = Number(form.get('document_id'))
    setBusy(true); setError(''); setNotice('')
    try { await authRequest(`/documents/${documentId}/external-references/${referenceId}`, 'POST', {}); await refresh(); setNotice('Reference linked to the visible document.') }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not link the reference.') }
    finally { setBusy(false) }
  }

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error} {rows === null && <button className="text-button" type="button" onClick={() => { setError(''); void refresh() }}>Retry</button>}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    <form className="panel registry-create" onSubmit={create}>
      <div className="panel-heading"><div><h2>Register an external reference</h2><p>Track standards, laws, regulations, and guidance without rewriting internal documents.</p></div></div>
      <label>Title<input name="title" required maxLength={500} autoComplete="off" /></label>
      <div className="registry-form-grid">
        <label>Issuing body<input name="issuing_body" maxLength={255} autoComplete="off" /></label>
        <label>Reference number<input name="reference_number" maxLength={255} autoComplete="off" /></label>
        <label>Reference URL<input name="url" type="url" maxLength={2048} autoComplete="off" /></label>
        <label>Published date<input name="published_at" type="date" autoComplete="off" /></label>
        <label>Last reviewed<input name="last_reviewed_at" type="date" autoComplete="off" /></label>
        <label>Review due<input name="review_due_at" type="date" autoComplete="off" /></label>
      </div>
      <label>Notes<textarea name="notes" rows={2} maxLength={4000} /></label>
      <button className="button button-primary" disabled={busy}>{busy ? 'Saving…' : 'Save reference'}</button>
    </form>

    <section className="panel registry-panel" aria-busy={rows === null && !error}>
      <div className="panel-heading"><div><h2>External references</h2><p>Review dates are tracked; external changes never update controlled documents automatically.</p></div></div>

      {rows === null
        ? (error ? <div className="registry-empty">External references could not be loaded.</div> : <p className="registry-loading">Loading external references…</p>)
        : !rows.length
          ? <div className="registry-empty">No external references recorded yet.</div>
          : <div className="registry-table-wrap external-references-wrap" role="region" tabIndex={0} aria-labelledby="external-references-caption">
            <table className="registry-table external-references-table">
              <caption className="sr-only" id="external-references-caption">External references — reference, status, review dates, linked documents, and actions.</caption>
              <thead>
                <tr>
                  <th scope="col">Reference</th>
                  <th scope="col">Status</th>
                  <th scope="col" className="is-date">Published</th>
                  <th scope="col" className="is-date">Last reviewed</th>
                  <th scope="col" className="is-date">Review due</th>
                  <th scope="col">Linked documents</th>
                  <th scope="col"><span className="sr-only">Row actions</span></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => <tr key={row.id}>
                  <th scope="row">
                    <strong>{row.title}</strong>
                    <small>{row.issuing_body ?? 'Issuing body not recorded'}{row.reference_number ? ` · ${row.reference_number}` : ''}</small>
                    {row.url && <a href={row.url} target="_blank" rel="noreferrer" className="registry-link">Open source reference</a>}
                    {row.notes && <small className="external-reference-notes">{row.notes}</small>}
                  </th>
                  <td><span className={`stamp stamp-${statusTone(row.status)}`}>{readableStatus(row.status)}</span></td>
                  <td className="is-date"><time dateTime={row.published_at ?? undefined}>{formatDate(row.published_at)}</time></td>
                  <td className="is-date"><time dateTime={row.last_reviewed_at ?? undefined}>{formatDate(row.last_reviewed_at)}</time></td>
                  <td className="is-date"><time dateTime={row.review_due_at ?? undefined}>{formatDate(row.review_due_at)}</time></td>
                  <td>
                    {row.documents.length === 0 ? <span className="records-none">No linked documents</span> : <ul className="external-reference-links">
                      {row.documents.map((document) => <li key={document.id}>{document.title} (#{document.id})</li>)}
                    </ul>}
                  </td>
                  <td className="registry-row-actions">
                    <form className="external-reference-link-form" onSubmit={(event) => linkDocument(event, row.id)}>
                      <label className="sr-only" htmlFor={`doc-${row.id}`}>Visible document ID for {row.title}</label>
                      <input id={`doc-${row.id}`} name="document_id" type="number" min="1" required autoComplete="off" placeholder="Document ID" aria-label={`Visible document ID to link to ${row.title}`} />
                      <button className="button button-secondary" disabled={busy}>{busy ? 'Linking…' : 'Link document'}</button>
                    </form>
                  </td>
                </tr>)}
              </tbody>
            </table>
          </div>}
    </section>
  </div>
}
