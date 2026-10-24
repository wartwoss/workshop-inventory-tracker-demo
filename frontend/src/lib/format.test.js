import { describe, expect, it } from 'vitest'
import { formatQuantity } from './format'

describe('formatQuantity unit precision', () => {
  it('always displays square metres with two decimal places', () => {
    expect(formatQuantity('99.1256789', { unit: 'm2' })).toBe('99.13 m2')
    expect(formatQuantity('4', { unit: 'm2' })).toBe('4.00 m2')
  })

  it('displays each-based quantities as whole numbers', () => {
    expect(formatQuantity('12.000000', { unit: 'ea' })).toBe('12 ea')
  })

  it('keeps the existing precision for other units', () => {
    expect(formatQuantity('1.2345', { unit: 'm' })).toBe('1.2345 m')
  })
})
