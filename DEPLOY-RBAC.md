# 三端角色 RBAC 部署文档

## 1. 系统概述

本系统采用基于角色的访问控制（RBAC）模型，支持 **平台端（platform）**、**商家端（merchant）**、**仓储端（warehouse）** 三端隔离。每端拥有独立的角色、菜单和权限体系，角色与菜单、权限通过关联表实现多对多映射。

### 1.1 数据模型

| 表名 | 说明 |
|------|------|
| `admin` | 管理员表，通过 `app_type` + `role_id` 绑定端与角色 |
| `role` | 角色表，每端独立角色编码空间 |
| `menu` | 菜单表，支持目录/菜单/按钮三级类型 |
| `permission` | 权限表，关联菜单，细粒度操作控制 |
| `role_menu` | 角色↔菜单关联表 |
| `role_permission` | 角色↔权限关联表 |

### 1.2 端隔离约束

- 角色的 `app_type` 决定其只能授权同端菜单和权限
- 操作权限的授权前置条件：所属菜单必须已授权给该角色（孤儿权限校验）
- 批量授权仅支持同一端的角色

---

## 2. 三端角色定义与授权矩阵

### 2.1 平台端（platform）

#### 角色列表

| 角色ID | 角色名称 | 角色编码 | 说明 |
|--------|----------|----------|------|
| 1 | 平台管理员 | `platform_admin` | 平台端超级管理员 |

#### 菜单授权

| 菜单ID | 菜单名称 | 路由路径 | 类型 | 权限标识 |
|--------|----------|----------|------|----------|
| 1 | 系统管理 | `/system` | directory | - |
| 2 | 角色管理 | `/system/role` | menu | `system:role:list` |
| 3 | 菜单管理 | `/system/menu` | menu | `system:menu:list` |
| 4 | 权限管理 | `/system/permission` | menu | `system:permission:list` |
| 5 | 用户管理 | `/system/admin` | menu | `system:admin:list` |
| 6 | 订单管理 | `/order` | directory | - |
| 7 | 订单列表 | `/order/list` | menu | `order:list:list` |
| 8 | 退款管理 | `/order/refund` | menu | `order:refund:list` |
| 9 | 商品管理 | `/product` | directory | - |
| 10 | 商品列表 | `/product/list` | menu | `product:list:list` |
| 11 | 分类管理 | `/product/category` | menu | `product:category:list` |

> 平台管理员授权全部菜单 ID：`[1,2,3,4,5,6,7,8,9,10,11]`

#### 操作授权

| 权限ID | 权限名称 | 权限编码 | 关联菜单 |
|--------|----------|----------|----------|
| 1 | 角色查看 | `system:role:list` | 角色管理(2) |
| 2 | 角色新增 | `system:role:add` | 角色管理(2) |
| 3 | 角色编辑 | `system:role:edit` | 角色管理(2) |
| 4 | 角色删除 | `system:role:delete` | 角色管理(2) |
| 5 | 菜单查看 | `system:menu:list` | 菜单管理(3) |
| 6 | 菜单新增 | `system:menu:add` | 菜单管理(3) |
| 7 | 菜单编辑 | `system:menu:edit` | 菜单管理(3) |
| 8 | 菜单删除 | `system:menu:delete` | 菜单管理(3) |
| 9 | 权限查看 | `system:permission:list` | 权限管理(4) |
| 10 | 权限新增 | `system:permission:add` | 权限管理(4) |
| 11 | 权限编辑 | `system:permission:edit` | 权限管理(4) |
| 12 | 权限删除 | `system:permission:delete` | 权限管理(4) |
| 13 | 用户查看 | `system:admin:list` | 用户管理(5) |
| 14 | 用户新增 | `system:admin:add` | 用户管理(5) |
| 15 | 用户编辑 | `system:admin:edit` | 用户管理(5) |
| 16 | 用户删除 | `system:admin:delete` | 用户管理(5) |
| 17 | 订单查看 | `order:list:list` | 订单列表(7) |
| 18 | 退款查看 | `order:refund:list` | 退款管理(8) |
| 19 | 商品查看 | `product:list:list` | 商品列表(10) |
| 20 | 分类查看 | `product:category:list` | 分类管理(11) |

> 平台管理员授权全部权限 ID：`[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]`

---

### 2.2 商家端（merchant）

#### 角色列表

