import { expect, test, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { signIn, watchConsole } from './support/session'

// Real-data coverage for two access rules: the register is only offered to accounts
// with document-control scope, and the replace-files form follows the backend rule
// (Process Owner of the owning unit, version draft or returned). The sample accounts
// come from Database\Seeders\LocalPhase8DemoSeeder.
//
// One case mocks a 403 response to reach the screen state directly; the API itself is
// not mocked anywhere else.

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const STAFF = 'staff.recipient@example.test'
const KMT = 'kmt@example.test'

const PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n' +
    '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n' +
    '4 0 obj\n<< /Length 60 >>\nstream\nBT /F1 12 Tf 72 720 Td (Fictional browser test file) Tj ET\nendstream\nendobj\n' +
    '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\ntrailer\n<< /Root 1 0 R /Size 6 >>\n%%EOF\n',
)

const upload = { name: 'fictional-replacement.pdf', mimeType: 'application/pdf', buffer: PDF }

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

async function openRegistry(page: Page) {
  await page.getByRole('button', { name: 'Document registry', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Document registry' })).toBeVisible()
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
    await toggle.focus()
    await page.keyboard.press('Enter')
  }
  const detail = page.locator(`#${detailId}`)
  await expect(detail).toBeVisible()

  return detail
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(() => {
  seedSampleDocuments()
})

test('an account without registry scope is not offered the register', async ({ page }) => {
  test.setTimeout(120_000)
  await signInAs(page, STAFF)

  await expect(page.getByRole('button', { name: 'Document registry', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Find a document/ })).toHaveCount(0)
  // The API agrees: a direct request from this account is refused, and 403 is the
  // approved authorization rule, not an error state.
  const response = await page.request.get('/api/documents')
  expect(response.status()).toBe(403)
})

test('reaching the register without access shows a plain state, not an error', async ({ page }) => {
  test.setTimeout(120_000)
  const { email, password } = localCredentials()
  await signIn(page)
  expect(email).not.toBe('')

  // The navigation entry is hidden, so this response is mocked to reach the screen
  // state that a scope change or stale session would produce.
  await page.route('**/api/documents', (route) =>
    route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ message: 'No active ONP role grants access to the document register.' }) }),
  )
  await page.getByRole('button', { name: 'Document registry', exact: true }).click()
  await expect(page.getByText('You don’t have access to the document registry.')).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(page.locator('.registry-error')).toHaveCount(0)
  await page.unroute('**/api/documents')
  expect(password).toBeTruthy()
})

test('the owning Process Owner can replace a returned version and a non-owner cannot', async ({ page }) => {
  test.setTimeout(180_000)
  const problems = watchConsole(page)
  const { email, password } = localCredentials()

  // Owner: the disposable local account holds a Process Owner scope for the sample unit.
  await signInAs(page, email, password)
  await openRegistry(page)
  let detail = await openDetails(page, 'Sample — Returned procedure')
  const replaceForm = detail.locator('details.version-upload').filter({ hasText: 'Replace current files and rerun checks' }).first()
  await expect(replaceForm).toBeVisible()

  await replaceForm.locator('summary').click()
  await replaceForm.locator('input[type="file"]').setInputFiles(upload)
  await replaceForm.getByRole('button', { name: 'Replace current file set' }).click()
  await expect(page.getByRole('status')).toContainText('Replacement files saved for revision 1.0')
  await expect(page.getByRole('alert')).toHaveCount(0)

  // Non-owner: a KMT account can see the register but has no Process Owner scope.
  await signInAs(page, KMT)
  await openRegistry(page)
  detail = await openDetails(page, 'Sample — Returned procedure')
  await expect(detail.locator('details.version-upload').filter({ hasText: 'Replace current files and rerun checks' })).toHaveCount(0)

  // The backend still refuses the same request from that account.
  const csrf = await page.request.get('/api/auth/csrf-cookie')
  expect(csrf.ok()).toBe(true)
  const xsrf = (await page.context().cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')
  expect(xsrf, 'the XSRF cookie must be set before a state-changing request').toBeTruthy()
  const registry = await (await page.request.get('/api/documents')).json()
  const returned = registry.data.find((item: { title: string }) => item.title === 'Sample — Returned procedure')
  expect(returned, 'the returned sample must be present').toBeTruthy()
  const response = await page.request.post(
    `/api/documents/${returned.id}/versions/${returned.versions[0].id}/files`,
    {
      headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf!.value) },
      multipart: { 'files[]': upload },
    },
  )
  expect(response.status(), await response.text()).toBe(403)

  expect(problems).toEqual([])
})
