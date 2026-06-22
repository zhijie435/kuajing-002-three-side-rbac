import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const currentAppType = ref('platform')

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setCurrentAppType(type) {
    currentAppType.value = type
  }

  return {
    sidebarCollapsed,
    currentAppType,
    toggleSidebar,
    setCurrentAppType,
  }
})
