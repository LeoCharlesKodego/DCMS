import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { signIn } from './support/session'

// Automated accessibility pass over the real screens, driven by the running
// Laravel API and the fictional sample documents. Contrast sampling is covered
// by the shell and notification suites; this one looks for what colour checks
// miss: accessible names, landmark and heading structure, form labelling,
// focus order, and a visible focus indicator on every stop.

const here = dirname(fileURLToPath(import.meta.url))
const axeSource = readFileSync(resolve(here, '../node_modules/axe-core/axe.min.js'), 'utf8')

// Rebuild the fictional sample documents so the suite starts from the same register
// every run, including after another spec added a register request.
test.beforeAll(() => {
  seedSampleDocuments()
})

type Violation = { id: string; impact?: string; help: string; nodes: string[] }

async function audit(page: Page, context: string) {
  await page.addScriptTag({ content: axeSource })
  const violations = await page.evaluate(async () => {
    const axe = (window as unknown as { axe: { run: (root: Document, options: unknown) => Promise<{ violations: Violation[] }> } }).axe
    const result = await axe.run(document, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
    })
    return result.violations.map((violation) => ({
      id: violation.id,
      impact: violation.impact,
      help: violation.help,
      nodes: violation.nodes.map((node) => node.target.join(' ')),
    }))
  })

  if (violations.length > 0) console.log(`${context}: ${JSON.stringify(violations)}`)
  return violations
}

type FocusState = {
  tag: string
  name: string
  outlineStyle: string
  outlineWidth: string
  boxShadow: string
  inDetail: boolean
  inRegisterScroller: boolean
  isRegisterScroller: boolean
  rowTitle: string
}

async function focusState(page: Page): Promise<FocusState> {
  return page.evaluate(() => {
    const element = document.activeElement as HTMLElement | null
    if (!element || element === document.body) {
      return { tag: 'BODY', name: '', outlineStyle: '', outlineWidth: '', boxShadow: '', inDetail: false, inRegisterScroller: false, isRegisterScroller: false, rowTitle: '' }
    }
    const style = getComputedStyle(element)
    // Labels wrap the fields in this application, so read the wrapping label's text.
    const labelled = (element as HTMLInputElement).labels?.[0]?.textContent ?? ''
    const name = element.getAttribute('aria-label') || labelled || element.textContent || ''
    return {
      tag: element.tagName,
      name: name.trim().replace(/\s+/g, ' ').slice(0, 80),
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      boxShadow: style.boxShadow,
      inDetail: element.closest('[id^="document-detail-"]') !== null,
      inRegisterScroller: element.closest('[aria-labelledby="registry-table-caption"]') !== null,
      isRegisterScroller: element.classList.contains('registry-table-wrap'),
      rowTitle: (element.closest('tr')?.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 90),
    }
  })
}

/** Presses Tab until the predicate holds, recording every stop it passed. */
async function tabUntil(page: Page, limit: number, done: (state: FocusState) => boolean) {
  const stops: FocusState[] = []
  for (let press = 0; press < limit; press += 1) {
    await page.keyboard.press('Tab')
    const state = await focusState(page)
    stops.push(state)
    if (done(state)) return { state, stops }
  }
  return { state: stops[stops.length - 1], stops }
}

function expectVisibleRing(state: FocusState, context: string) {
  const outlined = state.outlineStyle !== 'none' && Number.parseFloat(state.outlineWidth) > 0
  const shadowed = state.boxShadow !== 'none' && state.boxShadow !== ''
  expect(
    outlined || shadowed,
    `no visible focus indicator on ${state.tag} "${state.name}" (${context}); computed outline: ${state.outlineStyle} ${state.outlineWidth}, box-shadow: ${state.boxShadow}`,
  ).toBe(true)
}

test('the sign-in screen has no automated accessibility violations', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1, name: 'DCMS' })).toBeVisible()
  expect(await audit(page, 'sign-in'), 'sign-in').toEqual([])
})

