import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/useAuth'
import { usePageTitle } from '../components/usePageTitle'

interface FieldErrors {
  username?: string
  password?: string
}

function validate(username: string, password: string): FieldErrors {
  const errors: FieldErrors = {}
  if (username.trim() === '') {
    errors.username = 'Podaj nazwę użytkownika.'
  }
  if (password === '') {
    errors.password = 'Podaj hasło.'
  }
  return errors
}

export function LoginPage() {
  usePageTitle('Logowanie')
  const { isLoggedIn, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const from = (location.state as { from?: string } | null)?.from ?? '/admin'

  if (isLoggedIn && !submitting) {
    return <Navigate to={from} replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const errors = validate(username, password)
    setFieldErrors(errors)
    setError(null)
    if (Object.keys(errors).length > 0) {
      return
    }

    setSubmitting(true)
    try {
      await login(username.trim(), password)
      navigate(from, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError && err.status === 401 ? 'Nieprawidłowa nazwa użytkownika lub hasło.' : err instanceof Error ? err.message : 'Logowanie nie powiodło się.')
      setSubmitting(false)
    }
  }

  return (
    <div className="narrow">
      <h1>Logowanie administratora</h1>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <div className="field">
          <label htmlFor="username">Nazwa użytkownika</label>
          <input
            id="username"
            name="username"
            autoComplete="username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            aria-invalid={fieldErrors.username ? true : undefined}
            aria-describedby={fieldErrors.username ? 'username-error' : undefined}
            required
          />
          {fieldErrors.username && (
            <p id="username-error" className="field-error">
              {fieldErrors.username}
            </p>
          )}
        </div>
        <div className="field">
          <label htmlFor="password">Hasło</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            aria-invalid={fieldErrors.password ? true : undefined}
            aria-describedby={fieldErrors.password ? 'password-error' : undefined}
            required
          />
          {fieldErrors.password && (
            <p id="password-error" className="field-error">
              {fieldErrors.password}
            </p>
          )}
        </div>
        <button type="submit" className="button" disabled={submitting}>
          {submitting ? 'Logowanie…' : 'Zaloguj'}
        </button>
      </form>
    </div>
  )
}
