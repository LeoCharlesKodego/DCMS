import { useState, type ReactNode } from 'react'
import './App.css'

type PageId =
  | 'overview'
  | 'tasks'
  | 'registry'
  | 'workflows'
  | 'distribution'
  | 'migration'
  | 'records'
  | 'reports'
  | 'organization'
  | 'access'
  | 'settings'

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
  { id: 'records', label: 'Records & retention', icon: 'archive' },
  { id: 'reports', label: 'Reports & audit', icon: 'chart' },
]

const adminNavigation: NavItem[] = [
  { id: 'organization', label: 'Institution setup', icon: 'building' },
  { id: 'access', label: 'Users & access', icon: 'users' },
  { id: 'settings', label: 'Workflow configuration', icon: 'sliders' },
]

const pageCopy: Record<PageId, { title: string; eyebrow: string; description: string }> = {
  overview: {
    title: 'Good morning',
    eyebrow: 'Workspace overview',
    description: 'A clear view of controlled documents, decisions, and what needs attention.',
  },
  tasks: {
    title: 'My tasks',
    eyebrow: 'Your workspace',
    description: 'Reviews and actions assigned to you will appear here.',
  },
  registry: {
    title: 'Document registry',
    eyebrow: 'Controlled documents',
    description: 'Find and manage the institution’s controlled document records and versions.',
  },
  workflows: {
    title: 'Review & approval',
    eyebrow: 'Document lifecycle',
    description: 'Review decisions, approvals, and releases are tracked as separate actions.',
  },
  distribution: {
    title: 'Distribution',
    eyebrow: 'Controlled access',
    description: 'Track the exact released version shared with each team or recipient.',
  },
  migration: {
    title: 'Document migration',
    eyebrow: 'Bring existing documents in',
    description: 'Preserve source files and metadata while authorized staff verify each item.',
  },
  records: {
    title: 'Records & retention',
    eyebrow: 'Operational records',
    description: 'Manage approved retention schedules, holds, and documented disposition decisions.',
  },
  reports: {
    title: 'Reports & audit',
    eyebrow: 'Traceability',
    description: 'Authorized reporting and a protected history of important system events.',
  },
  organization: {
    title: 'ONP setup',
    eyebrow: 'Foundation',
    description: 'Set up Ospital ng Palawan and its configurable organizational structure.',
  },
  access: {
    title: 'Users & access',
    eyebrow: 'Foundation',
    description: 'Manage user access by role, organizational scope, and assignment validity.',
  },
  settings: {
    title: 'Workflow configuration',
    eyebrow: 'Foundation',
    description: 'Configure workflow templates and authority without hard-coding approval chains.',
  },
}

const metrics = [
  { label: 'Controlled documents', icon: 'files', tone: 'blue' },
  { label: 'Awaiting your review', icon: 'clock', tone: 'amber' },
  { label: 'Reviews coming due', icon: 'calendar', tone: 'violet' },
  { label: 'Acknowledgments pending', icon: 'users', tone: 'teal' },
]

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
  }

  return <svg {...common}>{paths[name] ?? paths.files}</svg>
}

