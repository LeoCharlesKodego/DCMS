import { useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type DocumentType = {
  id: number
  code: string | null
  name: string
  description: string | null
  is_active: boolean
  approval_flow_profile: 'source_defined' | 'phase4b_manual_sop_guideline' | 'deferred'
}

type DocumentCategory = {
  id: number
  parent_id: number | null
  name: string
  description: string | null
  is_active: boolean
}

type TypeForm = {
  code: string
  name: string
  description: string
  is_active: boolean
  approval_flow_profile: 'source_defined' | 'phase4b_manual_sop_guideline' | 'deferred'
}

type CategoryForm = {
  parent_id: string
  name: string
  description: string
  is_active: boolean
}
type SettingHistory = { key: string; old_value: string | null; new_value: string; changed_by: string; changed_at: string }

const emptyType: TypeForm = { code: '', name: '', description: '', is_active: true, approval_flow_profile: 'source_defined' }
const emptyCategory: CategoryForm = { parent_id: '', name: '', description: '', is_active: true }

function categoryDepth(category: DocumentCategory, categories: DocumentCategory[]): number {
  let depth = 0
  let parentId = category.parent_id
  const visited = new Set<number>([category.id])

  while (parentId !== null && !visited.has(parentId)) {
    visited.add(parentId)
    depth += 1
    parentId = categories.find((item) => item.id === parentId)?.parent_id ?? null
  }

  return depth
}

function orderedCategories(categories: DocumentCategory[]): DocumentCategory[] {
  const children = new Map<number | null, DocumentCategory[]>()
  categories.forEach((category) => {
    const siblings = children.get(category.parent_id) ?? []
    siblings.push(category)
    children.set(category.parent_id, siblings)
  })

  const ordered: DocumentCategory[] = []
  const addChildren = (parentId: number | null) => {
    const siblings = (children.get(parentId) ?? []).sort((left, right) => left.name.localeCompare(right.name))
    siblings.forEach((category) => {
      ordered.push(category)
      addChildren(category.id)
    })
  }

  addChildren(null)

  return ordered
}

export default function DocumentConfiguration() {
  const [types, setTypes] = useState<DocumentType[]>([])
  const [categories, setCategories] = useState<DocumentCategory[]>([])
  const [typeForm, setTypeForm] = useState<TypeForm>(emptyType)
  const [categoryForm, setCategoryForm] = useState<CategoryForm>(emptyCategory)
  const [editingTypeId, setEditingTypeId] = useState<number | null>(null)
  const [editingCategoryId, setEditingCategoryId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [sameKmtVerifyRelease, setSameKmtVerifyRelease] = useState(false)
  const [settingHistory, setSettingHistory] = useState<SettingHistory[]>([])
  const categoryTree = orderedCategories(categories)

  async function refresh() {
    const [typeResult, categoryResult] = await Promise.all([
      authRequest('/configuration/document-types'),
      authRequest('/configuration/document-categories'),
    ])
    setTypes(typeResult.data)
    setCategories(categoryResult.data)
  }

  useEffect(() => {
    let active = true
    Promise.all([
      authRequest('/configuration/document-types'),
      authRequest('/configuration/document-categories'),
      authRequest('/configuration/phase4b-settings'),
    ])
      .then(([typeResult, categoryResult, phaseSettings]) => {
        if (active) {
          setTypes(typeResult.data)
          setCategories(categoryResult.data)
          setSameKmtVerifyRelease(phaseSettings.same_kmt_verify_release)
          setSettingHistory(phaseSettings.history ?? [])
        }
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof Error ? requestError.message : 'Could not load configuration.')
      })
      .finally(() => { if (active) setLoading(false) })

    return () => { active = false }
  }, [])

  async function saveSameAccountSetting() {
    setSaving(true); setError(''); setNotice('')
    try {
      const result = await authRequest('/admin/settings/same-kmt-verify-release', 'PUT', { enabled: sameKmtVerifyRelease })
      setSameKmtVerifyRelease(result.same_kmt_verify_release)
      const settings = await authRequest('/configuration/phase4b-settings')
      setSettingHistory(settings.history ?? [])
      setNotice('Same-account KMT verify/release setting saved and logged.')
    } catch (requestError) { setError(requestError instanceof Error ? requestError.message : 'Could not save the KMT separation setting.') }
    finally { setSaving(false) }
  }

  async function saveType(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')
    setNotice('')

    try {
      const body = {
        code: typeForm.code === '' ? null : typeForm.code,
        name: typeForm.name,
        description: typeForm.description === '' ? null : typeForm.description,
        is_active: typeForm.is_active,
        approval_flow_profile: typeForm.approval_flow_profile,
      }
      await authRequest(
        editingTypeId === null ? '/configuration/document-types' : `/configuration/document-types/${editingTypeId}`,
        editingTypeId === null ? 'POST' : 'PATCH',
        body,
      )
      await refresh()
      setTypeForm(emptyType)
      setEditingTypeId(null)
      setNotice('Document type saved.')
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'Could not save document type.')
    } finally {
      setSaving(false)
    }
  }

  async function saveCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')
    setNotice('')

    try {
      const body = {
        parent_id: categoryForm.parent_id === '' ? null : Number(categoryForm.parent_id),
        name: categoryForm.name,
        description: categoryForm.description === '' ? null : categoryForm.description,
        is_active: categoryForm.is_active,
      }
      await authRequest(
        editingCategoryId === null ? '/configuration/document-categories' : `/configuration/document-categories/${editingCategoryId}`,
        editingCategoryId === null ? 'POST' : 'PATCH',
        body,
      )
      await refresh()
      setCategoryForm(emptyCategory)
      setEditingCategoryId(null)
      setNotice('Document category saved.')
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'Could not save document category.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="document-config" aria-label="Document type and category configuration">
      <div className="document-config-intro">
        <span className="placeholder-kicker">CONFIGURABLE REGISTRY VALUES</span>
        <p>Manage the type labels and category tree used by the document registry. These settings do not grant review or approval authority.</p>
        <p className="document-config-note">Document type codes are stored as entered. Code rules and uniqueness remain open under D-019; no ONP codes are preloaded.</p>
      </div>

      <section className="panel config-panel"><div className="panel-heading"><div><h2>KMT verify and release separation</h2><p>Default is OFF. Changes record the administrator and timestamp.</p></div></div><label className="config-checkbox"><input type="checkbox" checked={sameKmtVerifyRelease} onChange={(event) => setSameKmtVerifyRelease(event.target.checked)} /> Allow one KMT account to verify and release the same document</label><button className="button button-secondary" type="button" disabled={saving} onClick={() => void saveSameAccountSetting()}>Save setting</button><details><summary>Setting version history</summary><ul>{settingHistory.filter((entry) => entry.key === 'same_kmt_verify_release').map((entry, index) => <li key={`${entry.changed_at}-${index}`}>{entry.old_value ?? 'unset'} → {entry.new_value === '1' ? 'ON' : 'OFF'} · {entry.changed_by} · {new Date(entry.changed_at).toLocaleString()}</li>)}</ul></details></section>

      {error && <p className="auth-error config-message" role="alert">{error}</p>}
      {notice && <p className="config-success" role="status">{notice}</p>}
      {loading ? <p className="config-loading">Loading document configuration…</p> : (
        <div className="config-grid">
          <article className="panel config-panel">
            <div className="panel-heading"><div><h2>Document types</h2><p>Names and optional source codes</p></div><span className="setup-progress">{types.length}</span></div>
            <form className="config-form" onSubmit={(event) => void saveType(event)}>
              <label className="auth-label" htmlFor="type-name">Name</label>
              <input className="auth-input" id="type-name" value={typeForm.name} onChange={(event) => setTypeForm({ ...typeForm, name: event.target.value })} maxLength={255} required />
              <label className="auth-label" htmlFor="type-code">Code (optional)</label>
              <input className="auth-input" id="type-code" value={typeForm.code} onChange={(event) => setTypeForm({ ...typeForm, code: event.target.value })} maxLength={255} />
              <label className="auth-label" htmlFor="type-description">Description</label>
              <textarea className="auth-input config-textarea" id="type-description" value={typeForm.description} onChange={(event) => setTypeForm({ ...typeForm, description: event.target.value })} maxLength={2000} />
              <label className="auth-label" htmlFor="type-flow">Approval flow profile</label>
              <select className="auth-input" id="type-flow" value={typeForm.approval_flow_profile} onChange={(event) => setTypeForm({ ...typeForm, approval_flow_profile: event.target.value as TypeForm['approval_flow_profile'] })}>
                <option value="source_defined">Source-defined review chain</option>
                <option value="phase4b_manual_sop_guideline">Phase 4B manual / SOP / guideline</option>
                <option value="deferred">Deferred pending routing decision</option>
              </select>
              <label className="config-checkbox"><input type="checkbox" checked={typeForm.is_active} onChange={(event) => setTypeForm({ ...typeForm, is_active: event.target.checked })} /> Active</label>
              <div className="config-form-actions"><button className="button button-primary" type="submit" disabled={saving}>{saving ? 'Saving…' : editingTypeId === null ? 'Add type' : 'Save type'}</button>{editingTypeId !== null && <button className="button button-secondary" type="button" onClick={() => { setEditingTypeId(null); setTypeForm(emptyType) }}>Cancel</button>}</div>
            </form>
            <div className="config-list" aria-label="Configured document types">
              {types.length === 0 ? <p className="config-empty">No document types configured.</p> : types.map((type) => (
                <div className="config-row" key={type.id}>
                  <div className="config-row-copy"><strong>{type.name}</strong><span>{type.code || 'No code recorded'} · {type.approval_flow_profile.replaceAll('_', ' ')} · {type.is_active ? 'Active' : 'Inactive'}</span></div>
                  <button className="config-edit" type="button" onClick={() => { setEditingTypeId(type.id); setTypeForm({ code: type.code ?? '', name: type.name, description: type.description ?? '', is_active: type.is_active, approval_flow_profile: type.approval_flow_profile ?? 'source_defined' }); setError(''); setNotice('') }}>Edit</button>
                </div>
              ))}
            </div>
          </article>

          <article className="panel config-panel">
            <div className="panel-heading"><div><h2>Document categories</h2><p>Build a configurable parent and child structure</p></div><span className="setup-progress">{categories.length}</span></div>
            <form className="config-form" onSubmit={(event) => void saveCategory(event)}>
              <label className="auth-label" htmlFor="category-name">Name</label>
              <input className="auth-input" id="category-name" value={categoryForm.name} onChange={(event) => setCategoryForm({ ...categoryForm, name: event.target.value })} maxLength={255} required />
              <label className="auth-label" htmlFor="category-parent">Parent category</label>
              <select className="auth-input" id="category-parent" value={categoryForm.parent_id} onChange={(event) => setCategoryForm({ ...categoryForm, parent_id: event.target.value })}>
                <option value="">Top level</option>
                {categoryTree.filter((category) => category.is_active && category.id !== editingCategoryId).map((category) => (
                  <option key={category.id} value={category.id}>{'— '.repeat(categoryDepth(category, categories))}{category.name}</option>
                ))}
              </select>
              <label className="auth-label" htmlFor="category-description">Description</label>
              <textarea className="auth-input config-textarea" id="category-description" value={categoryForm.description} onChange={(event) => setCategoryForm({ ...categoryForm, description: event.target.value })} maxLength={2000} />
              <label className="config-checkbox"><input type="checkbox" checked={categoryForm.is_active} onChange={(event) => setCategoryForm({ ...categoryForm, is_active: event.target.checked })} /> Active</label>
              <div className="config-form-actions"><button className="button button-primary" type="submit" disabled={saving}>{saving ? 'Saving…' : editingCategoryId === null ? 'Add category' : 'Save category'}</button>{editingCategoryId !== null && <button className="button button-secondary" type="button" onClick={() => { setEditingCategoryId(null); setCategoryForm(emptyCategory) }}>Cancel</button>}</div>
            </form>
            <div className="config-list" aria-label="Configured document categories">
              {categories.length === 0 ? <p className="config-empty">No document categories configured.</p> : categoryTree.map((category) => (
                <div className="config-row" key={category.id}>
                  <div className="config-row-copy" style={{ paddingLeft: `${categoryDepth(category, categories) * 15}px` }}><strong>{category.name}</strong><span>{category.is_active ? 'Active' : 'Inactive'}</span></div>
                  <button className="config-edit" type="button" onClick={() => { setEditingCategoryId(category.id); setCategoryForm({ parent_id: category.parent_id?.toString() ?? '', name: category.name, description: category.description ?? '', is_active: category.is_active }); setError(''); setNotice('') }}>Edit</button>
                </div>
              ))}
            </div>
          </article>
        </div>
      )}
    </section>
  )
}
