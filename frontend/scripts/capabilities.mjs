// Reports the platform features the notification popover and glass depend on,
// measured in each installed Playwright engine rather than inferred.
import { chromium, firefox, webkit } from 'playwright'
import { readFileSync, mkdirSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const env = Object.fromEntries(
  readFileSync(resolve(here, '../../backend/.env.dev-local'), 'utf8')
    .split(/\r?\n/)
    .map((line) => line.match(/^([A-Z0-9_]+)=(.*)$/))
    .filter(Boolean)
    .map((match) => [match[1], match[2]]),
)

const engines = { chromium, firefox, webkit }
const rows = []

for (const [name, engine] of Object.entries(engines)) {
  const browser = await engine.launch()
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } })
  await page.goto('http://127.0.0.1:5173/')
  await page.getByLabel('Email').fill(env.DCMS_DEV_EMAIL)
  await page.getByLabel('Password', { exact: true }).fill(env.DCMS_DEV_PASSWORD)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await page.getByRole('heading', { level: 1, name: 'Overview' }).waitFor()

  const support = await page.evaluate(() => ({
    popover: Object.hasOwn(HTMLElement.prototype, 'popover'),
    anchorName: CSS.supports('anchor-name: --anchor'),
    positionArea: CSS.supports('position-area: block-end'),
    backdropFilter: CSS.supports('backdrop-filter: blur(1px)'),
    webkitBackdropFilter: CSS.supports('-webkit-backdrop-filter: blur(1px)'),
  }))

  await page.getByRole('button', { name: /^Notifications/ }).click()
  const panel = page.locator('#notifications-panel')
  await panel.waitFor({ state: 'visible' })
  const rendered = await panel.evaluate((element) => {
    const style = getComputedStyle(element)
    return {
      open: element.matches(':popover-open'),
      position: style.position,
      backdropFilter: style.backdropFilter,
      backgroundColor: style.backgroundColor,
      anchorName: style.anchorName,
      positionArea: style.positionArea,
    }
  })
  const box = await panel.boundingBox()
  mkdirSync(resolve(here, '../e2e/screenshots'), { recursive: true })
  await page.screenshot({ path: resolve(here, `../e2e/screenshots/notifications-${name}.png`) })
  rows.push({ engine: name, ...support, ...rendered, box: box ? `${Math.round(box.x)},${Math.round(box.y)} ${Math.round(box.width)}x${Math.round(box.height)}` : 'none' })
  await browser.close()
}

console.table(rows)
