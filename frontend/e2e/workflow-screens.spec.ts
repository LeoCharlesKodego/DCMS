import { expect, test, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { watchConsole } from './support/session'

// Real-data coverage for the two Step 4 screens. Samples come from
// Database\Seeders\LocalPhase8DemoSeeder; nothing here is mocked.

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const KMT = 'kmt@example.test'
const MCC = 'mcc@example.test'
const DIVISION_HEAD = 'division.head@example.test'
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

test('a returned submission offers the same verifier a new task after resubmission', async ({ page }) => {
  test.setTimeout(240_000)
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
  await expect(table.locator('td.is-date').first()).toHaveCSS('text-align', 'end')

  const firstRow = reviewRow(page, 'Sample — Draft procedure').first()
  await expect(firstRow).toBeVisible()
  await expect(firstRow.locator('.stamp').first()).toHaveText(/Registered/)
  await expect(page.getByText('No workflow decisions are waiting for your role.')).toHaveCount(0)

  // KMT verifies from this screen.
  const firstToggle = firstRow.locator('button[aria-controls^="document-detail-"]')
  const firstDetailId = await firstToggle.getAttribute('aria-controls')
  if ((await firstToggle.getAttribute('aria-expanded')) !== 'true') {
    await firstToggle.click()
  }
  const firstDetail = page.locator(`#${firstDetailId}`)
  await expect(firstDetail.getByText('Submitted', { exact: true })).toBeVisible()
  await firstDetail.locator('textarea[name="comment"]').fill('Fictional browser test: sample findings reviewed for the resubmission check.')
  await firstDetail.getByRole('button', { name: 'Approve', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded verified')

  // Division Head returns the submission.
  await signInAs(page, DIVISION_HEAD)
  await openScreen(page, 'Review & approval')
  const reviewRowBefore = reviewRow(page, 'Sample — Draft procedure').first()
  await expect(reviewRowBefore).toBeVisible()
  const reviewToggle = reviewRowBefore.locator('button[aria-controls^="document-detail-"]')
  const reviewDetailId = await reviewToggle.getAttribute('aria-controls')
  if ((await reviewToggle.getAttribute('aria-expanded')) !== 'true') {
    await reviewToggle.click()
  }
  const reviewDetail = page.locator(`#${reviewDetailId}`)
  await reviewDetail.locator('textarea[name="comment"]').fill('Fictional browser test: section 2 needs correction before approval.')
  await reviewDetail.getByRole('button', { name: 'Return for revision', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded returned for revision')

  // The owner resubmits the corrected version.
  await signInAs(page, email, password)
  await openScreen(page, 'Document registry')
  const resubmitRow = page.getByRole('region', { name: /Document control register/ }).locator('tbody tr').filter({ hasText: 'Sample — Draft procedure' }).first()
  const resubmitToggle = resubmitRow.locator('button[aria-controls^="document-detail-"]')
  const resubmitDetailId = await resubmitToggle.getAttribute('aria-controls')
  if ((await resubmitToggle.getAttribute('aria-expanded')) !== 'true') {
    await resubmitToggle.click()
  }
  await page.locator(`#${resubmitDetailId}`).getByRole('button', { name: 'submit', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded submit for revision 1.0')

  // The same KMT role gets the new pass's verification task instead of a silent stall.
  await signInAs(page, KMT)
  await openScreen(page, 'Review & approval')
  const newRow = reviewRow(page, 'Sample — Draft procedure').first()
  await expect(newRow).toBeVisible()
  await expect(page.getByText('No workflow decisions are waiting for your role.')).toHaveCount(0)
  const newToggle = newRow.locator('button[aria-controls^="document-detail-"]')
  const newDetailId = await newToggle.getAttribute('aria-controls')
  if ((await newToggle.getAttribute('aria-expanded')) !== 'true') {
    await newToggle.click()
  }
  await expect(page.locator(`#${newDetailId}`).getByRole('button', { name: 'Approve', exact: true })).toBeVisible()

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
