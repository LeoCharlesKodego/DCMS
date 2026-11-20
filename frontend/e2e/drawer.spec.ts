import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { signIn, watchConsole } from './support/session'

const here = dirname(fileURLToPath(import.meta.url))

test.use({ viewport: { width: 430, height: 900 } })

test('mobile drawer opens and closes with Escape, backdrop and focus return', async ({ page }) => {
  const problems = watchConsole(page)
  await signIn(page)

  const trigger = page.getByRole('button', { name: 'Open navigation' })
  const drawer = page.locator('#nav-dialog')

  await expect(trigger).toBeVisible()
  await expect(drawer).toBeHidden()

  await trigger.click()
  await expect(drawer).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(page.locator('body')).toHaveCSS('overflow', 'hidden')
  const box = await drawer.boundingBox()
  expect(box?.width ?? 999).toBeLessThanOrEqual(430)

  mkdirSync(resolve(here, 'screenshots'), { recursive: true })
  await page.screenshot({ path: resolve(here, 'screenshots', 'drawer-open.png') })

  await page.keyboard.press('Escape')
  await expect(drawer).toBeHidden()
  await expect(trigger).toBeFocused()
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden')

  await trigger.click()
  await expect(drawer).toBeVisible()
  await page.mouse.click(400, 450)
  await expect(drawer).toBeHidden()
  await expect(trigger).toBeFocused()

  await trigger.click()
  await expect(drawer).toBeVisible()
  await drawer.getByRole('button', { name: 'Institution setup' }).click()
  await expect(drawer).toBeHidden()
  await expect(page.getByRole('heading', { level: 1, name: 'Institution setup' })).toBeVisible()

  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})
