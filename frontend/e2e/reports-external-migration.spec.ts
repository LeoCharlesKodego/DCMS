import { expect, test, type Page } from '@playwright/test'
import { localCredentials, seedSampleDocuments } from './support/credentials'
import { watchConsole } from './support/session'

const SAMPLE_PASSWORD = 'LocalDemo!2026'
const KMT = 'kmt@example.test'

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

test('Reports & audit shows summary counts and an audit table', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  const { email, password } = localCredentials()
  await signInAs(page, email, password)
  await openScreen(page, 'Reports & audit')

  const summaryTable = page.locator('.reports-summary-table')
  await expect(summaryTable).toBeVisible()
  await expect(summaryTable.locator('thead th').first()).toHaveCSS('position', 'sticky')
  await expect(summaryTable.locator('td.is-numeric').first()).toHaveCSS('text-align', 'end')

  const auditTable = page.locator('.reports-audit-table')
  await expect(auditTable).toBeVisible()
  await expect(auditTable.locator('thead th').first()).toHaveCSS('position', 'sticky')
  await expect(auditTable.locator('td.is-date').first()).toHaveCSS('text-align', 'end')

  // Filters are labelled and keyboard-usable native controls.
  await expect(page.getByLabel('Event type')).toBeVisible()
  await expect(page.getByLabel('From date')).toBeVisible()
  await expect(page.getByLabel('To date')).toBeVisible()

  // Applying a filter keeps the table rendered.
  await page.getByRole('button', { name: 'Apply filters' }).click()
  await expect(auditTable).toBeVisible()

  expect(problems).toEqual([])
})

test('External references registers a reference and lists it as a table', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signInAs(page, KMT)
  await openScreen(page, 'External references')

  const form = page.locator('form').filter({ has: page.getByRole('heading', { name: 'Register an external reference' }) })
  await form.getByLabel('Title').fill('Fictional browser test reference')
  await form.getByLabel('Issuing body').fill('Fictional standards body')
  await form.getByRole('button', { name: 'Save reference' }).click()
  await expect(page.getByRole('status')).toContainText('External reference added.')

  const table = page.locator('.external-references-table')
  await expect(table).toBeVisible()
  await expect(table.locator('thead th').first()).toHaveCSS('position', 'sticky')

  const row = table.locator('tbody tr').filter({ hasText: 'Fictional browser test reference' }).first()
  await expect(row).toBeVisible()
  await expect(row.locator('.stamp').first()).toHaveText(/Active/)

  expect(problems).toEqual([])
})

test('Document migration creates a batch and shows the empty item state', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signInAs(page, KMT)
  await openScreen(page, 'Document migration')

  const form = page.locator('form').filter({ has: page.getByRole('heading', { name: 'Start a migration batch' }) })
  await form.getByLabel('Batch name').fill('Fictional browser test batch')
  await form.getByRole('button', { name: 'Create batch' }).click()
  await expect(page.getByRole('status')).toContainText('Migration batch created.')

  await expect(page.getByText('No source files recorded in this batch yet.')).toBeVisible()

  expect(problems).toEqual([])
})
