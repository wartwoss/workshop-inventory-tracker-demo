const API_BASE_URL = (
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1'
).replace(/\/+$/, '')

export class ApiError extends Error {
  constructor(message, { status = 0, errors = {} } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

function parseJson(text) {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

export async function request(path, { method = 'GET', body, headers = {}, signal } = {}) {
  let response

  const isFormData = body instanceof FormData

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined && !isFormData ? { 'Content-Type': 'application/json' } : {}),
        ...headers,
      },
      body: body !== undefined ? (isFormData ? body : JSON.stringify(body)) : undefined,
      signal,
    })
  } catch (error) {
    if (error.name === 'AbortError') {
      throw error
    }

    throw new ApiError(
      'Unable to reach the API. Make sure the Laravel server is running and VITE_API_BASE_URL is correct.',
      { status: 0 },
    )
  }

  const text = await response.text()
  const payload = text ? parseJson(text) : null

  if (!response.ok) {
    throw new ApiError(
      payload?.message || `The API returned an error (HTTP ${response.status}).`,
      { status: response.status, errors: payload?.errors ?? {} },
    )
  }

  return payload
}

export function withQuery(path, params = {}) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '' || value === false) {
      return
    }

    query.set(key, value === true ? '1' : String(value))
  })

  const suffix = query.toString()

  return suffix ? `${path}?${suffix}` : path
}

export { API_BASE_URL }
