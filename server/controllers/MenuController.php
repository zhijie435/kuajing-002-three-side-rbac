<?php

class MenuController extends Controller
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function index($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT * FROM `menu` WHERE app_type = :app_type ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($this->buildTree($menus));
    }

    public function tree($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT id, parent_id, name, path, icon, type, permission_key, sort_order FROM `menu` WHERE app_type = :app_type ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($this->buildTree($menus));
    }

    public function enabled($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT * FROM `menu` WHERE app_type = :app_type AND status = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($this->buildTree($menus));
    }

    public function enabledTree($appType)
    {
        $this->validateAppType($appType);

        $stmt = $this->pdo->prepare('SELECT id, parent_id, name, path, icon, type, permission_key, sort_order FROM `menu` WHERE app_type = :app_type AND status = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success($this->buildTree($menus));
    }

    public function store()
    {
        $data = $this->getJsonBody();
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
            $this->error('name, app_type 为必填项', 1, 400);
        }

        $this->validateAppType($appType);

        if (strlen($name) > 64) {
            $this->error('菜单名称不能超过 64 个字符', 1, 400);
        }

        if ($parentId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, app_type FROM `menu` WHERE id = :id');
            $stmt->execute([':id' => intval($parentId)]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$parent) {
                $this->error('父菜单不存在', 1, 400);
            }
            if ($parent['app_type'] !== $appType) {
                $this->error('父菜单与当前菜单不属于同一端', 1, 400);
            }
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

        $this->success(['id' => $this->pdo->lastInsertId()], '创建成功');
    }

    public function update($id)
    {
        $data = $this->getJsonBody();
        $fields = [];
        $params = [':id' => intval($id)];

        $stmt = $this->pdo->prepare('SELECT id, app_type, parent_id FROM `menu` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$menu) {
            $this->error('菜单不存在', 1, 404);
        }

        $allowFields = ['parent_id', 'name', 'path', 'icon', 'component', 'sort_order', 'type', 'permission_key', 'status'];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (isset($params[':name']) && strlen(trim($params[':name'])) > 64) {
            $this->error('菜单名称不能超过 64 个字符', 1, 400);
        }

        if (isset($params[':parent_id'])) {
            $newParentId = intval($params[':parent_id']);
            if ($newParentId > 0) {
                if ($newParentId === intval($id)) {
                    $this->error('不能将自己设为父菜单', 1, 400);
                }
                $stmt = $this->pdo->prepare('SELECT id, app_type FROM `menu` WHERE id = :id');
                $stmt->execute([':id' => $newParentId]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$parent) {
                    $this->error('父菜单不存在', 1, 400);
                }
                if ($parent['app_type'] !== $menu['app_type']) {
                    $this->error('父菜单与当前菜单不属于同一端', 1, 400);
                }
            }
        }

        if (empty($fields)) {
            $this->error('无更新数据', 1, 400);
        }

        $sql = 'UPDATE `menu` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->success(null, '更新成功');
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `menu` WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        if (!$stmt->fetch()) {
            $this->error('菜单不存在', 1, 404);
        }

        $childIds = $this->getDescendantIds($id);
        $allIds = array_merge([$id], $childIds);

        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($allIds), '?'));
            $this->pdo->prepare("DELETE FROM role_menu WHERE menu_id IN ($placeholders)")->execute($allIds);
            $this->pdo->prepare("DELETE FROM `menu` WHERE id IN ($placeholders)")->execute($allIds);

            $this->pdo->commit();
            $this->success(null, '删除成功');
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->error('删除失败: ' . $e->getMessage(), 1, 500);
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
}
