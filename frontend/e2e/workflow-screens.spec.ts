import { expect, test, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { watchConsole } from './support/session'

// Real-data coverage for the two Step 4 screens. Samples come from
// Database\Seeders\LocalPhase8DemoSeeder; nothing here is mocked.

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const KMT = 'kmt@example.test'
const MCC = 'mcc@example.test'
const STAFF = 'staff.recipient@example.test'

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

function reviewRow(page: Page, title: string) {
  return page.getByRole('region', { name: /Review and approval/ }).locator('tbody tr').filter({ hasText: title })
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(() => {
  seedSampleDocuments()
})

test('Review & approval shows an empty state when no decision waits for the role', async ({ page }) => {
  test.setTimeout(120_000)
  const { email, password } = localCredentials()
  await signInAs(page, MCC)
  expect(email).not.toBe('')

  await openScreen(page, 'Review & approval')
  await expect(page.getByText('No workflow decisions are waiting for your role.')).toBeVisible()
  await expect(page.getByRole('region', { name: /Review and approval/ })).toHaveCount(0)
  expect(password).toBeTruthy()
})

test('Review & approval lists the document whose verification decision is waiting', async ({ page }) => {
  test.setTimeout(180_000)
  const problems = watchConsole(page)

  // The disposable local account owns the sample unit, so it can submit the draft and
  // create a real verification task for KMT.
  const { email, password } = localCredentials()
  await signInAs(page, email, password)
  await openScreen(page, 'Document registry')
  const registryRow = page.getByRole('region', { name: /Document control register/ }).locator('tbody tr').filter({ hasText: 'Sample — Draft procedure' }).first()
  const toggle = registryRow.locator('button[aria-controls^="document-detail-"]')
  const detailId = await toggle.getAttribute('aria-controls')
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }
  const detail = page.locator(`#${detailId}`)
  await detail.getByRole('button', { name: 'submit', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded submit for revision 1.0')

  await signInAs(page, KMT)
  await openScreen(page, 'Review & approval')

  // The register's creation form is not offered on a review screen.
  await expect(page.getByRole('heading', { name: 'Register a document' })).toHaveCount(0)

  const region = page.getByRole('region', { name: /Review and approval/ })
  await expect(region).toBeVisible()
  const table = region.getByRole('table')
  await expect(table).toBeVisible()

  // Table treatment: sticky header, an end-aligned date column, and stamped status.
  const headCell = table.locator('thead th').first()
  await expect(headCell).toHaveCSS('position', 'sticky')
  const dateCell = table.locator('td.is-date').first()
  await expect(dateCell).toHaveCSS('text-align', 'end')

  const row = reviewRow(page, 'Sample — Draft procedure').first()
  await expect(row).toBeVisible()
  await expect(row.locator('.stamp').first()).toHaveText(/Registered/)
  await expect(page.getByText('No workflow decisions are waiting for your role.')).toHaveCount(0)

  // The decision itself is reachable from this screen, alongside the version's
  // submitted stamp.
  const rowToggle = row.locator('button[aria-controls^="document-detail-"]')
  const rowDetailId = await rowToggle.getAttribute('aria-controls')
  if ((await rowToggle.getAttribute('aria-expanded')) !== 'true') {
    await rowToggle.click()
  }
  const rowDetail = page.locator(`#${rowDetailId}`)
  await expect(rowDetail.getByText('Submitted', { exact: true })).toBeVisible()
  await expect(rowDetail.getByRole('button', { name: 'verified' })).toBeVisible()

  expect(problems).toEqual([])
})

test('Distribution lists the receipt as a table and acknowledges it', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signInAs(page, STAFF)
  await openScreen(page, 'Distribution')

  const table = page.getByTestId('distribution-table')
  await expect(table).toBeVisible()
  await expect(page.getByRole('region', { name: /Distributed to me/ })).toBeVisible()

  const headCell = table.locator('thead th').first()
  await expect(headCell).toHaveCSS('position', 'sticky')
  const issued = table.locator('thead th', { hasText: 'Issued' })
  await expect(issued).toHaveCSS('text-align', 'end')

  const row = table.locator('tbody tr').filter({ hasText: 'Sample — Released procedure' }).first()
  await expect(row.locator('.stamp').first()).toHaveText(/Pending/)
  await expect(row).toContainText('Rev 1.0')

  await row.getByRole('button', { name: 'Acknowledge receipt' }).click()
  await expect(page.getByRole('status')).toContainText('Receipt acknowledged for Sample — Released procedure')
  const receiptRow = table.locator('tbody tr').filter({ hasText: 'Sample — Released procedure' }).first()
  await expect(receiptRow.locator('.stamp').first()).toHaveText(/Acknowledged/)
  await expect(receiptRow.getByRole('button', { name: 'Acknowledge receipt' })).toHaveCount(0)

  expect(problems).toEqual([])
})

test('Distribution shows an empty state for an account with nothing issued to it', async ({ page }) => {
  test.setTimeout(120_000)
  await signInAs(page, KMT)
  await openScreen(page, 'Distribution')

  await expect(page.getByTestId('distribution-empty')).toHaveText('No document versions have been distributed to you.')
  await expect(page.getByTestId('distribution-table')).toHaveCount(0)
})
