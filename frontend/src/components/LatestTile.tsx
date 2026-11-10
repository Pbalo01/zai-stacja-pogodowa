import type { Measurement, Series } from '../api/types'
import { formatDateTime, formatValue } from './format'
import { SeriesMarker } from './SeriesMarker'

interface LatestTileProps {
  series: Series
  latest: Measurement | null
}

export function LatestTile({ series, latest }: LatestTileProps) {
  const headingId = `tile-${series.id}`

  return (
    <article className="tile" aria-labelledby={headingId} style={{ borderTopColor: series.color }}>
      <h3 id={headingId} className="tile-name">
        <SeriesMarker color={series.color} icon={series.icon} />
        <span className="tile-name-text" title={series.name}>
          {series.name}
        </span>
      </h3>
      {latest ? (
        <>
          <p className="tile-value">
            {formatValue(latest.value)}
            {series.unit && <span className="tile-unit">{series.unit}</span>}
          </p>
          <p className="tile-time">
            <time dateTime={latest.timestamp}>{formatDateTime(latest.timestamp)}</time>
          </p>
        </>
      ) : (
        <p className="tile-empty">Brak odczytów</p>
      )}
    </article>
  )
}
