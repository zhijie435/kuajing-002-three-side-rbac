const MenuManageComponent = {
    props: {
        terminal: {
            type: String,
            default: 'platform'
        }
    },
    setup(props) {
        const { ref, onMounted, watch, reactive } = Vue;
        
        const menuTree = ref([]);
        const menuList = ref([]);
        const loading = ref(false);
        
        const dialogVisible = ref(false);
        const dialogTitle = ref('新增菜单');
        const form = reactive({
            id: null,
            parent_id: 0,
            name: '',
            path: '',
            component: '',
            icon: '',
            sort: 0,
            terminal: 'platform',
            type: 2,
            status: 1
        });
        
        const parentOptions = ref([]);
        
        const loadMenus = async () => {
            loading.value = true;
            try {
                const data = await api.menus.list({ terminal: props.terminal });
                menuTree.value = data.list || [];
                menuList.value = data.flat || [];
                
                const options = [{ id: 0, name: '顶级菜单' }];
                const buildOptions = (menus, level = 0) => {
                    menus.forEach(menu => {
                        if (menu.type === 1) {
                            options.push({
                                id: menu.id,
                                name: '　'.repeat(level) + menu.name
                            });
                            if (menu.children && menu.children.length) {
                                buildOptions(menu.children, level + 1);
                            }
                        }
                    });
                };
                buildOptions(data.list || []);
                parentOptions.value = options;
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };
        
        const handleAdd = () => {
            dialogTitle.value = '新增菜单';
            form.id = null;
            form.parent_id = 0;
            form.name = '';
            form.path = '';
            form.component = '';
            form.icon = '';
            form.sort = 0;
            form.terminal = props.terminal;
            form.type = 2;
            form.status = 1;
            dialogVisible.value = true;
        };
        
        const handleAddChild = (row) => {
            dialogTitle.value = '新增子菜单';
            form.id = null;
            form.parent_id = row.id;
            form.name = '';
            form.path = '';
            form.component = '';
            form.icon = '';
            form.sort = 0;
            form.terminal = row.terminal;
            form.type = 2;
            form.status = 1;
            dialogVisible.value = true;
        };
        
        const handleEdit = (row) => {
            dialogTitle.value = '编辑菜单';
            form.id = row.id;
            form.parent_id = row.parent_id;
            form.name = row.name;
            form.path = row.path || '';
            form.component = row.component || '';
            form.icon = row.icon || '';
            form.sort = row.sort || 0;
            form.terminal = row.terminal;
            form.type = row.type;
            form.status = row.status;
            dialogVisible.value = true;
        };
        
        const handleDelete = (row) => {
            ElementPlus.ElMessageBox.confirm(
                `确定要删除菜单「${row.name}」吗？`,
                '提示',
                {
                    confirmButtonText: '确定',
                    cancelButtonText: '取消',
                    type: 'warning'
                }
            ).then(async () => {
                try {
                    await api.menus.delete(row.id);
                    ElementPlus.ElMessage.success('删除成功');
                    loadMenus();
                } catch (e) {
                    console.error(e);
                }
            }).catch(() => {});
        };
        
        const handleSubmit = async () => {
            if (!form.name) {
                ElementPlus.ElMessage.warning('请填写菜单名称');
                return;
            }
            
            try {
                if (form.id) {
                    await api.menus.update(form.id, form);
                    ElementPlus.ElMessage.success('更新成功');
                } else {
                    await api.menus.create(form);
                    ElementPlus.ElMessage.success('创建成功');
                }
                dialogVisible.value = false;
                loadMenus();
            } catch (e) {
                console.error(e);
            }
        };
        
        const typeOptions = [
            { value: 1, label: '目录' },
            { value: 2, label: '菜单' }
        ];
        
        const terminalOptions = [
            { value: 'platform', label: '平台端' },
            { value: 'merchant', label: '商家端' },
            { value: 'warehouse', label: '仓储端' }
        ];
        
        onMounted(() => {
            loadMenus();
        });
        
        watch(() => props.terminal, () => {
            loadMenus();
        });
        
        return {
            menuTree,
            menuList,
            loading,
            dialogVisible,
            dialogTitle,
            form,
            parentOptions,
            typeOptions,
            terminalOptions,
            handleAdd,
            handleAddChild,
            handleEdit,
            handleDelete,
            handleSubmit
        };
    },
    template: `
        <div class="page-card">
            <div class="page-header">
                <div class="page-title">菜单管理</div>
                <el-button type="primary" @click="handleAdd">
                    <el-icon><Plus /></el-icon>
                    新增菜单
                </el-button>
            </div>
            
            <el-table :data="menuTree" v-loading="loading" border row-key="id" default-expand-all>
                <el-table-column prop="name" label="菜单名称" width="200">
                    <template #default="{ row }">
                        <el-icon v-if="row.type === 1" style="color: #409eff; margin-right: 4px;"><Folder /></el-icon>
                        <el-icon v-else style="color: #67c23a; margin-right: 4px;"><Document /></el-icon>
                        {{ row.name }}
                    </template>
                </el-table-column>
                <el-table-column prop="path" label="路径" width="150" />
                <el-table-column prop="component" label="组件" width="180" />
                <el-table-column prop="icon" label="图标" width="100" />
                <el-table-column prop="sort" label="排序" width="80" align="center" />
                <el-table-column prop="type" label="类型" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 1 ? 'primary' : 'success'" size="small">
                            {{ row.type === 1 ? '目录' : '菜单' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="280" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" link @click="handleAddChild(row)">
                            新增子菜单
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
        
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
            <el-form :model="form" label-width="100px">
                <el-form-item label="上级菜单">
                    <el-select v-model="form.parent_id" style="width: 100%">
                        <el-option
                            v-for="item in parentOptions"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="菜单名称">
                    <el-input v-model="form.name" placeholder="请输入菜单名称" />
                </el-form-item>
                <el-form-item label="菜单类型">
                    <el-select v-model="form.type" style="width: 100%">
                        <el-option
                            v-for="item in typeOptions"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="路径">
                    <el-input v-model="form.path" placeholder="请输入菜单路径" />
                </el-form-item>
                <el-form-item label="组件">
                    <el-input v-model="form.component" placeholder="请输入组件路径" />
                </el-form-item>
                <el-form-item label="图标">
                    <el-input v-model="form.icon" placeholder="请输入图标名称" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="form.sort" :min="0" style="width: 100%" />
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
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
    `
};
