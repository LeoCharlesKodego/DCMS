import { expect, test, type Locator, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { seedSampleDocuments } from './support/credentials'
import { signIn, watchConsole } from './support/session'

const screenshots = resolve(dirname(fileURLToPath(import.meta.url)), 'screenshots')

// These tests drive the real Laravel API against the fictional sample data
// created by backend/scripts/dev-local.sh (dcms:dev-sample-documents). No
// endpoint is mocked here, so a successful submit and a rendered validation
// error are both real server responses.

const PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n' +
    '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n' +
    '4 0 obj\n<< /Length 60 >>\nstream\nBT /F1 12 Tf 72 720 Td (Fictional browser test file) Tj ET\nendstream\nendobj\n' +
    '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\ntrailer\n<< /Root 1 0 R /Size 6 >>\n%%EOF\n',
)

const upload = { name: 'fictional-browser-test.pdf', mimeType: 'application/pdf', buffer: PDF }

async function openRegistry(page: Page) {
  await signIn(page)
  await page.getByRole('button', { name: 'Document registry' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Document registry' })).toBeVisible()
  await expect(page.getByRole('table', { name: /Document control register/ })).toBeVisible()
}

/** Selects the option whose visible text matches, without hard-coding option values. */
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
  // The toggle's accessible name changes from Details to Hide details once open,
  // so address it by the detail row it controls instead.
  const toggle = row.locator('button[aria-controls^="document-detail-"]')
  const detailId = await toggle.getAttribute('aria-controls')
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    // Keyboard activation, because a pointer click can be intercepted by the sticky
    // top bar where the row sits under it.
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

test('the register shows the fictional sample documents across lifecycle states', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await openRegistry(page)

  const table = page.getByRole('table', { name: /Document control register/ })
  await expect(table).toContainText('Sample — Awaiting official code')
  await expect(table).toContainText('Sample — Draft procedure')
  await expect(table).toContainText('Sample — Returned procedure')
  await expect(table).toContainText('Sample — Released procedure')
  // The row stamp reports the document state; the version state lives in the detail row.
  await expect(documentRow(page, 'Sample — Awaiting official code')).toContainText('Pending code')
  await expect(documentRow(page, 'Sample — Draft procedure')).toContainText('Registered')
  await expect(documentRow(page, 'Sample — Returned procedure')).toContainText('Registered')
  await expect(documentRow(page, 'Sample — Released procedure')).toContainText('Active')

  const released = await openDetails(page, 'Sample — Released procedure')
  await expect(released.getByText('Released', { exact: true }).first()).toBeVisible()
  const returned = await openDetails(page, 'Sample — Returned procedure')
  await expect(returned.getByText('Returned for revision', { exact: true }).first()).toBeVisible()
  const draft = await openDetails(page, 'Sample — Draft procedure')
  await expect(draft.getByText('Draft', { exact: true }).first()).toBeVisible()

  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})

test('assigning an official code from the detail row registers the document', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await openRegistry(page)
  await page.getByRole('button', { name: 'Load official code lists' }).click()

  const detail = await openDetails(page, 'Sample — Awaiting official code')
  await detail.locator('details.code-assignment summary').click()
  const form = detail.locator('details.code-assignment')

  await selectByText(form.locator('select[name="service_component_id"]'), /Training Service/)
  await selectByText(form.locator('select[name="section_component_id"]'), /Operations Section/)
  await selectByText(form.locator('select[name="document_type_component_id"]'), /Sample Procedure/)
  await form.getByRole('button', { name: 'Assign code' }).click()

  await expect(page.getByRole('status')).toContainText('Official code assigned and added to the Master Code Register.')
  const row = documentRow(page, 'Sample — Awaiting official code')
  await expect(row).toContainText('DEMO-TRN-OPS-PROC-')
  await expect(row.getByText('Registered', { exact: true })).toBeVisible()

  const reloaded = await openDetails(page, 'Sample — Awaiting official code')
  await expect(reloaded).toContainText('No versions recorded yet.')
  await expect(reloaded.locator('details.version-upload summary')).toHaveText('Upload first draft')
  expect(problems).toEqual([])
})

