<?php

class MenuController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `menu` WHERE app_type = :app_type ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $this->buildTree($menus)]);
    }

    public function tree($appType)
    {
        $stmt = $this->pdo->prepare('SELECT id, parent_id, name, path, icon, type, permission_key, sort_order FROM `menu` WHERE app_type = :app_type ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $this->buildTree($menus)]);
    }

    public function enabled($appType)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `menu` WHERE app_type = :app_type AND status = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $this->buildTree($menus)]);
    }

    public function enabledTree($appType)
    {
        $stmt = $this->pdo->prepare('SELECT id, parent_id, name, path, icon, type, permission_key, sort_order FROM `menu` WHERE app_type = :app_type AND status = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['code' => 0, 'data' => $this->buildTree($menus)]);
    }

    public function store()
    {
        $data = $this->getInput();
        $name = $data['name'] ?? '';
        $appType = $data['app_type'] ?? '';
        $parentId = $data['parent_id'] ?? 0;
        $sortOrder = $data['sort_order'] ?? 0;
        $icon = $data['icon'] ?? '';
        $path = $data['path'] ?? '';
        $component = $data['component'] ?? '';
        $type = $data['type'] ?? 'menu';
        $permissionKey = $data['permission_key'] ?? '';
        $status = $data['status'] ?? 1;

        if (empty($name) || empty($appType)) {
            $this->json(['code' => 1, 'message' => 'name, app_type 为必填项'], 400);
        }

        $stmt = $this->pdo->prepare('INSERT INTO `menu` (parent_id, name, path, icon, component, app_type, sort_order, type, permission_key, status) VALUES (:parent_id, :name, :path, :icon, :component, :app_type, :sort_order, :type, :permission_key, :status)');
        $stmt->execute([
            ':parent_id' => intval($parentId),
            ':name' => $name,
            ':path' => $path,
            ':icon' => $icon,
            ':component' => $component,
            ':app_type' => $appType,
            ':sort_order' => intval($sortOrder),
            ':type' => $type,
            ':permission_key' => $permissionKey,
            ':status' => intval($status),
        ]);

        $this->json(['code' => 0, 'data' => ['id' => $this->pdo->lastInsertId()], 'message' => '创建成功']);
    }

    public function update($id)
    {
        $data = $this->getInput();
        $fields = [];
        $params = [':id' => $id];

        $allowFields = ['parent_id', 'name', 'path', 'icon', 'component', 'sort_order', 'type', 'permission_key', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            $this->json(['code' => 1, 'message' => '无更新数据'], 400);
        }

        $sql = 'UPDATE `menu` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->json(['code' => 0, 'message' => '更新成功']);
    }

    public function delete($id)
    {
        $childIds = $this->getDescendantIds($id);
        $allIds = array_merge([$id], $childIds);

        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($allIds), '?'));
            $this->pdo->prepare("DELETE FROM role_menu WHERE menu_id IN ($placeholders)")->execute($allIds);
            $this->pdo->prepare("DELETE FROM `menu` WHERE id IN ($placeholders)")->execute($allIds);

            $this->pdo->commit();
            $this->json(['code' => 0, 'message' => '删除成功']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->json(['code' => 1, 'message' => '删除失败: ' . $e->getMessage()], 500);
        }
    }

    private function getDescendantIds($parentId)
    {
        $ids = [];
        $stmt = $this->pdo->prepare('SELECT id FROM `menu` WHERE parent_id = :parent_id');
        $stmt->execute([':parent_id' => $parentId]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getDescendantIds($childId));
        }

        return $ids;
    }

    private function buildTree($menus, $parentId = 0)
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['children'] = $this->buildTree($menus, $menu['id']);
                $tree[] = $menu;
            }
        }
        return $tree;
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