const screens = [
  'Overview',
  'My tasks',
  'Document registry',
  'Review & approval',
  'Distribution',
  'Document migration',
  'External references',
  'Records & retention',
  'Reports & audit',
  'Institution setup',
  'Users & access',
  'Document types & categories',
  'Workflow configuration',
]

test('every signed-in screen has no automated accessibility violations', async ({ page }) => {
  test.setTimeout(120_000)
  await signIn(page)

  const findings: Record<string, Violation[]> = {}
  for (const screen of screens) {
    await page.getByRole('button', { name: screen, exact: true }).click()
    await expect(page.getByRole('heading', { level: 1, name: screen })).toBeVisible()
    // Let the screen's own loading state settle so the audit sees real content.
    await page.waitForFunction(() => document.querySelector('[aria-busy="true"]') === null)
    const violations = await audit(page, screen)
    if (violations.length > 0) findings[screen] = violations
  }

  await page.getByRole('button', { name: 'Document registry', exact: true }).click()
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()
  const releasedRow = page.getByRole('table', { name: /Document control register/ }).getByRole('row').filter({ hasText: 'Sample — Released procedure' }).first()
  await releasedRow.locator('button[aria-controls^="document-detail-"]').click()
  await expect(page.locator('[id^="document-detail-"]').first()).toBeVisible()
  const detailViolations = await audit(page, 'registry detail row')
  if (detailViolations.length > 0) findings['registry detail row'] = detailViolations

  expect(findings, JSON.stringify(findings, null, 2)).toEqual({})
})

test('a top-of-scroller control reached by Tab is not covered by the sticky top bar', async ({ page }) => {
  await signIn(page)
  await page.getByRole('button', { name: 'Document registry' }).click()
  const table = page.getByRole('table', { name: /Document control register/ })
  await expect(table).toBeVisible()

  // A long detail row fills the register's own scroller, so the test navigates a
  // register with something to scroll. Nothing here is clicked except the row toggle.
  const row = table.getByRole('row').filter({ hasText: 'Sample — Released procedure' }).first()
  const toggle = row.locator('button[aria-controls^="document-detail-"]')
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.focus()
    await page.keyboard.press('Enter')
  }
  const scroller = page.getByRole('region', { name: /Document control register/ })
  await expect(scroller).toBeVisible()

  // Start at the last control in the register, then park the page so the scroller's
  // own top edge sits behind the sticky top bar: the state a pointer user meets after
  // scrolling a long detail row.
  const controls = scroller.locator('button, summary, input, select, textarea, a')
  const last = controls.nth((await controls.count()) - 1)
  await last.scrollIntoViewIfNeeded()
  await last.focus()
  await page.evaluate(() => {
    const region = document.querySelector('[aria-labelledby="registry-table-caption"]') as HTMLElement
    window.scrollTo(0, region.getBoundingClientRect().top + window.scrollY - 8)
  })

  // Plain Tab (backwards) is the only navigation used from here. Moving backwards is
  // the direction where a browser aligns the target to the top edge of the viewport,
  // which is exactly where the sticky top bar sits.
  let stop = await focusState(page)
  for (let press = 0; press < 20 && !stop.isRegisterScroller; press += 1) {
    await page.keyboard.press('Shift+Tab')
    stop = await focusState(page)
  }
  expect(stop.isRegisterScroller, 'the register scroller was not reachable by Tab after scrolling').toBe(true)

  const geometry = await page.evaluate(() => {
    const bar = document.querySelector('.topbar') as HTMLElement
    const focused = document.activeElement as HTMLElement
    const barBox = bar.getBoundingClientRect()
    const focusBox = focused.getBoundingClientRect()
    return {
      barBottom: barBox.bottom,
      focusTop: focusBox.top,
      focusBottom: focusBox.bottom,
      tag: focused.tagName,
      name: (focused.getAttribute('aria-label') || focused.textContent || '').trim().slice(0, 40),
    }
  })
  expect(
    geometry.focusTop,
    `${geometry.tag} "${geometry.name}" sits under the sticky top bar: ${JSON.stringify(geometry)}`,
  ).toBeGreaterThanOrEqual(geometry.barBottom - 1)
  expect(geometry.focusBottom, 'the focused control is above the viewport').toBeGreaterThan(geometry.focusTop)
})

