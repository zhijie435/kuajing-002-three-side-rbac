<?php

class RoleController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        if (!in_array($appType, ['platform', 'merchant', 'warehouse'], true)) {
            $this->json(['code' => 1, 'message' => '无效的端类型'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM `role` WHERE app_type = :app_type ORDER BY id ASC');
        $stmt->execute([':app_type' => $appType]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $roles]);
    }

    public function store()
    {
        $data = $this->getInput();
        $name = trim($data['name'] ?? '');
        $code = trim($data['code'] ?? '');
        $appType = $data['app_type'] ?? '';
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 1;

        if (empty($name) || empty($code) || empty($appType)) {
            $this->json(['code' => 1, 'message' => '角色名称、编码、端类型为必填项'], 400);
        }

        if (!in_array($appType, ['platform', 'merchant', 'warehouse'], true)) {
            $this->json(['code' => 1, 'message' => '无效的端类型'], 400);
        }

        if (strlen($name) > 64) {
            $this->json(['code' => 1, 'message' => '角色名称不能超过 64 个字符'], 400);
        }

        if (strlen($code) > 64) {
            $this->json(['code' => 1, 'message' => '角色编码不能超过 64 个字符'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE code = :code AND app_type = :app_type');
        $stmt->execute([':code' => $code, ':app_type' => $appType]);
        if ($stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '同端下角色编码已存在，请更换编码'], 409);
        }

        $stmt = $this->pdo->prepare('INSERT INTO `role` (name, code, app_type, description, status) VALUES (:name, :code, :app_type, :description, :status)');
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':app_type' => $appType,
            ':description' => $description,
            ':status' => intval($status),
        ]);

