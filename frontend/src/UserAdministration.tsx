import { useEffect, useState, type FormEvent } from 'react'
import { authRequest } from './api'

type Assignment = {
  id: number
  role_id: number
  organizational_unit_id: number | null
  organizational_unit_name: string | null
  role_name: string
  work_scope: string
  valid_from: string
  valid_until: string | null
}

type ManagedUser = {
  id: number
  name: string
  email: string
  is_active: boolean
  is_system_admin: boolean
  role_assignments: Assignment[]
}

type ManagedRole = { id: number; code: string; name: string }
type OrganizationalUnit = { id: number; name: string; unit_type: string }

export default function UserAdministration() {
  const [users, setUsers] = useState<ManagedUser[]>([])
  const [roles, setRoles] = useState<ManagedRole[]>([])
  const [today, setToday] = useState('')
  const [organizationalUnits, setOrganizationalUnits] = useState<OrganizationalUnit[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  async function load() {
    const result = await authRequest('/admin/access')
    setUsers(result.users)
    setRoles(result.roles)
    setOrganizationalUnits(result.organizational_units)
    setToday(result.effective_date)
  }

  useEffect(() => {
    load().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load user access.'))
      .finally(() => setLoading(false))
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>, action: (data: FormData) => Promise<void>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setSuccess('')
    const form = event.currentTarget
    try {
      await action(new FormData(form))
      form.reset()
      await load()
      setSuccess('Changes saved.')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not save changes.')
    } finally {
      setBusy(false)
    }
  }

  const value = (data: FormData, key: string) => String(data.get(key) ?? '')

  return <div className="user-admin">
    <div className="document-config-intro">
      <p>Create individual accounts and assign the ONP roles defined in its original guidelines. Role assignments carry a work scope and validity dates.</p>
      <p className="document-config-note">Role duties follow the source guideline. System administration stays separate and does not grant document approval authority.</p>
    </div>
    {error && <p className="config-message" role="alert">{error}</p>}
    {success && <p className="config-success" role="status">{success}</p>}

    <div className="config-grid">
      <section className="panel config-panel">
        <div className="panel-heading"><div><h2>Create user account</h2><p>New users sign in with an individual account.</p></div></div>
        <form className="config-form" onSubmit={(event) => submit(event, async (data) => {
          await authRequest('/admin/users', 'POST', {
            name: value(data, 'name'), email: value(data, 'email'), password: value(data, 'password'),
            password_confirmation: value(data, 'password_confirmation'),
          })
        })}>
          <label className="auth-label">Full name<input className="auth-input" name="name" required maxLength={255} /></label>
          <label className="auth-label">Email<input className="auth-input" name="email" type="email" required maxLength={255} /></label>
          <label className="auth-label">Temporary password<input className="auth-input" name="password" type="password" minLength={12} required autoComplete="new-password" /></label>
          <label className="auth-label">Confirm temporary password<input className="auth-input" name="password_confirmation" type="password" minLength={12} required autoComplete="new-password" /></label>
          <button className="button button-primary" disabled={busy}>Create account</button>
        </form>
      </section>

      <section className="panel config-panel">
        <div className="panel-heading"><div><h2>ONP guideline roles</h2><p>Role names and grants come from the approved source material.</p></div></div>
        {roles.length === 0 ? <p className="config-empty">Run the ONP role-permission seeder after database migrations to load these roles.</p> : <div className="config-list">{roles.map((role) => <div className="config-row" key={role.code}><div className="config-row-copy"><strong>{role.name}</strong><span>Source role</span></div></div>)}</div>}
      </section>
    </div>

    <section className="panel user-list-panel">
      <div className="panel-heading"><div><h2>User accounts and work assignments</h2><p>System administrator access is managed separately from document roles.</p></div></div>
      {loading ? <p className="config-loading">Loading accounts…</p> : users.map((user) => <article className="managed-user" key={user.id}>
        {user.is_system_admin ? <div className="managed-user-heading"><div><strong>{user.name}</strong><span>{user.email} · System administrator</span></div></div> : <form className="managed-user-heading" onSubmit={(event) => submit(event, async (data) => {
          await authRequest(`/admin/users/${user.id}`, 'PATCH', {
            name: value(data, 'name'), email: value(data, 'email'), is_active: data.get('is_active') === 'on',
          })
        })}>
          <div className="managed-user-fields">
            <label className="auth-label">Full name<input className="auth-input" name="name" required maxLength={255} defaultValue={user.name} /></label>
            <label className="auth-label">Email<input className="auth-input" name="email" type="email" required maxLength={255} defaultValue={user.email} /></label>
            <label className="config-checkbox"><input type="checkbox" name="is_active" defaultChecked={user.is_active} /> Account active</label>
          </div>
          <button className="config-edit" disabled={busy}>Save account</button>
        </form>}
        {!user.is_system_admin && <>
          <form className="assignment-form" onSubmit={(event) => submit(event, async (data) => {
            await authRequest(`/admin/users/${user.id}/role-assignments`, 'POST', {
              role_id: Number(value(data, 'role_id')), work_scope: value(data, 'work_scope'),
              organizational_unit_id: value(data, 'organizational_unit_id') ? Number(value(data, 'organizational_unit_id')) : null,
              valid_from: value(data, 'valid_from'), valid_until: value(data, 'valid_until') || null,
            })
          })}>
            <label className="auth-label">Role<select className="auth-input" name="role_id" required defaultValue=""><option value="" disabled>Select a role</option>{roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}</select></label>
            <label className="auth-label">Work scope<input className="auth-input" name="work_scope" required maxLength={255} placeholder="Describe the assigned work area" /></label>
            <label className="auth-label">Organizational unit (optional)<select className="auth-input" name="organizational_unit_id" defaultValue=""><option value="">No unit selected</option>{organizationalUnits.map((unit) => <option key={unit.id} value={unit.id}>{unit.name} · {unit.unit_type}</option>)}</select></label>
            <label className="auth-label">Starts<input className="auth-input" name="valid_from" type="date" required defaultValue={today} /></label>
            <label className="auth-label">Ends (optional)<input className="auth-input" name="valid_until" type="date" /></label>
            <button className="button button-secondary" disabled={busy || roles.length === 0}>Assign role</button>
          </form>
          <div className="assignment-list">{user.role_assignments.map((assignment) => <div className="config-row" key={assignment.id}>
            <div className="config-row-copy"><strong>{assignment.role_name} · {assignment.work_scope}</strong><span>{assignment.organizational_unit_name ? `${assignment.organizational_unit_name} · ` : ''}{assignment.valid_from} to {assignment.valid_until ?? 'No end date'}</span></div>
            {!assignment.valid_until && <button className="config-edit" type="button" disabled={busy} onClick={async () => {
              setBusy(true); setError(''); setSuccess('')
              try { await authRequest(`/admin/role-assignments/${assignment.id}`, 'PATCH', { valid_until: today }); await load(); setSuccess('Changes saved.') }
              catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not end assignment.') }
              finally { setBusy(false) }
            }}>End assignment</button>}
          </div>)}</div>
          <form className="reset-form" onSubmit={(event) => submit(event, async (data) => {
            await authRequest(`/admin/users/${user.id}/password`, 'POST', {
              password: value(data, 'password'), password_confirmation: value(data, 'password_confirmation'), reason: value(data, 'reason'),
            })
          })}>
            <label className="auth-label">Temporary password<input className="auth-input" name="password" type="password" minLength={12} required autoComplete="new-password" /></label>
            <label className="auth-label">Confirm password<input className="auth-input" name="password_confirmation" type="password" minLength={12} required autoComplete="new-password" /></label>
            <label className="auth-label">Reset reason<input className="auth-input" name="reason" required maxLength={1000} /></label>
            <button className="config-edit" disabled={busy}>Reset password</button>
          </form>
        </>}
      </article>)}
    </section>
  </div>
}
