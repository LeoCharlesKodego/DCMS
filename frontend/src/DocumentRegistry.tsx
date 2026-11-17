import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { authRequest, authUpload, uploadLimitLabel } from './api'

type DocumentRow = {
  id: number; title: string; document_type: string | null; category: string | null
  owner: string | null; owning_unit: string | null; base_code: string | null
  display_code: string | null; revision_label: string; status: string
  owning_unit_id: number; versions: VersionRow[]
  audit_events: { id: number; event_type: string; target_type: string; from_state: string | null; to_state: string | null; actor: string | null; reason: string | null; occurred_at: string | null }[]
  effective_date: string | null; retention_years: number | null; remarks: string | null
}
type VersionRow = {
  id: number; revision_label: string; status: string; change_summary: string | null; proposed_effective_date: string | null; effective_date: string | null
  created_at: string | null; available_actions: string[]; lifecycle_note: string | null; files: { id: number; original_filename: string; media_type: string; byte_size: number; sha256: string; uploaded_at: string | null; is_current: boolean; validation_runs?: { run_number: number; status: string; code_source: string | null; extracted_code: string | null; findings: { rule: string; result: string; detail?: unknown }[] }[] }[]
  workflow_tasks: { id: number; stage_name: string; action_type: string; role: string; organizational_unit: string | null; decisions: string[] }[]
  distributions: { id: number; issued_at: string | null; issued_by: string | null; targets: string[]; recipients: { id: number; user_id: number; name: string; acknowledgment_required: boolean; status: string; due_at: string | null; acknowledged_at: string | null; acknowledged_by: string | null; can_acknowledge: boolean; can_exempt: boolean }[] }[]
}
type Option = { id: number; name: string; unit_type?: string; parent_service_id?: number; code?: string; source_group?: string }
type RegistryData = {
  data: DocumentRow[]; can_assign_code: boolean; can_distribute: boolean; can_view_master_code_register: boolean; create_units: Option[]
  document_types: Option[]; categories: Option[]
}
type CodeData = { services: Option[]; section_units: Option[]; document_types: Option[] }
type DistributionTargetOption = { target_type: 'organizational_unit' | 'role' | 'user'; id: number; label: string }
type MasterEntry = { id: number; base_code: string; sequence_number: number; requested_at: string; assigned_at: string; document: { title: string; current_revision_label: string; owning_unit: { name: string } }; service_component: Option; section_component: Option; unit_component: Option | null; document_type_component: Option; assigned_by: { name: string } }

