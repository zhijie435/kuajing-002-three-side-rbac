<template>
  <div class="permission-page">
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>权限管理</span>
          <div class="header-actions">
            <el-radio-group v-model="appType" size="small" @change="loadPermissions">
              <el-radio-button value="platform">平台端</el-radio-button>
              <el-radio-button value="merchant">商家端</el-radio-button>
              <el-radio-button value="warehouse">仓储端</el-radio-button>
            </el-radio-group>
            <el-button type="primary" size="small" @click="openForm(null)">
              <el-icon><Plus /></el-icon> 新增权限
            </el-button>
          </div>
        </div>
      </template>

      <el-table :data="permissionList" border stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="权限名称" min-width="120" />
        <el-table-column prop="code" label="权限编码" min-width="180" />
        <el-table-column prop="menu_name" label="关联菜单" min-width="120" />
        <el-table-column prop="description" label="描述" min-width="160" />
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openForm(row)">编辑</el-button>
            <el-popconfirm title="确认删除？" @confirm="handleDelete(row.id)">
              <template #reference>
                <el-button link type="danger" size="small">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="formVisible" :title="formData.id ? '编辑权限' : '新增权限'" width="520px" destroy-on-close>
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="权限名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入权限名称" />
        </el-form-item>
        <el-form-item label="权限编码" prop="code">
          <el-input v-model="formData.code" placeholder="如 system:role:add" />
        </el-form-item>
        <el-form-item label="关联菜单" prop="menu_id">
          <el-tree-select
            v-model="formData.menu_id"
            :data="menuTreeOptions"
            :props="{ label: 'name', value: 'id', children: 'children' }"
            check-strictly
            clearable
            placeholder="选择关联菜单"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" type="textarea" placeholder="请输入描述" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getPermissionList, createPermission, updatePermission, deletePermission } from '@/api/permission'
import { getMenuList } from '@/api/menu'

const appType = ref('platform')
const permissionList = ref([])
const menuTreeOptions = ref([])
const formVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref()

const defaultForm = () => ({
  id: null,
  name: '',
  code: '',
  menu_id: 0,
  description: '',
  status: 1,
  app_type: 'platform',
})

const formData = ref(defaultForm())

const formRules = {
  name: [{ required: true, message: '请输入权限名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入权限编码', trigger: 'blur' }],
}

async function loadPermissions() {
  const res = await getPermissionList(appType.value)
  permissionList.value = res.data || []
}

async function loadMenuOptions() {
  const res = await getMenuList(appType.value)
  menuTreeOptions.value = res.data || []
}

function openForm(row) {
  if (row) {
    formData.value = { ...row, app_type: appType.value }
  } else {
    const d = defaultForm()
    d.app_type = appType.value
    formData.value = d
  }
  loadMenuOptions()
  formVisible.value = true
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    if (formData.value.id) {
      await updatePermission(formData.value.id, formData.value)
      ElMessage.success('更新成功')
    } else {
      await createPermission(formData.value)
      ElMessage.success('创建成功')
    }
    formVisible.value = false
    loadPermissions()
  } finally {
    submitLoading.value = false
  }
}

async function handleDelete(id) {
  await deletePermission(id)
  ElMessage.success('删除成功')
  loadPermissions()
}

onMounted(() => {
  loadPermissions()
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
</style>
