import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { localCredentials } from './support/credentials'
import { watchConsole } from './support/session'

const here = dirname(fileURLToPath(import.meta.url))

test('sign-in keeps only the product name, fields, submit and real errors', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/')

  await expect(page.getByRole('heading', { level: 1, name: 'DCMS' })).toBeVisible()
  await expect(page.getByLabel('Email')).toBeVisible()
  await expect(page.getByLabel('Password', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible()

  await expect(page.getByText('Welcome back.')).toHaveCount(0)
  await expect(page.getByText('Secure local sign-in')).toHaveCount(0)
  await expect(page.getByText(/contact a DCMS system administrator/)).toHaveCount(0)
  await expect(page.getByText('Sign in with your individual DCMS account.')).toHaveCount(0)

  mkdirSync(resolve(here, 'screenshots'), { recursive: true })
  await page.screenshot({ path: resolve(here, 'screenshots', 'auth-light.png') })

  expect(problems).toEqual([])
})

test('empty submit focuses the first invalid field and links its error text', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/')

  await page.getByRole('button', { name: 'Sign in' }).click()

  const email = page.getByLabel('Email')
  await expect(email).toBeFocused()
  await expect(email).toHaveAttribute('aria-invalid', 'true')
  await expect(email).toHaveAttribute('aria-describedby', 'login-email-error')
  await expect(page.locator('#login-email-error')).toHaveText('Enter your email address.')
  await expect(page.locator('#login-email-error')).toHaveAttribute('role', 'alert')
  await expect(page.locator('#login-password-error')).toHaveText('Enter your password.')
  await expect(page.getByLabel('Password', { exact: true })).toHaveAttribute('aria-invalid', 'true')

  // Fixing the email clears the message once it is valid.
  await email.fill('not-an-email')
  await expect(page.locator('#login-email-error')).toHaveText('Enter an email address in the format name@example.org.')
  await email.fill('admin@dcms.local')
  await expect(page.locator('#login-email-error')).toHaveCount(0)
  await expect(email).not.toHaveAttribute('aria-invalid', 'true')

  expect(problems).toEqual([])
})

test('password visibility toggle is keyboard operable and announces its state', async ({ page }) => {
  const problems = watchConsole(page)
  await page.goto('/')

  const password = page.getByLabel('Password', { exact: true })
  const toggle = page.getByRole('button', { name: 'Show password' })
  await expect(password).toHaveAttribute('type', 'password')
  await expect(toggle).toHaveAttribute('aria-pressed', 'false')

  await toggle.click()
  await expect(password).toHaveAttribute('type', 'text')
  await expect(page.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true')

  await page.getByRole('button', { name: 'Hide password' }).click()
  await expect(password).toHaveAttribute('type', 'password')

  expect(problems).toEqual([])
})

test('failed sign-in stays generic for a wrong password and an unknown account', async ({ page }) => {
  const problems = watchConsole(page, { ignoreStatuses: [422] })
  const { email } = localCredentials()
  await page.goto('/')

  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill('wrong-password-123')
  await page.getByRole('button', { name: 'Sign in' }).click()
  const wrongPassword = page.getByRole('alert').filter({ hasText: 'incorrect' })
  await expect(wrongPassword).toHaveText('The email or password is incorrect.')

  await page.getByLabel('Email').fill(`missing-${Date.now()}@dcms.local`)
  await page.getByLabel('Password', { exact: true }).fill('wrong-password-123')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('alert').filter({ hasText: 'incorrect' })).toHaveText('The email or password is incorrect.')

  expect(problems).toEqual([])
})

test('repeated failures hit the rate limiter with a generic retry message', async ({ page }) => {
  const problems = watchConsole(page, { ignoreStatuses: [422, 429] })
  const email = `throttle-${Date.now()}@dcms.local`
  await page.goto('/')

  let limited = false
  for (let attempt = 0; attempt < 7 && !limited; attempt += 1) {
    await page.getByLabel('Email').fill(email)
    await page.getByLabel('Password', { exact: true }).fill('wrong-password-123')
    await page.getByRole('button', { name: 'Sign in' }).click()
    const alert = page.getByRole('alert').first()
    await expect(alert).toBeVisible()
    const text = (await alert.textContent()) ?? ''
    if (text.startsWith('Too many sign-in attempts.')) limited = true
    else expect(text).toBe('The email or password is incorrect.')
  }

  expect(limited, 'the auth route did not rate-limit repeated failures').toBe(true)
  await expect(page.getByRole('alert').first()).toHaveText(/^Too many sign-in attempts\. Try again in \d+ seconds\.$/)
  // The limiter must not reveal whether the account exists.
  await expect(page.getByRole('alert').first()).not.toHaveText(/unknown|not found|no account/i)

  expect(problems).toEqual([])
})
