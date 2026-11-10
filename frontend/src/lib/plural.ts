export function seriesCountLabel(count: number): string {
  if (count === 1) {
    return '1 seria'
  }
  const lastDigit = count % 10
  const lastTwo = count % 100
  const few = lastDigit >= 2 && lastDigit <= 4 && (lastTwo < 12 || lastTwo > 14)
  return `${count} ${few ? 'serie' : 'serii'}`
}
