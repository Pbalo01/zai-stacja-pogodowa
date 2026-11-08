export interface Series {
  id: number
  name: string
  minValue: number
  maxValue: number
  color: string
  icon: string | null
  unit: string | null
}

export interface Measurement {
  id: number
  seriesId: number
  sensorId: number | null
  value: number
  timestamp: string
}

export interface FieldError {
  field: string
  message: string
}

export interface Problem {
  type?: string
  title: string
  status: number
  detail?: string
  errors?: FieldError[]
}

export interface LoginResponse {
  accessToken: string
  tokenType: 'Bearer'
  expiresIn: number
}
