import { useCallback, useEffect, useRef, useState, type FormEvent, type MouseEvent, type ReactNode } from 'react'
import { authRequest } from './api'
import DocumentConfiguration from './DocumentConfiguration'
import DocumentRegistry from './DocumentRegistry'
import UserAdministration from './UserAdministration'
import InstitutionConfiguration from './InstitutionConfiguration'
import WorkflowConfiguration from './WorkflowConfiguration'
import DistributionInbox from './DistributionInbox'
import DocumentMigration from './DocumentMigration'
import ExternalReferences from './ExternalReferences'
import OperationalRecords from './OperationalRecords'
import NotificationCenter from './NotificationCenter'
import ReportsDashboard from './ReportsDashboard'
import './App.css'

type AuthUser = {
  id: number
  name: string
  email: string
  is_system_admin: boolean
  must_change_password: boolean
  roles: { name: string; code?: string; work_scope: string; organizational_unit?: string | null }[]
  permissions: string[]
  has_distributions: boolean
}

type PageId =
  | 'overview'
  | 'tasks'
  | 'registry'
  | 'workflows'
  | 'distribution'
  | 'migration'
  | 'external-references'
  | 'records'
  | 'reports'
  | 'organization'
  | 'access'
  | 'settings'
  | 'document-configuration'

type NavItem = {
  id: PageId
  label: string
  icon: string
}

const mainNavigation: NavItem[] = [
  { id: 'overview', label: 'Overview', icon: 'grid' },
  { id: 'tasks', label: 'My tasks', icon: 'inbox' },
  { id: 'registry', label: 'Document registry', icon: 'files' },
  { id: 'workflows', label: 'Review & approval', icon: 'check' },
  { id: 'distribution', label: 'Distribution', icon: 'send' },
  { id: 'migration', label: 'Document migration', icon: 'upload' },
  { id: 'external-references', label: 'External references', icon: 'files' },
  { id: 'records', label: 'Records & retention', icon: 'archive' },
  { id: 'reports', label: 'Reports & audit', icon: 'chart' },
]

const adminNavigation: NavItem[] = [
  { id: 'organization', label: 'Institution setup', icon: 'building' },
  { id: 'access', label: 'Users & access', icon: 'users' },
  { id: 'document-configuration', label: 'Document types & categories', icon: 'files' },
  { id: 'settings', label: 'Workflow configuration', icon: 'sliders' },
]

const pageTitles: Record<PageId, string> = {
  overview: 'Overview',
  tasks: 'My tasks',
  registry: 'Document registry',
  workflows: 'Review & approval',
  distribution: 'Distribution',
  migration: 'Document migration',
  'external-references': 'External references',
  records: 'Records & retention',
  reports: 'Reports & audit',
  organization: 'Institution setup',
  access: 'Users & access',
  settings: 'Workflow configuration',
  'document-configuration': 'Document types & categories',
}

