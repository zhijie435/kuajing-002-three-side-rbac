import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/store/user'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', noAuth: true },
  },
  {
    path: '/',
    component: () => import('@/layout/index.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '首页' },
      },
      {
        path: 'system/role',
        name: 'Role',
        component: () => import('@/views/system/Role.vue'),
        meta: { title: '角色管理', permissionKey: 'system:role:list' },
      },
      {
        path: 'system/menu',
        name: 'Menu',
        component: () => import('@/views/system/Menu.vue'),
        meta: { title: '菜单管理', permissionKey: 'system:menu:list' },
      },
      {
        path: 'system/permission',
        name: 'Permission',
        component: () => import('@/views/system/Permission.vue'),
        meta: { title: '权限管理', permissionKey: 'system:permission:list' },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()

  if (to.meta.noAuth) {
    next()
    return
  }

  if (!userStore.token) {
    next('/login')
    return
  }

  if (!userStore.userInfo.id) {
    try {
      await userStore.fetchUserInfo()
    } catch {
      userStore.logout()
      next('/login')
      return
    }
  }

  if (to.meta.permissionKey && !userStore.hasPermission(to.meta.permissionKey)) {
    next('/dashboard')
    return
  }

  next()
})

export default router
