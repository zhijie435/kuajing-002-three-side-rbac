<?php

class PermissionController extends Controller
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT p.*, m.name AS menu_name FROM `permission` p LEFT JOIN `menu` m ON p.menu_id = m.id WHERE p.app_type = :app_type ORDER BY p.id ASC');
        $stmt->execute([':app_type' => $appType]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($permissions);
    }

    public function enabled($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT p.*, m.name AS menu_name FROM `permission` p LEFT JOIN `menu` m ON p.menu_id = m.id WHERE p.app_type = :app_type AND p.status = 1 ORDER BY p.id ASC');
        $stmt->execute([':app_type' => $appType]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($permissions);
    }

    public function store()
    {
        $data = $this->getJsonBody();
        $name = $data['name'] ?? '';
        $code = $data['code'] ?? '';
        $appType = $data['app_type'] ?? '';
        $menuId = $data['menu_id'] ?? 0;
        $description = $data['description'] ?? '';
        $status = $data['status'] ?? 1;

        if (empty($name) || empty($code) || empty($appType)) {
            $this->error('name, code, app_type 为必填项', 1, 400);
        }

        $this->validateAppType($appType);

        if (strlen($name) > 64) {
            $this->error('权限名称不能超过 64 个字符', 1, 400);
        }

        if (strlen($code) > 128) {
            $this->error('权限编码不能超过 128 个字符', 1, 400);
        }

        if ($menuId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, app_type FROM `menu` WHERE id = :id');
            $stmt->execute([':id' => intval($menuId)]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$menu) {
                $this->error('关联菜单不存在', 1, 400);
            }
            if ($menu['app_type'] !== $appType) {
                $this->error('关联菜单与当前权限不属于同一端', 1, 400);
            }
        }

        $stmt = $this->pdo->prepare('SELECT id FROM `permission` WHERE code = :code AND app_type = :app_type');
        $stmt->execute([':code' => $code, ':app_type' => $appType]);
        if ($stmt->fetch()) {
            $this->error('同端下权限编码已存在', 1, 409);
        }

        $stmt = $this->pdo->prepare('INSERT INTO `permission` (name, code, menu_id, app_type, description, status) VALUES (:name, :code, :menu_id, :app_type, :description, :status)');
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':menu_id' => intval($menuId),
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

        $stmt = $this->pdo->prepare('SELECT id, app_type FROM `permission` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$permission) {
            $this->error('权限不存在', 1, 404);
        }

        $allowFields = ['name', 'code', 'menu_id', 'description', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (isset($params[':name']) && strlen(trim($params[':name'])) > 64) {
            $this->error('权限名称不能超过 64 个字符', 1, 400);
        }

        if (isset($params[':code']) && strlen(trim($params[':code'])) > 128) {
            $this->error('权限编码不能超过 128 个字符', 1, 400);
        }

        if (isset($params[':code'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM `permission` WHERE code = :code AND app_type = :app_type AND id != :exclude_id');
            $stmt->execute([
                ':code' => $params[':code'],
                ':app_type' => $permission['app_type'],
                ':exclude_id' => intval($id),
            ]);
            if ($stmt->fetch()) {
                $this->error('同端下权限编码已存在', 1, 409);
            }
        }

        if (isset($params[':menu_id'])) {
            $newMenuId = intval($params[':menu_id']);
            if ($newMenuId > 0) {
                $stmt = $this->pdo->prepare('SELECT id, app_type FROM `menu` WHERE id = :id');
                $stmt->execute([':id' => $newMenuId]);
                $menu = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$menu) {
                    $this->error('关联菜单不存在', 1, 400);
                }
                if ($menu['app_type'] !== $permission['app_type']) {
                    $this->error('关联菜单与当前权限不属于同一端', 1, 400);
                }
            }
        }

        if (empty($fields)) {
            $this->error('无更新数据', 1, 400);
        }

        $sql = 'UPDATE `permission` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->success(null, '更新成功');
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `permission` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        if (!$stmt->fetch()) {
            $this->error('权限不存在', 1, 404);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permission WHERE permission_id = :permission_id')->execute([':permission_id' => intval($id)]);
            $this->pdo->prepare('DELETE FROM `permission` WHERE id = :id')->execute([':id' => intval($id)]);

            $this->pdo->commit();
            $this->success(null, '删除成功');
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->error('删除失败: ' . $e->getMessage(), 1, 500);
        }
    }
}
