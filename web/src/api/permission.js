import request from '@/utils/request'

export function getPermissionList(appType) {
  return request.get(`/permissions/${appType}`)
}

export function createPermission(data) {
  return request.post('/permissions', data)
}

export function updatePermission(id, data) {
  return request.put(`/permissions/${id}`, data)
}

export function deletePermission(id) {
  return request.delete(`/permissions/${id}`)
}
