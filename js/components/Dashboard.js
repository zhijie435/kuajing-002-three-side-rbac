const DashboardComponent = {
    props: {
        terminal: {
            type: String,
            default: 'platform'
        }
    },
    setup(props) {
        const { ref, onMounted, watch } = Vue;
        
        const stats = ref({
            roleCount: 0,
            menuCount: 0,
            operationCount: 0,
            permissionCount: 0
        });
        
        const roleList = ref([]);
        const loading = ref(false);
        
        const loadData = async () => {
            loading.value = true;
            try {
                const [roles, menusData, opsData] = await Promise.all([
                    api.roles.list({ terminal: props.terminal }),
                    api.menus.list({ terminal: props.terminal }),
                    api.operations.list()
                ]);
                
                stats.value.roleCount = roles.total;
                stats.value.menuCount = Array.isArray(menusData.flat) ? menusData.flat.length : (menusData.list ? menusData.list.length : 0);
                
                const menuIds = Array.isArray(menusData.flat) ? menusData.flat.map(m => m.id) : [];
                const filteredOps = opsData.list ? opsData.list.filter(o => menuIds.includes(o.menu_id)) : opsData;
                stats.value.operationCount = Array.isArray(filteredOps) ? filteredOps.length : 0;
                
                stats.value.permissionCount = stats.value.menuCount + stats.value.operationCount;
                
                roleList.value = roles.list || [];
                
                for (const role of roleList.value) {
                    try {
                        const [roleMenus, roleOps] = await Promise.all([
                            api.roles.menus(role.id),
                            api.roles.operations(role.id)
                        ]);
                        role.menuCount = Array.isArray(roleMenus) ? roleMenus.length : 0;
                        role.operationCount = Array.isArray(roleOps) ? roleOps.length : 0;
                    } catch (e) {
                        role.menuCount = 0;
                        role.operationCount = 0;
                    }
                }
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };
        
        onMounted(() => {
            loadData();
        });
        
        watch(() => props.terminal, () => {
            loadData();
        });
        
        const terminalLabels = {
            platform: '平台端',
            merchant: '商家端',
            warehouse: '仓储端'
        };
        
        return {
            stats,
            roleList,
            loading,
            terminalLabels
        };
    },
    template: `
        <div>
            <div class="stats-cards">
                <div class="stat-card">
                    <el-icon class="icon" style="color: #409eff;"><UserFilled /></el-icon>
                    <div class="label">角色数量</div>
                    <div class="value">{{ stats.roleCount }}</div>
                </div>
                <div class="stat-card">
                    <el-icon class="icon" style="color: #67c23a;"><Menu /></el-icon>
                    <div class="label">菜单数量</div>
                    <div class="value">{{ stats.menuCount }}</div>
                </div>
                <div class="stat-card">
                    <el-icon class="icon" style="color: #e6a23c;"><Operation /></el-icon>
                    <div class="label">操作权限</div>
                    <div class="value">{{ stats.operationCount }}</div>
                </div>
                <div class="stat-card">
                    <el-icon class="icon" style="color: #f56c6c;"><Shield /></el-icon>
                    <div class="label">权限点总数</div>
                    <div class="value">{{ stats.permissionCount }}</div>
                </div>
            </div>
            
            <div class="page-card">
                <div class="page-header">
                    <div class="page-title">角色权限概览 - {{ terminalLabels[terminal] }}</div>
                </div>
                <el-table :data="roleList" v-loading="loading" border>
                    <el-table-column prop="name" label="角色名称" width="150" />
                    <el-table-column prop="code" label="角色编码" width="180" />
                    <el-table-column prop="description" label="角色描述" />
                    <el-table-column prop="menuCount" label="菜单权限数" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag type="success">{{ row.menuCount || 0 }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="operationCount" label="操作权限数" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag type="warning">{{ row.operationCount || 0 }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 1 ? 'success' : 'info'">
                                {{ row.status === 1 ? '启用' : '禁用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    `
};
