import { useEffect, useMemo, useRef, useState } from 'react'
import { apiRequest, isAbortError } from '../api/client'
import type { Measurement, Series } from '../api/types'
import { formatDateTime } from '../components/format'
import { LatestTile } from '../components/LatestTile'
import { ResultsTable } from '../components/ResultsTable'
import { SeriesMarker } from '../components/SeriesMarker'
import { TimeChart } from '../components/TimeChart'
import { TimeFilter } from '../components/TimeFilter'
import { usePageTitle } from '../components/usePageTitle'
import { useMediaQuery } from '../hooks/useMediaQuery'
import { useResults } from '../hooks/useResults'
import { seriesCountLabel } from '../lib/plural'
import { describeRange, inputToIso, presetRange, rangeError, type TimeRange } from '../lib/timeRange'

const MOBILE_QUERY = '(max-width: 52rem)'

interface FiltersToggle {
  mobile: boolean
  open: boolean
}

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
  const [range, setRange] = useState<TimeRange>(() => presetRange('7d'))
  const [selected, setSelected] = useState<string | null>(null)
  const chartSectionRef = useRef<HTMLElement>(null)
  const tableSectionRef = useRef<HTMLElement>(null)
  const isMobile = useMediaQuery(MOBILE_QUERY)
  const [filtersToggle, setFiltersToggle] = useState<FiltersToggle | null>(null)
  const filtersOpen = filtersToggle?.mobile === isMobile ? filtersToggle.open : !isMobile

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

  const readings = useMemo(() => (state.status === 'ready' ? state.readings : []), [state])
  const visible = useMemo(() => readings.filter((r) => !hidden.has(r.series.id)), [readings, hidden])
  const visibleSeries = useMemo(() => visible.map((r) => r.series), [visible])
  const styleIndex = useMemo(() => new Map(readings.map((r, index) => [r.series.id, index])), [readings])

  const invalidRange = rangeError(range)
  const measurements = useResults(
    visibleSeries.map((s) => s.id),
    inputToIso(range.from),
    inputToIso(range.to),
    invalidRange === null,
  )
  const highlighted = selected !== null && measurements.data.some((m) => m.timestamp === selected) ? selected : null

  function changeRange(next: TimeRange) {
    setRange(next)
    setSelected(null)
  }

  function selectRow(timestamp: string | null) {
    setSelected(timestamp)
    const chart = chartSectionRef.current
    if (!timestamp || !chart) {
      return
    }
    const rect = chart.getBoundingClientRect()
    const visible = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0)
    if (visible < rect.height / 2) {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      chart.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' })
    }
  }

  function selectPoint(timestamp: string) {
    if (timestamp === highlighted) {
      setSelected(null)
      return
    }
    setSelected(timestamp)
    const row = tableSectionRef.current?.querySelector<HTMLTableRowElement>(`tr[data-timestamp="${CSS.escape(timestamp)}"]`)
    const container = row?.closest<HTMLElement>('.table-scroll')
    if (!row || !container) {
      return
    }
    row.focus({ preventScroll: true })
    const header = container.querySelector('thead')?.getBoundingClientRect().height ?? 0
    const box = container.getBoundingClientRect()
    const visibleTop = Math.max(box.top + header, 0)
    const visibleBottom = Math.min(box.bottom, window.innerHeight)
    const [top, bottom] = visibleBottom > visibleTop ? [visibleTop, visibleBottom] : [box.top + header, box.bottom]
    const rowBox = row.getBoundingClientRect()
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    container.scrollBy({
      top: rowBox.top + rowBox.height / 2 - (top + bottom) / 2,
      behavior: reduceMotion ? 'auto' : 'smooth',
    })
  }

  const hasData = measurements.data.length > 0
  const filtersSummary = `${seriesCountLabel(visibleSeries.length)} · ${describeRange(range)}`

  return (
    <>
      <h1>Pomiary</h1>
      <div className="dashboard">
        <aside className="sidebar" aria-labelledby="filters-heading">
          <details
            className="filters"
            open={filtersOpen}
            onToggle={(event) => setFiltersToggle({ mobile: isMobile, open: event.currentTarget.open })}
          >
            <summary>
              <h2 id="filters-heading">Filtry</h2>
              <span className="filters-summary">{filtersSummary}</span>
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
            <TimeFilter range={range} error={invalidRange} onChange={changeRange} />
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
                  <LatestTile key={series.id} series={series} latest={latest} />
                ))}
              </div>
            )}
          </section>

          <section aria-labelledby="chart-heading" ref={chartSectionRef}>
            <h2 id="chart-heading">Przebieg w czasie</h2>
            {measurements.loading && (
              <p className="status" role="status">
                Ładowanie pomiarów…
              </p>
            )}
            {measurements.error && (
              <div className="error" role="alert">
                <p>{measurements.error}</p>
                <button type="button" className="button" onClick={measurements.retry}>
                  Spróbuj ponownie
                </button>
              </div>
            )}
            {state.status === 'ready' && visibleSeries.length === 0 && <p className="muted">Zaznacz co najmniej jedną serię w filtrach.</p>}
            {visibleSeries.length > 0 && !measurements.loading && !measurements.error && !hasData && invalidRange === null && (
              <p className="muted">Brak pomiarów w wybranym zakresie czasu.</p>
            )}
            {hasData && (
              <TimeChart
                series={visibleSeries}
                styleIndex={styleIndex}
                measurements={measurements.data}
                highlighted={highlighted}
                onPointSelect={selectPoint}
              />
            )}
            <p className="visually-hidden" role="status">
              {highlighted ? `Wyróżniono na wykresie pomiary z ${formatDateTime(highlighted)}.` : ''}
            </p>
          </section>

          {hasData && (
            <section aria-labelledby="table-heading" ref={tableSectionRef}>
              <h2 id="table-heading">Tabela wyników</h2>
              <ResultsTable series={visibleSeries} measurements={measurements.data} selected={highlighted} onSelect={selectRow} />
            </section>
          )}
        </div>
      </div>
    </>
  )
}
