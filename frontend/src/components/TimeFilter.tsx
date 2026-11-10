import { presetRange, type Preset, type TimeRange } from '../lib/timeRange'

const PRESETS: { value: Exclude<Preset, 'custom'>; label: string }[] = [
  { value: '24h', label: '24 h' },
  { value: '7d', label: '7 dni' },
  { value: 'all', label: 'Wszystko' },
]

interface TimeFilterProps {
  range: TimeRange
  error: string | null
  onChange: (range: TimeRange) => void
}

export function TimeFilter({ range, error, onChange }: TimeFilterProps) {
  return (
    <fieldset className="filter-group">
      <legend>Zakres czasu</legend>
      <div className="presets" role="group" aria-label="Szybkie zakresy">
        {PRESETS.map((preset) => (
          <button
            key={preset.value}
            type="button"
            className="button button-secondary button-small"
            aria-pressed={range.preset === preset.value}
            onClick={() => onChange(presetRange(preset.value))}
          >
            {preset.label}
          </button>
        ))}
      </div>
      <div className="field">
        <label htmlFor="range-from">Od</label>
        <input
          id="range-from"
          type="datetime-local"
          value={range.from}
          max={range.to || undefined}
          onChange={(e) => onChange({ ...range, preset: 'custom', from: e.target.value })}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? 'range-error' : undefined}
        />
      </div>
      <div className="field">
        <label htmlFor="range-to">Do</label>
        <input
          id="range-to"
          type="datetime-local"
          value={range.to}
          min={range.from || undefined}
          onChange={(e) => onChange({ ...range, preset: 'custom', to: e.target.value })}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? 'range-error' : undefined}
        />
      </div>
      {error && (
        <p id="range-error" className="field-error" role="alert">
          {error}
        </p>
      )}
    </fieldset>
  )
}
