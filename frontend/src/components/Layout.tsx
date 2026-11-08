import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../auth/useAuth'

export function Layout() {
  const { isLoggedIn, logout, notice, dismissNotice } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/')
  }

  return (
    <>
      <a className="skip-link" href="#main">
        Przejdź do treści
      </a>
      <header className="site-header">
        <Link to="/" className="brand">
          Domowa stacja pogodowa
        </Link>
        <nav aria-label="Nawigacja główna" className="site-nav">
          <ul>
            <li>
              <NavLink to="/" end>
                Pomiary
              </NavLink>
            </li>
            {isLoggedIn && (
              <li>
                <NavLink to="/admin">Administracja</NavLink>
              </li>
            )}
          </ul>
        </nav>
        <div className="session">
          {isLoggedIn ? (
            <button type="button" className="button button-secondary" onClick={handleLogout}>
              Wyloguj
            </button>
          ) : (
            <Link to="/login" className="button button-secondary">
              Zaloguj
            </Link>
          )}
        </div>
      </header>
      {notice && (
        <div className="notice" role="alert">
          <p>{notice}</p>
          <button type="button" className="button button-secondary" onClick={dismissNotice}>
            Zamknij
          </button>
        </div>
      )}
      <main id="main" tabIndex={-1}>
        <Outlet />
      </main>
    </>
  )
}
