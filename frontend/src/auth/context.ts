import { createContext } from 'react'

export interface AuthContextValue {
  token: string | null
  isLoggedIn: boolean
  notice: string | null
  login: (username: string, password: string) => Promise<void>
  logout: () => Promise<void>
  dismissNotice: () => void
}

export const AuthContext = createContext<AuthContextValue | null>(null)
