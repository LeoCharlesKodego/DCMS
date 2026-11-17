import { useEffect, useState, type FormEvent } from 'react'
import { authRequest, authUpload } from './api'

type Institution = {
  name: string
  short_name: string | null
  contact_details: string | null
  facility_type: string | null
  timezone: string | null
  status: string
  logo_path: string | null
}

type Unit = {
  id: number
  parent_id: number | null
  parent_name: string | null
  name: string
  unit_type: string
  is_active: boolean
}

export default function InstitutionConfiguration() {
  const [institution, setInstitution] = useState<Institution | null>(null)
  const [units, setUnits] = useState<Unit[]>([])
  const [editingId, setEditingId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  async function load() {
    const [profileResult, unitResult] = await Promise.all([
      authRequest('/admin/institution'), authRequest('/admin/organizational-units'),
    ])
    setInstitution(profileResult.data)
    setUnits(unitResult.data)
  }

  useEffect(() => {
    load().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load institution settings.'))
      .finally(() => setLoading(false))
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>, action: (form: FormData) => Promise<void>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setSuccess('')
    const form = event.currentTarget
    try {
      await action(new FormData(form))
      form.reset()
      setEditingId(null)
      await load()
      setSuccess('Changes saved.')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not save changes.')
    } finally {
      setBusy(false)
    }
  }

  const value = (form: FormData, key: string) => String(form.get(key) ?? '')

  if (loading || !institution) return <div className="config-loading">{loading ? 'Loading institution settings…' : error}</div>

  return <div className="institution-admin">
    <div className="document-config-intro">
      <p>Maintain the institution profile and its configurable organizational hierarchy. Unit names and levels are editable and are not seeded from the unresolved ONP code lists.</p>
      <p className="document-config-note">Organizational structure does not automatically determine document approval authority.</p>
    </div>
    {error && <p className="config-message" role="alert">{error}</p>}
    {success && <p className="config-success" role="status">{success}</p>}

    <section className="panel config-panel institution-profile">
      <div className="panel-heading"><div><h2>Institution profile</h2><p>Single-institution configuration for this ONP installation.</p></div></div>
      <form className="config-form institution-form" onSubmit={(event) => submit(event, async (form) => {
        const upload = new FormData()
        for (const key of ['name', 'short_name', 'contact_details', 'facility_type', 'timezone', 'status']) upload.set(key, value(form, key))
        const logo = form.get('logo')
        if (logo instanceof File && logo.size > 0) upload.set('logo', logo)
        upload.set('_method', 'PUT')
        const result = await authUpload('/admin/institution', upload)
        setInstitution(result.data)
      })}>
        <label className="auth-label">Institution name<input className="auth-input" name="name" required maxLength={255} defaultValue={institution.name} /></label>
        <label className="auth-label">Short name<input className="auth-input" name="short_name" maxLength={80} defaultValue={institution.short_name ?? ''} /></label>
        <label className="auth-label">Facility type<input className="auth-input" name="facility_type" maxLength={120} defaultValue={institution.facility_type ?? ''} /></label>
        <label className="auth-label">Timezone<input className="auth-input" name="timezone" placeholder="e.g. Asia/Manila" defaultValue={institution.timezone ?? ''} /></label>
        <label className="auth-label">Contact details<textarea className="auth-input config-textarea" name="contact_details" maxLength={2000} defaultValue={institution.contact_details ?? ''} /></label>
        <label className="auth-label">Institution status<select className="auth-input" name="status" defaultValue={institution.status}><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        <label className="auth-label">Logo (PNG, JPG, or WebP; max 2 MB)<input className="auth-input" name="logo" type="file" accept="image/png,image/jpeg,image/webp" /></label>
        {institution.logo_path && <img className="institution-logo-preview" src={`/api/admin/institution/logo?version=${encodeURIComponent(institution.logo_path)}`} alt="Institution logo" />}
        <button className="button button-primary" disabled={busy}>Save institution</button>
      </form>
    </section>

    <div className="config-grid institution-units-grid">
      <section className="panel config-panel">
        <div className="panel-heading"><div><h2>Add organizational unit</h2><p>Create a division, department, section, unit, office, committee, or other named level.</p></div></div>
        <UnitForm units={units} busy={busy} onSubmit={(event) => submit(event, async (form) => {
          await authRequest('/admin/organizational-units', 'POST', {
            parent_id: value(form, 'parent_id') ? Number(value(form, 'parent_id')) : null,
            name: value(form, 'name'), unit_type: value(form, 'unit_type'),
          })
        })} />
      </section>

      <section className="panel config-panel">
        <div className="panel-heading"><div><h2>Organizational structure</h2><p>{units.length} configured {units.length === 1 ? 'unit' : 'units'}</p></div></div>
        {units.length === 0 ? <p className="config-empty">No organizational units have been added.</p> : <div className="config-list">{units.map((unit) => editingId === unit.id ? (
          <form className="unit-edit-form" key={unit.id} onSubmit={(event) => submit(event, async (form) => {
            await authRequest(`/admin/organizational-units/${unit.id}`, 'PATCH', {
              parent_id: value(form, 'parent_id') ? Number(value(form, 'parent_id')) : null,
              name: value(form, 'name'), unit_type: value(form, 'unit_type'), is_active: form.get('is_active') === 'on',
            })
          })}>
            <label className="auth-label">Name<input className="auth-input" name="name" required maxLength={180} defaultValue={unit.name} /></label>
            <label className="auth-label">Type<input className="auth-input" name="unit_type" required maxLength={80} defaultValue={unit.unit_type} /></label>
            <label className="auth-label">Parent<select className="auth-input" name="parent_id" defaultValue={unit.parent_id ?? ''}><option value="">No parent</option>{units.filter((candidate) => candidate.id !== unit.id && candidate.is_active).map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name} · {candidate.unit_type}</option>)}</select></label>
            <label className="config-checkbox"><input type="checkbox" name="is_active" defaultChecked={unit.is_active} /> Active</label>
            <div className="config-form-actions"><button className="config-edit" disabled={busy}>Save</button><button className="config-edit" type="button" onClick={() => setEditingId(null)}>Cancel</button></div>
          </form>
        ) : <div className="config-row" key={unit.id}>
          <div className="config-row-copy"><strong>{unit.name}</strong><span>{unit.unit_type}{unit.parent_name ? ` · under ${unit.parent_name}` : ' · top level'} · {unit.is_active ? 'Active' : 'Inactive'}</span></div>
          <button className="config-edit" type="button" onClick={() => setEditingId(unit.id)}>Edit</button>
        </div>)}</div>}
      </section>
    </div>
  </div>
}

function UnitForm({ units, busy, onSubmit }: { units: Unit[]; busy: boolean; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  return <form className="config-form" onSubmit={onSubmit}>
    <label className="auth-label">Unit name<input className="auth-input" name="name" required maxLength={180} /></label>
    <label className="auth-label">Level or type<input className="auth-input" name="unit_type" required maxLength={80} placeholder="Service, department, unit…" /></label>
    <label className="auth-label">Parent unit<select className="auth-input" name="parent_id" defaultValue=""><option value="">No parent</option>{units.filter((unit) => unit.is_active).map((unit) => <option key={unit.id} value={unit.id}>{unit.name} · {unit.unit_type}</option>)}</select></label>
    <button className="button button-primary" disabled={busy}>Add unit</button>
  </form>
}
