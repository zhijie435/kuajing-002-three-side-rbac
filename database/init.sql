-- 三端角色权限矩阵数据库设计
-- 三端：platform(平台端)、merchant(商家端)、warehouse(仓储端)

-- 角色表
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    terminal VARCHAR(20) NOT NULL, -- platform/merchant/warehouse
    description TEXT,
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 菜单表
CREATE TABLE IF NOT EXISTS menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER DEFAULT 0,
    name VARCHAR(100) NOT NULL,
    path VARCHAR(200),
    component VARCHAR(200),
    icon VARCHAR(50),
    sort INTEGER DEFAULT 0,
    terminal VARCHAR(20) NOT NULL, -- platform/merchant/warehouse
    type TINYINT DEFAULT 1, -- 1:目录 2:菜单
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 操作权限表
CREATE TABLE IF NOT EXISTS operations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    menu_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(100) NOT NULL,
    description TEXT,
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
);

-- 角色-菜单关联表
CREATE TABLE IF NOT EXISTS role_menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    menu_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role_id, menu_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
);

-- 角色-操作关联表
CREATE TABLE IF NOT EXISTS role_operations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    operation_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role_id, operation_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE CASCADE
);

-- 初始化平台端菜单
INSERT OR IGNORE INTO menus (id, parent_id, name, path, component, icon, sort, terminal, type) VALUES
(1, 0, '系统管理', '/system', NULL, 'Setting', 1, 'platform', 1),
(2, 1, '角色管理', 'role', 'system/role', 'UserFilled', 1, 'platform', 2),
(3, 1, '菜单管理', 'menu', 'system/menu', 'Menu', 2, 'platform', 2),
(4, 0, '订单管理', '/order', NULL, 'List', 2, 'platform', 1),
(5, 4, '订单列表', 'list', 'order/list', 'Document', 1, 'platform', 2),
(6, 0, '商品管理', '/product', NULL, 'Goods', 3, 'platform', 1),
(7, 6, '商品列表', 'list', 'product/list', 'GoodsFilled', 1, 'platform', 2);

-- 初始化商家端菜单
INSERT OR IGNORE INTO menus (id, parent_id, name, path, component, icon, sort, terminal, type) VALUES
(100, 0, '店铺管理', '/shop', NULL, 'Shop', 1, 'merchant', 1),
(101, 100, '店铺信息', 'info', 'shop/info', 'InfoFilled', 1, 'merchant', 2),
(102, 0, '商品管理', '/product', NULL, 'Goods', 2, 'merchant', 1),
(103, 102, '商品列表', 'list', 'merchant/product/list', 'GoodsFilled', 1, 'merchant', 2),
(104, 102, '商品上架', 'publish', 'merchant/product/publish', 'Upload', 2, 'merchant', 2);

-- 初始化仓储端菜单
INSERT OR IGNORE INTO menus (id, parent_id, name, path, component, icon, sort, terminal, type) VALUES
(200, 0, '库存管理', '/inventory', NULL, 'Box', 1, 'warehouse', 1),
(201, 200, '库存查询', 'query', 'warehouse/inventory/query', 'Search', 1, 'warehouse', 2),
(202, 200, '入库管理', 'stock-in', 'warehouse/inventory/stock-in', 'Download', 2, 'warehouse', 2),
(203, 200, '出库管理', 'stock-out', 'warehouse/inventory/stock-out', 'Upload', 3, 'warehouse', 2);

-- 初始化操作权限
INSERT OR IGNORE INTO operations (id, menu_id, name, code, description) VALUES
(1, 2, '查看角色', 'role:list', '查看角色列表'),
(2, 2, '新增角色', 'role:add', '新增角色'),
(3, 2, '编辑角色', 'role:edit', '编辑角色'),
(4, 2, '删除角色', 'role:delete', '删除角色'),
(5, 2, '分配权限', 'role:assign', '分配菜单和操作权限'),
(6, 3, '查看菜单', 'menu:list', '查看菜单列表'),
(7, 3, '新增菜单', 'menu:add', '新增菜单'),
(8, 3, '编辑菜单', 'menu:edit', '编辑菜单'),
(9, 3, '删除菜单', 'menu:delete', '删除菜单');

-- 初始化角色
INSERT OR IGNORE INTO roles (id, name, code, terminal, description) VALUES
(1, '超级管理员', 'super_admin', 'platform', '平台端超级管理员，拥有所有权限'),
(2, '运营管理员', 'operation_admin', 'platform', '平台运营管理员'),
(3, '商家管理员', 'merchant_admin', 'merchant', '商家管理员'),
(4, '仓储管理员', 'warehouse_admin', 'warehouse', '仓储管理员'),
(5, '仓库操作员', 'warehouse_operator', 'warehouse', '仓库操作员');

-- 超级管理员拥有所有平台端菜单权限
INSERT OR IGNORE INTO role_menus (role_id, menu_id)
SELECT 1, id FROM menus WHERE terminal = 'platform';

-- 超级管理员拥有所有操作权限
INSERT OR IGNORE INTO role_operations (role_id, operation_id)
SELECT 1, id FROM operations;
