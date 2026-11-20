import { expect, test, type Page } from '@playwright/test'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { averageRegion, contrastRatio, decodePng, hex, parseCssColor, type Rgb } from './support/contrast'
import { signIn, watchConsole } from './support/session'

const here = dirname(fileURLToPath(import.meta.url))
const screenshots = resolve(here, 'screenshots')

type Target = { label: string; selector: string; panel: 'sidebar' | 'topbar' }

const targets: Target[] = [
  { label: 'Brand name', selector: '.sidebar .brand-name', panel: 'sidebar' },
  { label: 'Brand caption', selector: '.sidebar .brand-caption', panel: 'sidebar' },
  { label: 'Section label', selector: '.sidebar .nav-section-label', panel: 'sidebar' },
  { label: 'Active nav item', selector: '.sidebar .nav-item[aria-current="page"] span', panel: 'sidebar' },
  { label: 'Inactive nav item', selector: '.sidebar .nav-item:not([aria-current]) span', panel: 'sidebar' },
  { label: 'Institution status', selector: '.sidebar .institution-status', panel: 'sidebar' },
  { label: 'User name', selector: '.sidebar .sidebar-user strong', panel: 'sidebar' },
  { label: 'User role', selector: '.sidebar .sidebar-user-copy span', panel: 'sidebar' },
  { label: 'Avatar initial', selector: '.topbar .top-user-avatar', panel: 'topbar' },
]

async function panelBackgrounds(page: Page, positions: { x: number; y: number }[]) {
  const image = decodePng(await page.screenshot({ scale: 'css' }))
  return positions.map((position) => averageRegion(image, position.x, position.y, 3))
}

async function measure(page: Page, mode: 'light' | 'dark') {
  const sidebar = await page.locator('.sidebar').boundingBox()
  const topbar = await page.locator('.topbar').boundingBox()
  if (!sidebar || !topbar) throw new Error('Shell panels are not visible')

  const sidebarSamples = await panelBackgrounds(
    page,
    [40, 220, 400, 620, 860].map((y) => ({ x: sidebar.x + sidebar.width - 10, y })),
  )
  const topbarSamples = await panelBackgrounds(
    page,
    [0.35, 0.5, 0.65].map((fraction) => ({
      x: topbar.x + topbar.width * fraction,
      y: topbar.y + topbar.height / 2,
    })),
  )

  const rows = []
  for (const target of targets) {
    const locator = page.locator(target.selector).first()
    if ((await locator.count()) === 0) continue
    const color = parseCssColor(await locator.evaluate((element) => getComputedStyle(element).color))
    const samples: Rgb[] = target.panel === 'sidebar' ? sidebarSamples : topbarSamples
    const worst = Math.min(...samples.map((background) => contrastRatio(color, background)))
    rows.push({
      mode,
      element: target.label,
      foreground: hex(color),
      worstBackground: hex(samples.reduce((lowest, sample) => (contrastRatio(color, sample) < contrastRatio(color, lowest) ? sample : lowest))),
      ratio: Number(worst.toFixed(2)),
      passesAA: worst >= 4.5,
    })
  }

  return rows
}

test('authenticated shell renders and glass text meets AA on the rendered backdrop', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)

  await signIn(page)

  await expect(page.locator('.sidebar')).toBeVisible()
  await expect(page.locator('.topbar')).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible()
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()
  await expect(page.locator('.sidebar .nav-item[aria-current="page"]')).toHaveCount(1)
  await expect(page.getByRole('button', { name: /notifications/i })).toBeVisible()

  mkdirSync(screenshots, { recursive: true })
  await page.screenshot({ path: resolve(screenshots, 'shell-light.png') })
  const light = await measure(page, 'light')

  await page.emulateMedia({ colorScheme: 'dark' })
  await page.waitForFunction(() => matchMedia('(prefers-color-scheme: dark)').matches)
  await page.screenshot({ path: resolve(screenshots, 'shell-dark.png') })
  const dark = await measure(page, 'dark')
  await page.emulateMedia({ colorScheme: 'light' })

  const rows = [...light, ...dark]
  writeFileSync(resolve(screenshots, 'shell-contrast.json'), JSON.stringify(rows, null, 2))
  console.log('\nGlass text contrast (worst sampled backdrop pixel):')
  for (const row of rows) {
    console.log(`  ${row.mode.padEnd(5)} ${row.element.padEnd(20)} ${row.foreground} on ${row.worstBackground}  ${row.ratio.toFixed(2)}:1  ${row.passesAA ? 'AA' : 'FAIL'}`)
  }

  expect(rows.filter((row) => !row.passesAA), 'glass text below AA on the real backdrop').toEqual([])
  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})
