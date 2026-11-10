import { Interaction, type InteractionItem, type InteractionModeFunction } from 'chart.js'

declare module 'chart.js' {
  interface InteractionModeMap {
    sameTime: InteractionModeFunction
  }
}

interface TimePoint {
  x: number
}

function indexOfTime(data: readonly unknown[], time: number): number {
  let low = 0
  let high = data.length - 1
  while (low <= high) {
    const middle = (low + high) >> 1
    const value = (data[middle] as TimePoint).x
    if (value === time) {
      return middle
    }
    if (value < time) {
      low = middle + 1
    } else {
      high = middle - 1
    }
  }
  return -1
}

const sameTime: InteractionModeFunction = (chart, event, options, useFinalPosition) => {
  const [nearest] = Interaction.modes.nearest(chart, event, { ...options, axis: 'x', intersect: false }, useFinalPosition)
  if (!nearest) {
    return []
  }
  const time = (chart.data.datasets[nearest.datasetIndex].data[nearest.index] as TimePoint).x
  const items: InteractionItem[] = []
  chart.data.datasets.forEach((dataset, datasetIndex) => {
    if (!chart.isDatasetVisible(datasetIndex)) {
      return
    }
    const index = indexOfTime(dataset.data, time)
    if (index >= 0) {
      items.push({ datasetIndex, index, element: chart.getDatasetMeta(datasetIndex).data[index] })
    }
  })
  return items
}

Interaction.modes.sameTime = sameTime