| 角色ID | 角色名称 | 角色编码 | 说明 |
|--------|----------|----------|------|
| 2 | 商家管理员 | `merchant_admin` | 商家端管理员 |

#### 菜单授权

| 菜单ID | 菜单名称 | 路由路径 | 类型 | 权限标识 |
|--------|----------|----------|------|----------|
| 12 | 订单管理 | `/order` | directory | - |
| 13 | 订单列表 | `/order/list` | menu | `merchant:order:list` |
| 14 | 售后管理 | `/order/aftersale` | menu | `merchant:order:aftersale` |
| 15 | 商品管理 | `/product` | directory | - |
| 16 | 商品列表 | `/product/list` | menu | `merchant:product:list` |
| 17 | 库存管理 | `/product/stock` | menu | `merchant:product:stock` |

> 商家管理员授权全部菜单 ID：`[12,13,14,15,16,17]`

#### 操作授权

| 权限ID | 权限名称 | 权限编码 | 关联菜单 |
|--------|----------|----------|----------|
| 21 | 订单查看 | `merchant:order:list` | 订单列表(13) |
| 22 | 售后查看 | `merchant:order:aftersale` | 售后管理(14) |
| 23 | 商品查看 | `merchant:product:list` | 商品列表(16) |
| 24 | 库存查看 | `merchant:product:stock` | 库存管理(17) |

> 商家管理员授权全部权限 ID：`[21,22,23,24]`

---

### 2.3 仓储端（warehouse）

#### 角色列表

| 角色ID | 角色名称 | 角色编码 | 说明 |
|--------|----------|----------|------|
| 3 | 仓储管理员 | `warehouse_admin` | 仓储端管理员 |

#### 菜单授权

| 菜单ID | 菜单名称 | 路由路径 | 类型 | 权限标识 |
|--------|----------|----------|------|----------|
| 18 | 库存管理 | `/inventory` | directory | - |
| 19 | 库存查询 | `/inventory/query` | menu | `warehouse:inventory:query` |
| 20 | 入库管理 | `/inventory/in` | menu | `warehouse:inventory:in` |
| 21 | 出库管理 | `/inventory/out` | menu | `warehouse:inventory:out` |

> 仓储管理员授权全部菜单 ID：`[18,19,20,21]`

#### 操作授权

| 权限ID | 权限名称 | 权限编码 | 关联菜单 |
|--------|----------|----------|----------|
| 25 | 库存查询 | `warehouse:inventory:query` | 库存查询(19) |
| 26 | 入库操作 | `warehouse:inventory:in` | 入库管理(20) |
| 27 | 出库操作 | `warehouse:inventory:out` | 出库管理(21) |

> 仓储管理员授权全部权限 ID：`[25,26,27]`

---

## 3. 环境变量

### 3.1 后端（PHP Server）

在 `server/config/database.php` 中配置数据库连接参数：

| 环境变量 | 配置常量 | 默认值 | 说明 |
|----------|---------|--------|------|
| `RBAC_DB_HOST` | `DB_HOST` | `localhost` | 数据库主机 |
| `RBAC_DB_PORT` | `DB_PORT` | `3306` | 数据库端口 |
| `RBAC_DB_NAME` | `DB_NAME` | `rbac_system` | 数据库名称 |
| `RBAC_DB_USER` | `DB_USER` | `root` | 数据库用户名 |
| `RBAC_DB_PASS` | `DB_PASS` | _(空)_ | 数据库密码 |
| `RBAC_DB_CHARSET` | `DB_CHARSET` | `utf8mb4` | 数据库字符集 |

在 `server/config/cors.php` 中配置跨域参数：

| 环境变量 | 配置常量 | 默认值 | 说明 |
|----------|---------|--------|------|
| `RBAC_CORS_ORIGIN` | `CORS_ALLOWED_ORIGIN` | `http://localhost:5173` | 允许的前端来源 |
| `RBAC_CORS_METHODS` | `CORS_ALLOWED_METHODS` | `GET, POST, PUT, DELETE, OPTIONS` | 允许的 HTTP 方法 |
| `RBAC_CORS_HEADERS` | `CORS_ALLOWED_HEADERS` | `Content-Type, Authorization, X-Requested-With` | 允许的请求头 |
| `RBAC_CORS_MAX_AGE` | `CORS_MAX_AGE` | `86400` | 预检请求缓存时间（秒） |

