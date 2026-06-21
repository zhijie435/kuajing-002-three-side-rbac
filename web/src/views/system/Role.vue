<template>
  <div class="role-page">
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>角色管理</span>
          <div class="header-actions">
            <el-radio-group v-model="appType" size="small" @change="loadRoles">
              <el-radio-button value="platform">平台端</el-radio-button>
              <el-radio-button value="merchant">商家端</el-radio-button>
              <el-radio-button value="warehouse">仓储端</el-radio-button>
            </el-radio-group>
            <el-button type="primary" size="small" @click="openRoleForm(null)">
              <el-icon><Plus /></el-icon> 新增角色
            </el-button>
          </div>
        </div>
      </template>

      <el-table :data="roleList" border stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="角色名称" min-width="120" />
        <el-table-column prop="code" label="角色编码" min-width="140" />
        <el-table-column prop="description" label="描述" min-width="180" />
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openRoleForm(row)">编辑</el-button>
            <el-button link type="primary" size="small" @click="openMenuAuth(row)">菜单授权</el-button>
            <el-button link type="primary" size="small" @click="openPermAuth(row)">操作权限</el-button>
            <el-button link type="primary" size="small" @click="openPermMatrix(row)">权限矩阵</el-button>
            <el-popconfirm title="确认删除该角色？" @confirm="handleDeleteRole(row.id)">
              <template #reference>
                <el-button link type="danger" size="small">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 角色 CRUD 弹窗 -->
    <el-dialog v-model="roleFormVisible" :title="roleFormData.id ? '编辑角色' : '新增角色'" width="500px" destroy-on-close>
      <el-form ref="roleFormRef" :model="roleFormData" :rules="roleFormRules" label-width="80px">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="roleFormData.name" placeholder="请输入角色名称" />
        </el-form-item>
        <el-form-item label="角色编码" prop="code">
          <el-input v-model="roleFormData.code" placeholder="请输入角色编码" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="roleFormData.description" type="textarea" placeholder="请输入描述" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="roleFormData.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="roleFormVisible = false">取消</el-button>
        <el-button type="primary" :loading="roleSubmitLoading" @click="handleSubmitRole">确定</el-button>
      </template>
    </el-dialog>

    <!-- 菜单授权弹窗 -->
    <el-dialog v-model="menuAuthVisible" title="菜单授权" width="500px" destroy-on-close>
      <p style="margin-bottom: 12px; color: #909399">
        角色：<strong>{{ currentRole.name }}</strong>
      </p>
      <el-tree
        ref="menuTreeRef"
        :data="menuOptions"
        show-checkbox
        node-key="id"
        :default-checked-keys="checkedMenuKeys"
        :props="{ label: 'name', children: 'children' }"
        check-strictly
      />
      <template #footer>
        <el-button @click="menuAuthVisible = false">取消</el-button>
        <el-button type="primary" :loading="menuAuthLoading" @click="handleAssignMenus">确定</el-button>
      </template>
    </el-dialog>

    <!-- 操作权限授权弹窗 -->
    <el-dialog v-model="permAuthVisible" title="操作权限授权" width="600px" destroy-on-close>
      <p style="margin-bottom: 12px; color: #909399">
        角色：<strong>{{ currentRole.name }}</strong>
      </p>
      <el-checkbox v-model="permCheckAll" :indeterminate="permIndeterminate" @change="handlePermCheckAll">
        全选
      </el-checkbox>
      <el-divider style="margin: 12px 0" />
      <el-checkbox-group v-model="checkedPermIds">
        <div v-for="group in permissionGroups" :key="group.menuName" style="margin-bottom: 16px">
          <div style="font-weight: 600; margin-bottom: 6px; color: #303133">{{ group.menuName }}</div>
          <el-checkbox
            v-for="perm in group.permissions"
            :key="perm.id"
            :value="perm.id"
            style="margin-right: 16px; margin-bottom: 4px"
          >
            {{ perm.name }}（{{ perm.code }}）
          </el-checkbox>
        </div>
      </el-checkbox-group>
      <template #footer>
        <el-button @click="permAuthVisible = false">取消</el-button>
        <el-button type="primary" :loading="permAuthLoading" @click="handleAssignPermissions">确定</el-button>
      </template>
    </el-dialog>

    <!-- 权限矩阵弹窗 -->
    <el-dialog v-model="matrixVisible" title="角色权限矩阵" width="90%" top="5vh" destroy-on-close>
      <p style="margin-bottom: 12px; color: #909399">
        角色：<strong>{{ currentRole.name }}</strong>（{{ currentRole.code }}）
      </p>
      <div class="matrix-wrap">
        <el-table :data="matrixData" border size="small" max-height="60vh">
          <el-table-column prop="menuName" label="菜单" min-width="140" fixed />
          <el-table-column prop="permissionName" label="操作" min-width="120" />
          <el-table-column prop="permissionCode" label="权限标识" min-width="180" />
          <el-table-column label="授权" width="80" align="center">
            <template #default="{ row }">
              <el-checkbox
                v-model="row.checked"
                @change="(val) => handleMatrixCheck(row, val)"
              />
            </template>
          </el-table-column>
        </el-table>
      </div>
      <template #footer>
        <el-button @click="matrixVisible = false">取消</el-button>
        <el-button type="primary" :loading="matrixLoading" @click="handleMatrixSave">保存矩阵</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getRoleList,
  createRole,
  updateRole,
  deleteRole,
  getRoleMenus,
  assignRoleMenus,
  getRolePermissions,
  assignRolePermissions,
} from '@/api/role'
import { getMenuList } from '@/api/menu'
import { getPermissionList } from '@/api/permission'

