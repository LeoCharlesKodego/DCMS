import { expect, test, type Page } from '@playwright/test'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { averageRegion, contrastRatio, decodePng, hex, parseCssColor, type Rgb } from './support/contrast'
import { signIn, watchConsole } from './support/session'
import { localCredentials, seedNotifications } from './support/credentials'

const here = dirname(fileURLToPath(import.meta.url))
const screenshots = resolve(here, 'screenshots')

async function panelContrast(page: Page, mode: 'light' | 'dark') {
  const panel = await page.locator('#notifications-panel').boundingBox()
  if (!panel) throw new Error('Notification panel is not visible')
  const image = decodePng(await page.screenshot({ scale: 'css' }))
  const backgrounds: Rgb[] = [40, 140, 240]
    .filter((offset) => offset < panel.height)
    .map((offset) => averageRegion(image, panel.x + panel.width - 9, panel.y + offset, 3))

  const targets = [
    { label: 'Panel heading', selector: '.notification-head h2' },
    { label: 'Item title', selector: '.notification-item strong' },
    { label: 'Item body', selector: '.notification-item > span' },
    { label: 'Item timestamp', selector: '.notification-item small' },
  ]

  return Promise.all(targets.map(async (target) => {
    const color = parseCssColor(await page.locator(target.selector).first().evaluate((element) => getComputedStyle(element).color))
    const worst = Math.min(...backgrounds.map((background) => contrastRatio(color, background)))
    const worstBackground = backgrounds.reduce((lowest, sample) => (contrastRatio(color, sample) < contrastRatio(color, lowest) ? sample : lowest))
    return { mode, element: target.label, foreground: hex(color), worstBackground: hex(worstBackground), ratio: Number(worst.toFixed(2)), passesAA: worst >= 4.5 }
  }))
}

test('notification popover is native, dismissible, accessible and AA on glass', async ({ page }) => {
  test.setTimeout(120_000)
  seedNotifications()
  const problems = watchConsole(page)
  await signIn(page)

  const trigger = page.getByRole('button', { name: 'Notifications, 1 unread' })
  const panel = page.locator('#notifications-panel')

  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(panel).toBeHidden()
  await trigger.click()

  await expect(panel).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(panel).toHaveAttribute('popover', 'auto')
  await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible()
  await expect(panel.getByRole('listitem')).toHaveCount(2)
  await expect(panel.locator('.notification-item.is-unread')).toHaveCount(1)
  await expect(panel.getByText('Unread', { exact: true })).toBeVisible()

  mkdirSync(screenshots, { recursive: true })
  await page.screenshot({ path: resolve(screenshots, 'notifications-light.png') })
  const light = await panelContrast(page, 'light')

  await page.emulateMedia({ colorScheme: 'dark' })
  await page.waitForFunction(() => matchMedia('(prefers-color-scheme: dark)').matches)
  await page.screenshot({ path: resolve(screenshots, 'notifications-dark.png') })
  const dark = await panelContrast(page, 'dark')
  await page.emulateMedia({ colorScheme: 'light' })

  // Escape light-dismisses and returns focus to the invoker.
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
  await expect(trigger).toBeFocused()
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')

  // Clicking outside light-dismisses.
  await trigger.click()
  await expect(panel).toBeVisible()
  await page.mouse.click(240, 420)
  await expect(panel).toBeHidden()

  // Explicit close control.
  await trigger.click()
  await expect(panel).toBeVisible()
  await panel.getByRole('button', { name: 'Close notifications' }).click()
  await expect(panel).toBeHidden()
  await expect(trigger).toBeFocused()

  // Unread is not colour-only and marking read updates the trigger.
  await trigger.click()
  await panel.getByRole('button', { name: /mark read/i }).click()
  await expect(panel.locator('.notification-item.is-unread')).toHaveCount(0)
  await expect(panel.getByText('Unread', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Notifications', exact: true })).toHaveAttribute('aria-expanded', 'true')

  const rows = [...light, ...dark]
  writeFileSync(resolve(screenshots, 'notifications-contrast.json'), JSON.stringify(rows, null, 2))
  console.log('\nNotification glass text contrast (worst sampled backdrop pixel):')
  for (const row of rows) {
    console.log(`  ${row.mode.padEnd(5)} ${row.element.padEnd(16)} ${row.foreground} on ${row.worstBackground}  ${row.ratio.toFixed(2)}:1  ${row.passesAA ? 'AA' : 'FAIL'}`)
  }

  expect(rows.filter((row) => !row.passesAA), 'notification text below AA on glass').toEqual([])
  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})

test('falls back to a dismissible panel when the Popover API is missing', async ({ page }) => {
  const problems = watchConsole(page)
  await page.addInitScript(() => {
    delete (HTMLElement.prototype as { popover?: unknown }).popover
  })
  await page.goto('/')
  const supported = await page.evaluate(() => Object.hasOwn(HTMLElement.prototype, 'popover'))
  test.skip(supported, 'This browser cannot simulate a missing Popover API')

  const { email, password } = localCredentials()
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()

  const trigger = page.getByRole('button', { name: /^Notifications/ })
  const panel = page.locator('#notifications-panel')
  expect(await panel.getAttribute('popover')).toBeNull()

  await trigger.click()
  await expect(panel).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  const box = await panel.boundingBox()
  expect(box?.y ?? 0).toBeGreaterThan(60)

  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
  await expect(trigger).toBeFocused()

  await trigger.click()
  await page.mouse.click(200, 500)
  await expect(panel).toBeHidden()

  await trigger.click()
  await expect(panel).toBeVisible()
  await panel.getByRole('button', { name: 'Close notifications' }).click()
  await expect(panel).toBeHidden()
  await expect(trigger).toBeFocused()

  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})
