import type { PointStyle } from 'chart.js'

const LINE_DASHES: number[][] = [[], [8, 4], [2, 4], [12, 4, 2, 4]]

export function pointStyleFor(icon: string | null): PointStyle {
  switch (icon) {
    case 'square':
      return 'rect'
    case 'triangle':
      return 'triangle'
    default:
      return 'circle'
  }
}

export function lineDashFor(index: number): number[] {
  return LINE_DASHES[index % LINE_DASHES.length]
}