const appType = ref('platform')
const roleList = ref([])
const menuOptions = ref([])
const allPermissions = ref([])

const roleFormVisible = ref(false)
const roleSubmitLoading = ref(false)
const roleFormRef = ref()

const menuAuthVisible = ref(false)
const menuAuthLoading = ref(false)
const menuTreeRef = ref()
const checkedMenuKeys = ref([])

const permAuthVisible = ref(false)
const permAuthLoading = ref(false)
const checkedPermIds = ref([])
const allPermIds = ref([])

const matrixVisible = ref(false)
const matrixLoading = ref(false)
const matrixData = ref([])

const currentRole = ref({})

const defaultRoleForm = () => ({
  id: null,
  name: '',
  code: '',
  description: '',
  status: 1,
  app_type: 'platform',
})

const roleFormData = ref(defaultRoleForm())

const roleFormRules = {
  name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入角色编码', trigger: 'blur' }],
}

const permCheckAll = computed({
  get() {
    return checkedPermIds.value.length === allPermIds.value.length && allPermIds.value.length > 0
  },
  set() {},
})

const permIndeterminate = computed(() => {
  return checkedPermIds.value.length > 0 && checkedPermIds.value.length < allPermIds.value.length
})

const permissionGroups = computed(() => {
  const map = {}
  for (const p of allPermissions.value) {
    const key = p.menu_name || '未关联菜单'
    if (!map[key]) map[key] = { menuName: key, permissions: [] }
    map[key].permissions.push(p)
  }
  return Object.values(map)
})

async function loadRoles() {
  const res = await getRoleList(appType.value)
  roleList.value = res.data || []
}

async function loadMenuOptions() {
  const res = await getMenuList(appType.value)
  menuOptions.value = res.data || []
}

async function loadAllPermissions() {
  const res = await getPermissionList(appType.value)
  allPermissions.value = res.data || []
  allPermIds.value = allPermissions.value.map((p) => p.id)
}

function openRoleForm(row) {
  if (row) {
    roleFormData.value = { ...row, app_type: appType.value }
  } else {
    const d = defaultRoleForm()
    d.app_type = appType.value
    roleFormData.value = d
  }
  roleFormVisible.value = true
}

async function handleSubmitRole() {
  const valid = await roleFormRef.value.validate().catch(() => false)
  if (!valid) return

  roleSubmitLoading.value = true
  try {
    if (roleFormData.value.id) {
      await updateRole(roleFormData.value.id, roleFormData.value)
      ElMessage.success('更新成功')
    } else {
      await createRole(roleFormData.value)
      ElMessage.success('创建成功')
    }
    roleFormVisible.value = false
    loadRoles()
  } finally {
    roleSubmitLoading.value = false
  }
}

