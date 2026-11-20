import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { signIn, watchConsole } from './support/session'

const screenshots = resolve(dirname(fileURLToPath(import.meta.url)), 'screenshots')

const row = (id: number) => ({
  id,
  title: `Controlled document ${id}`,
  document_type: 'Standard operating procedure',
  category: 'Patient care',
  owner: 'Document owner',
  owning_unit: 'Nursing service',
  base_code: `ONP-${id}`,
  display_code: `ONP-${id} / 1.0`,
  revision_label: '1.0',
  status: id === 1 ? 'released' : 'draft',
  owning_unit_id: 1,
  versions: [],
  audit_events: [],
  effective_date: id === 1 ? '2026-11-01' : null,
  retention_years: 7,
  remarks: null,
})

const registryPayload = {
  data: Array.from({ length: 14 }, (_, index) => row(index + 1)),
  can_assign_code: false,
  can_distribute: false,
  can_view_master_code_register: false,
  create_units: [],
  document_types: [],
  categories: [],
}

test('registry table has a caption, scope, sticky header, stamps and a keyboard detail row', async ({ page }) => {
  test.setTimeout(90_000)
  const problems = watchConsole(page)
  await page.route('**/api/documents', (route) => route.fulfill({ json: registryPayload }))

  await signIn(page)
  await page.getByRole('button', { name: 'Document registry' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Document registry' })).toBeVisible()
  await expect(page).toHaveTitle('Document registry · ONP DCMS')

  const table = page.getByRole('table', { name: /Document control register/ })
  await expect(table).toBeVisible()
  await expect(table.locator('caption')).toContainText('Document control register')
  await expect(table.getByRole('columnheader', { name: 'Code / revision' })).toBeVisible()
  await expect(table.getByRole('columnheader', { name: 'Effective' })).toBeVisible()
  const firstRowHeader = table.getByRole('rowheader').first()
  await expect(firstRowHeader).toContainText('ONP-1')
  await expect(firstRowHeader).toHaveAttribute('scope', 'row')

  // Lifecycle status is a text stamp, not colour alone.
  await expect(table.getByText('Released', { exact: true }).first()).toBeVisible()
  await expect(table.getByText('Draft', { exact: true }).first()).toBeVisible()

  // Sticky header stays at the top of the scrolling region.
  const wrapper = page.getByRole('region', { name: /Document control register/ })
  await wrapper.evaluate((element) => { element.scrollTop = 280 })
  await page.waitForFunction(() => document.querySelector('[aria-labelledby="registry-table-caption"]')!.scrollTop > 0)
  const sticky = await page.evaluate(() => {
    const region = document.querySelector('[aria-labelledby="registry-table-caption"]') as HTMLElement
    const header = region.querySelector('thead th') as HTMLElement
    return {
      scrollTop: region.scrollTop,
      offset: header.getBoundingClientRect().top - region.getBoundingClientRect().top,
      sticky: getComputedStyle(header).position,
    }
  })
  expect(sticky.scrollTop).toBeGreaterThan(0)
  expect(sticky.sticky, `header position was ${sticky.sticky}; offset ${sticky.offset}`).toBe('sticky')
  expect(sticky.offset, `sticky header offset was ${sticky.offset}px`).toBeLessThanOrEqual(1)

  // Keyboard path: Tab to Details, Enter opens the detail row, Enter closes it.
  const details = page.getByRole('button', { name: 'Details' }).first()
  await details.focus()
  await page.keyboard.press('Enter')
  await expect(details).toHaveAttribute('aria-expanded', 'true')
  await expect(page.getByRole('button', { name: 'Hide details' }).first()).toBeVisible()
  const detailId = await details.getAttribute('aria-controls')
  await expect(page.locator(`#${detailId}`)).toBeVisible()
  await expect(page.locator(`#${detailId}`)).toContainText('Versions and actions')
  await page.keyboard.press('Enter')
  await expect(page.getByRole('button', { name: 'Details' }).first()).toHaveAttribute('aria-expanded', 'false')

  // 1280 and 1024 remain usable without page-level horizontal overflow.
  for (const width of [1280, 1024]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(table).toBeVisible()
    mkdirSync(screenshots, { recursive: true })
    await page.screenshot({ path: resolve(screenshots, `registry-${width}.png`) })
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
    expect(overflow, `page overflows horizontally at ${width}px`).toBeLessThanOrEqual(1)
  }

  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})

test('registry keeps a plain empty state when no documents are in scope', async ({ page }) => {
  const problems = watchConsole(page)
  await page.route('**/api/documents', (route) => route.fulfill({ json: { ...registryPayload, data: [] } }))

  await signIn(page)
  await page.getByRole('button', { name: 'Document registry' }).click()

  await expect(page.getByText('No documents recorded in your scope yet.')).toBeVisible()
  await expect(page.getByRole('table')).toHaveCount(0)
  expect(problems).toEqual([])
})
