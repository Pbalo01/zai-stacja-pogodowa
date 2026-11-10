export type Preset = '24h' | '7d' | 'all' | 'custom'

export interface TimeRange {
  preset: Preset
  from: string
  to: string
}

const HOUR = 60 * 60 * 1000

const pad = (n: number) => String(n).padStart(2, '0')

export function toInputValue(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function presetRange(preset: Exclude<Preset, 'custom'>, now = new Date()): TimeRange {
  if (preset === 'all') {
    return { preset, from: '', to: '' }
  }
  const hours = preset === '24h' ? 24 : 7 * 24
  return { preset, from: toInputValue(new Date(now.getTime() - hours * HOUR)), to: '' }
}

export function inputToIso(value: string): string | null {
  if (value === '') {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

export function rangeError(range: TimeRange): string | null {
  const from = inputToIso(range.from)
  const to = inputToIso(range.to)
  if (from && to && from > to) {
    return 'Data początkowa musi być wcześniejsza niż końcowa.'
  }
  return null
}

const shortFormat = new Intl.DateTimeFormat('pl-PL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

export function describeRange(range: TimeRange): string {
  switch (range.preset) {
    case '24h':
      return '24 h'
    case '7d':
      return '7 dni'
    case 'all':
      return 'wszystko'
    default: {
      const from = range.from ? shortFormat.format(new Date(range.from)) : 'początek'
      const to = range.to ? shortFormat.format(new Date(range.to)) : 'teraz'
      return `${from} – ${to}`
    }
  }
}