export default function DocumentRegistry() {
  const [registry, setRegistry] = useState<RegistryData | null>(null)
  const [codes, setCodes] = useState<CodeData | null>(null)
  const [masterEntries, setMasterEntries] = useState<MasterEntry[] | null>(null)
  const [distributionTargets, setDistributionTargets] = useState<DistributionTargetOption[] | null>(null)
  const [serviceId, setServiceId] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [titleSizePt, setTitleSizePt] = useState<number | null>(null)
  const [titleSettingHistory, setTitleSettingHistory] = useState<{ old_value: string | null; new_value: string; changed_by: string; changed_at: string }[]>([])

  async function refresh() {
    const result = await authRequest('/documents') as RegistryData
    setRegistry(result)
    if (result.can_assign_code) {
      const settings = await authRequest('/configuration/phase4b-settings')
      setTitleSizePt(settings.title_size_pt)
      setTitleSettingHistory(settings.history ?? [])
    }
  }

  async function saveTitleSize(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const result = await authRequest('/configuration/phase4b-title-size', 'PUT', { title_size_pt: titleSizePt })
      setTitleSizePt(result.title_size_pt)
      const settings = await authRequest('/configuration/phase4b-settings')
      setTitleSettingHistory(settings.history ?? [])
      setNotice('Title-size setting saved with version history.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save title-size setting.') }
    finally { setBusy(false) }
  }

  useEffect(() => {
    refresh().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load documents.'))
  }, [])

  const scopedCodes = useMemo(() => codes?.section_units.filter((item) => item.parent_service_id === Number(serviceId)) ?? [], [codes, serviceId])

  async function createDocument(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const formElement = event.currentTarget
    const form = new FormData(formElement)
    try {
      await authRequest('/documents', 'POST', {
        title: String(form.get('title') ?? ''), document_type_id: Number(form.get('document_type_id')),
        category_id: form.get('category_id') ? Number(form.get('category_id')) : null,
        owning_unit_id: Number(form.get('owning_unit_id')),
        retention_years: form.get('retention_years') ? Number(form.get('retention_years')) : null,
        remarks: String(form.get('remarks') || '') || null,
      })
      formElement.reset(); await refresh(); setNotice('Document request added to the register, awaiting code assignment.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not create the document request.') }
    finally { setBusy(false) }
  }

  async function assignCode(event: FormEvent<HTMLFormElement>, documentId: number) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    try {
      await authRequest(`/documents/${documentId}/assign-code`, 'POST', {
        service_component_id: Number(form.get('service_component_id')),
        section_component_id: Number(form.get('section_component_id')),
        unit_component_id: form.get('unit_component_id') ? Number(form.get('unit_component_id')) : null,
        document_type_component_id: Number(form.get('document_type_component_id')),
      })
      await refresh(); setNotice('Official code assigned and added to the Master Code Register.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not assign the code.') }
    finally { setBusy(false) }
  }

  async function loadCodeLists() {
    try { setCodes(await authRequest('/documents/code-components') as CodeData) }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not load official code lists.') }
  }

  async function loadMasterRegister() {
    try { const result = await authRequest('/master-code-register') as { data: MasterEntry[] }; setMasterEntries(result.data) }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not load the Master Code Register.') }
  }

  async function loadDistributionTargets() {
    try {
      const result = await authRequest('/distribution/target-options') as { targets: DistributionTargetOption[] }
      setDistributionTargets(result.targets)
      setError('')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not load distribution targets.') }
  }

  async function uploadVersion(event: FormEvent<HTMLFormElement>, document: DocumentRow) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    try {
      await authUpload(`/documents/${document.id}/versions`, form)
      await refresh(); setNotice('Draft version and file hashes were saved in private storage.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not store the document version.') }
    finally { setBusy(false) }
  }

  async function replaceFiles(event: FormEvent<HTMLFormElement>, document: DocumentRow, version: VersionRow) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    try {
      await authUpload(`/documents/${document.id}/versions/${version.id}/files`, form)
      await refresh(); setNotice(`Replacement files saved for revision ${version.revision_label}; checks reran and prior findings remain in history.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not replace the current files.') }
    finally { setBusy(false) }
  }

  async function lifecycleAction(event: FormEvent<HTMLFormElement>, document: DocumentRow, version: VersionRow) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    const action = String(form.get('action') ?? '')
    try {
      await authRequest(`/documents/${document.id}/versions/${version.id}/actions`, 'POST', {
        action,
        reason: String(form.get('reason') || '') || null,
        effective_date: String(form.get('effective_date') || '') || null,
      })
      await refresh(); setNotice(`Recorded ${action.replaceAll('_', ' ')} for revision ${version.revision_label}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not record the lifecycle action.') }
    finally { setBusy(false) }
  }

  async function workflowDecision(event: FormEvent<HTMLFormElement>, document: DocumentRow, version: VersionRow, taskId: number) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    const submitter = (event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null
    const decisionType = submitter?.value ?? ''
    const comment = String(form.get('comment') ?? '').trim()
    if (decisionType === 'returned_for_revision' && !comment) {
      setError('A return reason is required.')
      setBusy(false)
      return
    }
    try {
      await authRequest(`/workflow-tasks/${taskId}/decisions`, 'POST', { decision_type: decisionType, comment: comment || null })
      await refresh(); setNotice(`Recorded ${decisionType.replaceAll('_', ' ')} for revision ${version.revision_label} of ${document.title}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not record the workflow decision.') }
    finally { setBusy(false) }
  }

  async function issueDistribution(event: FormEvent<HTMLFormElement>, document: DocumentRow, version: VersionRow) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    const targets = form.getAll('targets').map((value) => {
      const [target_type, target_id] = String(value).split(':')
      return { target_type, target_id: Number(target_id) }
    })
    try {
      await authRequest(`/documents/${document.id}/versions/${version.id}/distributions`, 'POST', {
        acknowledgment_required: form.get('acknowledgment_required') === 'on',
        due_at: String(form.get('due_at') || '') || null,
        targets,
      })
      await refresh(); setNotice(`Distributed revision ${version.revision_label} of ${document.title} to ${targets.length} target(s).`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not issue the distribution.') }
    finally { setBusy(false) }
  }

  async function acknowledgeDistribution(recipientId: number, document: DocumentRow, version: VersionRow) {
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/distribution-recipients/${recipientId}/acknowledge`, 'POST', {})
      await refresh(); setNotice(`Acknowledged receipt of revision ${version.revision_label} of ${document.title}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not acknowledge receipt.') }
    finally { setBusy(false) }
  }

  async function exemptDistribution(event: FormEvent<HTMLFormElement>, recipientId: number, document: DocumentRow, version: VersionRow) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const form = new FormData(event.currentTarget)
    try {
      await authRequest(`/distribution-recipients/${recipientId}/exempt`, 'POST', { reason: String(form.get('reason') ?? '').trim() })
      await refresh(); setNotice(`Exempted one recipient from acknowledgment for revision ${version.revision_label} of ${document.title}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not exempt acknowledgment.') }
    finally { setBusy(false) }
  }

  if (!registry && !error) return <section className="panel registry-panel"><p>Loading document register…</p></section>

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    {!!registry?.create_units.length && <form className="panel registry-create" onSubmit={createDocument}>
      <div className="panel-heading"><div><h2>Register a document</h2><p>Requests begin at revision 1.0 and await official code assignment.</p></div></div>
      <label>Document title<input name="title" required maxLength={500} /></label>
      <div className="registry-form-grid">
        <label>Document type<select name="document_type_id" required defaultValue=""><option value="" disabled>Select type</option>{registry.document_types.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        <label>Category<select name="category_id" defaultValue=""><option value="">No category</option>{registry.categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        <label>Owning unit<select name="owning_unit_id" required defaultValue=""><option value="" disabled>Select unit</option>{registry.create_units.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        <label>Retention period (years)<input name="retention_years" type="number" min="0" max="65535" /></label>
      </div>
      <label>Remarks<textarea name="remarks" rows={2} maxLength={4000} /></label>
      <button className="button button-primary" disabled={busy}>Add to register</button>
    </form>}

    <section className="panel registry-panel">
      <div className="panel-heading"><div><h2>Document Control Register</h2><p>Documents visible within your assigned role and unit scope.</p></div>
        <div className="registry-actions">
          {registry?.can_view_master_code_register && <button className="button button-secondary" type="button" onClick={loadMasterRegister}>View Master Code Register</button>}
          {registry?.can_assign_code && <button className="button button-secondary" type="button" onClick={loadCodeLists}>Load official code lists</button>}
        </div>
      </div>
      {registry?.can_assign_code && titleSizePt !== null && <form className="lifecycle-actions" onSubmit={(event) => void saveTitleSize(event)}><label>KMT title-size default (pt)<input type="number" min="1" value={titleSizePt} onChange={(event) => setTitleSizePt(Number(event.target.value))} required /></label><button className="button button-secondary" disabled={busy}>Save setting</button><details><summary>Setting version history</summary><ul>{titleSettingHistory.map((entry, index) => <li key={`${entry.changed_at}-${index}`}>{entry.old_value ?? 'unset'} → {entry.new_value} pt · {entry.changed_by} · {new Date(entry.changed_at).toLocaleString()}</li>)}</ul></details></form>}
      {!registry?.data.length ? <div className="registry-empty">No documents are available in your scope.</div> : <div className="registry-table-wrap"><table className="registry-table"><thead><tr><th>Code / revision</th><th>Title</th><th>Type</th><th>Owner / unit</th><th>Status</th></tr></thead><tbody>
        {registry.data.map((document) => <tr key={document.id}><td><strong>{document.display_code ?? 'Awaiting code'}</strong></td><td>{document.title}
          {!!document.versions.length && <details className="version-details"><summary>Versions ({document.versions.length})</summary>{document.versions.map((version) => <div className="version-card" key={version.id}><strong>Rev {version.revision_label}</strong><span>{version.status.replaceAll('_', ' ')}{version.effective_date ? ` · effective ${version.effective_date}` : ''}</span>{version.change_summary && <p>{version.change_summary}</p>}{version.lifecycle_note && <small className="lifecycle-note">{version.lifecycle_note}</small>}
            {!!version.distributions.length && <div className="lifecycle-actions"><h4>Distribution history</h4>{version.distributions.map((distribution) => <article className="audit-event" key={distribution.id}><div><strong>Issued {distribution.issued_at ? new Date(distribution.issued_at).toLocaleString() : ''}</strong><small>By {distribution.issued_by ?? 'KMT'}{distribution.targets.length ? ` · ${distribution.targets.join(', ')}` : ''}</small>{distribution.recipients.map((recipient) => <div key={recipient.id}><span>{recipient.name}: {recipient.status.replaceAll('_', ' ')}</span>{recipient.due_at && <small> · due {recipient.due_at}</small>}{recipient.acknowledged_at && <small> · acknowledged {new Date(recipient.acknowledged_at).toLocaleString()}</small>}{recipient.can_acknowledge && <button className="button button-secondary" type="button" disabled={busy} onClick={() => acknowledgeDistribution(recipient.id, document, version)}>Acknowledge receipt</button>}{recipient.can_exempt && <form onSubmit={(event) => exemptDistribution(event, recipient.id, document, version)}><label>Exemption reason<textarea name="reason" rows={2} required maxLength={4000} /></label><button className="button button-secondary" disabled={busy}>Exempt this recipient</button></form>}</div>)}</div></article>)}</div>}
            {registry.can_distribute && version.status === 'released' && <div className="lifecycle-actions"><button className="button button-secondary" type="button" disabled={busy} onClick={loadDistributionTargets}>Prepare distribution</button>{distributionTargets && <form onSubmit={(event) => issueDistribution(event, document, version)}><label>Targets<select name="targets" required multiple size={5}>{distributionTargets.map((target) => <option key={`${target.target_type}:${target.id}`} value={`${target.target_type}:${target.id}`}>{target.target_type.replaceAll('_', ' ')} · {target.label}</option>)}</select></label><label><input type="checkbox" name="acknowledgment_required" defaultChecked /> Require recipient acknowledgment</label><label>Optional acknowledgment due date<input name="due_at" type="date" /></label><button className="button button-primary" disabled={busy}>Issue exact-version distribution</button></form>}</div>}
            {!!version.workflow_tasks.length && <div className="lifecycle-actions"><h4>My workflow tasks</h4>{version.workflow_tasks.map((task) => <form key={task.id} onSubmit={(event) => workflowDecision(event, document, version, task.id)}><strong>{task.stage_name}</strong><small>{task.action_type.replaceAll('_', ' ')} · {task.role}{task.organizational_unit ? ` · ${task.organizational_unit}` : ' · Institution scope'}</small><textarea name="comment" rows={2} maxLength={4000} placeholder="Reason required for return or resolving findings" /><div>{task.decisions.filter((decision) => decision !== 'returned_for_revision').map((decision) => <button key={decision} className="button button-secondary" type="submit" name="decision_type" value={decision} disabled={busy}>{decision.replaceAll('_', ' ')}</button>)}<button className="button button-secondary" type="submit" name="decision_type" value="returned_for_revision" disabled={busy}>Return for revision</button></div></form>)}</div>}
            {version.files.map((file) => <a key={file.id} href={`/api/document-files/${file.id}/download`}>{file.original_filename}<small>{(file.byte_size / 1048576).toFixed(2)} MiB · SHA-256 {file.sha256}</small></a>)}{registry.create_units.some((unit) => unit.id === document.owning_unit_id) && (version.status === "draft" || version.status === "returned_for_revision") && <details className="version-upload"><summary>Replace current files and rerun checks</summary><form onSubmit={(event) => replaceFiles(event, document, version)} encType="multipart/form-data"><label>Corrected files (PDF, DOCX, XLSX, JPEG, PNG; up to {uploadLimitLabel} each)<input type="file" name="files[]" multiple required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" /></label><button className="button button-primary" disabled={busy}>Replace current file set</button></form></details>}{!!version.available_actions.length && <div className="lifecycle-actions">{version.available_actions.map((action) => <form key={action} onSubmit={(event) => lifecycleAction(event, document, version)}><input type="hidden" name="action" value={action} />{action === 'return_for_revision' && <textarea name="reason" rows={2} required placeholder="Reason for return" />}{(action === 'propose_effective_date' || action === 'confirm_effective_date') && <label>{action === 'propose_effective_date' ? 'Proposed effective date' : 'Confirm proposed effective date'}<input name="effective_date" type="date" required defaultValue={action === 'confirm_effective_date' ? version.proposed_effective_date ?? '' : ''} /></label>}<button className="button button-secondary" disabled={busy}>{action.replaceAll('_', ' ')}</button></form>)}</div>}</div>)}</details>}
          </td><td>{document.document_type ?? '—'}</td><td>{document.owner ?? '—'}<small>{document.owning_unit ?? ''}</small></td><td><span className="registry-status">{document.status.replaceAll('_', ' ')}</span>
          {registry.create_units.some((unit) => unit.id === document.owning_unit_id) && (document.versions.length === 0 || document.versions[document.versions.length - 1]?.status === "released") && <details className="version-upload"><summary>{document.versions.length ? 'Upload revision' : 'Upload first draft'}</summary><form onSubmit={(event) => uploadVersion(event, document)} encType="multipart/form-data">
            {document.versions.length > 0 && <><label>Revision type<select name="revision_kind" required defaultValue=""><option value="" disabled>Select revision type</option><option value="minor">Minor (+0.1)</option><option value="major">Major (next .0)</option></select></label><label>Change summary<textarea name="change_summary" rows={2} required maxLength={4000} /></label></>}
            <label>Files (PDF, DOCX, XLSX, JPEG, PNG; up to {uploadLimitLabel} each)<input type="file" name="files[]" multiple required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" /></label>
            <button className="button button-primary" disabled={busy}>Save draft version</button>
          </form></details>}
          {registry.can_assign_code && document.status === 'pending_code' && codes && <details className="code-assignment"><summary>Assign official code</summary><form onSubmit={(event) => assignCode(event, document.id)}>
            <label>Service<select name="service_component_id" required value={serviceId} onChange={(event) => setServiceId(event.target.value)}><option value="">Select service</option>{codes.services.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.name}</option>)}</select></label>
            <label>Section<select name="section_component_id" required defaultValue=""><option value="">Select section</option>{scopedCodes.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.name}</option>)}</select></label>
            <label>Unit (optional)<select name="unit_component_id" defaultValue=""><option value="">None</option>{scopedCodes.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.name}</option>)}</select></label>
            <label>Code document type<select name="document_type_component_id" required defaultValue=""><option value="">Select official type</option>{codes.document_types.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.name}</option>)}</select></label>
            <button className="button button-primary" disabled={busy}>Assign code</button>
          </form></details>}
        </td></tr>)}
      </tbody></table></div>}
    </section>
    {registry?.data.some((document) => document.versions.some((version) => version.files.some((file) => file.validation_runs?.length))) && <section className="panel registry-panel"><div className="panel-heading"><div><h2>Submission check results</h2><p>Automatic findings are tied to each exact uploaded file and validation run.</p></div></div>{registry.data.flatMap((document) => document.versions.flatMap((version) => version.files.flatMap((file) => (file.validation_runs ?? []).map((run) => <article className="audit-event" key={`${file.id}-${run.run_number}`}><div><strong>{document.title} · Rev {version.revision_label} · {file.original_filename}</strong><small>Run {run.run_number}: {run.status}{run.code_source ? ` · code ${run.code_source}` : ''}{run.extracted_code ? ` · ${run.extracted_code}` : ''}</small><ul>{run.findings.map((finding, index) => <li key={`${finding.rule}-${index}`}>{finding.rule.replaceAll('_', ' ')}: {finding.result}{finding.detail ? ` · ${typeof finding.detail === 'string' ? finding.detail : JSON.stringify(finding.detail)}` : ''}</li>)}</ul></div></article>))))}</section>}
    {!!registry?.data.some((document) => document.audit_events.length > 0) && <section className="panel registry-panel"><div className="panel-heading"><div><h2>Audit history</h2><p>Recorded actions for documents visible within your scope.</p></div></div><div className="audit-timeline">{registry.data.flatMap((document) => document.audit_events.map((event) => ({ ...event, documentTitle: document.title, code: document.display_code }))).map((event) => <article key={event.id} className="audit-event"><span className="audit-event-mark" /><div><strong>{event.event_type.replaceAll('.', ' ').replaceAll('_', ' ')}</strong><p>{event.documentTitle}{event.code ? ` · ${event.code}` : ''}</p>{event.from_state && event.to_state && <small>{event.from_state.replaceAll('_', ' ')} → {event.to_state.replaceAll('_', ' ')}</small>}{event.reason && <small>{event.reason}</small>}</div><time>{event.occurred_at ? new Date(event.occurred_at).toLocaleString() : ''}<small>{event.actor ?? 'System'}</small></time></article>)}</div></section>}
    {masterEntries && <section className="panel registry-panel"><div className="panel-heading"><div><h2>Master Code Register</h2><p>Official code assignments maintained separately from document metadata.</p></div></div>
      <div className="registry-table-wrap"><table className="registry-table"><thead><tr><th>Base code</th><th>Components</th><th>Document</th><th>Requested</th><th>Assigned by / date</th></tr></thead><tbody>{masterEntries.map((entry) => <tr key={entry.id}><td><strong>{entry.base_code}</strong><small>Rev {entry.document.current_revision_label}</small></td><td>{[entry.service_component, entry.section_component, entry.unit_component, entry.document_type_component].filter(Boolean).map((item) => item!.code).join(' / ')}</td><td>{entry.document.title}<small>{entry.document.owning_unit.name}</small></td><td>{new Date(entry.requested_at).toLocaleDateString()}</td><td>{entry.assigned_by.name}<small>{new Date(entry.assigned_at).toLocaleDateString()}</small></td></tr>)}</tbody></table></div>
    </section>}
  </div>
}
