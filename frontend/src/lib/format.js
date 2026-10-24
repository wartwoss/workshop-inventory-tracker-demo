const quantityFormatter = new Intl.NumberFormat('en-US', {
  maximumFractionDigits: 6,
})

const areaFormatter = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const eachFormatter = new Intl.NumberFormat('en-US', {
  maximumFractionDigits: 0,
})

const dateFormatter = new Intl.DateTimeFormat('en-US', {
  dateStyle: 'medium',
  timeStyle: 'short',
})

export function formatQuantity(value, { signed = false, unit, includeUnit = true } = {}) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  const number = Number(value)

  if (Number.isNaN(number)) {
    return String(value)
  }

  const prefix = number < 0 ? '−' : signed && number > 0 ? '+' : ''
  const normalizedUnit = String(unit ?? '').toLowerCase()
  const formatter = normalizedUnit === 'm2' || normalizedUnit === 'm²'
    ? areaFormatter
    : normalizedUnit === 'ea' && Number.isInteger(number)
      ? eachFormatter
      : quantityFormatter
  const text = `${prefix}${formatter.format(Math.abs(number))}`

  return unit && includeUnit ? `${text} ${unit}` : text
}

export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date)
}

export function unitLabel(unit) {
  return unit || ''
}

export function materialStatus(material) {
  const quantity = Number(material.quantity_on_hand)

  if (quantity <= 0) {
    return { tone: 'danger', label: 'Out of stock' }
  }

  if (
    material.reorder_level !== null &&
    material.reorder_level !== undefined &&
    quantity <= Number(material.reorder_level)
  ) {
    return { tone: 'warning', label: 'Low stock' }
  }

  return { tone: 'success', label: 'In stock' }
}
