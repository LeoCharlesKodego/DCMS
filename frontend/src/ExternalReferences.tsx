import { useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Reference = { id: number; title: string; issuing_body: string | null; reference_number: string | null; url: string | null; published_at: string | null; last_reviewed_at: string | null; review_due_at: string | null; status: string; notes: string | null; documents: { id: number; title: string }[] }

export default function ExternalReferences() {
  const [rows, setRows] = useState<Reference[]>([])
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  async function refresh() { const result = await authRequest('/external-references') as { data: Reference[] }; setRows(result.data) }
  useEffect(() => { refresh().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load external references.')) }, [])

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
    {error && <p className="registry-message registry-error" role="alert">{error}</p>}{notice && <p className="registry-message registry-success" role="status">{notice}</p>}
    <form className="panel registry-create" onSubmit={create}><div className="panel-heading"><div><h2>Register an external reference</h2><p>Track standards, laws, regulations, and guidance without rewriting internal documents.</p></div></div>
      <label>Title<input name="title" required maxLength={500} /></label><div className="registry-form-grid"><label>Issuing body<input name="issuing_body" maxLength={255} /></label><label>Reference number<input name="reference_number" maxLength={255} /></label><label>Reference URL<input name="url" type="url" maxLength={2048} /></label><label>Published date<input name="published_at" type="date" /></label><label>Last reviewed<input name="last_reviewed_at" type="date" /></label><label>Review due<input name="review_due_at" type="date" /></label></div><label>Notes<textarea name="notes" rows={2} maxLength={4000} /></label><button className="button button-primary" disabled={busy}>Save reference</button>
    </form>
    <section className="panel registry-panel"><div className="panel-heading"><div><h2>External references</h2><p>Review dates are tracked; external changes never update controlled documents automatically.</p></div></div>
      {!rows.length ? <div className="registry-empty">No external references recorded yet.</div> : rows.map((row) => <article className="version-card" key={row.id}><strong>{row.title}</strong><span>{row.issuing_body ?? 'Issuing body not recorded'}{row.reference_number ? ` · ${row.reference_number}` : ''} · {row.status.replaceAll('_', ' ')}</span>{row.url && <a href={row.url} target="_blank" rel="noreferrer">Open source reference</a>}<small>Published {row.published_at ?? 'not recorded'} · reviewed {row.last_reviewed_at ?? 'not reviewed'} · next review {row.review_due_at ?? 'not set'}</small>{row.documents.map((document) => <small key={document.id}>Linked document: {document.title} (#{document.id})</small>)}<form className="registry-actions" onSubmit={(event) => linkDocument(event, row.id)}><label>Visible document ID<input name="document_id" type="number" min="1" required /></label><button className="button button-secondary" disabled={busy}>Link document</button></form>{row.notes && <p>{row.notes}</p>}</article>)}
    </section>
  </div>
}
