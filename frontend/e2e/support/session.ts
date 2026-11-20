import { expect, type Page } from '@playwright/test'
import { localCredentials } from './credentials'

export type ConsoleProblem = { type: string; text: string }

/** Collects browser console errors and uncaught page errors for a test. */
// The session probe intentionally returns 401 while signed out and a rejected
// sign-in returns 422; Chrome logs both failed responses as console errors.
// Those statuses are opt-out per test; every other error still fails.
export function watchConsole(page: Page, options: { ignoreStatuses?: number[] } = {}): ConsoleProblem[] {
  const ignore = new Set([401, ...(options.ignoreStatuses ?? [])])
  const problems: ConsoleProblem[] = []
  page.on('console', (message) => {
    if (message.type() !== 'error') return
    const status = message.text().match(/Failed to load resource: the server responded with a status of (\d{3})/)
    if (status && ignore.has(Number(status[1]))) return
    problems.push({ type: 'console', text: message.text() })
  })
  page.on('pageerror', (error) => problems.push({ type: 'pageerror', text: error.message }))
  return problems
}

export async function signIn(page: Page) {
  const { email, password } = localCredentials()
  await page.goto('/')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Overview' })).toBeVisible()
}
