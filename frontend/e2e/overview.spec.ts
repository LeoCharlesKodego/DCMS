import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const screenshots = resolve(dirname(fileURLToPath(import.meta.url)), 'screenshots')
import { signIn, watchConsole } from './support/session'

const widgets = [
  { key: 'accounts', label: 'Account and settings administration', count: 3 },
  { key: 'verification', label: 'Items awaiting KMT verification or code assignment', count: 0 },
  { key: 'release', label: 'Approved documents awaiting effective date or release', count: 4 },
  { key: 'records', label: 'Records and disposition work', count: null },
]

test('overview is one ruled summary strip with a loading and error path', async ({ page }) => {
  test.setTimeout(90_000)
  const problems = watchConsole(page, { ignoreStatuses: [500] })
  let calls = 0
  await page.route('**/api/dashboard', async (route) => {
    calls += 1
    if (calls === 1) {
      await new Promise((resolve) => setTimeout(resolve, 600))
      await route.fulfill({ json: { widgets } })
      return
    }
    if (calls === 2) {
      await route.fulfill({ status: 500, json: { message: 'Server error' } })
      return
    }
    await route.fulfill({ json: { widgets } })
  })

  await signIn(page)
  await expect(page).toHaveTitle('Overview · ONP DCMS')

  const strip = page.locator('.summary-strip')
  await expect(strip).toHaveAttribute('aria-busy', 'true')
  await expect(strip).toContainText('Loading count')
  await expect(strip).toHaveAttribute('aria-busy', 'false')
  await expect(strip.getByText('Account and settings administration')).toBeVisible()
  await expect(page.locator('.metric-card')).toHaveCount(0)

  // Reload so the second intercepted response exercises the error state.
  await page.reload()
  await expect(page.getByRole('alert').filter({ hasText: 'Summary counts could not be loaded.' })).toBeVisible()
  await page.getByRole('button', { name: 'Retry' }).click()
  await expect(strip.getByText('Account and settings administration')).toBeVisible()

  await expect(strip.getByText('No records yet').first()).toBeVisible()
  await expect(strip.getByText('Unavailable')).toBeVisible()
  mkdirSync(screenshots, { recursive: true })
  await page.screenshot({ path: resolve(screenshots, 'overview-light.png') })

  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})

test('overview explains when a role has no summary counts', async ({ page }) => {
  const problems = watchConsole(page)
  await page.route('**/api/dashboard', (route) => route.fulfill({ json: { widgets: [] } }))

  await signIn(page)

  await expect(page.getByText('No summary counts are available for this role yet.')).toBeVisible()
  await expect(page.locator('.summary-strip')).toHaveAttribute('aria-busy', 'false')
  expect(problems).toEqual([])
})
