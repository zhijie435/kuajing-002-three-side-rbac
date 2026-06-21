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

      <div class="batch-bar" v-if="selectedRoles.length > 0">
        <span class="batch-tip">
          已选择 <strong>{{ selectedRoles.length }}</strong> 个角色
        </span>
        <el-button type="primary" size="small" @click="openBatchMenuAuth">
          批量菜单授权
        </el-button>
        <el-button type="primary" size="small" @click="openBatchPermAuth">
          批量操作权限
        </el-button>
        <el-button size="small" @click="clearSelection">取消选择</el-button>
      </div>

      <el-table
        :data="roleList"
        border
        stripe
        ref="roleTableRef"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="50" />
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

    <!-- 批量菜单授权弹窗 -->
    <el-dialog v-model="batchMenuAuthVisible" title="批量菜单授权" width="500px" destroy-on-close>
      <p style="margin-bottom: 12px; color: #909399">
        已选择 <strong>{{ selectedRoles.length }}</strong> 个角色，将为它们统一设置菜单权限
      </p>
      <el-tree
        ref="batchMenuTreeRef"
        :data="menuOptions"
        show-checkbox
        node-key="id"
        :default-checked-keys="batchCheckedMenuKeys"
        :props="{ label: 'name', children: 'children' }"
        check-strictly
      />
      <template #footer>
        <el-button @click="batchMenuAuthVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchMenuAuthLoading" @click="handleBatchAssignMenus">确定</el-button>
      </template>
    </el-dialog>

    <!-- 批量操作权限授权弹窗 -->
    <el-dialog v-model="batchPermAuthVisible" title="批量操作权限授权" width="600px" destroy-on-close>
      <p style="margin-bottom: 12px; color: #909399">
        已选择 <strong>{{ selectedRoles.length }}</strong> 个角色，将为它们统一设置操作权限
      </p>
      <el-checkbox v-model="batchPermCheckAll" :indeterminate="batchPermIndeterminate" @change="handleBatchPermCheckAll">
        全选
      </el-checkbox>
      <el-divider style="margin: 12px 0" />
      <el-checkbox-group v-model="batchCheckedPermIds">
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
        <el-button @click="batchPermAuthVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchPermAuthLoading" @click="handleBatchAssignPermissions">确定</el-button>
      </template>
    </el-dialog>

    <!-- 批量授权结果弹窗 -->
    <el-dialog v-model="batchResultVisible" title="批量授权结果" width="560px" destroy-on-close>
      <div class="batch-result-summary">
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="总数">
            <span>{{ batchResultData.total }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="成功">
            <span style="color: #67c23a; font-weight: 600">{{ batchResultData.success_count }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="失败">
            <span style="color: #f56c6c; font-weight: 600">{{ batchResultData.fail_count }}</span>
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <div v-if="batchResultData.fail_details && batchResultData.fail_details.length > 0" style="margin-top: 16px">
        <div style="font-weight: 600; margin-bottom: 8px; color: #f56c6c">失败明细</div>
        <el-table :data="batchResultData.fail_details" border size="small" max-height="300px">
          <el-table-column prop="role_id" label="角色ID" width="80" />
          <el-table-column prop="role_name" label="角色名称" min-width="120">
            <template #default="{ row }">
              {{ row.role_name || '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="role_app_type" label="所属端" width="100">
            <template #default="{ row }">
              {{ row.role_app_type ? appTypeLabel(row.role_app_type) : '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="reason" label="失败原因" min-width="200" />
        </el-table>
      </div>

      <template #footer>
        <el-button type="primary" @click="batchResultVisible = false">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getRoleList,
  createRole,
  updateRole,
  deleteRole,
  getRoleMenus,
  assignRoleMenus,
  getRolePermissions,
  assignRolePermissions,
  batchAssignRoleMenus,
  batchAssignRolePermissions,
} from '@/api/role'
import { getMenuList } from '@/api/menu'
import { getPermissionList } from '@/api/permission'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()

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

const roleTableRef = ref()
const selectedRoles = ref([])

const batchMenuAuthVisible = ref(false)
const batchMenuAuthLoading = ref(false)
const batchMenuTreeRef = ref()
const batchCheckedMenuKeys = ref([])

const batchPermAuthVisible = ref(false)
const batchPermAuthLoading = ref(false)
const batchCheckedPermIds = ref([])

const batchResultVisible = ref(false)
const batchResultData = ref({
  total: 0,
  success_count: 0,
  fail_count: 0,
  fail_details: [],
})

const batchPermCheckAll = computed({
  get() {
    return batchCheckedPermIds.value.length === allPermIds.value.length && allPermIds.value.length > 0
  },
  set() {},
})

const batchPermIndeterminate = computed(() => {
  return batchCheckedPermIds.value.length > 0 && batchCheckedPermIds.value.length < allPermIds.value.length
})

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
  clearSelection()
  const res = await getRoleList(appType.value)
  roleList.value = res.data || []
}

async function loadMenuOptions(targetAppType) {
  const app = targetAppType || appType.value
  const res = await getMenuList(app)
  menuOptions.value = res.data || []
}

async function loadAllPermissions(targetAppType) {
  const app = targetAppType || appType.value
  const res = await getPermissionList(app)
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
  await loadMenuOptions(role.app_type)
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

    if (allKeys.length === 0) {
      try {
        await ElMessageBox.confirm(
          '当前未勾选任何菜单，提交后该角色将失去所有菜单访问权限，确认继续？',
          '清空菜单授权',
          {
            confirmButtonText: '确认清空',
            cancelButtonText: '取消',
            type: 'warning',
          }
        )
      } catch {
        menuAuthLoading.value = false
        return
      }
    }

    const res = await assignRoleMenus(currentRole.value.id, allKeys)
    ElMessage.success(res.message || '菜单授权成功')
    menuAuthVisible.value = false
    await afterAuthRefresh()
  } finally {
    menuAuthLoading.value = false
  }
}

async function openPermAuth(role) {
  currentRole.value = role
  await loadAllPermissions(role.app_type)
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
    if (checkedPermIds.value.length === 0) {
      try {
        await ElMessageBox.confirm(
          '当前未勾选任何操作权限，提交后该角色将失去所有操作权限，确认继续？',
          '清空操作权限',
          {
            confirmButtonText: '确认清空',
            cancelButtonText: '取消',
            type: 'warning',
          }
        )
      } catch {
        permAuthLoading.value = false
        return
      }
    }

    const res = await assignRolePermissions(currentRole.value.id, checkedPermIds.value)
    ElMessage.success(res.message || '权限授权成功')
    permAuthVisible.value = false
    await afterAuthRefresh()
  } finally {
    permAuthLoading.value = false
  }
}

async function openPermMatrix(role) {
  currentRole.value = role
  await loadMenuOptions(role.app_type)
  await loadAllPermissions(role.app_type)

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

    if (menuIds.size === 0 && permIds.length === 0) {
      try {
        await ElMessageBox.confirm(
          '当前未勾选任何权限，提交后该角色将失去所有菜单和操作权限，确认继续？',
          '清空角色权限',
          {
            confirmButtonText: '确认清空',
            cancelButtonText: '取消',
            type: 'warning',
          }
        )
      } catch {
        matrixLoading.value = false
        return
      }
    }

    const [menuRes, permRes] = await Promise.all([
      assignRoleMenus(currentRole.value.id, [...menuIds]),
      assignRolePermissions(currentRole.value.id, permIds),
    ])
    ElMessage.success(`权限矩阵保存成功（菜单 ${menuRes.data?.count ?? menuIds.size} 个，权限 ${permRes.data?.count ?? permIds.length} 个）`)
    matrixVisible.value = false
    await afterAuthRefresh()
  } finally {
    matrixLoading.value = false
  }
}

function handleSelectionChange(selection) {
  selectedRoles.value = selection
}

function clearSelection() {
  roleTableRef.value?.clearSelection()
}

function validateSelectedRolesSameAppType() {
  if (selectedRoles.value.length === 0) {
    ElMessage.warning('请先选择角色')
    return null
  }
  const appTypes = [...new Set(selectedRoles.value.map((r) => r.app_type))]
  if (appTypes.length > 1) {
    ElMessage.warning('批量授权仅支持选择同一端的角色')
    return null
  }
  return appTypes[0]
}

function appTypeLabel(type) {
  const map = {
    platform: '平台端',
    merchant: '商家端',
    warehouse: '仓储端',
  }
  return map[type] || type
}

async function openBatchMenuAuth() {
  const targetAppType = validateSelectedRolesSameAppType()
  if (!targetAppType) return
  await loadMenuOptions(targetAppType)
  batchCheckedMenuKeys.value = []
  batchMenuAuthVisible.value = true
  await nextTick()
  batchMenuTreeRef.value?.setCheckedKeys([])
}

async function handleBatchAssignMenus() {
  batchMenuAuthLoading.value = true
  try {
    const checkedKeys = batchMenuTreeRef.value.getCheckedKeys()
    const halfCheckedKeys = batchMenuTreeRef.value.getHalfCheckedKeys()
    const allKeys = [...checkedKeys, ...halfCheckedKeys]

    if (allKeys.length === 0) {
      try {
        await ElMessageBox.confirm(
          '当前未勾选任何菜单，提交后选中角色将失去所有菜单访问权限，确认继续？',
          '批量清空菜单授权',
          {
            confirmButtonText: '确认清空',
            cancelButtonText: '取消',
            type: 'warning',
          }
        )
      } catch {
        batchMenuAuthLoading.value = false
        return
      }
    }

    const roleIds = selectedRoles.value.map((r) => r.id)
    const res = await batchAssignRoleMenus(roleIds, allKeys)
    batchResultData.value = res.data
    batchMenuAuthVisible.value = false
    batchResultVisible.value = true
    await afterBatchAuthRefresh()
  } finally {
    batchMenuAuthLoading.value = false
  }
}

async function openBatchPermAuth() {
  const targetAppType = validateSelectedRolesSameAppType()
  if (!targetAppType) return
  await loadAllPermissions(targetAppType)
  batchCheckedPermIds.value = []
  batchPermAuthVisible.value = true
}

function handleBatchPermCheckAll(val) {
  batchCheckedPermIds.value = val ? [...allPermIds.value] : []
}

async function handleBatchAssignPermissions() {
  batchPermAuthLoading.value = true
  try {
    if (batchCheckedPermIds.value.length === 0) {
      try {
        await ElMessageBox.confirm(
          '当前未勾选任何操作权限，提交后选中角色将失去所有操作权限，确认继续？',
          '批量清空操作权限',
          {
            confirmButtonText: '确认清空',
            cancelButtonText: '取消',
            type: 'warning',
          }
        )
      } catch {
        batchPermAuthLoading.value = false
        return
      }
    }

    const roleIds = selectedRoles.value.map((r) => r.id)
    const res = await batchAssignRolePermissions(roleIds, batchCheckedPermIds.value)
    batchResultData.value = res.data
    batchPermAuthVisible.value = false
    batchResultVisible.value = true
    await afterBatchAuthRefresh()
  } finally {
    batchPermAuthLoading.value = false
  }
}

async function afterBatchAuthRefresh() {
  await loadRoles()
  clearSelection()
}

async function afterAuthRefresh() {
  await loadRoles()
  if (
    userStore.role &&
    userStore.role.id === currentRole.value.id &&
    userStore.appType === appType.value
  ) {
    await userStore.fetchUserInfo()
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
.batch-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  margin-bottom: 12px;
  background-color: #ecf5ff;
  border: 1px solid #d9ecff;
  border-radius: 4px;
}
.batch-tip {
  flex: 1;
  color: #409eff;
  font-size: 14px;
}
.batch-tip strong {
  color: #409eff;
  font-weight: 600;
  margin: 0 4px;
}
.batch-result-summary {
  margin-bottom: 8px;
}
</style>
