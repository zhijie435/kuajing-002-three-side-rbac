import request from '@/utils/request'

export function getRoleList(appType) {
  return request.get(`/roles/${appType}`)
}

export function createRole(data) {
  return request.post('/roles', data)
}

export function updateRole(id, data) {
  return request.put(`/roles/${id}`, data)
}

export function deleteRole(id) {
  return request.delete(`/roles/${id}`)
}

export function getRoleMenus(roleId) {
  return request.get(`/roles/${roleId}/menus`)
}

export function assignRoleMenus(roleId, menuIds) {
  return request.post(`/roles/${roleId}/menus`, { menu_ids: menuIds })
}

export function getRolePermissions(roleId) {
  return request.get(`/roles/${roleId}/permissions`)
}

export function assignRolePermissions(roleId, permissionIds) {
  return request.post(`/roles/${roleId}/permissions`, { permission_ids: permissionIds })
}

export function batchAssignRoleMenus(roleIds, menuIds) {
  return request.post('/roles/batch/menus', { role_ids: roleIds, menu_ids: menuIds })
}

export function batchAssignRolePermissions(roleIds, permissionIds) {
  return request.post('/roles/batch/permissions', { role_ids: roleIds, permission_ids: permissionIds })
}
