import { describe, expect, it } from 'vitest'
import { formatDuration, formatValue } from './format'

describe('formatDuration', () => {
  it('renders sub-millisecond durations in microseconds', () => {
    expect(formatDuration(500)).toBe('500μs')
  })

  it('renders sub-second durations in milliseconds', () => {
    expect(formatDuration(2_500)).toBe('2.5ms')
  })

  it('renders durations over a second in seconds', () => {
    expect(formatDuration(1_500_000)).toBe('1.50s')
  })

  it('renders null as an em dash', () => {
    expect(formatDuration(null)).toBe('—')
  })
})

describe('formatValue', () => {
  it('renders booleans as Yes/No', () => {
    expect(formatValue(true, 'boolean')).toBe('Yes')
    expect(formatValue(false, 'boolean')).toBe('No')
  })

  it('renders empty values as an em dash', () => {
    expect(formatValue('', undefined)).toBe('—')
    expect(formatValue(null, undefined)).toBe('—')
  })

  it('passes through plain values unchanged', () => {
    expect(formatValue('hello', undefined)).toBe('hello')
  })
})
