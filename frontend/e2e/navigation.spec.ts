import { expect, test } from '@playwright/test'
import { signIn, watchConsole } from './support/session'

test('every visible nav entry opens a real view, not a placeholder', async ({ page }) => {
  test.setTimeout(120_000)
  const problems = watchConsole(page)
  await signIn(page)

  const items = page.locator('.sidebar .side-navigation .nav-item')
  const count = await items.count()
  expect(count).toBeGreaterThan(4)

  const visited: string[] = []
  for (let index = 0; index < count; index++) {
    const item = items.nth(index)
    const label = (await item.innerText()).trim()
    await item.click()
    await expect(page.getByRole('heading', { level: 1, name: label })).toBeVisible()
    await expect(page.locator('.module-placeholder')).toHaveCount(0)
    visited.push(label)
  }

  console.log(`\nVisited ${visited.length} nav entries: ${visited.join(', ')}`)
  expect(problems, `browser console problems:\n${problems.map((problem) => `${problem.type}: ${problem.text}`).join('\n')}`).toEqual([])
})
