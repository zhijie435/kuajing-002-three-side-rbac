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
        $stmt = $this->pdo->prepare('SELECT * FROM `role` WHERE app_type = :app_type ORDER BY id ASC');
        $stmt->execute([':app_type' => $appType]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $roles]);
    }

    public function store()
    {
        $data = $this->getInput();
        $name = $data['name'] ?? '';
        $code = $data['code'] ?? '';
        $appType = $data['app_type'] ?? '';
        $description = $data['description'] ?? '';

        if (empty($name) || empty($code) || empty($appType)) {
            $this->json(['code' => 1, 'message' => 'name, code, app_type 为必填项'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM `role` WHERE code = :code AND app_type = :app_type');
        $stmt->execute([':code' => $code, ':app_type' => $appType]);
        if ($stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '同端下角色编码已存在'], 409);
        }

        $stmt = $this->pdo->prepare('INSERT INTO `role` (name, code, app_type, description) VALUES (:name, :code, :app_type, :description)');
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':app_type' => $appType,
            ':description' => $description,
        ]);

        $this->json(['code' => 0, 'data' => ['id' => $this->pdo->lastInsertId()], 'message' => '创建成功']);
    }

    public function update($id)
    {
        $data = $this->getInput();
        $fields = [];
        $params = [':id' => $id];

        $allowFields = ['name', 'code', 'description', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
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
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => $id]);
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => $id]);
            $this->pdo->prepare('DELETE FROM `role` WHERE id = :id')->execute([':id' => $id]);

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

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_menu WHERE role_id = :role_id')->execute([':role_id' => $id]);

            if (!empty($menuIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_menu (role_id, menu_id) VALUES (:role_id, :menu_id)');
                foreach ($menuIds as $menuId) {
                    $stmt->execute([':role_id' => $id, ':menu_id' => intval($menuId)]);
                }
            }

            $this->pdo->commit();
            $this->json(['code' => 0, 'message' => '菜单授权成功']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '授权失败: ' . $e->getMessage()], 500);
        }
    }

    public function assignPermissions($id)
    {
        $data = $this->getInput();
        $permissionIds = $data['permission_ids'] ?? [];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permission WHERE role_id = :role_id')->execute([':role_id' => $id]);

            if (!empty($permissionIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)');
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([':role_id' => $id, ':permission_id' => intval($permissionId)]);
                }
            }

            $this->pdo->commit();
            $this->json(['code' => 0, 'message' => '权限授权成功']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '授权失败: ' . $e->getMessage()], 500);
        }
    }

    public function getMenus($id)
    {
        $stmt = $this->pdo->prepare('SELECT menu_id FROM role_menu WHERE role_id = :role_id');
        $stmt->execute([':role_id' => $id]);
        $menuIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'menu_id');
        $this->json(['code' => 0, 'data' => array_map('intval', $menuIds)]);
    }

    public function getPermissions($id)
    {
        $stmt = $this->pdo->prepare('SELECT permission_id FROM role_permission WHERE role_id = :role_id');
        $stmt->execute([':role_id' => $id]);
        $permissionIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
        $this->json(['code' => 0, 'data' => array_map('intval', $permissionIds)]);
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
