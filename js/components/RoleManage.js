const RoleManageComponent = {
    props: {
        terminal: {
            type: String,
            default: 'platform'
        }
    },
    setup(props) {
        const { ref, onMounted, watch, reactive, nextTick } = Vue;
        
        const roleList = ref([]);
        const loading = ref(false);
        const searchKeyword = ref('');
        const rolePermissionCache = ref({});
        
        const dialogVisible = ref(false);
        const dialogTitle = ref('新增角色');
        const form = reactive({
            id: null,
            name: '',
            code: '',
            terminal: 'platform',
            description: '',
            status: 1
        });
        
        const permissionDialogVisible = ref(false);
        const activeTab = ref('menu');
        const currentRole = ref(null);
        const menuTree = ref([]);
        const checkedMenus = ref([]);
        const checkedOperations = ref([]);
        const menuOperations = ref({});
        
        const loadRolePermissions = async (roleId) => {
            try {
                const [roleMenus, roleOps] = await Promise.all([
                    api.roles.menus(roleId),
                    api.roles.operations(roleId)
                ]);
                rolePermissionCache.value[roleId] = {
                    menuCount: Array.isArray(roleMenus) ? roleMenus.length : 0,
                    operationCount: Array.isArray(roleOps) ? roleOps.length : 0,
                    menus: roleMenus,
                    operations: roleOps
                };
                return rolePermissionCache.value[roleId];
            } catch (e) {
                return { menuCount: 0, operationCount: 0, menus: [], operations: [] };
            }
        };
        
        const loadRoles = async () => {
            loading.value = true;
            try {
                const params = { terminal: props.terminal };
                if (searchKeyword.value) {
                    params.keyword = searchKeyword.value;
                }
                const data = await api.roles.list(params);
                roleList.value = data.list || [];
                
                for (const role of roleList.value) {
                    await loadRolePermissions(role.id);
                }
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };
        
        const getRoleMenuCount = (roleId) => {
            return rolePermissionCache.value[roleId]?.menuCount || 0;
        };
        
        const getRoleOperationCount = (roleId) => {
            return rolePermissionCache.value[roleId]?.operationCount || 0;
        };
        
        const handleAdd = () => {
            dialogTitle.value = '新增角色';
            form.id = null;
            form.name = '';
            form.code = '';
            form.terminal = props.terminal;
            form.description = '';
            form.status = 1;
            dialogVisible.value = true;
        };
        
        const handleEdit = (row) => {
            dialogTitle.value = '编辑角色';
            form.id = row.id;
            form.name = row.name;
            form.code = row.code;
            form.terminal = row.terminal;
            form.description = row.description;
            form.status = row.status;
            dialogVisible.value = true;
        };
        
        const handleDelete = (row) => {
            ElementPlus.ElMessageBox.confirm(
                `确定要删除角色「${row.name}」吗？`,
                '提示',
                {
                    confirmButtonText: '确定',
                    cancelButtonText: '取消',
                    type: 'warning'
                }
            ).then(async () => {
                try {
                    await api.roles.delete(row.id);
                    delete rolePermissionCache.value[row.id];
                    ElementPlus.ElMessage.success('删除成功');
                    loadRoles();
                } catch (e) {
                    console.error(e);
                }
            }).catch(() => {});
        };
        
        const handleSubmit = async () => {
            if (!form.name || !form.code) {
                ElementPlus.ElMessage.warning('请填写角色名称和编码');
                return;
            }
            
            try {
                if (form.id) {
                    await api.roles.update(form.id, form);
                    ElementPlus.ElMessage.success('更新成功');
                } else {
                    const result = await api.roles.create(form);
                    ElementPlus.ElMessage.success('创建成功');
                }
                dialogVisible.value = false;
                loadRoles();
            } catch (e) {
                console.error(e);
            }
        };
        
        const handleAssignPermission = async (row) => {
            currentRole.value = { ...row };
            activeTab.value = 'menu';
            permissionDialogVisible.value = true;
            checkedMenus.value = [];
            checkedOperations.value = [];
            menuOperations.value = {};
            menuTree.value = [];
            
            try {
                const [treeData, roleMenus, roleOps] = await Promise.all([
                    api.menus.tree({ terminal: row.terminal }),
                    api.roles.menus(row.id),
                    api.roles.operations(row.id)
                ]);
                
                menuTree.value = treeData;
                
                await nextTick();
                checkedMenus.value = roleMenus.map(m => m.id);
                checkedOperations.value = roleOps.map(o => o.id);
                
                const opsMap = {};
                const collectOps = (menus) => {
                    menus.forEach(menu => {
                        if (menu.operations && menu.operations.length) {
                            opsMap[menu.id] = menu.operations;
                        }
                        if (menu.children && menu.children.length) {
                            collectOps(menu.children);
                        }
                    });
                };
                collectOps(treeData);
                menuOperations.value = opsMap;
                
            } catch (e) {
                console.error(e);
                ElementPlus.ElMessage.error('加载权限数据失败');
            }
        };
        
        const handleSavePermissions = async () => {
            if (!currentRole.value) return;
            
            try {
                await Promise.all([
                    api.roles.assignMenus(currentRole.value.id, checkedMenus.value),
                    api.roles.assignOperations(currentRole.value.id, checkedOperations.value)
                ]);
                
                ElementPlus.ElMessage.success('权限分配成功');
                
                rolePermissionCache.value[currentRole.value.id] = {
                    menuCount: checkedMenus.value.length,
                    operationCount: checkedOperations.value.length,
                    menus: checkedMenus.value,
                    operations: checkedOperations.value
                };
                
                permissionDialogVisible.value = false;
                loadRoles();
            } catch (e) {
                console.error(e);
                ElementPlus.ElMessage.error('权限分配失败');
            }
        };
        
        const handleMenuCheck = (checkedKeys, info) => {
            checkedMenus.value = typeof checkedKeys === 'object' && checkedKeys.checkedKeys 
                ? checkedKeys.checkedKeys 
                : checkedKeys;
        };
        
        const handleOperationCheck = (menuId, operationId, checked) => {
            if (checked) {
                if (!checkedOperations.value.includes(operationId)) {
                    checkedOperations.value.push(operationId);
                }
                if (!checkedMenus.value.includes(menuId)) {
                    checkedMenus.value.push(menuId);
                }
            } else {
                checkedOperations.value = checkedOperations.value.filter(id => id !== operationId);
            }
        };
        
        const isOperationChecked = (operationId) => {
            return checkedOperations.value.includes(operationId);
        };
        
        const terminalOptions = [
            { value: 'platform', label: '平台端' },
            { value: 'merchant', label: '商家端' },
            { value: 'warehouse', label: '仓储端' }
        ];
        
        onMounted(() => {
            loadRoles();
        });
        
        watch(() => props.terminal, () => {
            searchKeyword.value = '';
            loadRoles();
        });
        
        return {
            roleList,
            loading,
            searchKeyword,
            dialogVisible,
            dialogTitle,
            form,
            permissionDialogVisible,
            activeTab,
            currentRole,
            menuTree,
            checkedMenus,
            checkedOperations,
            menuOperations,
            terminalOptions,
            loadRoles,
            getRoleMenuCount,
            getRoleOperationCount,
            handleAdd,
            handleEdit,
            handleDelete,
            handleSubmit,
            handleAssignPermission,
            handleSavePermissions,
            handleMenuCheck,
            handleOperationCheck,
            isOperationChecked
        };
    },
    template: `
        <div class="page-card">
            <div class="page-header">
                <div class="page-title">角色管理</div>
                <el-button type="primary" @click="handleAdd">
                    <el-icon><Plus /></el-icon>
                    新增角色
                </el-button>
            </div>
            
            <div class="toolbar">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索角色名称/编码"
                    style="width: 250px"
                    clearable
                    @keyup.enter="loadRoles">
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>
                <el-button type="primary" @click="loadRoles">查询</el-button>
                <el-button @click="searchKeyword = ''; loadRoles()">重置</el-button>
            </div>
            
            <el-table :data="roleList" v-loading="loading" border stripe>
                <el-table-column prop="id" label="ID" width="80" align="center" />
                <el-table-column prop="name" label="角色名称" width="150" />
                <el-table-column prop="code" label="角色编码" width="180" />
                <el-table-column prop="terminal" label="所属终端" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.terminal === 'platform'" type="primary">平台端</el-tag>
                        <el-tag v-else-if="row.terminal === 'merchant'" type="success">商家端</el-tag>
                        <el-tag v-else type="warning">仓储端</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="菜单权限数" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag type="success" effect="plain">{{ getRoleMenuCount(row.id) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作权限数" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag type="warning" effect="plain">{{ getRoleOperationCount(row.id) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="description" label="描述" />
                <el-table-column prop="status" label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">
                            {{ row.status === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="280" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" link @click="handleAssignPermission(row)">
                            分配权限
                        </el-button>
                        <el-button size="small" type="primary" link @click="handleEdit(row)">
                            编辑
                        </el-button>
                        <el-button size="small" type="danger" link @click="handleDelete(row)">
                            删除
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px" destroy-on-close>
            <el-form :model="form" label-width="100px">
                <el-form-item label="角色名称">
                    <el-input v-model="form.name" placeholder="请输入角色名称" />
                </el-form-item>
                <el-form-item label="角色编码">
                    <el-input v-model="form.code" placeholder="请输入角色编码" />
                </el-form-item>
                <el-form-item label="所属终端">
                    <el-select v-model="form.terminal" style="width: 100%">
                        <el-option
                            v-for="item in terminalOptions"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请输入描述" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
        
        <el-dialog v-model="permissionDialogVisible" title="分配权限" width="680px" top="5vh" destroy-on-close>
            <div v-if="currentRole" style="margin-bottom: 16px;">
                <el-alert
                    :title="'当前角色：' + currentRole.name + '（终端：' + (currentRole.terminal === 'platform' ? '平台端' : currentRole.terminal === 'merchant' ? '商家端' : '仓储端') + '）'"
                    type="info"
                    :closable="false"
                    show-icon />
            </div>
            
            <el-tabs v-model="activeTab" type="border-card">
                <el-tab-pane label="菜单权限" name="menu">
                    <div class="permission-tree" style="padding: 8px;">
                        <el-tree
                            :data="menuTree"
                            show-checkbox
                            node-key="id"
                            :props="{ label: 'name', children: 'children' }"
                            :checked-keys="checkedMenus"
                            default-expand-all
                            :expand-on-click-node="false"
                            @check="handleMenuCheck" />
                    </div>
                    <div style="margin-top: 12px; padding: 10px; background: #f5f7fa; border-radius: 4px;">
                        <span style="color: #606266;">已选菜单：</span>
                        <el-tag type="success" style="margin-right: 4px;">{{ checkedMenus.length }}</el-tag>
                        <span style="color: #909399; margin-left: 12px; font-size: 12px;">提示：选择父菜单将自动包含所有子菜单</span>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="操作权限" name="operation">
                    <div class="permission-tree" style="padding: 8px; max-height: 500px; overflow-y: auto;">
                        <div v-for="menu in menuTree" :key="menu.id" style="margin-bottom: 20px; border-bottom: 1px dashed #ebeef5; padding-bottom: 12px;">
                            <div style="font-weight: 600; margin-bottom: 10px; color: #303133; font-size: 14px;">
                                <el-icon style="vertical-align: middle; margin-right: 6px; color: #409eff;"><Folder /></el-icon>
                                {{ menu.name }}
                            </div>
                            <div v-for="child in menu.children" :key="child.id" style="margin: 10px 0; padding-left: 20px;">
                                <div style="margin-bottom: 6px; color: #606266; font-size: 13px;">
                                    <el-icon style="vertical-align: middle; margin-right: 4px; color: #67c23a;"><Document /></el-icon>
                                    {{ child.name }}
                                </div>
                                <div class="operation-tags" v-if="menuOperations[child.id] && menuOperations[child.id].length">
                                    <el-checkbox
                                        v-for="op in menuOperations[child.id]"
                                        :key="op.id"
                                        :model-value="isOperationChecked(op.id)"
                                        @change="(val) => handleOperationCheck(child.id, op.id, val)"
                                        style="margin: 4px 16px 4px 0; padding: 2px 8px;">
                                        <span style="font-size: 13px;">{{ op.name }}</span>
                                        <span style="color: #909399; font-size: 11px; margin-left: 6px;">({{ op.code }})</span>
                                    </el-checkbox>
                                </div>
                                <div v-else style="padding-left: 24px; color: #c0c4cc; font-size: 12px;">
                                    └─ 暂无操作权限定义
                                </div>
                            </div>
                        </div>
                        <div v-if="!menuTree.length" style="text-align: center; padding: 40px; color: #909399;">
                            <el-icon style="font-size: 40px;"><DocumentDelete /></el-icon>
                            <div style="margin-top: 8px;">暂无菜单数据</div>
                        </div>
                    </div>
                    <div style="margin-top: 12px; padding: 10px; background: #f5f7fa; border-radius: 4px;">
                        <span style="color: #606266;">已选操作权限：</span>
                        <el-tag type="warning" style="margin-right: 4px;">{{ checkedOperations.length }}</el-tag>
                        <span style="color: #909399; margin-left: 12px; font-size: 12px;">提示：勾选操作权限将自动关联所属菜单</span>
                    </div>
                </el-tab-pane>
            </el-tabs>
            
            <template #footer>
                <el-button @click="permissionDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSavePermissions" :loading="loading">
                    保存权限分配
                </el-button>
            </template>
        </el-dialog>
    `
};
