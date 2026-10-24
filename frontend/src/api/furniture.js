import { request } from './client'

export function listFurnitureModels(signal) {
  return request('/furniture-models', { signal })
}

export function getFurnitureModel(slug, signal) {
  return request(`/furniture-models/${encodeURIComponent(slug)}`, { signal })
}

export function createFurnitureModel(payload) {
  return request('/furniture-models', { method: 'POST', body: payload })
}

export function deleteFurnitureModel(slug) {
  return request(`/furniture-models/${encodeURIComponent(slug)}`, { method: 'DELETE' })
}
