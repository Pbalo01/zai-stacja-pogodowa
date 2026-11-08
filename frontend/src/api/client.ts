import type { Problem } from './types'

export class ApiError extends Error {
  readonly status: number
  readonly problem: Problem | null

  constructor(status: number, problem: Problem | null, fallback: string) {
    super(problem?.detail ?? problem?.title ?? fallback)
    this.name = 'ApiError'
    this.status = status
    this.problem = problem
  }
}

interface RequestOptions {
  method?: string
  body?: unknown
  token?: string | null
  signal?: AbortSignal
  handleUnauthorized?: boolean
}

let unauthorizedHandler: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

function isProblem(data: unknown): data is Problem {
  return typeof data === 'object' && data !== null && 'status' in data && 'title' in data
}

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError'
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, token, signal, handleUnauthorized = true } = options
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  let response: Response
  try {
    response = await fetch(path, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
    })
  } catch (error) {
    if (isAbortError(error)) {
      throw error
    }
    throw new ApiError(0, null, 'Brak połączenia z serwerem. Sprawdź połączenie i spróbuj ponownie.')
  }

  if (response.status === 204) {
    return undefined as T
  }

  const data: unknown = await response.json().catch(() => null)
  if (!response.ok) {
    if (response.status === 401 && token && handleUnauthorized) {
      unauthorizedHandler?.()
    }
    throw new ApiError(response.status, isProblem(data) ? data : null, `Błąd serwera (${response.status}). Spróbuj ponownie później.`)
  }

  return data as T
}
