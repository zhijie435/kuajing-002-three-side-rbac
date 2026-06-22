const OperationManageComponent = {
    props: {
        terminal: {
            type: String,
            default: 'platform'
        }
    },
    setup(props) {
        const { ref, onMounted, watch, reactive, computed } = Vue;
        
        const operationList = ref([]);
        const loading = ref(false);
        const selectedMenu = ref('');
        
        const menuOptions = ref([]);
        
        const dialogVisible = ref(false);
        const dialogTitle = ref('新增操作权限');
        const form = reactive({
            id: null,
            menu_id: '',
            name: '',
            code: '',
            description: '',
            status: 1
        });
        
        const loadMenus = async () => {
            try {
                const data = await api.menus.list({ terminal: props.terminal });
                const flatMenus = data.flat || [];
                menuOptions.value = flatMenus.filter(m => m.type === 2);
            } catch (e) {
                console.error(e);
            }
        };
        
        const loadOperations = async () => {
            loading.value = true;
            try {
                const params = {};
                if (selectedMenu.value) {
                    params.menu_id = selectedMenu.value;
                }
                const data = await api.operations.list(params);
                operationList.value = data.list || [];
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };
        
        const handleAdd = () => {
            dialogTitle.value = '新增操作权限';
            form.id = null;
            form.menu_id = selectedMenu.value || '';
            form.name = '';
            form.code = '';
            form.description = '';
            form.status = 1;
            dialogVisible.value = true;
        };
        
        const handleEdit = (row) => {
            dialogTitle.value = '编辑操作权限';
            form.id = row.id;
            form.menu_id = row.menu_id;
            form.name = row.name;
            form.code = row.code;
            form.description = row.description || '';
            form.status = row.status;
            dialogVisible.value = true;
        };
        
        const handleDelete = (row) => {
            ElementPlus.ElMessageBox.confirm(
                `确定要删除操作权限「${row.name}」吗？`,
                '提示',
                {
                    confirmButtonText: '确定',
                    cancelButtonText: '取消',
                    type: 'warning'
                }
            ).then(async () => {
                try {
                    await api.operations.delete(row.id);
                    ElementPlus.ElMessage.success('删除成功');
                    loadOperations();
                } catch (e) {
                    console.error(e);
                }
            }).catch(() => {});
        };
        
        const handleSubmit = async () => {
            if (!form.name || !form.code || !form.menu_id) {
                ElementPlus.ElMessage.warning('请填写完整信息');
                return;
            }
            
            try {
                if (form.id) {
                    await api.operations.update(form.id, form);
                    ElementPlus.ElMessage.success('更新成功');
                } else {
                    await api.operations.create(form);
                    ElementPlus.ElMessage.success('创建成功');
                }
                dialogVisible.value = false;
                loadOperations();
            } catch (e) {
                console.error(e);
            }
        };
        
        onMounted(() => {
            loadMenus();
            loadOperations();
        });
        
        watch(() => props.terminal, () => {
            selectedMenu.value = '';
            loadMenus();
            loadOperations();
        });
        
        watch(selectedMenu, () => {
            loadOperations();
        });
        
        return {
            operationList,
            loading,
            selectedMenu,
            menuOptions,
            dialogVisible,
            dialogTitle,
            form,
            handleAdd,
            handleEdit,
            handleDelete,
            handleSubmit
        };
    },
    template: `
        <div class="page-card">
            <div class="page-header">
                <div class="page-title">操作权限</div>
                <el-button type="primary" @click="handleAdd">
                    <el-icon><Plus /></el-icon>
                    新增操作
                </el-button>
            </div>
            
            <div class="toolbar">
                <el-select
                    v-model="selectedMenu"
                    placeholder="选择菜单筛选"
                    style="width: 250px"
                    clearable>
                    <el-option
                        v-for="menu in menuOptions"
                        :key="menu.id"
                        :label="menu.name"
                        :value="menu.id" />
                </el-select>
            </div>
            
            <el-table :data="operationList" v-loading="loading" border stripe>
                <el-table-column prop="id" label="ID" width="80" align="center" />
                <el-table-column prop="menu_name" label="所属菜单" width="150" />
                <el-table-column prop="name" label="操作名称" width="150" />
                <el-table-column prop="code" label="操作编码" width="200" />
                <el-table-column prop="description" label="描述" />
                <el-table-column prop="status" label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160" align="center" fixed="right">
                    <template #default="{ row }">
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
                <el-form-item label="所属菜单">
                    <el-select v-model="form.menu_id" style="width: 100%" placeholder="请选择菜单">
                        <el-option
                            v-for="menu in menuOptions"
                            :key="menu.id"
                            :label="menu.name"
                            :value="menu.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="操作名称">
                    <el-input v-model="form.name" placeholder="请输入操作名称" />
                </el-form-item>
                <el-form-item label="操作编码">
                    <el-input v-model="form.code" placeholder="如：user:add" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="请输入描述" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
    `
};
