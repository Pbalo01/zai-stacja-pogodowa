const numberFormat = new Intl.NumberFormat('pl-PL', {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1,
  signDisplay: 'negative',
})

const dateTimeFormat = new Intl.DateTimeFormat('pl-PL', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

export function formatValue(value: number): string {
  return numberFormat.format(value)
}

export function formatDateTime(iso: string): string {
  return dateTimeFormat.format(new Date(iso))
}

export function formatWithUnit(value: number, unit: string | null): string {
  return unit ? `${formatValue(value)} ${unit}` : formatValue(value)
}
