import { useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type ScopeType = 'institution' | 'organizational_unit'
type ActionType = 'verification' | 'review' | 'approval' | 'release'
type RoleOption = { id: number; code: string; name: string; scope_types: ScopeType[] }
type StageRole = { role_id: number; scope_type: ScopeType }
type StageDraft = {
  stage_key: string; name: string; purpose: string; stage_order: number
  parallel_group: number | null; action_type: ActionType; is_mandatory: boolean
  required_decisions: 1; roles: StageRole[]
}
type StageRow = {
  id: number; stage_key: string; name: string; purpose: string | null; stage_order: number
  parallel_group: number | null; action_type: string; is_mandatory: boolean
  required_decisions: number; eligible_roles: { id: number; code: string; name: string; pivot: { scope_type: ScopeType } }[]
}
type WorkflowVersion = {
  id: number; version_no: number; status: string; trigger_event: string
  document_type: { id: number; name: string; code: string | null }
  category: { id: number; name: string } | null
  organizational_unit: { id: number; name: string } | null
  stages: StageRow[]; published_at: string | null
}
type WorkflowTemplate = { id: number; template_key: string; name: string; description: string | null; is_active: boolean; versions: WorkflowVersion[] }
type Option = { id: number; name: string; code?: string | null; unit_type?: string }
type WorkflowData = {
  data: WorkflowTemplate[]; document_types: Option[]; categories: Option[]
  organizational_units: Option[]; eligible_roles: Record<ActionType, RoleOption[]>
}

function newStage(order: number): StageDraft {
  return {
    stage_key: `stage_${order}`, name: '', purpose: '', stage_order: order, parallel_group: null,
    action_type: 'review', is_mandatory: true, required_decisions: 1, roles: [],
  }
}

export default function WorkflowConfiguration() {
  const [data, setData] = useState<WorkflowData | null>(null)
  const [templateId, setTemplateId] = useState('')
  const [templateKey, setTemplateKey] = useState('')
  const [templateName, setTemplateName] = useState('')
  const [description, setDescription] = useState('')
  const [documentTypeId, setDocumentTypeId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [unitId, setUnitId] = useState('')
  const [stages, setStages] = useState<StageDraft[]>([newStage(1)])
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  async function load() {
    setData(await authRequest('/admin/workflow-templates') as WorkflowData)
  }

  useEffect(() => {
    let active = true
    void (async () => {
      try {
        await load()
      } catch (reason) {
        if (active) setError(reason instanceof Error ? reason.message : 'Could not load workflow templates.')
      } finally {
        if (active) setLoading(false)
      }
    })()

    return () => { active = false }
  }, [])

  function resetForm() {
    setTemplateId(''); setTemplateKey(''); setTemplateName(''); setDescription('')
    setDocumentTypeId(''); setCategoryId(''); setUnitId(''); setStages([newStage(1)])
  }

  function startVersion(template: WorkflowTemplate) {
    const latest = [...template.versions].sort((left, right) => right.version_no - left.version_no)[0]
    setTemplateId(String(template.id)); setTemplateKey(''); setTemplateName(template.name)
    setDescription(template.description ?? '')
    setDocumentTypeId(String(latest?.document_type.id ?? ''))
    setCategoryId(String(latest?.category?.id ?? ''))
    setUnitId(String(latest?.organizational_unit?.id ?? ''))
    setStages(latest?.stages.map((stage) => ({
      stage_key: stage.stage_key, name: stage.name, purpose: stage.purpose ?? '', stage_order: stage.stage_order,
      parallel_group: stage.parallel_group, action_type: stage.action_type as ActionType,
      is_mandatory: stage.is_mandatory, required_decisions: 1,
      roles: stage.eligible_roles.map((role) => ({ role_id: role.id, scope_type: role.pivot.scope_type })),
    })) ?? [newStage(1)])
    setError(''); setNotice('')
    document.getElementById('workflow-template-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  function updateStage(index: number, changes: Partial<StageDraft>) {
    setStages((current) => current.map((stage, stageIndex) => stageIndex === index ? { ...stage, ...changes } : stage))
  }

  function changeAction(index: number, action_type: ActionType) {
    updateStage(index, { action_type, roles: [] })
  }

  function toggleRole(index: number, role: RoleOption, checked: boolean) {
    const stage = stages[index]
    const scope_type = role.scope_types[0]
    if (!scope_type) return
    const roles = checked
      ? [...stage.roles, { role_id: role.id, scope_type }]
      : stage.roles.filter((assignment) => assignment.role_id !== role.id)
    updateStage(index, { roles })
  }

  function setRoleScope(index: number, roleId: number, scope_type: ScopeType) {
    updateStage(index, { roles: stages[index].roles.map((assignment) => assignment.role_id === roleId ? { ...assignment, scope_type } : assignment) })
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    const payload = {
      ...(!templateId ? { template_key: templateKey.trim() } : {}),
      ...(!templateId ? { name: templateName.trim() } : {}),
      description: description.trim() || null,
      document_type_id: Number(documentTypeId),
      category_id: categoryId ? Number(categoryId) : null,
      organizational_unit_id: unitId ? Number(unitId) : null,
      stages: stages.map((stage) => ({
        ...stage, stage_key: stage.stage_key.trim(), name: stage.name.trim(), purpose: stage.purpose.trim() || null,
      })),
    }
    try {
      const path = templateId ? `/admin/workflow-templates/${templateId}/versions` : '/admin/workflow-templates'
      await authRequest(path, 'POST', payload)
      resetForm(); await load(); setNotice('Draft workflow version saved. It is not used until published.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not save the workflow draft.') }
    finally { setBusy(false) }
  }

  async function publish(versionId: number) {
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/admin/workflow-template-versions/${versionId}/publish`, 'POST', {})
      await load(); setNotice('Workflow version published for new instances.')
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not publish the workflow version.') }
    finally { setBusy(false) }
  }

  async function setTemplateActive(template: WorkflowTemplate, is_active: boolean) {
    setBusy(true); setError(''); setNotice('')
    try {
      await authRequest(`/admin/workflow-templates/${template.id}`, 'PATCH', { is_active })
      await load(); setNotice(`Workflow template ${is_active ? 'activated' : 'deactivated'}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not update the workflow template.') }
    finally { setBusy(false) }
  }

  if (loading) return <section className="panel workflow-config"><p>Loading workflow configuration…</p></section>

  return <section className="workflow-config">
    {error && <p className="registry-message registry-error" role="alert">{error}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    <article className="panel workflow-config-panel" id="workflow-template-form">
      <div className="panel-heading"><div><h2>{templateId ? `New version of ${templateName}` : 'New workflow template'}</h2><p>Only roles with an existing source-defined permission are available for each action.</p></div></div>
      <form className="workflow-form" onSubmit={save}>
        {!templateId && <div className="workflow-form-grid">
          <label>Template key<input value={templateKey} onChange={(event) => setTemplateKey(event.target.value)} maxLength={64} pattern="[A-Za-z0-9_-]+" required /></label>
          <label>Template name<input value={templateName} onChange={(event) => setTemplateName(event.target.value)} maxLength={180} required /></label>
        </div>}
        <label>Description<textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={2} maxLength={4000} /></label>
        <div className="workflow-form-grid">
          <label>Document type<select value={documentTypeId} onChange={(event) => setDocumentTypeId(event.target.value)} required><option value="">Select document type</option>{data?.document_types.map((item) => <option key={item.id} value={item.id}>{item.code ? `${item.code} · ` : ''}{item.name}</option>)}</select></label>
          <label>Category scope<select value={categoryId} onChange={(event) => setCategoryId(event.target.value)}><option value="">Any category</option>{data?.categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
          <label>Organizational scope<select value={unitId} onChange={(event) => setUnitId(event.target.value)}><option value="">Institution-wide</option>{data?.organizational_units.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        </div>

        <div className="workflow-stage-heading"><div><h3>Stages</h3><p>Review, approval, and release remain distinct actions.</p></div><button className="button button-secondary" type="button" onClick={() => setStages((current) => [...current, newStage(current.reduce((highest, stage) => Math.max(highest, stage.stage_order), 0) + 1)])}>Add stage</button></div>
        <p className="workflow-help">Parallel groups, optional stages, recommendations, and quorum above one can be saved as drafts, but cannot be published while the ONP source leaves these rules undefined.</p>
        <div className="workflow-stages">{stages.map((stage, index) => {
          const options = data?.eligible_roles[stage.action_type] ?? []
          return <article className="workflow-stage-card" key={`${stage.stage_key}-${index}`}>
            <div className="workflow-stage-card-heading"><strong>Stage {index + 1}</strong><button className="text-button" type="button" disabled={stages.length === 1} onClick={() => setStages((current) => current.filter((_, stageIndex) => stageIndex !== index))}>Remove</button></div>
            <div className="workflow-form-grid">
              <label>Stage key<input value={stage.stage_key} onChange={(event) => updateStage(index, { stage_key: event.target.value })} maxLength={64} required /></label>
              <label>Stage name<input value={stage.name} onChange={(event) => updateStage(index, { name: event.target.value })} maxLength={180} required /></label>
              <label>Order<input type="number" min="1" max="65535" value={stage.stage_order} onChange={(event) => updateStage(index, { stage_order: Number(event.target.value) })} required /></label>
                <label>Action<select value={stage.action_type} onChange={(event) => changeAction(index, event.target.value as ActionType)}><option value="verification">KMT submission verification</option><option value="review">Review</option><option value="approval">Final approval</option><option value="release">Controlled release</option></select></label>
              <label>Parallel group (optional)<input type="number" min="1" max="65535" value={stage.parallel_group ?? ''} onChange={(event) => updateStage(index, { parallel_group: event.target.value ? Number(event.target.value) : null })} /></label>
            </div>
            <label>Purpose<textarea value={stage.purpose} onChange={(event) => updateStage(index, { purpose: event.target.value })} rows={2} maxLength={4000} /></label>
            <label className="workflow-mandatory"><input type="checkbox" checked={stage.is_mandatory} onChange={(event) => updateStage(index, { is_mandatory: event.target.checked })} /> Mandatory stage</label>
            <fieldset className="workflow-role-list"><legend>Eligible ONP roles</legend>
              {!options.length ? <p className="workflow-help">No source role currently has permission for this action.</p> : options.map((role) => {
                const selected = stage.roles.find((assignment) => assignment.role_id === role.id)
                return <div className="workflow-role-option" key={role.id}>
                  <label><input type="checkbox" checked={!!selected} onChange={(event) => toggleRole(index, role, event.target.checked)} />{role.name}</label>
                  {selected && <select aria-label={`${role.name} scope`} value={selected.scope_type} onChange={(event) => setRoleScope(index, role.id, event.target.value as ScopeType)}>{role.scope_types.map((scope) => <option key={scope} value={scope}>{scope === 'institution' ? 'Institution' : 'Assigned organizational unit'}</option>)}</select>}
                </div>
              })}
            </fieldset>
            <p className="workflow-help">Quorum is fixed at one while D-017 remains open. Recommendations and Forms Committee pre-coding are not configured by this submission-triggered workflow.</p>
          </article>
        })}</div>
        <div className="config-form-actions"><button className="button button-primary" type="submit" disabled={busy}>{busy ? 'Saving…' : 'Save draft version'}</button>{templateId && <button className="button button-secondary" type="button" onClick={resetForm}>Cancel</button>}</div>
      </form>
    </article>

    <article className="panel workflow-config-panel">
      <div className="panel-heading"><div><h2>Workflow templates</h2><p>Published versions are immutable; new changes require a new version.</p></div><span className="setup-progress">{data?.data.length ?? 0}</span></div>
      {!data?.data.length ? <p className="config-empty">No workflow templates configured.</p> : <div className="workflow-template-list">{data.data.map((template) => <section className="workflow-template-card" key={template.id}>
        <div className="workflow-template-heading"><div><strong>{template.name}</strong><span>{template.template_key} · {template.is_active ? 'Active' : 'Inactive'}</span>{template.description && <p>{template.description}</p>}</div><div className="workflow-template-actions"><button className="button button-secondary" type="button" disabled={busy || !template.is_active} onClick={() => startVersion(template)}>New version</button><button className="text-button" type="button" disabled={busy} onClick={() => void setTemplateActive(template, !template.is_active)}>{template.is_active ? 'Deactivate' : 'Activate'}</button></div></div>
        {template.versions.map((version) => <div className="workflow-version-row" key={version.id}>
          <div><strong>Version {version.version_no}</strong><span>{version.status} · {version.document_type.code ? `${version.document_type.code} · ` : ''}{version.document_type.name}{version.category ? ` · ${version.category.name}` : ''}{version.organizational_unit ? ` · ${version.organizational_unit.name}` : ' · Institution-wide'}</span>
            <ol>{version.stages.map((stage) => <li key={stage.id}>{stage.stage_order}. {stage.name} ({stage.action_type}) — {stage.eligible_roles.map((role) => `${role.name} / ${role.pivot.scope_type}`).join(', ')}</li>)}</ol>
          </div>
          {version.status === 'draft' && <button className="button button-secondary" type="button" disabled={busy} onClick={() => void publish(version.id)}>Publish</button>}
        </div>)}
      </section>)}</div>}
    </article>
  </section>
}
