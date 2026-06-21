<?php

class RoleController extends Controller
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT * FROM `role` WHERE app_type = :app_type ORDER BY id ASC');
        $stmt->execute([':app_type' => $appType]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($roles);
    }

    public function store()
    {
        $data = $this->getJsonBody();
        $name = trim($data['name'] ?? '');
        $code = trim($data['code'] ?? '');
        $appType = $data['app_type'] ?? '';
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 1;

        if (empty($name) || empty($code) || empty($appType)) {
            $this->error('角色名称、编码、端类型为必填项', 1, 400);
        }

        $this->validateAppType($appType);

        if (strlen($name) > 64) {
            $this->error('角色名称不能超过 64 个字符', 1, 400);
        }

        if (strlen($code) > 64) {
            $this->error('角色编码不能超过 64 个字符', 1, 400);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE code = :code AND app_type = :app_type');
        $stmt->execute([':code' => $code, ':app_type' => $appType]);
        if ($stmt->fetch()) {
            $this->error('同端下角色编码已存在，请更换编码', 1, 409);
        }

        $stmt = $this->pdo->prepare('INSERT INTO `role` (name, code, app_type, description, status) VALUES (:name, :code, :app_type, :description, :status)');
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':app_type' => $appType,
            ':description' => $description,
            ':status' => intval($status),
        ]);

        $this->success(['id' => $this->pdo->lastInsertId()], '创建成功');
    }

    public function update($id)
    {
        $data = $this->getJsonBody();
        $fields = [];
        $params = [':id' => intval($id)];

        $role = $this->getRoleById(intval($id));
        if (!$role) {
            $this->error('角色不存在', 1, 404);
        }

        $allowFields = ['name', 'code', 'description', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (isset($params[':name']) && strlen(trim($params[':name'])) > 64) {
            $this->error('角色名称不能超过 64 个字符', 1, 400);
        }

        if (isset($params[':code']) && strlen(trim($params[':code'])) > 64) {
            $this->error('角色编码不能超过 64 个字符', 1, 400);
        }

        if (isset($params[':code'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE code = :code AND app_type = :app_type AND id != :exclude_id');
            $stmt->execute([
                ':code' => $params[':code'],
                ':app_type' => $role['app_type'],
                ':exclude_id' => intval($id),
            ]);
            if ($stmt->fetch()) {
                $this->error('同端下角色编码已存在，请更换编码', 1, 409);
            }
        }

        if (empty($fields)) {
            $this->error('无更新数据', 1, 400);
        }

        $sql = 'UPDATE `role` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->success(null, '更新成功');
    }

    public function delete($id)
    {
        if (!$this->getRoleById(intval($id))) {
            $this->error('角色不存在', 1, 404);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);
            $this->pdo->prepare('DELETE FROM `role` WHERE id = :id')->execute([':id' => intval($id)]);

            $this->pdo->commit();
            $this->success(null, '删除成功');
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->error('删除失败: ' . $e->getMessage(), 1, 500);
        }
    }

    public function getMenus($id)
    {
        if (!$this->getRoleById(intval($id))) {
            $this->error('角色不存在', 1, 404);
        }

        $menuIds = $this->getRoleMenuIds(intval($id));
        $this->success(array_map('intval', $menuIds));
    }

    public function getPermissions($id)
    {
        if (!$this->getRoleById(intval($id))) {
            $this->error('角色不存在', 1, 404);
        }

        $permissionIds = $this->getRolePermissionIds(intval($id));
        $this->success(array_map('intval', $permissionIds));
    }

    public function getMatrix($id)
    {
        $role = $this->getRoleById(intval($id));
        if (!$role) {
            $this->error('角色不存在', 1, 404);
        }

        $appType = $role['app_type'];

        $menuStmt = $this->pdo->prepare('SELECT * FROM `menu` WHERE app_type = :app_type AND status = 1 ORDER BY sort_order ASC, id ASC');
        $menuStmt->execute([':app_type' => $appType]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        $menuTree = $this->buildMenuTree($menus);
        $allMenuIds = array_column($menus, 'id');

        $permStmt = $this->pdo->prepare('SELECT p.*, m.name AS menu_name FROM `permission` p LEFT JOIN `menu` m ON p.menu_id = m.id WHERE p.app_type = :app_type AND p.status = 1 ORDER BY p.id ASC');
        $permStmt->execute([':app_type' => $appType]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);

        $grantedMenuIds = $this->getRoleMenuIds(intval($id));
        $grantedPermIds = $this->getRolePermissionIds(intval($id));

        $flatMenus = $this->flattenMenuTree($menuTree);
        $matrixRows = [];
        $grantedMenuSet = array_flip($grantedMenuIds);
        $grantedPermSet = array_flip($grantedPermIds);

        foreach ($flatMenus as $menu) {
            $menuPerms = array_values(array_filter($permissions, function ($p) use ($menu) {
                return intval($p['menu_id']) === intval($menu['id']);
            }));

            $menuChecked = isset($grantedMenuSet[$menu['id']]);

            if (!empty($menuPerms)) {
                foreach ($menuPerms as $perm) {
                    $matrixRows[] = [
                        'menu_id' => intval($menu['id']),
                        'menu_name' => $menu['name'],
                        'permission_id' => intval($perm['id']),
                        'permission_name' => $perm['name'],
                        'permission_code' => $perm['code'],
                        'menu_checked' => $menuChecked,
                        'permission_checked' => isset($grantedPermSet[$perm['id']]),
                        'type' => 'permission',
                    ];
                }
            } else {
                $matrixRows[] = [
                    'menu_id' => intval($menu['id']),
                    'menu_name' => $menu['name'],
                    'permission_id' => null,
                    'permission_name' => '-',
                    'permission_code' => '-',
                    'menu_checked' => $menuChecked,
                    'permission_checked' => false,
                    'type' => 'menu',
                ];
            }
        }

        $this->success([
            'role' => [
                'id' => intval($role['id']),
                'name' => $role['name'],
                'code' => $role['code'],
                'app_type' => $role['app_type'],
                'app_type_label' => $this->getAppTypeLabel($role['app_type']),
                'description' => $role['description'],
                'status' => intval($role['status']),
            ],
            'menu_tree' => $menuTree,
            'permissions' => $permissions,
            'matrix_rows' => $matrixRows,
            'granted_menu_ids' => array_map('intval', $grantedMenuIds),
            'granted_permission_ids' => array_map('intval', $grantedPermIds),
            'stats' => [
                'total_menus' => count($allMenuIds),
                'total_permissions' => count($permissions),
                'granted_menu_count' => count($grantedMenuIds),
                'granted_permission_count' => count($grantedPermIds),
            ],
        ]);
    }

    public function assignMenus($id)
    {
        $data = $this->getJsonBody();
        $menuIds = $data['menu_ids'] ?? [];
        $menuIds = is_array($menuIds) ? array_values(array_unique(array_map('intval', $menuIds))) : [];

        $role = $this->getRoleById(intval($id));
        if (!$role) {
            $this->error('角色不存在', 1, 404);
        }

        $validateErrors = $this->validateMenuIds($menuIds, $role['app_type']);
        if (!empty($validateErrors)) {
            $this->error('菜单授权校验失败：' . $validateErrors[0], 1, 400, ['validate_errors' => $validateErrors]);
        }

        try {
            $this->saveRoleMenus(intval($id), $menuIds);
            $this->success([
                'menu_count' => count($menuIds),
                'permission_count' => 0,
            ], '菜单授权成功，共 ' . count($menuIds) . ' 个菜单');
        } catch (Exception $e) {
            $this->error('菜单授权失败，数据库已自动回滚：' . $e->getMessage(), 1, 500, ['rollback' => true]);
        }
    }

    public function assignPermissions($id)
    {
        $data = $this->getJsonBody();
        $permissionIds = $data['permission_ids'] ?? [];
        $permissionIds = is_array($permissionIds) ? array_values(array_unique(array_map('intval', $permissionIds))) : [];

        $role = $this->getRoleById(intval($id));
        if (!$role) {
            $this->error('角色不存在', 1, 404);
        }

        $existingMenuIds = $this->getRoleMenuIds(intval($id));

        $validateErrors = $this->validatePermissionIds($permissionIds, $role['app_type'], $existingMenuIds);
        if (!empty($validateErrors)) {
            $this->error('权限授权校验失败：' . $validateErrors[0], 1, 400, ['validate_errors' => $validateErrors]);
        }

        try {
            $this->saveRolePermissions(intval($id), $permissionIds);
            $this->success([
                'menu_count' => 0,
                'permission_count' => count($permissionIds),
            ], '权限授权成功，共 ' . count($permissionIds) . ' 个权限');
        } catch (Exception $e) {
            $this->error('权限授权失败，数据库已自动回滚：' . $e->getMessage(), 1, 500, ['rollback' => true]);
        }
    }

    public function assignMatrix($id)
    {
        $data = $this->getJsonBody();
        $menuIds = $data['menu_ids'] ?? [];
        $permissionIds = $data['permission_ids'] ?? [];

        $menuIds = is_array($menuIds) ? array_values(array_unique(array_map('intval', $menuIds))) : [];
        $permissionIds = is_array($permissionIds) ? array_values(array_unique(array_map('intval', $permissionIds))) : [];

        $role = $this->getRoleById(intval($id));
        if (!$role) {
            $this->error('角色不存在', 1, 404);
        }

        $validateErrors = array_merge(
            $this->validateMenuIds($menuIds, $role['app_type']),
            $this->validatePermissionIds($permissionIds, $role['app_type'], $menuIds)
        );

        if (!empty($validateErrors)) {
            $this->error('权限矩阵校验失败：' . $validateErrors[0], 1, 400, ['validate_errors' => $validateErrors]);
        }

        try {
            $this->saveRoleMatrix(intval($id), $menuIds, $permissionIds);
            $this->success([
                'menu_count' => count($menuIds),
                'permission_count' => count($permissionIds),
            ], '权限矩阵保存成功');
        } catch (Exception $e) {
            $this->error('权限矩阵保存失败，数据库已自动回滚：' . $e->getMessage(), 1, 500, ['rollback' => true]);
        }
    }

    public function batchAssignMenus()
    {
        $data = $this->getJsonBody();
        $roleIds = $data['role_ids'] ?? [];
        $menuIds = $data['menu_ids'] ?? [];

        $roleIds = is_array($roleIds) ? array_values(array_unique(array_map('intval', $roleIds))) : [];
        $menuIds = is_array($menuIds) ? array_values(array_unique(array_map('intval', $menuIds))) : [];

        if (empty($roleIds)) {
            $this->error('请选择要授权的角色', 1, 400);
        }

        $roles = $this->getRolesByIds($roleIds);
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['id']] = $role;
        }

        $successCount = 0;
        $failCount = 0;
        $failDetails = [];

        foreach ($roleIds as $roleId) {
            try {
                if (!isset($roleMap[$roleId])) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail($roleId, '', '', ['角色不存在'], '角色不存在');
                    continue;
                }

                $role = $roleMap[$roleId];
                $validateErrors = $this->validateMenuIds($menuIds, $role['app_type']);
                if (!empty($validateErrors)) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail(
                        $roleId,
                        $role['name'],
                        $role['app_type'],
                        $validateErrors,
                        implode('；', $validateErrors)
                    );
                    continue;
                }

                try {
                    $this->saveRoleMenus($roleId, $menuIds);
                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail(
                        $roleId,
                        $role['name'],
                        $role['app_type'],
                        ['数据库写入失败，已自动回滚：' . $e->getMessage()],
                        '授权失败，数据库已自动回滚：' . $e->getMessage(),
                        true
                    );
                }
            } catch (Exception $e) {
                $failCount++;
                $failDetails[] = $this->buildFailDetail($roleId, '', '', ['处理异常：' . $e->getMessage()], '处理异常：' . $e->getMessage());
            }
        }

        $this->success([
            'total' => count($roleIds),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'fail_details' => $failDetails,
        ], "批量菜单授权完成，成功 {$successCount} 个，失败 {$failCount} 个");
    }

    public function batchAssignPermissions()
    {
        $data = $this->getJsonBody();
        $roleIds = $data['role_ids'] ?? [];
        $permissionIds = $data['permission_ids'] ?? [];

        $roleIds = is_array($roleIds) ? array_values(array_unique(array_map('intval', $roleIds))) : [];
        $permissionIds = is_array($permissionIds) ? array_values(array_unique(array_map('intval', $permissionIds))) : [];

        if (empty($roleIds)) {
            $this->error('请选择要授权的角色', 1, 400);
        }

        $roles = $this->getRolesByIds($roleIds);
        $roleMap = [];
        $roleMenuMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['id']] = $role;
            $roleMenuMap[$role['id']] = $this->getRoleMenuIds($role['id']);
        }

        $successCount = 0;
        $failCount = 0;
        $failDetails = [];

        foreach ($roleIds as $roleId) {
            try {
                if (!isset($roleMap[$roleId])) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail($roleId, '', '', ['角色不存在'], '角色不存在');
                    continue;
                }

                $role = $roleMap[$roleId];
                $existingMenuIds = $roleMenuMap[$roleId] ?? [];

                $validateErrors = $this->validatePermissionIds($permissionIds, $role['app_type'], $existingMenuIds);
                if (!empty($validateErrors)) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail(
                        $roleId,
                        $role['name'],
                        $role['app_type'],
                        $validateErrors,
                        implode('；', $validateErrors)
                    );
                    continue;
                }

                try {
                    $this->saveRolePermissions($roleId, $permissionIds);
                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                    $failDetails[] = $this->buildFailDetail(
                        $roleId,
                        $role['name'],
                        $role['app_type'],
                        ['数据库写入失败，已自动回滚：' . $e->getMessage()],
                        '授权失败，数据库已自动回滚：' . $e->getMessage(),
                        true
                    );
                }
            } catch (Exception $e) {
                $failCount++;
                $failDetails[] = $this->buildFailDetail($roleId, '', '', ['处理异常：' . $e->getMessage()], '处理异常：' . $e->getMessage());
            }
        }

        $this->success([
            'total' => count($roleIds),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'fail_details' => $failDetails,
        ], "批量权限授权完成，成功 {$successCount} 个，失败 {$failCount} 个");
    }

    private function getRoleById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `role` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ?: null;
    }

    private function getRolesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM `role` WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRoleMenuIds(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT rm.menu_id FROM role_menu rm INNER JOIN `menu` m ON rm.menu_id = m.id WHERE rm.role_id = :role_id AND m.status = 1');
        $stmt->execute([':role_id' => $roleId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'menu_id');
    }

    private function getRolePermissionIds(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT rp.permission_id FROM role_permission rp INNER JOIN `permission` p ON rp.permission_id = p.id WHERE rp.role_id = :role_id AND p.status = 1');
        $stmt->execute([':role_id' => $roleId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
    }

    private function saveRoleMenus(int $roleId, array $menuIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

            if (!empty($menuIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_menu (role_id, menu_id) VALUES (:role_id, :menu_id)');
                foreach ($menuIds as $menuId) {
                    $stmt->execute([':role_id' => $roleId, ':menu_id' => $menuId]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function saveRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

            if (!empty($permissionIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function saveRoleMatrix(int $roleId, array $menuIds, array $permissionIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => $roleId]);
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

            if (!empty($menuIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_menu (role_id, menu_id) VALUES (:role_id, :menu_id)');
                foreach ($menuIds as $menuId) {
                    $stmt->execute([':role_id' => $roleId, ':menu_id' => $menuId]);
                }
            }

            if (!empty($permissionIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function validateMenuIds(array $menuIds, string $roleAppType): array
    {
        $errors = [];
        if (empty($menuIds)) {
            return $errors;
        }

        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name, app_type, status FROM `menu` WHERE id IN ($placeholders)");
        $stmt->execute($menuIds);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $foundMenuIds = array_column($menus, 'id');
        $invalidMenuIds = array_values(array_diff($menuIds, $foundMenuIds));

        $wrongAppMenuIds = [];
        $disabledMenuIds = [];
        $appTypeLabel = $this->getAppTypeLabel($roleAppType);
        foreach ($menus as $m) {
            if ($m['app_type'] !== $roleAppType) {
                $wrongAppMenuIds[] = intval($m['id']);
            }
            if (intval($m['status']) !== 1) {
                $disabledMenuIds[] = intval($m['id']);
            }
        }

        if (!empty($invalidMenuIds)) {
            $errors[] = '不存在的菜单ID：' . implode('、', $invalidMenuIds);
        }
        if (!empty($wrongAppMenuIds)) {
            $errors[] = '[' . $appTypeLabel . ']角色不能授权其他端的菜单ID：' . implode('、', $wrongAppMenuIds);
        }
        if (!empty($disabledMenuIds)) {
            $errors[] = '已禁用的菜单ID：' . implode('、', $disabledMenuIds);
        }

        return $errors;
    }

    private function validatePermissionIds(array $permissionIds, string $roleAppType, $grantedMenuIds = null): array
    {
        $errors = [];
        if (empty($permissionIds)) {
            return $errors;
        }

        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name, app_type, status, menu_id FROM `permission` WHERE id IN ($placeholders)");
        $stmt->execute($permissionIds);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $foundPermIds = array_column($permissions, 'id');
        $invalidPermIds = array_values(array_diff($permissionIds, $foundPermIds));

        $wrongAppPermIds = [];
        $disabledPermIds = [];
        $orphanPermIds = [];
        $appTypeLabel = $this->getAppTypeLabel($roleAppType);
        $menuIdSet = $grantedMenuIds !== null ? array_flip($grantedMenuIds) : null;
        foreach ($permissions as $p) {
            if ($p['app_type'] !== $roleAppType) {
                $wrongAppPermIds[] = intval($p['id']);
            }
            if (intval($p['status']) !== 1) {
                $disabledPermIds[] = intval($p['id']);
            }
            if ($menuIdSet !== null && !empty($p['menu_id']) && !isset($menuIdSet[intval($p['menu_id'])])) {
                $orphanPermIds[] = intval($p['id']);
            }
        }

        if (!empty($invalidPermIds)) {
            $errors[] = '不存在的权限ID：' . implode('、', $invalidPermIds);
        }
        if (!empty($wrongAppPermIds)) {
            $errors[] = '[' . $appTypeLabel . ']角色不能授权其他端的权限ID：' . implode('、', $wrongAppPermIds);
        }
        if (!empty($disabledPermIds)) {
            $errors[] = '已禁用的权限ID：' . implode('、', $disabledPermIds);
        }
        if (!empty($orphanPermIds)) {
            $errors[] = '所属菜单未授权的权限ID：' . implode('、', $orphanPermIds) . '（请先勾选对应菜单）';
        }

        return $errors;
    }

    private function buildFailDetail($roleId, $roleName, $roleAppType, $validateErrors, $reason, $rollback = false): array
    {
        return [
            'role_id' => intval($roleId),
            'role_name' => $roleName,
            'role_app_type' => $roleAppType,
            'role_app_type_label' => $roleAppType ? $this->getAppTypeLabel($roleAppType) : '',
            'validate_errors' => $validateErrors,
            'reason' => $reason,
            'rollback' => $rollback,
        ];
    }

    private function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['children'] = $this->buildMenuTree($menus, $menu['id']);
                $tree[] = $menu;
            }
        }
        return $tree;
    }

    private function flattenMenuTree(array $menus): array
    {
        $result = [];
        foreach ($menus as $menu) {
            $result[] = $menu;
            if (!empty($menu['children'])) {
                $result = array_merge($result, $this->flattenMenuTree($menu['children']));
            }
        }
        return $result;
    }
}
