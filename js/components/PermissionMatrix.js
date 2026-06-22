const PermissionMatrixComponent = {
    props: {
        terminal: {
            type: String,
            default: 'platform'
        }
    },
    setup(props) {
        const { ref, onMounted, watch, computed } = Vue;
        
        const roleList = ref([]);
        const menuList = ref([]);
        const loading = ref(false);
        const matrixData = ref({});
        
        const loadData = async () => {
            loading.value = true;
            try {
                const [roles, menusData] = await Promise.all([
                    api.roles.list({ terminal: props.terminal }),
                    api.menus.tree({ terminal: props.terminal })
                ]);
                
                roleList.value = roles.list || [];
                menuList.value = menusData || [];
                
                const matrix = {};
                for (const role of roleList.value) {
                    try {
                        const [roleMenus, roleOps] = await Promise.all([
                            api.roles.menus(role.id),
                            api.roles.operations(role.id)
                        ]);
                        matrix[role.id] = {
                            menus: roleMenus.map(m => m.id),
                            operations: roleOps.map(o => o.id)
                        };
                    } catch (e) {
                        matrix[role.id] = { menus: [], operations: [] };
                    }
                }
                matrixData.value = matrix;
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };
        
        const hasMenu = (roleId, menuId) => {
            return matrixData.value[roleId]?.menus?.includes(menuId) || false;
        };
        
        const hasOperation = (roleId, operationId) => {
            return matrixData.value[roleId]?.operations?.includes(operationId) || false;
        };
        
        const flattenMenus = computed(() => {
            const result = [];
            const flatten = (menus, level = 0) => {
                menus.forEach(menu => {
                    result.push({ ...menu, level });
                    if (menu.children && menu.children.length) {
                        flatten(menu.children, level + 1);
                    }
                });
            };
            flatten(menuList.value);
            return result;
        });
        
        const terminalLabels = {
            platform: '平台端',
            merchant: '商家端',
            warehouse: '仓储端'
        };
        
        onMounted(() => {
            loadData();
        });
        
        watch(() => props.terminal, () => {
            loadData();
        });
        
        return {
            roleList,
            menuList,
            flattenMenus,
            loading,
            matrixData,
            hasMenu,
            hasOperation,
            terminalLabels
        };
    },
    template: `
        <div class="page-card">
            <div class="page-header">
                <div class="page-title">权限矩阵 - {{ terminalLabels[terminal] }}</div>
                <el-button type="primary" @click="loadData">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
            
            <el-alert
                title="矩阵说明：横向为角色，纵向为菜单和操作权限，勾选表示该角色拥有此权限"
                type="info"
                :closable="false"
                show-icon
                style="margin-bottom: 20px;" />
            
            <div style="overflow-x: auto;" v-loading="loading">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th style="width: 250px; text-align: left;">权限 / 角色</th>
                            <th v-for="role in roleList" :key="role.id" style="min-width: 120px;">
                                {{ role.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="menu in flattenMenus" :key="menu.id">
                            <tr>
                                <td :class="{ 'sub-menu': menu.level > 0 }" class="menu-name">
                                    <el-icon v-if="menu.type === 1" style="color: #409eff; margin-right: 4px;"><Folder /></el-icon>
                                    <el-icon v-else style="color: #67c23a; margin-right: 4px;"><Document /></el-icon>
                                    {{ menu.name }}
                                    <el-tag v-if="menu.type === 1" size="small" style="margin-left: 8px;">目录</el-tag>
                                    <el-tag v-else size="small" type="success" style="margin-left: 8px;">菜单</el-tag>
                                </td>
                                <td v-for="role in roleList" :key="role.id" align="center">
                                    <el-icon v-if="hasMenu(role.id, menu.id)" style="color: #67c23a; font-size: 18px;"><CircleCheckFilled /></el-icon>
                                    <el-icon v-else style="color: #dcdfe6; font-size: 18px;"><CircleCloseFilled /></el-icon>
                                </td>
                            </tr>
                            <tr v-if="menu.operations && menu.operations.length">
                                <td colspan="100" style="padding: 0; background: #fafafa;">
                                    <table style="width: 100%; border: none;">
                                        <tbody>
                                            <tr v-for="op in menu.operations" :key="op.id">
                                                <td style="width: 250px; padding-left: 50px; border: none; text-align: left; color: #909399;">
                                                    <el-icon style="vertical-align: middle; margin-right: 4px;"><Operation /></el-icon>
                                                    {{ op.name }}
                                                    <span style="color: #c0c4cc; font-size: 12px; margin-left: 4px;">({{ op.code }})</span>
                                                </td>
                                                <td v-for="role in roleList" :key="role.id" align="center" style="border: none;">
                                                    <el-icon v-if="hasOperation(role.id, op.id)" style="color: #e6a23c; font-size: 16px;"><CircleCheckFilled /></el-icon>
                                                    <el-icon v-else style="color: #e4e7ed; font-size: 16px;"><CircleCloseFilled /></el-icon>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <el-icon style="color: #67c23a;"><CircleCheckFilled /></el-icon>
                    <span style="color: #606266;">菜单权限</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <el-icon style="color: #e6a23c;"><CircleCheckFilled /></el-icon>
                    <span style="color: #606266;">操作权限</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <el-icon style="color: #dcdfe6;"><CircleCloseFilled /></el-icon>
                    <span style="color: #606266;">无权限</span>
                </div>
            </div>
        </div>
    `
};