test('uploading a first draft from the detail row saves a version and updates the register', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await openRegistry(page)
  const detail = await openDetails(page, 'Sample — Awaiting official code')

  const uploadForm = detail.locator('details.version-upload').first()
  await uploadForm.locator('summary').click()
  await uploadForm.getByLabel(/Files \(PDF/).setInputFiles(upload)
  await uploadForm.getByRole('button', { name: 'Save draft version' }).click()

  await expect(page.getByRole('status')).toContainText('Draft version and file hashes were saved in private storage.')
  const reloaded = await openDetails(page, 'Sample — Awaiting official code')
  await expect(reloaded).toContainText('1 recorded version')
  await expect(reloaded).toContainText('Revision 1.0')
  await expect(reloaded).toContainText('fictional-browser-test.pdf')
  expect(problems).toEqual([])
})

test('a workflow decision refuses an empty reason and succeeds with one', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page, { ignoreStatuses: [422] })
  await openRegistry(page)
  const detail = await openDetails(page, 'Sample — Returned procedure')

  await expect(detail.getByText('Returned for revision', { exact: true }).first()).toBeVisible()
  await detail.getByRole('button', { name: 'submit', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Recorded submit for revision')
  await expect(detail.getByText('Submitted', { exact: true }).first()).toBeVisible()

  const task = detail.locator('form').filter({ has: page.getByRole('button', { name: 'Approve' }) }).first()
  await expect(task).toBeVisible()
  await task.getByRole('button', { name: 'Approve' }).click()

  // The server requires a recorded reason for the manual and not-checked findings.
  const alert = page.getByRole('alert')
  await expect(alert).toContainText('reason')
  await expect(detail.getByText('In review', { exact: true }).first()).toBeHidden()

  await task.locator('textarea[name="comment"]').fill('Fictional browser test: warnings reviewed against the sample file.')
  await task.getByRole('button', { name: 'Approve' }).click()
  await expect(page.getByRole('status')).toContainText('Recorded verified')
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(detail.getByText('In review', { exact: true }).first()).toBeVisible()
  expect(problems).toEqual([])
})

test('issuing and exempting a distribution updates the released row', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await openRegistry(page)
  const detail = await openDetails(page, 'Sample — Released procedure')

  await expect(detail.getByText('Released', { exact: true }).first()).toBeVisible()
  await detail.getByRole('button', { name: 'Prepare distribution' }).click()
  const issueForm = detail.locator('form').filter({ has: page.getByRole('button', { name: 'Issue exact-version distribution' }) })
  await selectByText(issueForm.locator('select[name="targets"]'), /Sample Staff Recipient/)
  await issueForm.getByRole('button', { name: 'Issue exact-version distribution' }).click()

  await expect(page.getByRole('status')).toContainText('Distributed revision')
  await expect(detail).toContainText('Distribution history')
  await expect(detail).toContainText('Sample Staff Recipient: Pending')

  // The sample data already left one pending recipient, so address the first form.
  const exemptForm = detail.locator('form').filter({ has: page.getByRole('button', { name: 'Exempt this recipient' }) }).first()
  await exemptForm.getByLabel('Exemption reason').fill('Fictional browser test exemption.')
  // The sticky top bar can cover a control inside the scrolling register, so submit
  // with the keyboard, which also proves the form is operable without a pointer.
  await exemptForm.getByRole('button', { name: 'Exempt this recipient' }).focus()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('status')).toContainText('Exempted one recipient')
  await expect(detail).toContainText('Sample Staff Recipient: Exempted')
  expect(problems).toEqual([])
})

test('uploading a revision and saving the title-size setting keep the register consistent', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await openRegistry(page)

  const sizeForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save setting' }) }).first()
  await sizeForm.getByLabel('KMT title-size default (pt)').fill('14')
  await sizeForm.getByRole('button', { name: 'Save setting' }).click()
  await expect(page.getByRole('status')).toContainText('Title-size setting saved with version history.')

  const detail = await openDetails(page, 'Sample — Released procedure')
  const uploadForm = detail.locator('details.version-upload').filter({ hasText: 'Upload revision' }).first()
  await uploadForm.locator('summary').click()
  await uploadForm.getByLabel('Revision type').selectOption('minor')
  await uploadForm.getByLabel('Change summary').fill('Fictional browser test revision.')
  await uploadForm.getByLabel(/Files \(PDF/).setInputFiles(upload)
  await uploadForm.getByRole('button', { name: 'Save draft version' }).click()

  await expect(page.getByRole('status')).toContainText('Draft version and file hashes were saved in private storage.')
  const reloaded = await openDetails(page, 'Sample — Released procedure')
  await expect(reloaded).toContainText('2 recorded versions')
  await expect(reloaded).toContainText('Revision 1.1')
  await expect(documentRow(page, 'Sample — Released procedure')).toContainText('Rev 1.1')
  expect(problems).toEqual([])
})

