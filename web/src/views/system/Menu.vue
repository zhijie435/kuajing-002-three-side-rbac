<template>
  <div class="menu-page">
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>菜单管理</span>
          <div class="header-actions">
            <el-radio-group v-model="appType" size="small" @change="loadMenus">
              <el-radio-button value="platform">平台端</el-radio-button>
              <el-radio-button value="merchant">商家端</el-radio-button>
              <el-radio-button value="warehouse">仓储端</el-radio-button>
            </el-radio-group>
            <el-button type="primary" size="small" @click="openForm(null)">
              <el-icon><Plus /></el-icon> 新增菜单
            </el-button>
          </div>
        </div>
      </template>

      <el-table
        :data="menuTree"
        row-key="id"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        border
        default-expand-all
      >
        <el-table-column prop="name" label="菜单名称" min-width="180" />
        <el-table-column prop="icon" label="图标" width="80" align="center">
          <template #default="{ row }">
            <el-icon v-if="row.icon"><component :is="row.icon" /></el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="type" label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTagMap[row.type]" size="small">{{ typeLabelMap[row.type] }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="path" label="路由路径" min-width="160" />
        <el-table-column prop="component" label="组件路径" min-width="160" />
        <el-table-column prop="permission_key" label="权限标识" min-width="160" />
        <el-table-column prop="sort_order" label="排序" width="70" align="center" />
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openForm(row)">编辑</el-button>
            <el-button link type="primary" size="small" @click="openForm(null, row.id)">新增子菜单</el-button>
            <el-popconfirm title="确认删除？" @confirm="handleDelete(row.id)">
              <template #reference>
                <el-button link type="danger" size="small">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="formVisible" :title="formData.id ? '编辑菜单' : '新增菜单'" width="600px" destroy-on-close>
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="90px">
        <el-form-item label="上级菜单" prop="parent_id">
          <el-tree-select
            v-model="formData.parent_id"
            :data="parentTreeOptions"
            :props="{ label: 'name', value: 'id', children: 'children' }"
            check-strictly
            clearable
            placeholder="顶级菜单"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="菜单类型" prop="type">
          <el-radio-group v-model="formData.type">
            <el-radio value="directory">目录</el-radio>
            <el-radio value="menu">菜单</el-radio>
            <el-radio value="button">按钮</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="菜单名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入菜单名称" />
        </el-form-item>
        <el-form-item label="路由路径" prop="path">
          <el-input v-model="formData.path" placeholder="请输入路由路径" />
        </el-form-item>
        <el-form-item label="图标" prop="icon">
          <el-input v-model="formData.icon" placeholder="Element Plus 图标名，如 Setting" />
        </el-form-item>
        <el-form-item label="组件路径" prop="component">
          <el-input v-model="formData.component" placeholder="如 system/role/index" />
        </el-form-item>
        <el-form-item label="权限标识" prop="permission_key">
          <el-input v-model="formData.permission_key" placeholder="如 system:role:list" />
        </el-form-item>
        <el-form-item label="排序" prop="sort_order">
          <el-input-number v-model="formData.sort_order" :min="0" />
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
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { getMenuList, createMenu, updateMenu, deleteMenu } from '@/api/menu'

const appType = ref('platform')
const menuTree = ref([])
const formVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref()

const typeLabelMap = { directory: '目录', menu: '菜单', button: '按钮' }
const typeTagMap = { directory: '', menu: 'success', button: 'warning' }

const defaultForm = () => ({
  id: null,
  parent_id: 0,
  name: '',
  path: '',
  icon: '',
  component: '',
  type: 'menu',
  permission_key: '',
  sort_order: 0,
  status: 1,
  app_type: 'platform',
})

const formData = ref(defaultForm())

const formRules = {
  name: [{ required: true, message: '请输入菜单名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择菜单类型', trigger: 'change' }],
}

const parentTreeOptions = computed(() => {
  const root = [{ id: 0, name: '顶级菜单', children: menuTree.value }]
  return root
})

async function loadMenus() {
  const res = await getMenuList(appType.value)
  menuTree.value = res.data || []
}

function openForm(row, parentId) {
  if (row) {
    formData.value = { ...row, app_type: appType.value }
  } else {
    const d = defaultForm()
    d.parent_id = parentId || 0
    d.app_type = appType.value
    formData.value = d
  }
  formVisible.value = true
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    if (formData.value.id) {
      await updateMenu(formData.value.id, formData.value)
      ElMessage.success('更新成功')
    } else {
      await createMenu(formData.value)
      ElMessage.success('创建成功')
    }
    formVisible.value = false
    loadMenus()
  } finally {
    submitLoading.value = false
  }
}

async function handleDelete(id) {
  await deleteMenu(id)
  ElMessage.success('删除成功')
  loadMenus()
}

onMounted(() => {
  loadMenus()
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
