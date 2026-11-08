import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { apiRequest, setUnauthorizedHandler } from '../api/client'
import type { LoginResponse } from '../api/types'
import { AuthContext, type AuthContextValue } from './context'
import { readToken, writeToken } from './tokenStorage'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(readToken)
  const [notice, setNotice] = useState<string | null>(null)

  const storeToken = useCallback((value: string | null) => {
    writeToken(value)
    setToken(value)
  }, [])

  useEffect(() => {
    setUnauthorizedHandler(() => {
      storeToken(null)
      setNotice('Sesja wygasła lub token jest nieważny. Zaloguj się ponownie.')
    })
    return () => setUnauthorizedHandler(null)
  }, [storeToken])

  const login = useCallback(
    async (username: string, password: string) => {
      const result = await apiRequest<LoginResponse>('/api/auth/login', {
        method: 'POST',
        body: { username, password },
      })
      storeToken(result.accessToken)
      setNotice(null)
    },
    [storeToken],
  )

  const logout = useCallback(async () => {
    const current = token
    storeToken(null)
    if (current) {
      await apiRequest('/api/auth/logout', { method: 'POST', token: current, handleUnauthorized: false }).catch(() => undefined)
    }
  }, [token, storeToken])

  const dismissNotice = useCallback(() => setNotice(null), [])

  const value = useMemo<AuthContextValue>(
    () => ({ token, isLoggedIn: token !== null, notice, login, logout, dismissNotice }),
    [token, notice, login, logout, dismissNotice],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
