interface SeriesMarkerProps {
  color: string
  icon: string | null
  size?: number
}

export function SeriesMarker({ color, icon, size = 14 }: SeriesMarkerProps) {
  return (
    <svg className="series-marker" width={size} height={size} viewBox="0 0 14 14" aria-hidden="true" focusable="false">
      {icon === 'square' && <rect x="1.5" y="1.5" width="11" height="11" fill={color} />}
      {icon === 'triangle' && <polygon points="7,1 13,13 1,13" fill={color} />}
      {icon !== 'square' && icon !== 'triangle' && <circle cx="7" cy="7" r="5.5" fill={color} />}
    </svg>
  )
}
