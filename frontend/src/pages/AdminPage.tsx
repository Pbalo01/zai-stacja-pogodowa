import { usePageTitle } from '../components/usePageTitle'

const SECTIONS = [
  { id: 'series', title: 'Serie' },
  { id: 'sensors', title: 'Czujniki' },
  { id: 'account', title: 'Konto' },
]

export function AdminPage() {
  usePageTitle('Administracja')

  return (
    <div className="admin">
      <h1>Administracja</h1>
      <nav aria-label="Sekcje administracji" className="section-nav">
        <ul>
          {SECTIONS.map((section) => (
            <li key={section.id}>
              <a href={`#${section.id}`}>{section.title}</a>
            </li>
          ))}
        </ul>
      </nav>
      {SECTIONS.map((section) => (
        <section key={section.id} id={section.id} aria-labelledby={`${section.id}-heading`} className="admin-section">
          <h2 id={`${section.id}-heading`}>{section.title}</h2>
          <p className="muted">Ta sekcja powstanie w kolejnym kroku.</p>
        </section>
      ))}
    </div>
  )
}
