import {
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  TimeScale,
  Tooltip,
  type ActiveElement,
  type ChartData,
  type ChartOptions,
} from 'chart.js'
import 'chartjs-adapter-date-fns'
import { pl } from 'date-fns/locale'
import { useCallback, useEffect, useMemo, useRef } from 'react'
import { Line } from 'react-chartjs-2'
import type { Measurement, Series } from '../api/types'
import '../lib/chartTooltip'
import { lineDashFor, pointStyleFor } from '../lib/seriesStyle'
import { formatDateTime, formatWithUnit } from './format'

ChartJS.register(LineElement, PointElement, LinearScale, TimeScale, Tooltip, Legend)
ChartJS.defaults.color = '#a8b5c2'
ChartJS.defaults.borderColor = '#2e3843'
ChartJS.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"

interface Point {
  x: number
  y: number
}

interface Highlight {
  highlighted: string | null
  timestamps: string[][]
}

function applyHighlight(chart: ChartJS<'line', Point[]>, { highlighted, timestamps }: Highlight): void {
  const active: ActiveElement[] = []
  if (highlighted) {
    timestamps.forEach((list, datasetIndex) => {
      const index = list.indexOf(highlighted)
      if (index >= 0) {
        const element = chart.getDatasetMeta(datasetIndex).data[index]
        active.push({ datasetIndex, index, element })
      }
    })
  }
  chart.setActiveElements(active)
  const anchor = active[0]?.element
  chart.tooltip?.setActiveElements(active, { x: anchor?.x ?? 0, y: anchor?.y ?? 0 })
  chart.update()
}

interface TimeChartProps {
  series: Series[]
  styleIndex: Map<number, number>
  measurements: Measurement[]
  highlighted: string | null
  onPointSelect: (timestamp: string) => void
}

export function TimeChart({ series, styleIndex, measurements, highlighted, onPointSelect }: TimeChartProps) {
  const chartRef = useRef<ChartJS<'line', Point[]>>(null)
  const highlightRef = useRef<Highlight>({ highlighted: null, timestamps: [] })
  const onPointSelectRef = useRef(onPointSelect)

  useEffect(() => {
    onPointSelectRef.current = onPointSelect
  }, [onPointSelect])

  const restoreHighlight = useCallback(() => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const chart = chartRef.current
        if (chart && highlightRef.current.highlighted) {
          applyHighlight(chart, highlightRef.current)
        }
      })
    })
  }, [])

  const { data, timestamps } = useMemo(() => {
    const bySeries = new Map<number, Measurement[]>()
    for (const m of measurements) {
      const list = bySeries.get(m.seriesId) ?? []
      list.push(m)
      bySeries.set(m.seriesId, list)
    }
    const timestamps: string[][] = []
    const data: ChartData<'line', Point[]> = {
      datasets: series.map((s) => {
        const list = bySeries.get(s.id) ?? []
        timestamps.push(list.map((m) => m.timestamp))
        const pointStyle = pointStyleFor(s.icon)
        return {
          label: s.unit ? `${s.name} (${s.unit})` : s.name,
          data: list.map((m) => ({ x: Date.parse(m.timestamp), y: m.value })),
          borderColor: s.color,
          backgroundColor: s.color,
          borderDash: lineDashFor(styleIndex.get(s.id) ?? 0),
          borderWidth: 2,
          pointStyle,
          pointRadius: 3,
          pointHoverRadius: 7,
          tension: 0.2,
        }
      }),
    }
    return { data, timestamps }
  }, [series, styleIndex, measurements])

  const options = useMemo<ChartOptions<'line'>>(
    () => ({
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      onResize: restoreHighlight,
      onClick: (event, _active, chart) => {
        if (!event.native) {
          return
        }
        const [nearest] = chart.getElementsAtEventForMode(event.native, 'nearest', { intersect: false }, true)
        const timestamp = nearest && highlightRef.current.timestamps[nearest.datasetIndex]?.[nearest.index]
        if (timestamp) {
          onPointSelectRef.current(timestamp)
        }
      },
      interaction: { mode: 'sameTime', intersect: false },
      scales: {
        x: {
          type: 'time',
          adapters: { date: { locale: pl } },
          time: {
            tooltipFormat: 'dd MMM yyyy, HH:mm',
            displayFormats: { minute: 'HH:mm', hour: 'HH:mm', day: 'd MMM', week: 'd MMM', month: 'LLL yyyy' },
          },
          ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 16 },
        },
        y: {
          title: { display: true, text: 'Wartość' },
        },
      },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxHeight: 8, padding: 16 } },
        tooltip: {
          usePointStyle: true,
          callbacks: {
            label: (ctx) => {
              const s = series[ctx.datasetIndex]
              return `${s.name}: ${formatWithUnit(ctx.parsed.y ?? 0, s.unit)}`
            },
          },
        },
      },
    }),
    [series, restoreHighlight],
  )

  useEffect(() => {
    highlightRef.current = { highlighted, timestamps }
    const chart = chartRef.current
    if (chart) {
      applyHighlight(chart, highlightRef.current)
    }
  }, [highlighted, timestamps])

  const description = useMemo(() => {
    if (measurements.length === 0) {
      return 'Wykres pomiarów – brak danych w wybranym zakresie.'
    }
    const first = measurements.reduce((a, b) => (a.timestamp < b.timestamp ? a : b))
    const last = measurements.reduce((a, b) => (a.timestamp > b.timestamp ? a : b))
    const names = series.map((s) => s.name).join(', ')
    return `Wykres liniowy pomiarów w czasie dla serii: ${names}, od ${formatDateTime(first.timestamp)} do ${formatDateTime(last.timestamp)}. Wartości liczbowe znajdują się w tabeli poniżej.`
  }, [measurements, series])

  return (
    <div className="chart-container" onMouseLeave={restoreHighlight}>
      <Line ref={chartRef} data={data} options={options} role="img" aria-label={description} fallbackContent={<p>{description}</p>} />
    </div>
  )
}
