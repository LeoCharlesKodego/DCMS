import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { authRequest, authUpload, uploadLimitLabel } from './api'

type MigrationItem = {
  id: number; original_filename: string; sha256: string; byte_size: number; media_type: string
  verification_status: string; verification_notes: string | null; registered_document_id: number | null; source_metadata: Record<string, string | null>
  mapped_metadata: Record<string, unknown> | null; duplicate_candidates: { type: string; label: string }[] | null
}
type Batch = { id: number; name: string; status: string; items: MigrationItem[] }
type BatchSummary = { id: number; name: string; status: string; items_count: number }
type Reconciliation = { total: number; verified: number; pending: number; rejected: number; duplicates: number; issues: number; obsolete: number; superseded: number }
type Options = { document_types: { id: number; name: string }[]; units: { id: number; name: string }[]; owners: { id: number; name: string }[] }

const fields = ['original_code', 'original_title', 'document_type', 'category', 'revision_label', 'approval_authority', 'approval_date', 'effective_date', 'owner', 'department', 'historical_version_of'] as const

function statusTone(value: string) {
  if (['verified'].includes(value)) return 'released'
  if (['requires_correction', 'conflicting_information', 'duplicate_candidate', 'pending'].includes(value)) return 'pending'
  if (['rejected_for_migration', 'obsolete'].includes(value)) return 'rejected'
  return 'neutral'
}

function readableStatus(value: string) {
  return value.replaceAll('_', ' ').replace(/^\w/, (char) => char.toUpperCase())
}