async function handleDeleteRole(id) {
  await deleteRole(id)
  ElMessage.success('删除成功')
  loadRoles()
}

async function openMenuAuth(role) {
  currentRole.value = role
  await loadMenuOptions()
  const res = await getRoleMenus(role.id)
  checkedMenuKeys.value = res.data || []
  menuAuthVisible.value = true
  await nextTick()
  menuTreeRef.value?.setCheckedKeys(checkedMenuKeys.value)
}

async function handleAssignMenus() {
  menuAuthLoading.value = true
  try {
    const checkedKeys = menuTreeRef.value.getCheckedKeys()
    const halfCheckedKeys = menuTreeRef.value.getHalfCheckedKeys()
    const allKeys = [...checkedKeys, ...halfCheckedKeys]
    await assignRoleMenus(currentRole.value.id, allKeys)
    ElMessage.success('菜单授权成功')
    menuAuthVisible.value = false
  } finally {
    menuAuthLoading.value = false
  }
}

async function openPermAuth(role) {
  currentRole.value = role
  await loadAllPermissions()
  const res = await getRolePermissions(role.id)
  checkedPermIds.value = res.data || []
  permAuthVisible.value = true
}

function handlePermCheckAll(val) {
  checkedPermIds.value = val ? [...allPermIds.value] : []
}

async function handleAssignPermissions() {
  permAuthLoading.value = true
  try {
    await assignRolePermissions(currentRole.value.id, checkedPermIds.value)
    ElMessage.success('权限授权成功')
    permAuthVisible.value = false
  } finally {
    permAuthLoading.value = false
  }
}

async function openPermMatrix(role) {
  currentRole.value = role
  await loadMenuOptions()
  await loadAllPermissions()

  const [menuRes, permRes] = await Promise.all([
    getRoleMenus(role.id),
    getRolePermissions(role.id),
  ])
  const grantedMenuIds = new Set(menuRes.data || [])
  const grantedPermIds = new Set(permRes.data || [])

  const flatMenus = flattenMenus(menuOptions.value)
  const rows = []
  for (const menu of flatMenus) {
    const perms = allPermissions.value.filter((p) => p.menu_id === menu.id)
    const menuChecked = grantedMenuIds.has(menu.id)
    if (perms.length > 0) {
      for (const perm of perms) {
        rows.push({
          menuId: menu.id,
          menuName: menu.name,
          permissionId: perm.id,
          permissionName: perm.name,
          permissionCode: perm.code,
          menuChecked,
          permChecked: grantedPermIds.has(perm.id),
          checked: grantedPermIds.has(perm.id),
          type: 'permission',
        })
      }
    } else {
      rows.push({
        menuId: menu.id,
        menuName: menu.name,
        permissionId: null,
        permissionName: '-',
        permissionCode: '-',
        menuChecked,
        permChecked: false,
        checked: menuChecked,
        type: 'menu',
      })
    }
  }
  matrixData.value = rows
  matrixVisible.value = true
}

function handleMatrixCheck(row, val) {
  if (row.type === 'menu') {
    row.menuChecked = val
    row.checked = val
    for (const r of matrixData.value) {
      if (r.menuId === row.menuId && r.type === 'permission') {
        r.checked = val
        r.permChecked = val
      }
    }
  } else {
    row.permChecked = val
    row.checked = val
  }
}

async function handleMatrixSave() {
  matrixLoading.value = true
  try {
    const menuIds = new Set()
    const permIds = []
    for (const row of matrixData.value) {
      if (row.type === 'menu' && row.checked) {
        menuIds.add(row.menuId)
      }
      if (row.type === 'permission' && row.checked) {
        menuIds.add(row.menuId)
        permIds.push(row.permissionId)
      }
    }
    await Promise.all([
      assignRoleMenus(currentRole.value.id, [...menuIds]),
      assignRolePermissions(currentRole.value.id, permIds),
    ])
    ElMessage.success('权限矩阵保存成功')
    matrixVisible.value = false
  } finally {
    matrixLoading.value = false
  }
}

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

onMounted(() => {
  loadRoles()
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}
.matrix-wrap {
  overflow-x: auto;
}
</style>
