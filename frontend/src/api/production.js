import { request } from './client'

export function quoteConfiguration(payload) {
  return request('/production/quote', { method: 'POST', body: payload })
}

export function confirmProductionOrder(payload, idempotencyKey) {
  return request('/production-orders/confirm', {
    method: 'POST',
    body: payload,
    headers: {
      'Idempotency-Key': idempotencyKey,
    },
  })
}