### 3.2 前端（Vue + Vite）

在 `web/vite.config.js` 中配置代理和端口：

| 环境变量 | 用途 | 默认值 | 说明 |
|----------|------|--------|------|
| `VITE_PORT` | `server.port` | `5173` | 前端开发服务端口 |
| `VITE_API_TARGET` | `proxy /api target` | `http://localhost:8000` | 后端 API 地址 |

### 3.3 部署环境变量模板

```bash
# ===== 数据库 =====
export RBAC_DB_HOST=localhost
export RBAC_DB_PORT=3306
export RBAC_DB_NAME=rbac_system
export RBAC_DB_USER=root
export RBAC_DB_PASS=your_secure_password

# ===== CORS =====
export RBAC_CORS_ORIGIN=https://your-frontend-domain.com
export RBAC_CORS_MAX_AGE=86400

# ===== 前端 =====
export VITE_PORT=5173
export VITE_API_TARGET=http://localhost:8000
```

---

## 4. 部署步骤

### 4.1 数据库初始化

```bash
# 1. 登录 MySQL
mysql -u root -p

# 2. 执行初始化 SQL（含建库、建表、初始数据）
source /path/to/server/database/rbac_system.sql

# 3. 确认数据
USE rbac_system;
SELECT COUNT(*) AS role_count FROM role;
SELECT COUNT(*) AS menu_count FROM menu;
SELECT COUNT(*) AS permission_count FROM permission;
SELECT COUNT(*) AS admin_count FROM admin;
```

预期输出：
- `role_count` = 3
- `menu_count` = 21
- `permission_count` = 27
- `admin_count` = 3

### 4.2 后端配置与启动

```bash
# 1. 修改数据库配置
# 编辑 server/config/database.php，根据环境设置 DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS

# 2. 启动 PHP 内置服务器
cd server
php -S localhost:8000 -t public/
```

### 4.3 前端配置与启动

```bash
# 1. 安装依赖
cd web
npm install

# 2. 开发环境启动
npm run dev

# 3. 生产构建
npm run build
# 产物输出至 web/dist/
```

---

## 5. 验收命令

### 5.1 数据库层验收

```bash
mysql -u root -p rbac_system -e "
  -- 角色三端各一条
  SELECT id, name, code, app_type FROM role ORDER BY id;
  -- 预期：3条记录，app_type 分别为 platform/merchant/warehouse

  -- 平台端菜单数量
  SELECT COUNT(*) AS platform_menu_count FROM menu WHERE app_type='platform';
  -- 预期：11

  -- 商家端菜单数量
  SELECT COUNT(*) AS merchant_menu_count FROM menu WHERE app_type='merchant';
  -- 预期：6

  -- 仓储端菜单数量
  SELECT COUNT(*) AS warehouse_menu_count FROM menu WHERE app_type='warehouse';
  -- 预期：4

  -- 权限数量
  SELECT app_type, COUNT(*) AS perm_count FROM permission GROUP BY app_type;
  -- 预期：platform=20, merchant=4, warehouse=3

  -- 角色-菜单关联完整性
  SELECT r.name, r.app_type, COUNT(rm.menu_id) AS menu_count
  FROM role r LEFT JOIN role_menu rm ON r.id = rm.role_id
  GROUP BY r.id;
  -- 预期：platform_admin=11, merchant_admin=6, warehouse_admin=4

  -- 角色-权限关联完整性
  SELECT r.name, r.app_type, COUNT(rp.permission_id) AS perm_count
  FROM role r LEFT JOIN role_permission rp ON r.id = rp.role_id
  GROUP BY r.id;
  -- 预期：platform_admin=20, merchant_admin=4, warehouse_admin=3

  -- 管理员账号状态
  SELECT id, username, realname, app_type, role_id, status FROM admin;
  -- 预期：3条记录，status 全部为 1
"
```

### 5.2 后端 API 验收