function App() {
  const [activePage, setActivePage] = useState<PageId>('overview')
  const [isSidebarOpen, setSidebarOpen] = useState(false)
  const [isNoticeVisible, setNoticeVisible] = useState(true)
  const currentPage = pageCopy[activePage]

  function navigate(page: PageId) {
    setActivePage(page)
    setSidebarOpen(false)
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
        {item.id === 'tasks' && <span className="nav-count">—</span>}
      </button>
    ))
  }

  return (
    <div className="app-shell">
      <aside className={`sidebar ${isSidebarOpen ? 'sidebar-open' : ''}`}>
        <div className="brand-lockup">
          <div className="brand-mark" aria-hidden="true"><span>D</span></div>
          <div className="brand-copy">
            <span className="brand-name">DCMS</span>
            <span className="brand-caption">DOCUMENT CONTROL</span>
          </div>
          <button className="mobile-close icon-button" type="button" onClick={() => setSidebarOpen(false)} aria-label="Close navigation">
            <Icon name="chevron" />
          </button>
        </div>

        <div className="institution-switcher">
          <div className="institution-avatar"><Icon name="building" size={17} /></div>
          <div className="institution-copy">
            <span className="institution-label">ONP WORKSPACE</span>
            <strong>Ospital ng Palawan setup needed</strong>
          </div>
          <Icon name="chevron" size={15} />
        </div>

        <nav className="side-navigation" aria-label="Main navigation">
          <span className="nav-section-label">WORKSPACE</span>
          {renderNav(mainNavigation)}

          <span className="nav-section-label admin-label">ADMINISTRATION</span>
          {renderNav(adminNavigation)}
        </nav>

        <div className="sidebar-bottom">
          <div className="offline-card">
            <div className="offline-icon"><Icon name="shield" size={16} /></div>
            <div>
              <strong>Private by design</strong>
              <span>Built for your local network</span>
            </div>
            <span className="status-dot" aria-label="Local mode" />
          </div>
          <div className="sidebar-user">
            <div className="avatar avatar-user" aria-hidden="true">—</div>
            <div className="sidebar-user-copy">
              <strong>No user signed in</strong>
              <span>Authentication not connected</span>
            </div>
            <button className="icon-button user-menu-button" type="button" aria-label="User menu is not available yet" title="User menu is not available yet">
              <span className="more-dots">···</span>
            </button>
          </div>
        </div>
      </aside>

      {isSidebarOpen && <button className="sidebar-backdrop" type="button" aria-label="Close navigation" onClick={() => setSidebarOpen(false)} />}

      <main className="main-area">
        <header className="topbar">
          <div className="topbar-left">
            <button className="mobile-menu icon-button" type="button" onClick={() => setSidebarOpen(true)} aria-label="Open navigation">
              <span className="menu-lines"><i /><i /><i /></span>
            </button>
            <div className="breadcrumb"><span>Workspace</span><Icon name="chevron" size={13} /><strong>{currentPage.eyebrow}</strong></div>
          </div>
          <div className="topbar-actions">
            <div className="environment-pill"><span /> DEVELOPMENT PREVIEW</div>
            <button className="top-icon-button" type="button" aria-label="Notifications are not available yet" title="Notifications are not available yet"><Icon name="bell" size={19} /><i /></button>
            <div className="top-user-avatar" aria-label="Authentication not connected">—</div>
          </div>
        </header>

        <div className="page-content">
          <section className="welcome-row">
            <div>
              <div className="eyebrow"><span className="eyebrow-line" />{currentPage.eyebrow}</div>
              <h1>{activePage === 'overview' ? <>Your documents,<br className="title-break" /> <em>under control.</em></> : currentPage.title}</h1>
              <p className="page-description">{currentPage.description}</p>
            </div>
            <div className="welcome-actions">
              <button className="button button-secondary" type="button" onClick={() => navigate('registry')}><Icon name="search" size={16} /> Find a document</button>
              <button className="button button-primary" type="button" onClick={() => navigate('organization')}><Icon name="plus" size={17} /> Set up ONP</button>
            </div>
          </section>

          {isNoticeVisible && <div className="preview-notice" role="status">
            <div className="preview-notice-icon"><Icon name="spark" size={17} /></div>
            <p><strong>Foundation preview</strong><span>This workspace has no live records yet. Data and actions will appear as the DCMS modules are connected.</span></p>
            <button type="button" className="notice-dismiss" onClick={() => setNoticeVisible(false)} aria-label="Dismiss preview notice">×</button>
          </div>}

          {activePage === 'overview' ? (
            <>
              <section className="metric-grid" aria-label="Document control metrics">
                {metrics.map((metric) => (
                  <article className="metric-card" key={metric.label}>
                    <div className={`metric-icon metric-${metric.tone}`}><Icon name={metric.icon} size={18} /></div>
                    <div className="metric-copy">
                      <span>{metric.label}</span>
                      <strong>—</strong>
                      <small>Available when records are connected</small>
                    </div>
                  </article>
                ))}
              </section>

              <section className="dashboard-grid">
                <article className="panel activity-panel">
                  <div className="panel-heading">
                    <div><h2>Recent activity</h2><p>Keep track of important document events</p></div>
                    <button className="text-button" type="button" onClick={() => navigate('reports')}>View audit log <Icon name="chevron" size={14} /></button>
                  </div>
                  <div className="empty-activity">
                    <div className="empty-illustration"><div className="empty-doc"><span /><span /><span /></div><div className="empty-clock"><Icon name="clock" size={17} /></div></div>
                    <strong>No activity to show yet</strong>
                    <span>Document events will appear here as your team starts using the system.</span>
                  </div>
                </article>

                <article className="panel setup-panel">
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
                </article>
              </section>

              <section className="bottom-grid">
                <article className="panel task-panel">
                  <div className="panel-heading">
                    <div><h2>Assigned to me</h2><p>Your review and approval queue</p></div>
                    <button className="text-button" type="button" onClick={() => navigate('tasks')}>Open tasks <Icon name="chevron" size={14} /></button>
                  </div>
                  <div className="task-empty"><div className="task-empty-icon"><Icon name="inbox" size={20} /></div><span>No pending tasks</span><small>New assignments will be listed here.</small></div>
                </article>
                <article className="panel principle-panel">
                  <div className="principle-top"><div className="principle-icon"><Icon name="shield" size={18} /></div><span>DCMS PRINCIPLE</span></div>
                  <blockquote>“A file upload is not an approval.”</blockquote>
                  <p>Every review, decision, effective date, and controlled release is recorded as a separate step.</p>
                  <button className="principle-link" type="button" onClick={() => navigate('workflows')}>Explore document lifecycle <Icon name="chevron" size={14} /></button>
                </article>
              </section>
            </>
          ) : (
            <section className="module-placeholder panel">
              <div className="placeholder-icon"><Icon name={mainNavigation.concat(adminNavigation).find((item) => item.id === activePage)?.icon ?? 'files'} size={22} /></div>
              <span className="placeholder-kicker">MODULE FOUNDATION</span>
              <h2>{currentPage.title} is being prepared</h2>
              <p>This section is part of the approved DCMS scope. Its records and controls will be connected as the foundation is implemented.</p>
              <div className="placeholder-rule" />
              <div className="placeholder-note"><Icon name="shield" size={16} /><span>Institution settings, authority rules, and source codes will remain configurable.</span></div>
              <button className="button button-secondary" type="button" onClick={() => navigate('overview')}>Return to overview</button>
            </section>
          )}

          <footer className="page-footer">
            <span>DCMS <b>·</b> Institutional Document Control</span>
            <span><i /> Local-network ready <b>·</b> Foundation preview</span>
          </footer>
        </div>
      </main>
    </div>
  )
}

export default App
