import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { localCredentials } from './support/credentials'
import { signIn } from './support/session'

// Automated accessibility pass over the real screens, driven by the running
// Laravel API and the fictional sample documents. Contrast sampling is covered
// by the shell and notification suites; this one looks for what colour checks
// miss: accessible names, landmark and heading structure, form labelling,
// focus order, and a visible focus indicator on every stop.

const here = dirname(fileURLToPath(import.meta.url))
const axeSource = readFileSync(resolve(here, '../node_modules/axe-core/axe.min.js'), 'utf8')

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
}

async function focusState(page: Page): Promise<FocusState> {
  return page.evaluate(() => {
    const element = document.activeElement as HTMLElement | null
    if (!element || element === document.body) {
      return { tag: 'BODY', name: '', outlineStyle: '', outlineWidth: '', boxShadow: '', inDetail: false }
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
  await page.getByRole('button', { name: 'Details' }).first().click()
  await expect(page.locator('[id^="document-detail-"]').first()).toBeVisible()
  const detailViolations = await audit(page, 'registry detail row')
  if (detailViolations.length > 0) findings['registry detail row'] = detailViolations

  expect(findings, JSON.stringify(findings, null, 2)).toEqual({})
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

  // Open the first row's detail.
  const toggleStop = await tabUntil(page, 60, (state) => state.name === 'Details')
  expect(toggleStop.state.name, 'a row Details button is not reachable by Tab').toBe('Details')
  expectVisibleRing(toggleStop.state, 'row Details button')
  await page.keyboard.press('Enter')
  const detail = page.locator('[id^="document-detail-"]').first()
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
