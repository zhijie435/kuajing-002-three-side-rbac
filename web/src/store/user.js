import { defineStore } from 'pinia'
import { ref } from 'vue'
import { login as loginApi, getUserInfo as getUserInfoApi } from '@/api/auth'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('token') || '')
  const userInfo = ref({})
  const role = ref({})
  const menus = ref([])
  const permissions = ref([])
  const permissionCodes = ref(new Set())
  const appType = ref('platform')

  const APP_TYPE_MAP = {
    platform: '平台端',
    merchant: '商家端',
    warehouse: '仓储端',
  }

  function getAppTypeLabel(type) {
    return APP_TYPE_MAP[type] || type
  }

  async function login(loginForm) {
    const res = await loginApi(loginForm)
    token.value = res.data.token
    localStorage.setItem('token', res.data.token)
    userInfo.value = res.data.user
    role.value = res.data.role
    menus.value = res.data.menus
    permissions.value = res.data.permissions
    appType.value = res.data.user.app_type
    permissionCodes.value = new Set(res.data.permissions.map((p) => p.code))
  }

  async function fetchUserInfo() {
    const res = await getUserInfoApi()
    userInfo.value = res.data.user
    role.value = res.data.role
    menus.value = res.data.menus
    permissions.value = res.data.permissions
    appType.value = res.data.user.app_type
    permissionCodes.value = new Set(res.data.permissions.map((p) => p.code))
  }

  function hasPermission(code) {
    return permissionCodes.value.has(code)
  }

  function logout() {
    token.value = ''
    userInfo.value = {}
    role.value = {}
    menus.value = []
    permissions.value = []
    permissionCodes.value = new Set()
    localStorage.removeItem('token')
  }

  return {
    token,
    userInfo,
    role,
    menus,
    permissions,
    permissionCodes,
    appType,
    login,
    fetchUserInfo,
    hasPermission,
    logout,
    getAppTypeLabel,
  }
})