```bash
BASE_URL="http://localhost:8000"

# ---- 登录 & 获取 Token ----

# 平台管理员登录
PLATFORM_TOKEN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"platform_admin","password":"123456"}' | jq -r '.data.token')
echo "平台端 Token: ${PLATFORM_TOKEN:0:16}..."

# 商家管理员登录
MERCHANT_TOKEN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"merchant_admin","password":"123456"}' | jq -r '.data.token')
echo "商家端 Token: ${MERCHANT_TOKEN:0:16}..."

# 仓储管理员登录
WAREHOUSE_TOKEN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"warehouse_admin","password":"123456"}' | jq -r '.data.token')
echo "仓储端 Token: ${WAREHOUSE_TOKEN:0:16}..."

# ---- 登录响应验收 ----

# 平台端：登录返回菜单数量
curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"platform_admin","password":"123456"}' | \
  jq '{menus: (.data.menus | length), permissions: (.data.permissions | length), app_type: .data.user.app_type}'
# 预期：menus=3(顶级目录), permissions=20, app_type="platform"

# 商家端：登录返回
curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"merchant_admin","password":"123456"}' | \
  jq '{menus: (.data.menus | length), permissions: (.data.permissions | length), app_type: .data.user.app_type}'
# 预期：menus=2(顶级目录), permissions=4, app_type="merchant"

# 仓储端：登录返回
curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"warehouse_admin","password":"123456"}' | \
  jq '{menus: (.data.menus | length), permissions: (.data.permissions | length), app_type: .data.user.app_type}'
# 预期：menus=1(顶级目录), permissions=3, app_type="warehouse"

# ---- Token 鉴权验收 ----

# 用平台端 Token 获取用户信息
curl -s "$BASE_URL/api/auth/info" \
  -H "Authorization: Bearer $PLATFORM_TOKEN" | \
  jq '{user: .data.user.realname, role: .data.role.name, menus: (.data.menus | length), perms: (.data.permissions | length)}'
# 预期：user="平台管理员", role="平台管理员", menus=3, perms=20

# 无效 Token 应返回 401
curl -s "$BASE_URL/api/auth/info" \
  -H "Authorization: Bearer invalid_token" | jq '.code'
# 预期：1 (错误码)

# ---- 角色 API 验收 ----

# 获取平台端角色列表
curl -s "$BASE_URL/api/roles/platform" | jq '.data | length'
# 预期：1

# 获取商家端角色列表
curl -s "$BASE_URL/api/roles/merchant" | jq '.data | length'
# 预期：1

# 获取仓储端角色列表
curl -s "$BASE_URL/api/roles/warehouse" | jq '.data | length'
# 预期：1

# ---- 菜单授权验收 ----

# 获取平台管理员(角色ID=1)已授权菜单
curl -s "$BASE_URL/api/roles/1/menus" | jq '.data | sort'
# 预期：[1,2,3,4,5,6,7,8,9,10,11]

# 获取商家管理员(角色ID=2)已授权菜单
curl -s "$BASE_URL/api/roles/2/menus" | jq '.data | sort'
# 预期：[12,13,14,15,16,17]

# 获取仓储管理员(角色ID=3)已授权菜单
curl -s "$BASE_URL/api/roles/3/menus" | jq '.data | sort'
# 预期：[18,19,20,21]

# ---- 操作授权验收 ----

# 获取平台管理员(角色ID=1)已授权权限
curl -s "$BASE_URL/api/roles/1/permissions" | jq '.data | sort'
# 预期：[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]

# 获取商家管理员(角色ID=2)已授权权限
curl -s "$BASE_URL/api/roles/2/permissions" | jq '.data | sort'
# 预期：[21,22,23,24]

# 获取仓储管理员(角色ID=3)已授权权限
curl -s "$BASE_URL/api/roles/3/permissions" | jq '.data | sort'
# 预期：[25,26,27]

# ---- 权限矩阵验收 ----

# 获取平台管理员权限矩阵统计
curl -s "$BASE_URL/api/roles/1/matrix" | jq '.data.stats'
# 预期：total_menus=11, total_permissions=20, granted_menu_count=11, granted_permission_count=20

# 获取商家管理员权限矩阵统计
curl -s "$BASE_URL/api/roles/2/matrix" | jq '.data.stats'
# 预期：total_menus=6, total_permissions=4, granted_menu_count=6, granted_permission_count=4

# 获取仓储管理员权限矩阵统计
curl -s "$BASE_URL/api/roles/3/matrix" | jq '.data.stats'
# 预期：total_menus=4, total_permissions=3, granted_menu_count=4, granted_permission_count=3

# ---- 跨端隔离校验 ----

# 平台端角色不能授权商家端菜单（应报错）
curl -s -X POST "$BASE_URL/api/roles/1/menus" \
  -H "Content-Type: application/json" \
  -d '{"menu_ids":[12,13]}' | jq '{code, message}'
# 预期：code=1, message 包含 "不能授权其他端的菜单"

# 角色未授权菜单时不能授权其下权限（孤儿权限校验）
curl -s -X POST "$BASE_URL/api/roles/1/permissions" \
  -H "Content-Type: application/json" \
  -d '{"permission_ids":[25,26]}' | jq '{code, message}'
# 预期：code=1, message 包含 "所属菜单未授权"

# ---- 菜单/权限 API 验收 ----

# 获取平台端菜单树
curl -s "$BASE_URL/api/menus/platform/tree" | jq '.data | length'
# 预期：3（系统管理、订单管理、商品管理三个顶级目录）

# 获取商家端菜单树
curl -s "$BASE_URL/api/menus/merchant/tree" | jq '.data | length'
# 预期：2

# 获取仓储端菜单树
curl -s "$BASE_URL/api/menus/warehouse/tree" | jq '.data | length'
# 预期：1

# 获取平台端权限列表
curl -s "$BASE_URL/api/permissions/platform" | jq '.data | length'
# 预期：20

# 获取商家端权限列表
curl -s "$BASE_URL/api/permissions/merchant" | jq '.data | length'
# 预期：4

# 获取仓储端权限列表
curl -s "$BASE_URL/api/permissions/warehouse" | jq '.data | length'
# 预期：3
```

