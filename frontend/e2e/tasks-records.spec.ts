import { expect, test, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { watchConsole } from './support/session'

// Real-data coverage for My tasks and Records & retention. The samples come from the
// dev sample command; the one load-failure test stubs the endpoint and says so.

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const KMT = 'kmt@example.test'
const MCC = 'mcc@example.test'
const RECORD_TITLE = 'Fictional browser test record'

async function signInAs(page: Page, email: string, password = SAMPLE_PASSWORD) {
  await page.goto('/')
  const signOut = page.getByRole('button', { name: 'Sign out' })
  const emailField = page.getByLabel('Email')
  await expect.poll(async () => (await signOut.isVisible()) || (await emailField.isVisible())).toBe(true)
  if (await signOut.isVisible()) {
    await signOut.click()
    await expect(emailField).toBeVisible()
  }
  await emailField.fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()
}

async function openScreen(page: Page, name: string) {
  await page.getByRole('button', { name, exact: true }).click()
  await expect(page.getByRole('heading', { level: 1, name })).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(() => {
  seedSampleDocuments()
})

test('My tasks lists the lifecycle action waiting for the process owner', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  const { email, password } = localCredentials()
  await signInAs(page, email, password)
  await openScreen(page, 'My tasks')

  // The register's creation form belongs to the register, not to a work queue.
  await expect(page.getByRole('heading', { name: 'Register a document' })).toHaveCount(0)

  const region = page.getByRole('region', { name: /My tasks/ })
  await expect(region).toBeVisible()
  const table = region.getByRole('table')
  await expect(table).toBeVisible()

  // Table treatment: caption, sticky header, end-aligned date column, stamped status.
  const headCell = table.locator('thead th').first()
  await expect(headCell).toHaveCSS('position', 'sticky')
  await expect(table.locator('td.is-date').first()).toHaveCSS('text-align', 'end')

  const row = table.locator('tbody tr').filter({ hasText: 'Sample — Returned procedure' }).first()
  await expect(row).toBeVisible()
  await expect(row.locator('.stamp').first()).toHaveText(/Registered/)

  const toggle = row.locator('button[aria-controls^="document-detail-"]')
  const detailId = await toggle.getAttribute('aria-controls')
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }
  await expect(page.locator(`#${detailId}`).getByRole('button', { name: 'submit', exact: true })).toBeVisible()

  expect(problems).toEqual([])
})

test('My tasks shows an empty state when nothing waits for the role', async ({ page }) => {
  test.setTimeout(120_000)
  await signInAs(page, MCC)
  await openScreen(page, 'My tasks')

  await expect(page.getByText('No tasks are waiting for you right now.')).toBeVisible()
  await expect(page.getByRole('region', { name: /My tasks/ })).toHaveCount(0)
})

test('Records & retention lists records as a table and records a hold', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signInAs(page, KMT)
  await openScreen(page, 'Records & retention')

  const registerForm = page.locator('form').filter({ has: page.getByRole('heading', { name: 'Register operational record' }) })
  await registerForm.getByLabel('Record type').fill('Fictional routine report')
  await registerForm.getByLabel('Title', { exact: true }).fill(RECORD_TITLE)
  await registerForm.getByLabel('Reference number').fill('FICT-BROWSER-001')
  await registerForm.getByLabel('Retention trigger date').fill('2026-11-01')
  await registerForm.getByRole('button', { name: 'Save record metadata' }).click()
  await expect(page.getByRole('status')).toContainText('Operational record metadata saved.')

  const table = page.getByTestId('records-table')
  await expect(table).toBeVisible()
  await expect(page.getByRole('region', { name: /Operational records/ })).toBeVisible()
  await expect(table.locator('thead th').first()).toHaveCSS('position', 'sticky')
  await expect(table.locator('thead th', { hasText: 'Retention triggered' })).toHaveCSS('text-align', 'end')

  const row = table.locator('tbody tr').filter({ hasText: RECORD_TITLE }).first()
  await expect(row.locator('.stamp').first()).toHaveText(/Active/)
  await expect(row).toContainText('No holds')
  await expect(row.locator('td.is-date').first()).not.toHaveText('—')

  await row.getByLabel(`Hold reason for ${RECORD_TITLE}`).fill('Fictional hold for the browser test.')
  await row.getByRole('button', { name: 'Place hold' }).click()
  await expect(page.getByRole('status')).toContainText('Record hold placed.')

  const updated = table.locator('tbody tr').filter({ hasText: RECORD_TITLE }).first()
  await expect(updated).toContainText('Fictional hold for the browser test.')
  await expect(updated).toContainText('active')

  expect(problems).toEqual([])
})

test('Records & retention reports a load failure and recovers on retry', async ({ page }) => {
  test.setTimeout(120_000)
  await signInAs(page, KMT)
  // Mocked on purpose: a load failure cannot be produced from the seeded data.
  await page.route('**/api/operational-records', (route) => route.fulfill({
    status: 500,
    contentType: 'application/json',
    body: JSON.stringify({ message: 'Fictional outage for the browser test.' }),
  }))

  await openScreen(page, 'Records & retention')
  const alert = page.getByRole('alert')
  await expect(alert).toContainText('Fictional outage for the browser test.')
  await expect(page.getByTestId('records-empty')).toContainText('Records could not be loaded.')

  await page.unroute('**/api/operational-records')
  await alert.getByRole('button', { name: 'Retry' }).click()
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(page.getByTestId('records-empty').or(page.getByTestId('records-table'))).toBeVisible()
})
