<?php

class PermissionController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        $stmt = $this->pdo->prepare('SELECT p.*, m.name AS menu_name FROM `permission` p LEFT JOIN `menu` m ON p.menu_id = m.id WHERE p.app_type = :app_type ORDER BY p.id ASC');
        $stmt->execute([':app_type' => $appType]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $permissions]);
    }

    public function enabled($appType)
    {
        $stmt = $this->pdo->prepare('SELECT p.*, m.name AS menu_name FROM `permission` p LEFT JOIN `menu` m ON p.menu_id = m.id WHERE p.app_type = :app_type AND p.status = 1 ORDER BY p.id ASC');
        $stmt->execute([':app_type' => $appType]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $permissions]);
    }

    public function store()
    {
        $data = $this->getInput();
        $name = $data['name'] ?? '';
        $code = $data['code'] ?? '';
        $appType = $data['app_type'] ?? '';
        $menuId = $data['menu_id'] ?? 0;
        $description = $data['description'] ?? '';
        $status = $data['status'] ?? 1;

        if (empty($name) || empty($code) || empty($appType)) {
            $this->json(['code' => 1, 'message' => 'name, code, app_type 为必填项'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM `permission` WHERE code = :code AND app_type = :app_type');
        $stmt->execute([':code' => $code, ':app_type' => $appType]);
        if ($stmt->fetch()) {
            $this->json(['code' => 1, 'message' => '同端下权限编码已存在'], 409);
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

        $this->json(['code' => 0, 'data' => ['id' => $this->pdo->lastInsertId()], 'message' => '创建成功']);
    }

    public function update($id)
    {
        $data = $this->getInput();
        $fields = [];
        $params = [':id' => $id];

        $allowFields = ['name', 'code', 'menu_id', 'description', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            $this->json(['code' => 1, 'message' => '无更新数据'], 400);
        }

        $sql = 'UPDATE `permission` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->json(['code' => 0, 'message' => '更新成功']);
    }

    public function delete($id)
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permission WHERE permission_id = :permission_id')->execute([':permission_id' => $id]);
            $this->pdo->prepare('DELETE FROM `permission` WHERE id = :id')->execute([':id' => $id]);

            $this->pdo->commit();
            $this->json(['code' => 0, 'message' => '删除成功']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '删除失败: ' . $e->getMessage()], 500);
        }
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