export default function DocumentMigration() {
  const [batches, setBatches] = useState<BatchSummary[] | null>(null)
  const [selected, setSelected] = useState<number | null>(null)
  const [batch, setBatch] = useState<Batch | null>(null)
  const [report, setReport] = useState<Reconciliation | null>(null)
  const [options, setOptions] = useState<Options | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  const loadBatch = useCallback(async (id: number) => {
    const result = await authRequest(`/document-migrations/${id}`) as { data: Batch; reconciliation: Reconciliation }
    setSelected(id)
    setBatch(result.data)
    setReport(result.reconciliation)
  }, [])

  const refreshBatches = useCallback(async () => {
    const result = await authRequest('/document-migrations') as { data: BatchSummary[] }
    setBatches(result.data)
    setError('')
    if (selected) await loadBatch(selected)
  }, [selected, loadBatch])

  useEffect(() => {
    let active = true
    Promise.all([
      authRequest('/document-migrations'),
      authRequest('/document-migrations/options'),
    ])
      .then(([batchResult, optionsResult]) => {
        if (!active) return
        setBatches((batchResult as { data: BatchSummary[] }).data)
        setOptions(optionsResult as Options)
        setError('')
      })
      .catch((reason: unknown) => {
        if (!active) return
        setBatches(null)
        setError(reason instanceof Error ? reason.message : 'Could not load migration batches.')
      })
    return () => { active = false }
  }, [])

  async function createBatch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    try {
      const result = await authRequest('/document-migrations', 'POST', { name: String(form.get('name') ?? ''), source_description: String(form.get('source_description') || '') || null }) as { data: { id: number } }
      await refreshBatches(); await loadBatch(result.data.id); setNotice('Migration batch created.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not create the migration batch.') }
    finally { setBusy(false) }
  }

  async function uploadFiles(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selected) return
    const formElement = event.currentTarget
    setBusy(true); setError(''); setNotice('')
    try {
      await authUpload(`/document-migrations/${selected}/files`, new FormData(formElement))
      formElement.reset(); await refreshBatches(); setNotice('Source files uploaded and hashed in private storage.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not upload source files.') }
    finally { setBusy(false) }
  }

  async function saveMetadata(event: FormEvent<HTMLFormElement>, item: MigrationItem) {
    event.preventDefault(); if (!selected) return
    const form = new FormData(event.currentTarget)
    const source_metadata = Object.fromEntries(fields.map((field) => [field, String(form.get(field) ?? '') || null]))
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/document-migration-items/${item.id}/mapping`, 'PUT', { source_metadata, mapped_metadata: item.mapped_metadata ?? {}, source_location: String(form.get('source_location') || '') || null })
      await refreshBatches(); setNotice('Source metadata saved; duplicate candidates were recalculated.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save source metadata.') }
    finally { setBusy(false) }
  }

  async function verifyItem(event: FormEvent<HTMLFormElement>, item: MigrationItem) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/document-migration-items/${item.id}/verify`, 'POST', { verification_status: String(form.get('verification_status')), verification_notes: String(form.get('verification_notes') || '') || null })
      await refreshBatches(); setNotice(`Updated verification status for ${item.original_filename}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not verify the migration item.') }
    finally { setBusy(false) }
  }

  async function registerItem(event: FormEvent<HTMLFormElement>, item: MigrationItem) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/document-migration-items/${item.id}/register`, 'POST', {
        title: String(form.get('title') ?? ''), revision_label: String(form.get('revision_label') ?? ''),
        document_type_id: Number(form.get('document_type_id')), owning_unit_id: Number(form.get('owning_unit_id')), owner_user_id: Number(form.get('owner_user_id')),
      })
      await refreshBatches(); setNotice('Verified source registered as an unreleased draft. Approval was not inferred.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not register the verified source.') }
    finally { setBusy(false) }
  }

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error} {batches === null && <button className="text-button" type="button" onClick={() => { setError(''); void refreshBatches().catch(() => {}) }}>Retry</button>}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    <form className="panel registry-create" onSubmit={createBatch}>
      <div className="panel-heading"><div><h2>Start a migration batch</h2><p>Source documents enter a verification queue; upload does not approve or release them.</p></div></div>
      <div className="registry-form-grid"><label>Batch name<input name="name" required maxLength={255} autoComplete="off" /></label><label>Source description<input name="source_description" maxLength={1000} placeholder="Source binder, folder, or legacy system" autoComplete="off" /></label></div>
      <button className="button button-primary" disabled={busy}>{busy ? 'Creating…' : 'Create batch'}</button>
    </form>

    <section className="panel registry-panel" aria-busy={batches === null && !error}>
      <div className="panel-heading">
        <div><h2>Migration verification queue</h2><p>Managed by the KMT registration permission.</p></div>
        <label>Batch
          <select value={selected ?? ''} onChange={(event) => { if (event.target.value) loadBatch(Number(event.target.value)).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load batch.')) }}>
            <option value="">Select batch</option>
            {(batches ?? []).map((item) => <option key={item.id} value={item.id}>{item.name} · {item.items_count} files · {item.status}</option>)}
          </select>
        </label>
      </div>

      {report && <div className="registry-table-wrap migration-reconciliation-wrap" role="region" tabIndex={0} aria-labelledby="migration-reconciliation-caption">
        <table className="registry-table migration-reconciliation-table">
          <caption className="sr-only" id="migration-reconciliation-caption">Batch reconciliation counts.</caption>
          <thead><tr><th scope="col">Metric</th><th scope="col" className="is-numeric">Count</th></tr></thead>
          <tbody>
            {Object.entries(report).map(([key, value]) => <tr key={key}><th scope="row">{key.replaceAll('_', ' ')}</th><td className="is-numeric">{value}</td></tr>)}
          </tbody>
        </table>
      </div>}

      {selected && <form className="version-upload migration-upload" onSubmit={uploadFiles}>
        <label>Source files (PDF, DOCX, XLSX, JPEG, PNG; up to {uploadLimitLabel} each)<input name="files[]" type="file" multiple required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" /></label>
        <button className="button button-secondary" disabled={busy}>Add source files</button>
      </form>}

      {batches === null
        ? (error ? <div className="registry-empty">Migration batches could not be loaded.</div> : <p className="registry-loading">Loading migration batches…</p>)
        : !batches.length
          ? <div className="registry-empty">No migration batches recorded yet.</div>
          : !selected
            ? <div className="registry-empty">Select a batch to inspect its source items.</div>
            : !batch
              ? <p className="registry-loading">Loading batch…</p>
              : !batch.items.length
                ? <div className="registry-empty">No source files recorded in this batch yet.</div>
                : <div className="migration-items">
                    {batch.items.map((item) => <article className="version-card migration-item" key={item.id}>
                      <div className="migration-item-heading">
                        <strong className="migration-filename">{item.original_filename}</strong>
                        <span className={`stamp stamp-${statusTone(item.verification_status)}`}>{readableStatus(item.verification_status)}</span>
                      </div>
                      <small className="migration-meta">{(item.byte_size / 1048576).toFixed(2)} MiB · SHA-256 <code className="migration-hash">{item.sha256}</code></small>
                      <a href={`/api/document-migration-items/${item.id}/source`} className="registry-link">Download integrity-checked source</a>
                      {!!item.duplicate_candidates?.length && <div><strong>Possible matches</strong><ul>{item.duplicate_candidates.map((candidate, index) => <li key={`${candidate.type}-${index}`}>{candidate.type.replaceAll('_', ' ')} · {candidate.label}</li>)}</ul></div>}
                      <details><summary>Preserve source metadata</summary><form className="workflow-form" onSubmit={(event) => saveMetadata(event, item)}>
                        <div className="registry-form-grid">{fields.map((field) => <label key={field}>{field.replaceAll('_', ' ')}<input name={field} defaultValue={item.source_metadata?.[field] ?? ''} autoComplete="off" /></label>)}<label>Source location<input name="source_location" autoComplete="off" /></label></div>
                        <button className="button button-secondary" disabled={busy}>Save metadata</button>
                      </form></details>
                      <form className="workflow-form" onSubmit={(event) => verifyItem(event, item)}>
                        <label>Verification status<select name="verification_status" defaultValue={item.verification_status === 'duplicate_candidate' ? 'verified' : item.verification_status}><option value="verified">Verified</option><option value="requires_correction">Requires correction</option><option value="conflicting_information">Conflicting information</option><option value="obsolete">Obsolete</option><option value="superseded">Superseded</option><option value="rejected_for_migration">Rejected for migration</option></select></label>
                        <label>Verification notes<textarea name="verification_notes" maxLength={4000} /></label>
                        <button className="button button-primary" disabled={busy}>Record verification</button>
                      </form>
                      {item.registered_document_id ? <small>Registered as document #{item.registered_document_id} · unreleased draft</small> : item.verification_status === 'verified' && options && <details><summary>Register as an unreleased draft</summary><form className="workflow-form" onSubmit={(event) => registerItem(event, item)}>
                        <label>Document title<input name="title" required maxLength={500} defaultValue={item.source_metadata?.original_title ?? ''} autoComplete="off" /></label>
                        <label>Source revision label<input name="revision_label" required maxLength={16} defaultValue={item.source_metadata?.revision_label ?? ''} autoComplete="off" /></label>
                        <label>Document type<select name="document_type_id" required defaultValue=""><option value="">Select type</option>{options.document_types.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>
                        <label>Owning unit<select name="owning_unit_id" required defaultValue=""><option value="">Select unit</option>{options.units.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>
                        <label>Document owner<select name="owner_user_id" required defaultValue=""><option value="">Select owner</option>{options.owners.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>
                        <button className="button button-secondary" disabled={busy}>Register unreleased draft</button>
                      </form></details>}
                    </article>)}
                  </div>}
    </section>
  </div>
}
