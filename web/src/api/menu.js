import request from '@/utils/request'

export function getMenuList(appType) {
  return request.get(`/menus/${appType}`)
}

export function getMenuTree(appType) {
  return request.get(`/menus/${appType}/tree`)
}

export function createMenu(data) {
  return request.post('/menus', data)
}

export function updateMenu(id, data) {
  return request.put(`/menus/${id}`, data)
}

export function deleteMenu(id) {
  return request.delete(`/menus/${id}`)
}
