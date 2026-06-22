<template>
  <div>
    <el-row :gutter="20">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>当前用户</template>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="用户名">{{ userStore.userInfo.username }}</el-descriptions-item>
            <el-descriptions-item label="姓名">{{ userStore.userInfo.realname }}</el-descriptions-item>
            <el-descriptions-item label="所属端">{{ userStore.getAppTypeLabel(userStore.appType) }}</el-descriptions-item>
            <el-descriptions-item label="角色">{{ userStore.role.name }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>菜单权限</template>
          <el-statistic title="已授权菜单数" :value="flattenMenus(userStore.menus).length" />
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>操作权限</template>
          <el-statistic title="已授权操作数" :value="userStore.permissions.length" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { useUserStore } from '@/store/user'

const userStore = useUserStore()

function flattenMenus(menus) {
  const result = []
  for (const menu of menus) {
    result.push(menu)
    if (menu.children && menu.children.length) {
      result.push(...flattenMenus(menu.children))
    }
  }
  return result
}
</script>