test('keyboard only: sign in, open the register, and reach the detail forms', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByLabel('Email')).toBeVisible()

  // Sign-in order: email, password, show-password toggle, submit.
  await page.keyboard.press('Tab')
  const email = await focusState(page)
  expect(email.tag).toBe('INPUT')
  const emailName = await page.locator(':focus').ariaSnapshot()
  expect(emailName, 'email field has no accessible name').toContain('Email')
  expectVisibleRing(email, 'sign-in email')

  const credentials = localCredentials()
  await page.keyboard.type(credentials.email)
  await page.keyboard.press('Tab')

  const password = await focusState(page)
  expect(password.tag, 'second Tab must land on the password field').toBe('INPUT')
  const passwordName = await page.locator(':focus').ariaSnapshot()
  expect(passwordName, 'password field has no accessible name').toContain('Password')
  expectVisibleRing(password, 'sign-in password')
  await page.keyboard.type(credentials.password)

  await page.keyboard.press('Tab')
  const toggle = await focusState(page)
  expect(toggle.name, 'show-password toggle has no accessible name').toBe('Show password')
  expectVisibleRing(toggle, 'show-password toggle')

  await page.keyboard.press('Tab')
  const submit = await focusState(page)
  expect(submit.name).toBe('Sign in')
  expectVisibleRing(submit, 'sign-in submit')
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()

  // Sidebar navigation must be reachable without a pointer.
  const nav = await tabUntil(page, 20, (state) => state.name === 'Document registry')
  expect(nav.state.name, 'Document registry is not reachable by Tab').toBe('Document registry')
  expectVisibleRing(nav.state, 'document registry nav item')
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { level: 1, name: 'Document registry' })).toBeVisible()
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()

  // Open the released row's detail; it carries version, distribution, and lifecycle forms.
  const toggleStop = await tabUntil(page, 80, (state) => state.name === 'Details' && state.rowTitle.includes('Sample — Released procedure'))
  expect(toggleStop.state.rowTitle, 'the released row Details button is not reachable by Tab').toContain('Sample — Released procedure')
  expectVisibleRing(toggleStop.state, 'row Details button')
  const detailId = await page.locator(':focus').getAttribute('aria-controls')
  await page.keyboard.press('Enter')
  const detail = page.locator(`#${detailId}`)
  await expect(detail).toBeVisible()

  // Tab into the first form control inside the detail row and use it.
  const field = await tabUntil(page, 80, (state) => state.inDetail && (state.tag === 'TEXTAREA' || state.tag === 'SELECT' || state.tag === 'INPUT'))
  expect(field.state.inDetail, 'the detail forms are not reachable by Tab').toBe(true)
  expectVisibleRing(field.state, 'detail form control')

  const fieldName = await page.locator(':focus').ariaSnapshot()
  expect(fieldName.trim().length, 'detail form control has no accessible name').toBeGreaterThan(3)

  // The control must accept keyboard input and focus must move back with Shift+Tab.
  if (field.state.tag === 'TEXTAREA' || field.state.tag === 'INPUT') {
    const before = await page.locator(':focus').inputValue()
    await page.keyboard.type('X')
    expect(await page.locator(':focus').inputValue()).not.toBe(before)
  }
  await page.keyboard.press('Shift+Tab')
  expect((await focusState(page)).tag, 'focus does not move back with Shift+Tab').not.toBe('BODY')

  // Every stop on the path must have a name and a visible indicator.
  const unnamed = [...nav.stops, ...toggleStop.stops, ...field.stops].filter((stop) => stop.tag !== 'BODY' && stop.name === '')
  expect(unnamed, `focusable elements without an accessible name: ${JSON.stringify(unnamed)}`).toEqual([])
  for (const stop of [...nav.stops, ...toggleStop.stops, ...field.stops]) {
    if (stop.tag !== 'BODY') expectVisibleRing(stop, 'keyboard path')
  }
})

