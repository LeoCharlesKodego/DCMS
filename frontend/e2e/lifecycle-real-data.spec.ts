import { expect, test, type Locator, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { watchConsole } from './support/session'

// Real-data coverage for the flows agreed after the forms pass: adding a register
// entry, acknowledging a distributed version, and driving one document through the
// configured ONP workflow to controlled release. Every request goes to the running
// Laravel API against the fictional local sample data; nothing is mocked.
//
// The sample accounts are created by Database\Seeders\LocalPhase8DemoSeeder with a
// shared fictional password. They never exist outside a local SQLite database.

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const OWNER = 'process.owner@example.test'
const KMT = 'kmt@example.test'
const DIVISION_HEAD = 'division.head@example.test'
const MCC = 'mcc@example.test'
const KMT_RELEASE = 'kmt.release@example.test'
const STAFF = 'staff.recipient@example.test'

async function signInAs(page: Page, email: string, password = SAMPLE_PASSWORD) {
  await page.goto('/')
  const signOut = page.getByRole('button', { name: 'Sign out' })
  const emailField = page.getByLabel('Email')
  // The session probe settles asynchronously, so wait for either state before choosing.
  await expect.poll(async () => (await signOut.isVisible()) || (await emailField.isVisible())).toBe(true)
  if (await signOut.isVisible()) {
    await signOut.click()
    await expect(emailField).toBeVisible()
  }
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()
}

async function openScreen(page: Page, screen: string) {
  await page.getByRole('button', { name: screen, exact: true }).click()
  await expect(page.getByRole('heading', { level: 1, name: screen })).toBeVisible()
}

async function openRegistry(page: Page) {
  await openScreen(page, 'Document registry')
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()
}

async function selectByText(select: Locator, pattern: RegExp) {
  let label = ''
  await expect.poll(async () => {
    const texts = await select.locator('option').allTextContents()
    label = texts.find((candidate) => pattern.test(candidate)) ?? ''
    return label
  }, { message: `no option matched ${pattern}` }).not.toBe('')
  await select.selectOption({ label })
}

function documentRow(page: Page, title: string) {
  return page.getByRole('table', { name: /Document control register/ }).getByRole('row').filter({ hasText: title }).first()
}

async function openDetails(page: Page, title: string) {
  const row = documentRow(page, title)
  await expect(row).toBeVisible()
  const toggle = row.locator('button[aria-controls^="document-detail-"]')
  const detailId = await toggle.getAttribute('aria-controls')
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    // Keyboard activation: a pointer click can be intercepted by the sticky top bar.
    await toggle.focus()
    await page.keyboard.press('Enter')
  }
  const detail = page.locator(`#${detailId}`)
  await expect(detail).toBeVisible()

  return detail
}

function workflowTask(detail: Locator, decision: string) {
  return detail.locator('form').filter({ has: detail.page().getByRole('button', { name: decision, exact: true }) }).first()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(() => {
  seedSampleDocuments()
})

test('Add to register creates a pending-code row and reports a blank title on the field', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page, { ignoreStatuses: [422] })
  await signInAs(page, OWNER)
  await openRegistry(page)

  const createForm = page.locator('form.registry-create')
  await expect(createForm).toBeVisible()
  const title = 'Sample — Browser register entry'
  await selectByText(createForm.getByLabel('Document type'), /Sample Procedure/)
  await selectByText(createForm.getByLabel('Owning unit'), /Sample Operations Unit/)

  // A whitespace-only title passes the browser's required check and is rejected by
  // the API, which must report the problem on the title field itself.
  await createForm.getByLabel('Document title').fill('   ')
  await createForm.getByRole('button', { name: 'Add to register' }).click()
  const fieldError = page.locator('#register-title-error')
  await expect(fieldError).toBeVisible()
  await expect(fieldError).toContainText('title')
  await expect(createForm.getByLabel('Document title')).toHaveAttribute('aria-invalid', 'true')
  await expect(documentRow(page, title)).toHaveCount(0)

  // Typing again clears the field error and the valid path creates the request.
  await createForm.getByLabel('Document title').fill(title)
  await expect(fieldError).toHaveCount(0)
  await createForm.getByRole('button', { name: 'Add to register' }).click()

  await expect(page.getByRole('status')).toContainText('Document request added to the register, awaiting code assignment.')
  const row = documentRow(page, title)
  await expect(row).toBeVisible()
  await expect(row.getByText('Pending code', { exact: true })).toBeVisible()
  await expect(row).toContainText('Awaiting code')

  expect(problems).toEqual([])
})