function Icon({ name, size = 18 }: { name: string; size?: number }) {
  const common = {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true as const,
  }

  const paths: Record<string, ReactNode> = {
    grid: <><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" /></>,
    inbox: <><path d="M4 4.5h16l1 11.5v3.5H3V16z" /><path d="M3.5 14h5l1.5 2h4l1.5-2h5" /></>,
    files: <><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5A1.5 1.5 0 0 1 7.5 3.5z" /><path d="M14 4v4h4M9 13h6M9 16.5h6" /></>,
    check: <><path d="M8 4h11a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H8" /><path d="m3.5 12 2.5 2.5 5-5M3.5 5.5H6M3.5 19H6" /></>,
    send: <><path d="m21 3-7.2 18-3.4-7.4L3 10.2 21 3z" /><path d="M10.4 13.6 21 3" /></>,
    upload: <><path d="M12 15V3.5m0 0L8 7.5m4-4 4 4" /><path d="M5 13.5v5A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5v-5" /></>,
    archive: <><path d="M4 7h16l-1 13H5L4 7zM3 3.5h18V7H3z" /><path d="M9 11h6" /></>,
    chart: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></>,
    building: <><path d="M4 20.5V5a1.5 1.5 0 0 1 1.5-1.5h9A1.5 1.5 0 0 1 16 5v15.5M2.5 20.5h19" /><path d="M8 7.5h2M8 11h2M8 14.5h2M18 10.5h1a1.5 1.5 0 0 1 1.5 1.5v8.5M12 20.5v-3h3v3" /></>,
    users: <><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20" /><circle cx="9.5" cy="7.5" r="3.5" /><path d="M17 4.5a3.5 3.5 0 0 1 0 6.8M21 20v-1.5a4 4 0 0 0-3-3.9" /></>,
    sliders: <><path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h8M16 18h4" /><circle cx="16" cy="6" r="2" /><circle cx="8" cy="12" r="2" /><circle cx="14" cy="18" r="2" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></>,
    calendar: <><rect x="3.5" y="5" width="17" height="16" rx="2" /><path d="M7.5 3v4M16.5 3v4M3.5 9.5h17" /></>,
    search: <><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></>,
    bell: <><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></>,
    chevron: <path d="m9 18 6-6-6-6" />,
    plus: <><path d="M12 5v14M5 12h14" /></>,
    shield: <><path d="M12 3 20 6v5c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-3z" /><path d="m9 12 2 2 4-4" /></>,
    spark: <><path d="m12 3 1.5 6.5L20 12l-6.5 1.5L12 20l-1.5-6.5L4 12l6.5-2.5L12 3z" /><path d="m19 16 .7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7L19 16z" /></>,
    close: <><path d="m6 6 12 12M18 6 6 18" /></>,
  }

  return <svg {...common}>{paths[name] ?? paths.files}</svg>
}

