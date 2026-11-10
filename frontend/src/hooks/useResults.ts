import { useEffect, useState } from 'react'
import { apiRequest, isAbortError } from '../api/client'
import type { Measurement } from '../api/types'

interface Result {
  key: string
  data: Measurement[]
  error: string | null
}

export interface ResultsState {
  data: Measurement[]
  loading: boolean
  error: string | null
  retry: () => void
}

export function useResults(seriesIds: number[], from: string | null, to: string | null, enabled: boolean): ResultsState {
  const [result, setResult] = useState<Result | null>(null)
  const [attempt, setAttempt] = useState(0)

  const params = new URLSearchParams({ series: seriesIds.join(','), limit: '10000' })
  if (from) {
    params.set('from', from)
  }
  if (to) {
    params.set('to', to)
  }
  const query = params.toString()
  const key = `${query}#${attempt}`
  const active = enabled && seriesIds.length > 0

  useEffect(() => {
    if (!active) {
      return
    }
    const controller = new AbortController()
    apiRequest<Measurement[]>(`/api/measurements?${query}`, { signal: controller.signal })
      .then((data) => setResult({ key, data, error: null }))
      .catch((error: unknown) => {
        if (!isAbortError(error)) {
          setResult({ key, data: [], error: error instanceof Error ? error.message : 'Nie udało się pobrać pomiarów.' })
        }
      })
    return () => controller.abort()
  }, [active, query, key])

  const current = result?.key === key ? result : null

  return {
    data: active ? (result?.data ?? []) : [],
    loading: active && current === null,
    error: active ? (current?.error ?? null) : null,
    retry: () => setAttempt((n) => n + 1),
  }
}
