import { Link } from 'react-router'
import { usePageTitle } from '../components/usePageTitle'

export function NotFoundPage() {
  usePageTitle('Nie znaleziono strony')

  return (
    <div className="narrow">
      <h1>Nie znaleziono strony</h1>
      <p>
        <Link to="/">Wróć do pomiarów</Link>
      </p>
    </div>
  )
}
