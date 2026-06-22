const { createApp, ref, computed, onMounted } = Vue;

const app = createApp({
    setup() {
        const activeMenu = ref('dashboard');
        const currentTerminal = ref('platform');
        
        const terminals = [
            { value: 'platform', label: '平台端' },
            { value: 'merchant', label: '商家端' },
            { value: 'warehouse', label: '仓储端' }
        ];
        
        const menuTitles = {
            dashboard: '权限概览',
            role: '角色管理',
            menu: '菜单管理',
            operation: '操作权限',
            matrix: '权限矩阵'
        };
        
        const pageTitle = computed(() => menuTitles[activeMenu.value] || '');
        
        const handleMenuSelect = (index) => {
            activeMenu.value = index;
        };
        
        const switchTerminal = (terminal) => {
            currentTerminal.value = terminal;
        };
        
        return {
            activeMenu,
            currentTerminal,
            terminals,
            pageTitle,
            handleMenuSelect,
            switchTerminal
        };
    }
});

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(ElementPlus);
app.component('dashboard', DashboardComponent);
app.component('role-manage', RoleManageComponent);
app.component('menu-manage', MenuManageComponent);
app.component('operation-manage', OperationManageComponent);
app.component('permission-matrix', PermissionMatrixComponent);
app.mount('#app');