test('a distributed version can be acknowledged from the receipt inbox', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signInAs(page, STAFF)
  await openScreen(page, 'Distribution')

  const inbox = page.locator('.distribution-inbox')
  await expect(inbox).toBeVisible()
  const receipt = inbox.locator('article').filter({ hasText: 'Sample — Released procedure' }).first()
  await expect(receipt).toContainText('pending')
  await receipt.getByRole('button', { name: 'Acknowledge receipt' }).click()

  await expect(page.getByRole('status')).toContainText('Receipt acknowledged for Sample — Released procedure')
  await expect(receipt).toContainText('acknowledged')
  await expect(receipt.getByRole('button', { name: 'Acknowledge receipt' })).toHaveCount(0)
  await expect(receipt).toContainText('Received')

  expect(problems).toEqual([])
})

test('a draft is submitted, verified, reviewed, approved, dated, and released', async ({ page }) => {
  test.setTimeout(240_000)
  const problems = watchConsole(page, { ignoreStatuses: [422] })
  const { email: localEmail, password: localPassword } = localCredentials()

  // The disposable local account is KMT plus a Process Owner scope for the sample
  // unit, so it submits the draft and later proposes the effective date.
  await signInAs(page, localEmail, localPassword)
  await openRegistry(page)
  let detail = await openDetails(page, 'Sample — Draft procedure')
  await expect(detail.getByText('Draft', { exact: true }).first()).toBeVisible()
  await detail.getByRole('button', { name: 'submit', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded submit for revision')
  await expect(detail.getByText('Submitted', { exact: true }).first()).toBeVisible()

  // KMT verification. An empty return reason is reported on the field before any
  // request is sent; the verification itself then records the decision.
  await signInAs(page, KMT)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Draft procedure')
  const verifyForm = workflowTask(detail, 'verified')
  await expect(verifyForm).toBeVisible()
  await verifyForm.getByRole('button', { name: 'Return for revision' }).click()
  await expect(verifyForm.locator('.field-error')).toBeVisible()
  await expect(verifyForm.locator('.field-error')).toContainText('return reason is required')
  await expect(verifyForm.locator('textarea[name="comment"]')).toHaveAttribute('aria-invalid', 'true')
  await expect(detail.getByText('Submitted', { exact: true }).first()).toBeVisible()
  // The sample file carries findings KMT must resolve with a recorded reason.
  await verifyForm.locator('textarea[name="comment"]').fill('Fictional browser test: warnings reviewed against the sample file.')
  await verifyForm.getByRole('button', { name: 'verified', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded verified')
  await expect(detail.getByText('In review', { exact: true }).first()).toBeVisible()

  // The owning Division Head review.
  await signInAs(page, DIVISION_HEAD)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Draft procedure')
  await detail.getByRole('button', { name: 'reviewed', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded reviewed')

  // MCC final approval.
  await signInAs(page, MCC)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Draft procedure')
  await detail.getByRole('button', { name: 'approved', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded approved')
  await expect(detail.getByText('Approved', { exact: true }).first()).toBeVisible()

  // The Process Owner proposes the effective date.
  await signInAs(page, localEmail, localPassword)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Draft procedure')
  const propose = detail.locator('form').filter({ has: page.getByRole('button', { name: 'propose effective date' }) }).first()
  await propose.locator('input[name="effective_date"]').fill(new Date().toISOString().slice(0, 10))
  await propose.getByRole('button', { name: 'propose effective date' }).click()
  await expect(page.getByRole('status')).toContainText('Recorded propose effective date')

  // KMT confirms the proposed date and then releases the exact version.
  await signInAs(page, KMT_RELEASE)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Draft procedure')
  const confirm = detail.locator('form').filter({ has: page.getByRole('button', { name: 'confirm effective date' }) }).first()
  await confirm.getByRole('button', { name: 'confirm effective date' }).click()
  await expect(page.getByRole('status')).toContainText('Recorded confirm effective date')
  const release = detail.locator('form').filter({ has: page.getByRole('button', { name: 'release', exact: true }) }).first()
  await expect(release).toBeVisible()
  await release.getByRole('button', { name: 'release', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded release for revision 1.0')

  await expect(documentRow(page, 'Sample — Draft procedure').getByText('Active', { exact: true })).toBeVisible()
  const reloaded = await openDetails(page, 'Sample — Draft procedure')
  await expect(reloaded.getByText('Released', { exact: true }).first()).toBeVisible()
  await expect(page.locator('.audit-timeline')).toContainText('version released')

  expect(problems).toEqual([])
})
