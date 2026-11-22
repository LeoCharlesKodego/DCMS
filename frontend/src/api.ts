export const uploadLimitLabel = import.meta.env.MODE === 'ngrok' ? '2 MiB' : '50 MiB'

/** An API failure that keeps the status and the per-field validation messages. */
export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[]>

  constructor(message: string, status: number, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

/** Returns the first message the server recorded for each field. */
export function firstFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError)) return {}
  return Object.fromEntries(
    Object.entries(error.fieldErrors).map(([field, messages]) => [field, messages[0] ?? 'This value is not accepted.']),
  )
}

export async function authRequest(
  path: string,
  method = 'GET',
  body?: Record<string, unknown>,
) {
  const headers = new Headers({ Accept: 'application/json' })

  if (body) {
    await fetch('/api/auth/csrf-cookie', { credentials: 'same-origin' })
    const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))
    if (xsrfCookie) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)))
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(`/api${path}`, {
    method,
    headers,
    credentials: 'same-origin',
    body: body ? JSON.stringify(body) : undefined,
  })
  const result = await response.json().catch(() => ({}))

  if (!response.ok) {
    const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : undefined
    throw new ApiError(
      validationMessage ?? result.message ?? 'The request could not be completed.',
      response.status,
      result.errors ?? {},
    )
  }

  return result
}

export async function authUpload(path: string, form: FormData, method = 'POST') {
  await fetch('/api/auth/csrf-cookie', { credentials: 'same-origin' })
  const headers = new Headers({ Accept: 'application/json' })
  const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))
  if (xsrfCookie) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)))

  const response = await fetch(`/api${path}`, { method, headers, credentials: 'same-origin', body: form })
  const result = await response.json().catch(() => ({}))
  if (!response.ok) {
    const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : undefined
    throw new ApiError(
      validationMessage ?? result.message ?? 'The request could not be completed.',
      response.status,
      result.errors ?? {},
    )
  }
  return result
}
