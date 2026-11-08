const numberFormat = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2 })
const dateTimeFormat = new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' })

export function formatValue(value: number): string {
  return numberFormat.format(value)
}

export function formatDateTime(iso: string): string {
  return dateTimeFormat.format(new Date(iso))
}
