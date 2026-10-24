import { request, withQuery } from './client'

export function getDashboard(signal) {
  return request('/dashboard', { signal })
}

export function listMaterials(params = {}, signal) {
  return request(
    withQuery('/materials', {
      search: params.search,
      material_group: params.material_group,
      low_stock_only: params.low_stock_only,
      include_inactive: params.include_inactive,
      page: params.page,
      per_page: params.per_page,
    }),
    { signal },
  )
}

export function getMaterial(id, signal) {
  return request(`/materials/${id}`, { signal })
}

export function createMaterial(payload) {
  return request('/materials', { method: 'POST', body: payload })
}

export function updateMaterial(id, payload) {
  return request(`/materials/${id}`, { method: 'PUT', body: payload })
}

export function adjustStock(id, payload) {
  return request(`/materials/${id}/adjust`, { method: 'POST', body: payload })
}

export function listMovements(materialId, params = {}, signal) {
  return request(
    withQuery(`/materials/${materialId}/movements`, {
      type: params.type,
      page: params.page,
      per_page: params.per_page,
    }),
    { signal },
  )
}

export function deleteMaterial(id) {
  return request(`/materials/${id}`, { method: 'DELETE' })
}