### 5.3 后端单元测试验收

```bash
cd server

# 运行全部测试
./vendor/bin/phpunit

# 预期：全部测试通过，输出类似：
# OK (XX tests, YY assertions)
```

关键测试用例覆盖：
- 角色增删改查生命周期
- 菜单授权完整生命周期（授权→查询→变更→清空）
- 操作授权完整生命周期
- 权限矩阵授权与统计
- 跨端菜单/权限隔离校验
- 孤儿权限拦截（菜单未授权时拒绝其下权限）
- 批量菜单/权限授权
- 菜单授权→操作授权联动一致性

### 5.4 前端验收

```bash
cd web
npm run build
# 预期：构建成功，无错误

# 手动验收流程：
# 1. 访问 http://localhost:5173 → 自动跳转登录页
# 2. 使用 platform_admin / 123456 登录 → 进入平台端首页
# 3. 左侧菜单应显示：系统管理（角色/菜单/权限/用户管理）、订单管理、商品管理
# 4. 切换 merchant_admin 登录 → 菜单仅显示订单管理、商品管理
# 5. 切换 warehouse_admin 登录 → 菜单仅显示库存管理
# 6. 角色管理页面 → 可进行菜单授权、操作授权、权限矩阵操作
```

---

## 6. 一键验收脚本

