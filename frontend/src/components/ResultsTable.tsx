import { useEffect, useMemo, useRef, useState, type CSSProperties, type KeyboardEvent } from 'react'
import type { Measurement, Series } from '../api/types'
import { formatDateTime, formatValue } from './format'
import { SeriesMarker } from './SeriesMarker'

function rowsLabel(count: number): string {
  if (count === 1) {
    return 'wiersz'
  }
  const lastDigit = count % 10
  const lastTwo = count % 100
  return lastDigit >= 2 && lastDigit <= 4 && (lastTwo < 12 || lastTwo > 14) ? 'wiersze' : 'wierszy'
}

interface Row {
  timestamp: string
  values: Map<number, number>
}

interface Overflow {
  scrollable: boolean
  atEnd: boolean
  scrollbarWidth: number
}

function useHorizontalOverflow() {
  const scrollRef = useRef<HTMLDivElement>(null)
  const tableRef = useRef<HTMLTableElement>(null)
  const [overflow, setOverflow] = useState<Overflow>({ scrollable: false, atEnd: true, scrollbarWidth: 0 })

  useEffect(() => {
    const container = scrollRef.current
    const table = tableRef.current
    if (!container || !table) {
      return
    }
    const measure = () => {
      const scrollable = container.scrollWidth > container.clientWidth + 1
      const atEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 1
      const scrollbarWidth = container.offsetWidth - container.clientWidth - container.clientLeft * 2
      setOverflow((current) =>
        current.scrollable === scrollable && current.atEnd === atEnd && current.scrollbarWidth === scrollbarWidth
          ? current
          : { scrollable, atEnd, scrollbarWidth },
      )
    }
    const observer = new ResizeObserver(measure)
    observer.observe(container)
    observer.observe(table)
    container.addEventListener('scroll', measure, { passive: true })
    return () => {
      observer.disconnect()
      container.removeEventListener('scroll', measure)
    }
  }, [])

  return { scrollRef, tableRef, overflow }
}

interface ResultsTableProps {
  series: Series[]
  measurements: Measurement[]
  selected: string | null
  onSelect: (timestamp: string | null) => void
}

export function ResultsTable({ series, measurements, selected, onSelect }: ResultsTableProps) {
  const { scrollRef, tableRef, overflow } = useHorizontalOverflow()
  const rows = useMemo(() => {
    const byTimestamp = new Map<string, Row>()
    for (const m of measurements) {
      const row = byTimestamp.get(m.timestamp) ?? { timestamp: m.timestamp, values: new Map<number, number>() }
      row.values.set(m.seriesId, m.value)
      byTimestamp.set(m.timestamp, row)
    }
    return [...byTimestamp.values()].sort((a, b) => Date.parse(b.timestamp) - Date.parse(a.timestamp))
  }, [measurements])

  function toggle(timestamp: string) {
    onSelect(selected === timestamp ? null : timestamp)
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTableRowElement>, timestamp: string) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      toggle(timestamp)
    }
  }

  return (
    <>
      <p id="table-hint" className="muted table-hint">
        Kliknij wiersz, aby wyróżnić punkty na wykresie, lub punkt na wykresie, aby wskazać wiersz.
      </p>
      {overflow.scrollable && (
        <p id="table-scroll-hint" className="muted table-hint table-scroll-hint">
          Przewiń w bok, aby zobaczyć pozostałe serie.
        </p>
      )}
      <div
        className={overflow.scrollable && !overflow.atEnd ? 'table-wrap has-more' : 'table-wrap'}
        style={{ '--scrollbar-width': `${overflow.scrollbarWidth}px` } as CSSProperties}
      >
        <div
          ref={scrollRef}
          className="table-scroll"
          role="region"
          aria-labelledby="table-caption"
          aria-describedby={overflow.scrollable ? 'table-scroll-hint' : undefined}
          tabIndex={0}
        >
          <table ref={tableRef} className="data-table" aria-describedby="table-hint">
            <caption id="table-caption">
              Wyniki pomiarów ({rows.length} {rowsLabel(rows.length)})
            </caption>
            <thead>
              <tr>
                <th scope="col">Czas</th>
                {series.map((s) => (
                  <th key={s.id} scope="col">
                    <span className="th-series">
                      <SeriesMarker color={s.color} icon={s.icon} />
                      {s.name}
                      {s.unit && <span className="th-unit"> [{s.unit}]</span>}
                    </span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={row.timestamp}
                  data-timestamp={row.timestamp}
                  tabIndex={0}
                  className={row.timestamp === selected ? 'is-selected' : undefined}
                  onClick={() => toggle(row.timestamp)}
                  onKeyDown={(event) => handleKeyDown(event, row.timestamp)}
                >
                  <th scope="row">
                    <time dateTime={row.timestamp}>{formatDateTime(row.timestamp)}</time>
                  </th>
                  {series.map((s) => {
                    const value = row.values.get(s.id)
                    return <td key={s.id}>{value === undefined ? '—' : formatValue(value)}</td>
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