        $this->json(['code' => 0, 'data' => ['id' => $this->pdo->lastInsertId()], 'message' => '创建成功']);
    }

    public function update($id)
    {
        $data = $this->getInput();
        $fields = [];
        $params = [':id' => intval($id)];

        $stmt = $this->pdo->prepare('SELECT app_type FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        $allowFields = ['name', 'code', 'description', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (isset($params[':name']) && strlen(trim($params[':name'])) > 64) {
            $this->json(['code' => 1, 'message' => '角色名称不能超过 64 个字符'], 400);
        }

        if (isset($params[':code']) && strlen(trim($params[':code'])) > 64) {
            $this->json(['code' => 1, 'message' => '角色编码不能超过 64 个字符'], 400);
        }

        if (isset($params[':code'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE code = :code AND app_type = :app_type AND id != :exclude_id');
            $stmt->execute([
                ':code' => $params[':code'],
                ':app_type' => $role['app_type'],
                ':exclude_id' => intval($id),
            ]);
            if ($stmt->fetch()) {
                $this->json(['code' => 1, 'message' => '同端下角色编码已存在，请更换编码'], 409);
            }
        }

        if (empty($fields)) {
            $this->json(['code' => 1, 'message' => '无更新数据'], 400);
        }

        $sql = 'UPDATE `role` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->json(['code' => 0, 'message' => '更新成功']);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        if (!$stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);
            $this->pdo->prepare('DELETE FROM `role` WHERE id = :id')->execute([':id' => intval($id)]);

            $this->pdo->commit();
            $this->json(['code' => 0, 'message' => '删除成功']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '删除失败: ' . $e->getMessage()], 500);
        }
    }

    public function assignMenus($id)
    {
        $data = $this->getInput();
        $menuIds = $data['menu_ids'] ?? [];

        $menuIds = is_array($menuIds) ? array_map('intval', $menuIds) : [];

        $stmt = $this->pdo->prepare('SELECT id, app_type FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        if (!empty($menuIds)) {
            $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, name, app_type FROM `menu` WHERE id IN ($placeholders)");
            $stmt->execute($menuIds);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $foundMenuIds = array_column($menus, 'id');
            $invalidMenuIds = array_values(array_diff($menuIds, $foundMenuIds));

            $wrongAppMenuIds = [];
            foreach ($menus as $m) {
                if ($m['app_type'] !== $role['app_type']) {
                    $wrongAppMenuIds[] = intval($m['id']);
                }
            }

            if (!empty($invalidMenuIds) || !empty($wrongAppMenuIds)) {
                $reasons = [];
                if (!empty($invalidMenuIds)) {
                    $reasons[] = '不存在的菜单ID: ' . implode(', ', $invalidMenuIds);
                }
                if (!empty($wrongAppMenuIds)) {
                    $reasons[] = '角色端类型[' . $role['app_type'] . ']不匹配的菜单ID: ' . implode(', ', $wrongAppMenuIds);
                }
                $this->json(['code' => 1, 'message' => implode('；', $reasons)], 400);
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);

            if (!empty($menuIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_menu (role_id, menu_id) VALUES (:role_id, :menu_id)');
                foreach (array_unique($menuIds) as $menuId) {
                    $stmt->execute([':role_id' => intval($id), ':menu_id' => $menuId]);
                }
            }

            $this->pdo->commit();
            $this->json(['code' => 0, 'data' => ['count' => count($menuIds)], 'message' => '菜单授权成功，共 ' . count($menuIds) . ' 个菜单']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '授权失败: ' . $e->getMessage()], 500);
        }
    }

    public function assignPermissions($id)
    {
        $data = $this->getInput();
        $permissionIds = $data['permission_ids'] ?? [];

        $permissionIds = is_array($permissionIds) ? array_map('intval', $permissionIds) : [];

        $stmt = $this->pdo->prepare('SELECT id, app_type FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        if (!empty($permissionIds)) {
            $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, name, app_type FROM `permission` WHERE id IN ($placeholders)");
            $stmt->execute($permissionIds);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $foundPermIds = array_column($permissions, 'id');
            $invalidPermIds = array_values(array_diff($permissionIds, $foundPermIds));

            $wrongAppPermIds = [];
            foreach ($permissions as $p) {
                if ($p['app_type'] !== $role['app_type']) {
                    $wrongAppPermIds[] = intval($p['id']);
                }
            }

            if (!empty($invalidPermIds) || !empty($wrongAppPermIds)) {
                $reasons = [];
                if (!empty($invalidPermIds)) {
                    $reasons[] = '不存在的权限ID: ' . implode(', ', $invalidPermIds);
                }
                if (!empty($wrongAppPermIds)) {
                    $reasons[] = '角色端类型[' . $role['app_type'] . ']不匹配的权限ID: ' . implode(', ', $wrongAppPermIds);
                }
                $this->json(['code' => 1, 'message' => implode('；', $reasons)], 400);
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => intval($id)]);

            if (!empty($permissionIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');
                foreach (array_unique($permissionIds) as $permissionId) {
                    $stmt->execute([':role_id' => intval($id), ':permission_id' => $permissionId]);
                }
            }

            $this->pdo->commit();
            $this->json(['code' => 0, 'data' => ['count' => count($permissionIds)], 'message' => '权限授权成功，共 ' . count($permissionIds) . ' 个权限']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '授权失败: ' . $e->getMessage()], 500);
        }
    }

    public function getMenus($id)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        if (!$stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        $stmt = $this->pdo->prepare('SELECT menu_id FROM role_menu WHERE role_id = :role_id');
        $stmt->execute([':role_id' => intval($id)]);
        $menuIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'menu_id');
        $this->json(['code' => 0, 'data' => array_map('intval', $menuIds)]);
    }

    public function getPermissions($id)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        if (!$stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '角色不存在'], 404);
        }

        $stmt = $this->pdo->prepare('SELECT permission_id FROM role_permission WHERE role_id = :role_id');
        $stmt->execute([':role_id' => intval($id)]);
        $permissionIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
        $this->json(['code' => 0, 'data' => array_map('intval', $permissionIds)]);
    }

    public function batchAssignMenus()
    {
        $data = $this->getInput();
        $roleIds = $data['role_ids'] ?? [];
        $menuIds = $data['menu_ids'] ?? [];

        $roleIds = is_array($roleIds) ? array_map('intval', $roleIds) : [];
        $menuIds = is_array($menuIds) ? array_map('intval', $menuIds) : [];

        if (empty($roleIds)) {
            $this->json(['code' => 1, 'message' => '请选择要授权的角色'], 400);
        }

        $successCount = 0;
        $failCount = 0;
        $failDetails = [];

        foreach ($roleIds as $roleId) {
            try {
                $stmt = $this->pdo->prepare('SELECT id, name, app_type FROM `role` WHERE id = :id');
                $stmt->execute([':id' => $roleId]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$role) {
                    $failCount++;
                    $failDetails[] = [
                        'role_id' => $roleId,
                        'role_name' => '',
                        'reason' => '角色不存在',
                    ];
                    continue;
                }

                if (!empty($menuIds)) {
                    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
                    $stmt = $this->pdo->prepare("SELECT id, name, app_type FROM `menu` WHERE id IN ($placeholders)");
                    $stmt->execute($menuIds);
                    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $foundMenuIds = array_column($menus, 'id');
                    $invalidMenuIds = array_values(array_diff($menuIds, $foundMenuIds));

                    $wrongAppMenuIds = [];
                    foreach ($menus as $m) {
                        if ($m['app_type'] !== $role['app_type']) {
                            $wrongAppMenuIds[] = intval($m['id']);
                        }
                    }

                    if (!empty($invalidMenuIds) || !empty($wrongAppMenuIds)) {
                        $reasons = [];
                        if (!empty($invalidMenuIds)) {
                            $reasons[] = '不存在的菜单ID: ' . implode(', ', $invalidMenuIds);
                        }
                        if (!empty($wrongAppMenuIds)) {
                            $reasons[] = '角色端类型[' . $role['app_type'] . ']不匹配的菜单ID: ' . implode(', ', $wrongAppMenuIds);
                        }
                        $failCount++;
                        $failDetails[] = [
                            'role_id' => $roleId,
                            'role_name' => $role['name'],
                            'role_app_type' => $role['app_type'],
                            'reason' => implode('；', $reasons),
                        ];
                        continue;
                    }
                }

                $this->pdo->beginTransaction();
                try {
                    $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

                    if (!empty($menuIds)) {
                        $stmt = $this->pdo->prepare('INSERT INTO role_menu (role_id, menu_id) VALUES (:role_id, :menu_id)');
                        foreach (array_unique($menuIds) as $menuId) {
                            $stmt->execute([':role_id' => $roleId, ':menu_id' => $menuId]);
                        }
                    }

                    $this->pdo->commit();
                    $successCount++;
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $failCount++;
                    $failDetails[] = [
                        'role_id' => $roleId,
                        'role_name' => $role['name'],
                        'reason' => '授权失败: ' . $e->getMessage(),
                    ];
                }
            } catch (Exception $e) {
                $failCount++;
                $failDetails[] = [
                    'role_id' => $roleId,
                    'role_name' => '',
                    'reason' => '处理异常: ' . $e->getMessage(),
                ];
            }
        }

        $this->json([
            'code' => 0,
            'data' => [
                'total' => count($roleIds),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'fail_details' => $failDetails,
            ],
            'message' => "批量菜单授权完成，成功 {$successCount} 个，失败 {$failCount} 个",
        ]);
    }

    public function batchAssignPermissions()
    {
        $data = $this->getInput();
        $roleIds = $data['role_ids'] ?? [];
        $permissionIds = $data['permission_ids'] ?? [];

        $roleIds = is_array($roleIds) ? array_map('intval', $roleIds) : [];
        $permissionIds = is_array($permissionIds) ? array_map('intval', $permissionIds) : [];

        if (empty($roleIds)) {
            $this->json(['code' => 1, 'message' => '请选择要授权的角色'], 400);
        }

        $successCount = 0;
        $failCount = 0;
        $failDetails = [];

        foreach ($roleIds as $roleId) {
            try {
                $stmt = $this->pdo->prepare('SELECT id, name, app_type FROM `role` WHERE id = :id');
                $stmt->execute([':id' => $roleId]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$role) {
                    $failCount++;
                    $failDetails[] = [
                        'role_id' => $roleId,
                        'role_name' => '',
                        'reason' => '角色不存在',
                    ];
                    continue;
                }

                if (!empty($permissionIds)) {
                    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
                    $stmt = $this->pdo->prepare("SELECT id, name, app_type FROM `permission` WHERE id IN ($placeholders)");
                    $stmt->execute($permissionIds);
                    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $foundPermIds = array_column($permissions, 'id');
                    $invalidPermIds = array_values(array_diff($permissionIds, $foundPermIds));

                    $wrongAppPermIds = [];
                    foreach ($permissions as $p) {
                        if ($p['app_type'] !== $role['app_type']) {
                            $wrongAppPermIds[] = intval($p['id']);
                        }
                    }

                    if (!empty($invalidPermIds) || !empty($wrongAppPermIds)) {
                        $reasons = [];
                        if (!empty($invalidPermIds)) {
                            $reasons[] = '不存在的权限ID: ' . implode(', ', $invalidPermIds);
                        }
                        if (!empty($wrongAppPermIds)) {
                            $reasons[] = '角色端类型[' . $role['app_type'] . ']不匹配的权限ID: ' . implode(', ', $wrongAppPermIds);
                        }
                        $failCount++;
                        $failDetails[] = [
                            'role_id' => $roleId,
                            'role_name' => $role['name'],
                            'role_app_type' => $role['app_type'],
                            'reason' => implode('；', $reasons),
                        ];
                        continue;
                    }
                }

                $this->pdo->beginTransaction();
                try {
                    $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

                    if (!empty($permissionIds)) {
                        $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');
                        foreach (array_unique($permissionIds) as $permissionId) {
                            $stmt->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
                        }
                    }

                    $this->pdo->commit();
                    $successCount++;
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $failCount++;
                    $failDetails[] = [
                        'role_id' => $roleId,
                        'role_name' => $role['name'],
                        'reason' => '授权失败: ' . $e->getMessage(),
                    ];
                }
            } catch (Exception $e) {
                $failCount++;
                $failDetails[] = [
                    'role_id' => $roleId,
                    'role_name' => '',
                    'reason' => '处理异常: ' . $e->getMessage(),
                ];
            }
        }

        $this->json([
            'code' => 0,
            'data' => [
                'total' => count($roleIds),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'fail_details' => $failDetails,
            ],
            'message' => "批量权限授权完成，成功 {$successCount} 个，失败 {$failCount} 个",
        ]);
    }

    private function getInput()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
