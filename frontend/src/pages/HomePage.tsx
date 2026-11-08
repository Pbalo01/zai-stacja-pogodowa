import { useEffect, useState } from 'react'
import { apiRequest, isAbortError } from '../api/client'
import type { Measurement, Series } from '../api/types'
import { ReadingTile } from '../components/ReadingTile'
import { SeriesMarker } from '../components/SeriesMarker'
import { usePageTitle } from '../components/usePageTitle'

interface Reading {
  series: Series
  latest: Measurement | null
}

type LoadState = { status: 'loading' } | { status: 'error'; message: string } | { status: 'ready'; readings: Reading[] }

async function loadReadings(signal: AbortSignal): Promise<Reading[]> {
  const series = await apiRequest<Series[]>('/api/series', { signal })
  return Promise.all(
    series.map(async (s) => {
      const [latest] = await apiRequest<Measurement[]>(`/api/measurements?series=${s.id}&sort=-timestamp&limit=1`, { signal })
      return { series: s, latest: latest ?? null }
    }),
  )
}

export function HomePage() {
  usePageTitle('Pomiary')
  const [state, setState] = useState<LoadState>({ status: 'loading' })
  const [reloadKey, setReloadKey] = useState(0)
  const [hidden, setHidden] = useState<ReadonlySet<number>>(new Set())

  useEffect(() => {
    const controller = new AbortController()
    loadReadings(controller.signal)
      .then((readings) => setState({ status: 'ready', readings }))
      .catch((error: unknown) => {
        if (!isAbortError(error)) {
          setState({ status: 'error', message: error instanceof Error ? error.message : 'Nie udało się pobrać danych.' })
        }
      })
    return () => controller.abort()
  }, [reloadKey])

  function retry() {
    setState({ status: 'loading' })
    setReloadKey((key) => key + 1)
  }

  function toggleSeries(id: number) {
    setHidden((current) => {
      const next = new Set(current)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  const readings = state.status === 'ready' ? state.readings : []
  const visible = readings.filter((r) => !hidden.has(r.series.id))

  return (
    <>
      <h1>Pomiary</h1>
      <div className="dashboard">
        <aside className="sidebar" aria-labelledby="filters-heading">
          <details className="filters" open>
            <summary>
              <h2 id="filters-heading">Filtry</h2>
            </summary>
            <fieldset className="filter-group">
              <legend>Widoczne serie</legend>
              {readings.length === 0 && <p className="muted">Brak serii do wyboru.</p>}
              {readings.map(({ series }) => (
                <label key={series.id} className="checkbox">
                  <input type="checkbox" checked={!hidden.has(series.id)} onChange={() => toggleSeries(series.id)} />
                  <SeriesMarker color={series.color} icon={series.icon} />
                  {series.name}
                </label>
              ))}
            </fieldset>
          </details>
        </aside>

        <div className="content">
          <section aria-labelledby="latest-heading">
            <h2 id="latest-heading">Ostatnie odczyty</h2>
            {state.status === 'loading' && (
              <p className="status" role="status">
                Ładowanie danych…
              </p>
            )}
            {state.status === 'error' && (
              <div className="error" role="alert">
                <p>{state.message}</p>
                <button type="button" className="button" onClick={retry}>
                  Spróbuj ponownie
                </button>
              </div>
            )}
            {state.status === 'ready' && visible.length === 0 && <p className="muted">Brak serii do wyświetlenia.</p>}
            {visible.length > 0 && (
              <div className="tiles">
                {visible.map(({ series, latest }) => (
                  <ReadingTile key={series.id} series={series} latest={latest} />
                ))}
              </div>
            )}
          </section>
        </div>
      </div>
    </>
  )
}