```bash
#!/bin/bash
# rbac-verify.sh — 三端角色 RBAC 一键验收

set -e
BASE_URL="${1:-http://localhost:8000}"
PASS=0
FAIL=0

check() {
  local desc="$1"
  local actual="$2"
  local expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $desc: $actual"
    PASS=$((PASS + 1))
  else
    echo "  ❌ $desc: 期望=$expected 实际=$actual"
    FAIL=$((FAIL + 1))
  fi
}

echo "=============================="
echo " 三端角色 RBAC 验收"
echo " API: $BASE_URL"
echo "=============================="

# ---- 数据库检查 ----
echo ""
echo "[1/6] 数据库表结构检查"
TABLES=$(mysql -u root rbac_system -sN -e "SHOW TABLES" 2>/dev/null || echo "")
for t in admin role menu permission role_menu role_permission; do
  check "表 $t 存在" "$(echo "$TABLES" | grep -c "^${t}$")" "1"
done

echo ""
echo "[2/6] 初始数据量检查"
ROLE_COUNT=$(mysql -u root rbac_system -sN -e "SELECT COUNT(*) FROM role" 2>/dev/null || echo "0")
check "角色数量" "$ROLE_COUNT" "3"

MENU_COUNT=$(mysql -u root rbac_system -sN -e "SELECT COUNT(*) FROM menu" 2>/dev/null || echo "0")
check "菜单数量" "$MENU_COUNT" "21"

PERM_COUNT=$(mysql -u root rbac_system -sN -e "SELECT COUNT(*) FROM permission" 2>/dev/null || echo "0")
check "权限数量" "$PERM_COUNT" "27"

ADMIN_COUNT=$(mysql -u root rbac_system -sN -e "SELECT COUNT(*) FROM admin" 2>/dev/null || echo "0")
check "管理员数量" "$ADMIN_COUNT" "3"

# ---- API 登录检查 ----
echo ""
echo "[3/6] 三端登录检查"

P_LOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"platform_admin","password":"123456"}')
P_CODE=$(echo "$P_LOGIN" | jq -r '.code')
check "平台端登录" "$P_CODE" "0"
P_TOKEN=$(echo "$P_LOGIN" | jq -r '.data.token')

M_LOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"merchant_admin","password":"123456"}')
M_CODE=$(echo "$M_LOGIN" | jq -r '.code')
check "商家端登录" "$M_CODE" "0"

W_LOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"warehouse_admin","password":"123456"}')
W_CODE=$(echo "$W_LOGIN" | jq -r '.code')
check "仓储端登录" "$W_CODE" "0"

# ---- Token 鉴权 ----
echo ""
echo "[4/6] Token 鉴权检查"

INFO_CODE=$(curl -s "$BASE_URL/api/auth/info" \
  -H "Authorization: Bearer $P_TOKEN" | jq -r '.code')
check "有效 Token 鉴权" "$INFO_CODE" "0"

INVALID_CODE=$(curl -s "$BASE_URL/api/auth/info" \
  -H "Authorization: Bearer invalid_token_12345" | jq -r '.code')
check "无效 Token 拒绝" "$INVALID_CODE" "1"

# ---- 菜单授权检查 ----
echo ""
echo "[5/6] 菜单/权限授权检查"

P_MENUS=$(curl -s "$BASE_URL/api/roles/1/menus" | jq -r '.data | sort | join(",")')
check "平台管理员菜单" "$P_MENUS" "1,2,3,4,5,6,7,8,9,10,11"

M_MENUS=$(curl -s "$BASE_URL/api/roles/2/menus" | jq -r '.data | sort | join(",")')
check "商家管理员菜单" "$M_MENUS" "12,13,14,15,16,17"

W_MENUS=$(curl -s "$BASE_URL/api/roles/3/menus" | jq -r '.data | sort | join(",")')
check "仓储管理员菜单" "$W_MENUS" "18,19,20,21"

P_PERMS=$(curl -s "$BASE_URL/api/roles/1/permissions" | jq -r '.data | sort | join(",")')
check "平台管理员权限" "$P_PERMS" "1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20"

M_PERMS=$(curl -s "$BASE_URL/api/roles/2/permissions" | jq -r '.data | sort | join(",")')
check "商家管理员权限" "$M_PERMS" "21,22,23,24"

W_PERMS=$(curl -s "$BASE_URL/api/roles/3/permissions" | jq -r '.data | sort | join(",")')
check "仓储管理员权限" "$W_PERMS" "25,26,27"

# ---- 跨端隔离 ----
echo ""
echo "[6/6] 跨端隔离校验"

CROSS_MENU=$(curl -s -X POST "$BASE_URL/api/roles/1/menus" \
  -H "Content-Type: application/json" \
  -d '{"menu_ids":[12]}' | jq -r '.code')
check "跨端菜单授权拒绝" "$CROSS_MENU" "1"

ORPHAN_PERM=$(curl -s -X POST "$BASE_URL/api/roles/1/permissions" \
  -H "Content-Type: application/json" \
  -d '{"permission_ids":[25]}' | jq -r '.code')
check "孤儿权限授权拒绝" "$ORPHAN_PERM" "1"

# ---- 汇总 ----
echo ""
echo "=============================="
echo " 验收结果: ✅ $PASS 通过  ❌ $FAIL 失败"
echo "=============================="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
```

使用方式：

```bash
chmod +x rbac-verify.sh
./rbac-verify.sh http://localhost:8000
```

---

## 7. 初始管理员账号

| 端 | 用户名 | 密码 | 真实姓名 | 角色 |
|----|--------|------|----------|------|
| 平台端 | `platform_admin` | `123456` | 平台管理员 | platform_admin |
| 商家端 | `merchant_admin` | `123456` | 商家管理员 | merchant_admin |
| 仓储端 | `warehouse_admin` | `123456` | 仓储管理员 | warehouse_admin |

> ⚠️ 生产环境部署后请立即修改默认密码。