test('the open navigation dialog and notification popover have no automated accessibility violations', async ({ page }) => {
  test.setTimeout(120_000)
  // Below the 850px breakpoint the sidebar is replaced by the modal navigation dialog.
  await page.setViewportSize({ width: 800, height: 900 })
  await signIn(page)

  await page.getByRole('button', { name: 'Open navigation' }).click()
  const dialog = page.getByRole('dialog', { name: 'Navigation' })
  await expect(dialog).toBeVisible()
  expect(await audit(page, 'navigation dialog')).toEqual([])

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()

  await page.setViewportSize({ width: 1280, height: 900 })
  await page.getByRole('button', { name: /^Notifications/ }).click()
  const panel = page.locator('#notifications-panel')
  await expect(panel).toBeVisible()
  expect(await audit(page, 'notification popover')).toEqual([])
})

test('reduced motion is honoured and the screens stay accessible', async ({ page }) => {
  test.setTimeout(120_000)
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await signIn(page)
  expect(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true)

  await page.getByRole('button', { name: 'Document registry' }).click()
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()

  // Every duration is collapsed, so no control or overlay animates for this preference.
  const durations = await page.evaluate(() => {
    const sample = document.querySelector('.button, .top-icon-button, .surface-glass') as HTMLElement | null
    if (!sample) return null
    const style = getComputedStyle(sample)
    return { transition: style.transitionDuration, animation: style.animationDuration }
  })
  expect(durations, 'no representative control found').not.toBeNull()
  for (const value of Object.values(durations!)) {
    const longest = Math.max(...value.split(',').map((part) => Number.parseFloat(part) || 0))
    expect(longest, `motion was not collapsed: ${JSON.stringify(durations)}`).toBeLessThanOrEqual(0.00001)
  }

  expect(await audit(page, 'registry reduced motion')).toEqual([])
})

test('forced colours replace the glass surface and the screens stay accessible', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'Playwright emulates forced-colors in Chromium only.')
  test.setTimeout(120_000)
  await page.emulateMedia({ forcedColors: 'active' })
  await signIn(page)
  await page.getByRole('button', { name: 'Document registry' }).click()
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()

  const glass = await page.evaluate(() => {
    const surface = document.querySelector('.topbar.surface-glass') as HTMLElement
    const style = getComputedStyle(surface)
    return { backdrop: style.backdropFilter || style.webkitBackdropFilter, background: style.backgroundColor, border: style.borderBottomWidth }
  })
  expect(glass.backdrop, 'glass blur must be removed under forced colours').toBe('none')
  expect(glass.background, 'glass must fall back to a system colour').not.toContain('rgba')
  expect(Number.parseFloat(glass.border), 'glass must keep a visible border').toBeGreaterThan(0)

  expect(await audit(page, 'registry forced colours')).toEqual([])
})

test('every main screen reflows at 320 CSS pixels without page-level horizontal scrolling', async ({ page }) => {
  test.setTimeout(180_000)
  // 320 CSS px is the 1280 px reference width at 400 % zoom.
  await page.setViewportSize({ width: 320, height: 640 })
  await signIn(page)

  const overflow: Record<string, number> = {}
  for (const screen of screens) {
    await page.getByRole('button', { name: 'Open navigation' }).click()
    const dialog = page.getByRole('dialog', { name: 'Navigation' })
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: screen, exact: true }).click()
    await expect(page.getByRole('heading', { level: 1, name: screen })).toBeVisible()
    await page.waitForFunction(() => document.querySelector('[aria-busy="true"]') === null)
    const excess = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
    if (excess > 1) overflow[screen] = excess
  }

  expect(overflow, `screens scroll horizontally at 320px: ${JSON.stringify(overflow)}`).toEqual({})
})