test('submission check results fill the card and read as rows, not JSON', async ({ page }) => {
  test.setTimeout(120_000)
  await openRegistry(page)

  const card = page.locator('section.panel').filter({ has: page.getByRole('heading', { level: 2, name: 'Submission check results' }) })
  await expect(card).toBeVisible()
  await expect(card.locator('article').first()).toBeVisible()
  // Findings are rendered as sentences: raw JSON must never reach the page.
  await expect(card).not.toContainText('{"')
  await expect(card).toContainText('Expected PDF, detected PDF')

  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 900 })
    await card.scrollIntoViewIfNeeded()

    const geometry = await card.evaluate((section) => ({
      container: section.getBoundingClientRect().width,
      contents: Array.from(section.querySelectorAll('article')).map((record) => record.firstElementChild?.getBoundingClientRect().width ?? 0),
    }))

    expect(geometry.contents.length, 'no check results rendered').toBeGreaterThan(0)
    for (const content of geometry.contents) {
      expect(content, `check content is narrower than half the card at ${width}px`).toBeGreaterThanOrEqual(geometry.container / 2)
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
    expect(overflow, `page overflows horizontally at ${width}px`).toBeLessThanOrEqual(1)
  }
})

test('at 1024px code, title, status, and actions stay inside the register scroller', async ({ page }) => {
  test.setTimeout(120_000)
  await openRegistry(page)
  await page.setViewportSize({ width: 1024, height: 900 })

  const wrapper = page.getByRole('region', { name: /Document control register/ })
  await expect(wrapper).toBeVisible()
  const row = page.getByRole('table', { name: /Document control register/ }).getByRole('row').filter({ hasText: 'Sample — Draft procedure' }).first()
  await expect(row).toBeVisible()

  const target = row.getByRole('button', { name: /Details$/ })
  const code = row.getByRole('rowheader')
  const title = row.getByRole('cell').first()
  const status = row.locator('.stamp').first()
  for (const element of [code, title, status, target]) {
    await expect(element).toBeVisible()
  }

  const geometry = await page.evaluate(() => {
    const region = document.querySelector('[aria-labelledby="registry-table-caption"]') as HTMLElement
    const bounds = region.getBoundingClientRect()
    const rowElements = Array.from(document.querySelectorAll('.registry-table tbody tr'))[0] as HTMLElement
    const pick = (selector: string) => {
      const node = rowElements.querySelector(selector) as HTMLElement | null
      return node ? node.getBoundingClientRect() : null
    }
    // A horizontal scrollbar is the failure mode: the table would slide out of view.
    region.scrollLeft = 9999
    const horizontalScroll = region.scrollLeft
    region.scrollLeft = 0

    return {
      horizontalScroll,
      right: bounds.right,
      left: bounds.left,
      code: pick('th'),
      title: pick('td'),
      status: pick('.stamp'),
      actions: pick('.registry-row-actions button'),
    }
  })

  // Nothing may hide behind the inner scroller: no horizontal scrolling, and every
  // asserted cell stays within the visible box.
  expect(geometry.horizontalScroll, `register needs horizontal scrolling at 1024px: ${JSON.stringify(geometry)}`).toBe(0)
  for (const key of ['code', 'title', 'status', 'actions'] as const) {
    const rect = geometry[key]
    expect(rect, `${key} was not rendered`).not.toBeNull()
    expect(rect!.left, `${key} starts left of the scroller`).toBeGreaterThanOrEqual(geometry.left - 1)
    expect(rect!.right, `${key} hides behind the right edge of the scroller`).toBeLessThanOrEqual(geometry.right + 1)
  }

  mkdirSync(screenshots, { recursive: true })
  await page.screenshot({ path: resolve(screenshots, 'registry-real-1024.png') })
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(overflow, 'page overflows horizontally at 1024px').toBeLessThanOrEqual(1)
})