function App() {
  const [activePage, setActivePage] = useState<PageId>('overview')
  const navDialogRef = useRef<HTMLDialogElement>(null)
  const navTriggerRef = useRef<HTMLButtonElement>(null)
  const [isNavOpen, setNavOpen] = useState(false)
  const [authUser, setAuthUser] = useState<AuthUser | null>(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [authError, setAuthError] = useState('')
  const [authBusy, setAuthBusy] = useState(false)
  const [loginErrors, setLoginErrors] = useState<{ email?: string; password?: string }>({})
  const [showPassword, setShowPassword] = useState(false)
  const [dashboardWidgets, setDashboardWidgets] = useState<{ key: string; label: string; count: number | null }[]>([])
  const [dashboardState, setDashboardState] = useState<'loading' | 'ready' | 'error'>('loading')
  const pageTitle = pageTitles[activePage]
  const permissions = new Set(authUser?.permissions ?? [])
  const canSeeTasks = ['document.create_revise', 'document.submission.verify', 'document.review.division_head', 'document.review.verify', 'document.master_copy.approve.manual_sop_guideline', 'records.manage'].some((permission) => permissions.has(permission))
  const visibleMainNavigation = mainNavigation.filter((item) => {
    if (item.id === 'overview') return true
    if (item.id === 'tasks' || item.id === 'workflows') return canSeeTasks
    if (item.id === 'registry') return permissions.has('document.registry.view')
    if (item.id === 'distribution') return permissions.has('document.distribute') || Boolean(authUser?.has_distributions)
    if (item.id === 'migration' || item.id === 'external-references') return permissions.has('document.register')
    if (item.id === 'records') return permissions.has('records.manage')
    if (item.id === 'reports') return permissions.has('document.register') || permissions.has('records.manage')
    return false
  })

  const loadDashboard = useCallback(() => {
    setDashboardState('loading')
    authRequest('/dashboard')
      .then((data) => { setDashboardWidgets(data.widgets ?? []); setDashboardState('ready') })
      .catch(() => { setDashboardWidgets([]); setDashboardState('error') })
  }, [])

  // A new session always starts on the overview: one place sets the user, the page,
  // and the dashboard load, so no effect has to react to the user changing.
  const adoptSession = useCallback((user: AuthUser) => {
    setAuthUser(user)
    setActivePage('overview')
    loadDashboard()
  }, [loadDashboard])

  useEffect(() => {
    let active = true

    authRequest('/auth/user')
      .then((result) => { if (active) adoptSession(result.user) })
      .catch((error: unknown) => {
        if (active && !(error instanceof Error && error.message === 'Unauthenticated.')) {
          setAuthError('Could not reach the DCMS sign-in service. Start the Laravel server and refresh this page.')
        }
      })
      .finally(() => { if (active) setAuthLoading(false) })

    return () => { active = false }
  }, [adoptSession])

  useEffect(() => {
    document.title = authUser ? `${pageTitle} · ONP DCMS` : 'Sign in · ONP DCMS'
  }, [pageTitle, authUser])

  function validateLoginField(name: 'email' | 'password', value: string) {
    if (name === 'email') {
      if (!value.trim()) return 'Enter your email address.'
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) return 'Enter an email address in the format name@example.org.'
      return undefined
    }

    return value ? undefined : 'Enter your password.'
  }

  function applyLoginValidation(input: HTMLInputElement) {
    const name = input.name as 'email' | 'password'
    const message = validateLoginField(name, input.value)
    setLoginErrors((current) => ({ ...current, [name]: message }))
    return message
  }

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const formElement = event.currentTarget
    const emailInput = formElement.elements.namedItem('email') as HTMLInputElement
    const passwordInput = formElement.elements.namedItem('password') as HTMLInputElement
    const errors = {
      email: validateLoginField('email', emailInput.value),
      password: validateLoginField('password', passwordInput.value),
    }
    setLoginErrors(errors)

    if (errors.email || errors.password) {
      const firstInvalid = errors.email ? emailInput : passwordInput
      firstInvalid.focus()
      return
    }

    setAuthBusy(true)
    setAuthError('')
    const form = new FormData(formElement)

    try {
      const result = await authRequest('/auth/login', 'POST', {
        email: String(form.get('email') ?? ''),
        password: String(form.get('password') ?? ''),
      })
      setLoginErrors({})
      adoptSession(result.user)
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Sign-in failed.')
    } finally {
      setAuthBusy(false)
    }
  }

  async function handlePasswordChange(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setAuthBusy(true)
    setAuthError('')
    const form = new FormData(event.currentTarget)

    try {
      const result = await authRequest('/auth/password', 'POST', {
        current_password: String(form.get('current_password') ?? ''),
        password: String(form.get('password') ?? ''),
        password_confirmation: String(form.get('password_confirmation') ?? ''),
      })
      setAuthUser(result.user)
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Password change failed.')
    } finally {
      setAuthBusy(false)
    }
  }

  async function handleLogout() {
    setAuthBusy(true)
    try {
      await authRequest('/auth/logout', 'POST', {})
      setAuthUser(null)
      setAuthError('')
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Sign-out failed.')
    } finally {
      setAuthBusy(false)
    }
  }

  if (authLoading) {
    return <div className="auth-shell"><div className="auth-card"><div className="brand-mark" aria-hidden="true"><span>D</span></div><p>Connecting to ONP DCMS…</p></div></div>
  }

  if (!authUser) {
    return (
      <div className="auth-shell">
        <form className="auth-card" onSubmit={handleLogin} noValidate>
          <div className="brand-lockup auth-brand"><div className="brand-mark" aria-hidden="true"><span>D</span></div><div className="brand-copy"><h1 className="brand-name">DCMS</h1></div></div>
          <label className="auth-label" htmlFor="login-email">Email</label>
          <input
            className="auth-input"
            id="login-email"
            name="email"
            type="email"
            autoComplete="username"
            autoCorrect="off"
            autoCapitalize="none"
            spellCheck={false}
            enterKeyHint="next"
            required
            aria-invalid={loginErrors.email ? true : undefined}
            aria-describedby={loginErrors.email ? 'login-email-error' : undefined}
            onBlur={(event) => applyLoginValidation(event.currentTarget)}
            onInput={(event) => { if (loginErrors.email) applyLoginValidation(event.currentTarget) }}
          />
          {loginErrors.email && <p className="field-error" id="login-email-error" role="alert">{loginErrors.email}</p>}
          <label className="auth-label" htmlFor="login-password">Password</label>
          <div className="password-field">
            <input
              className="auth-input password-input"
              id="login-password"
              name="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              enterKeyHint="done"
              required
              aria-invalid={loginErrors.password ? true : undefined}
              aria-describedby={loginErrors.password ? 'login-password-error' : undefined}
              onBlur={(event) => applyLoginValidation(event.currentTarget)}
              onInput={(event) => { if (loginErrors.password) applyLoginValidation(event.currentTarget) }}
            />
            <button
              className="password-toggle"
              type="button"
              aria-pressed={showPassword}
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              onClick={() => setShowPassword((visible) => !visible)}
            >
              {showPassword ? 'Hide' : 'Show'}
            </button>
          </div>
          {loginErrors.password && <p className="field-error" id="login-password-error" role="alert">{loginErrors.password}</p>}
          {authError && <p className="auth-error" role="alert">{authError}</p>}
          <button className="button button-primary auth-submit" type="submit" disabled={authBusy}>{authBusy ? 'Signing in…' : 'Sign in'}</button>
        </form>
      </div>
    )
  }

  if (authUser.must_change_password) {
    return (
      <div className="auth-shell">
        <form className="auth-card" onSubmit={handlePasswordChange}>
          <div className="brand-lockup auth-brand"><div className="brand-mark" aria-hidden="true"><span>D</span></div><div className="brand-copy"><span className="brand-name">DCMS</span></div></div>
          <h1>Choose a new password.</h1>
          <p className="page-description">Your administrator provided a temporary password. Replace it before continuing.</p>
          <label className="auth-label" htmlFor="current-password">Temporary password</label>
          <input className="auth-input" id="current-password" name="current_password" type="password" autoComplete="current-password" required />
          <label className="auth-label" htmlFor="new-password">New password (at least 12 characters)</label>
          <input className="auth-input" id="new-password" name="password" type="password" autoComplete="new-password" minLength={12} required />
          <label className="auth-label" htmlFor="confirm-password">Confirm new password</label>
          <input className="auth-input" id="confirm-password" name="password_confirmation" type="password" autoComplete="new-password" minLength={12} required />
          {authError && <p className="auth-error" role="alert">{authError}</p>}
          <button className="button button-primary auth-submit" type="submit" disabled={authBusy}>{authBusy ? 'Updating…' : 'Update password'}</button>
        </form>
      </div>
    )
  }

  function openNav() {
    const dialog = navDialogRef.current
    if (!dialog) return
    dialog.showModal()
    setNavOpen(true)
  }

  function closeNav() {
    navDialogRef.current?.close()
  }

  function handleNavBackdrop(event: MouseEvent<HTMLDialogElement>) {
    if (event.target === event.currentTarget) closeNav()
  }

  function navigate(page: PageId) {
    setActivePage(page)
    closeNav()
  }

  function renderNav(items: NavItem[]) {
    return items.map((item) => (
      <button
        className={`nav-item ${activePage === item.id ? 'is-active' : ''}`}
        key={item.id}
        onClick={() => navigate(item.id)}
        type="button"
        aria-current={activePage === item.id ? 'page' : undefined}
      >
        <Icon name={item.icon} />
        <span>{item.label}</span>
      </button>
    ))
  }

  function renderSidebar(user: AuthUser, inDialog = false) {
    return (
      <>
        <div className="brand-lockup">
          <div className="brand-mark" aria-hidden="true"><span>D</span></div>
          <div className="brand-copy">
            <span className="brand-name">DCMS</span>
            <span className="brand-caption">Document control</span>
          </div>
          {inDialog && (
            <button className="icon-button nav-close" type="button" onClick={() => navDialogRef.current?.close()} aria-label="Close navigation">
              <Icon name="close" size={18} />
            </button>
          )}
        </div>

        <div className="institution-switcher">
          <div className="institution-avatar"><Icon name="building" size={17} /></div>
          <div className="institution-copy">
            <span className="institution-label">INSTITUTION</span>
            <strong>Ospital ng Palawan</strong>
            <span className="institution-status">Setup required</span>
          </div>
        </div>

        <nav className="side-navigation" aria-label="Main navigation">
          <span className="nav-section-label">WORKSPACE</span>
          {renderNav(visibleMainNavigation)}
          {user.is_system_admin && (
            <>
              <span className="nav-section-label admin-label">ADMINISTRATION</span>
              {renderNav(adminNavigation)}
            </>
          )}
        </nav>

        <div className="sidebar-bottom">
          <div className="offline-card">
            <div className="offline-icon"><Icon name="shield" size={16} /></div>
            <div>
              <strong>Local network</strong>
              <span>No internet required</span>
            </div>
            <span className="status-dot" aria-hidden="true" />
          </div>
          <div className="sidebar-user">
            <div className="avatar avatar-user" aria-hidden="true">{user.name.slice(0, 1).toUpperCase()}</div>
            <div className="sidebar-user-copy">
              <strong>{user.name}</strong>
              <span>{user.is_system_admin ? 'System administrator' : user.roles.map((role) => role.name).join(', ') || 'DCMS user'}</span>
            </div>
            <button className="sidebar-signout" type="button" onClick={() => void handleLogout()} disabled={authBusy}>
              {authBusy ? 'Signing out…' : 'Sign out'}
            </button>
          </div>
        </div>
      </>
    )
  }

  return (
    <div className="app-shell">
      <aside className="sidebar surface-glass">
        {renderSidebar(authUser)}
      </aside>

      <dialog
        ref={navDialogRef}
        id="nav-dialog"
        tabIndex={-1}
        className="nav-dialog surface-glass"
        aria-label="Navigation"
        onClick={handleNavBackdrop}
        onClose={() => {
          setNavOpen(false)
          // WebKit does not restore focus to the invoker on dialog close.
          requestAnimationFrame(() => {
            const active = document.activeElement
            if (!active || active === document.body || navDialogRef.current?.contains(active)) navTriggerRef.current?.focus()
          })
        }}
      >
        {renderSidebar(authUser, true)}
      </dialog>

      <main className="main-area">
        <header className="topbar surface-glass">
          <div className="topbar-left">
            <button ref={navTriggerRef} className="mobile-menu icon-button" type="button" onClick={openNav} aria-label="Open navigation" aria-haspopup="dialog" aria-controls="nav-dialog" aria-expanded={isNavOpen}>
              <span className="menu-lines" aria-hidden="true"><i /><i /><i /></span>
            </button>
          </div>
          <div className="topbar-actions">
            <NotificationCenter />
            <span className="top-user-avatar" aria-hidden="true">{authUser.name.slice(0, 1).toUpperCase()}</span>
            <span className="sr-only">Signed in as {authUser.name}</span>
          </div>
        </header>

        <div className="page-content">
          <section className="welcome-row">
            <h1>{pageTitle}</h1>
            <div className="welcome-actions">
              {permissions.has('document.registry.view') && <button className="button button-secondary" type="button" onClick={() => navigate('registry')}><Icon name="search" size={16} /> Find a document</button>}
              {authUser.is_system_admin && <button className="button button-primary" type="button" onClick={() => navigate('organization')}><Icon name="plus" size={17} /> Set up institution</button>}
            </div>
          </section>

          {activePage === 'registry' && !permissions.has('document.registry.view') ? <section className="panel"><h2>Document registry</h2><p className="registry-empty">You don’t have access to the document registry.</p></section> : visibleMainNavigation.some((item) => item.id === activePage) && (activePage === 'registry' || activePage === 'tasks' || activePage === 'workflows') ? <DocumentRegistry view={activePage === 'workflows' ? 'workflows' : 'registry'} /> : visibleMainNavigation.some((item) => item.id === activePage) && activePage === 'distribution' ? <DistributionInbox /> : visibleMainNavigation.some((item) => item.id === activePage) && activePage === 'migration' ? <DocumentMigration /> : visibleMainNavigation.some((item) => item.id === activePage) && activePage === 'external-references' ? <ExternalReferences /> : visibleMainNavigation.some((item) => item.id === activePage) && activePage === 'records' ? <OperationalRecords /> : visibleMainNavigation.some((item) => item.id === activePage) && activePage === 'reports' ? <ReportsDashboard /> : activePage === 'organization' && authUser.is_system_admin ? <InstitutionConfiguration /> : activePage === 'access' && authUser.is_system_admin ? <UserAdministration /> : activePage === 'document-configuration' && authUser.is_system_admin ? <DocumentConfiguration /> : activePage === 'settings' && authUser.is_system_admin ? <WorkflowConfiguration /> : activePage === 'overview' ? (
            <>
              <section className="summary-strip" aria-labelledby="summary-strip-heading" aria-busy={dashboardState === 'loading'}>
                <h2 className="sr-only" id="summary-strip-heading">Summary for your role</h2>
                {dashboardState === 'error' ? (
                  <p className="summary-message" role="alert">Summary counts could not be loaded. <button className="text-button" type="button" onClick={loadDashboard}>Retry</button></p>
                ) : dashboardState === 'ready' && dashboardWidgets.length === 0 ? (
                  <p className="summary-message">No summary counts are available for this role yet.</p>
                ) : (
                  <div className="summary-cells">
                    {(dashboardState === 'loading' ? Array.from({ length: 4 }, (_, index) => ({ key: `pending-${index}`, label: '', count: null })) : dashboardWidgets).map((widget) => (
                      <article className="summary-cell" key={widget.key}>
                        <span className="summary-label">{widget.label || 'Loading count'}</span>
                        <strong className="summary-figure">{widget.count ?? '—'}</strong>
                        <small className="summary-note">{widget.count === 0 ? 'No records yet' : widget.count === null ? (dashboardState === 'loading' ? 'Loading' : 'Unavailable') : ''}</small>
                      </article>
                    ))}
                  </div>
                )}
              </section>

              <section className="dashboard-grid">
                <article className="panel activity-panel">
                  <div className="panel-heading">
                    <div><h2>Recent activity</h2><p>Keep track of important document events</p></div>
                    {(permissions.has('document.register') || permissions.has('records.manage')) && <button className="text-button" type="button" onClick={() => navigate('reports')}>View audit log <Icon name="chevron" size={14} /></button>}
                  </div>
                  <div className="empty-activity">
                    <div className="empty-illustration"><div className="empty-doc"><span /><span /><span /></div><div className="empty-clock"><Icon name="clock" size={17} /></div></div>
                    <strong>No activity recorded yet.</strong>
                  </div>
                </article>

                {authUser.is_system_admin && <article className="panel setup-panel">
                  <div className="panel-heading">
                    <div><h2>Set up ONP</h2><p>Prepare the workspace in a few steps</p></div>
                    <div className="setup-progress"><span>0</span> / 3</div>
                  </div>
                  <div className="setup-steps">
                    <button className="setup-step" type="button" onClick={() => navigate('organization')}>
                      <span className="step-number">01</span><span className="step-copy"><strong>Configure Ospital ng Palawan</strong><small>Set ONP identity and organizational units</small></span><Icon name="chevron" size={16} />
                    </button>
                    <button className="setup-step" type="button" onClick={() => navigate('access')}>
                      <span className="step-number">02</span><span className="step-copy"><strong>Set up roles and access</strong><small>Assign people within their proper scope</small></span><Icon name="chevron" size={16} />
                    </button>
                    <button className="setup-step" type="button" onClick={() => navigate('settings')}>
                      <span className="step-number">03</span><span className="step-copy"><strong>Define approval workflows</strong><small>Configure reviewers and authorities</small></span><Icon name="chevron" size={16} />
                    </button>
                  </div>
                  <button className="setup-footer" type="button" onClick={() => navigate('organization')}>Continue setup <Icon name="chevron" size={15} /></button>
                </article>}
              </section>

              <section className="bottom-grid">
                {canSeeTasks && <article className="panel task-panel">
                  <div className="panel-heading">
                    <div><h2>Assigned to me</h2><p>Your review and approval queue</p></div>
                    <button className="text-button" type="button" onClick={() => navigate('tasks')}>Open tasks <Icon name="chevron" size={14} /></button>
                  </div>
                  <div className="task-empty"><div className="task-empty-icon"><Icon name="inbox" size={20} /></div><span>No tasks assigned yet.</span></div>
                </article>}
                {canSeeTasks && <article className="panel principle-panel">
                  <div className="principle-top"><div className="principle-icon"><Icon name="shield" size={18} /></div><span>DCMS PRINCIPLE</span></div>
                  <blockquote>“A file upload is not an approval.”</blockquote>
                  <p>Every review, decision, effective date, and controlled release is recorded as a separate step.</p>
                  <button className="principle-link" type="button" onClick={() => navigate('workflows')}>Explore document lifecycle <Icon name="chevron" size={14} /></button>
                </article>}
              </section>
            </>
          ) : (
            <section className="module-placeholder panel">
              <div className="placeholder-icon"><Icon name={mainNavigation.concat(adminNavigation).find((item) => item.id === activePage)?.icon ?? 'files'} size={22} /></div>
              <p>This module is not connected to live records yet.</p>
              <button className="button button-secondary" type="button" onClick={() => navigate('overview')}>Return to overview</button>
            </section>
          )}

        </div>
      </main>
    </div>
  )
}

export default App
