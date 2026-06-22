<?php
class RoleController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $terminal = $_GET['terminal'] ?? '';
        $keyword = $_GET['keyword'] ?? '';
        
        $sql = "SELECT * FROM roles WHERE 1=1";
        $params = [];
        
        if ($terminal) {
            $sql .= " AND terminal = ?";
            $params[] = $terminal;
        }
        
        if ($keyword) {
            $sql .= " AND (name LIKE ? OR code LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        
        $sql .= " ORDER BY id ASC";
        $roles = $this->db->fetchAll($sql, $params);
        
        json_response([
            'list' => $roles,
            'total' => count($roles)
        ]);
    }

    public function show($id) {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            json_error('角色不存在', 404);
        }
        json_response($role);
    }

    public function store() {
        $input = get_input();
        
        if (empty($input['name']) || empty($input['code']) || empty($input['terminal'])) {
            json_error('角色名称、编码和终端类型不能为空');
        }
        
        $existing = $this->db->fetchOne("SELECT id FROM roles WHERE code = ?", [$input['code']]);
        if ($existing) {
            json_error('角色编码已存在');
        }
        
        $id = $this->db->insert('roles', [
            'name' => $input['name'],
            'code' => $input['code'],
            'terminal' => $input['terminal'],
            'description' => $input['description'] ?? '',
            'status' => $input['status'] ?? 1
        ]);
        
        json_response(['id' => $id], 201);
    }

    public function update($id) {
        $input = get_input();
        
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            json_error('角色不存在', 404);
        }
        
        if (!empty($input['code'])) {
            $existing = $this->db->fetchOne("SELECT id FROM roles WHERE code = ? AND id != ?", [$input['code'], $id]);
            if ($existing) {
                json_error('角色编码已存在');
            }
        }
        
        $updateData = [];
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['code'])) $updateData['code'] = $input['code'];
        if (isset($input['terminal'])) $updateData['terminal'] = $input['terminal'];
        if (isset($input['description'])) $updateData['description'] = $input['description'];
        if (isset($input['status'])) $updateData['status'] = $input['status'];
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->update('roles', $updateData, 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function destroy($id) {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            json_error('角色不存在', 404);
        }
        
        $this->db->delete('roles', 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function menus($roleId) {
        $menus = $this->db->fetchAll("
            SELECT m.* FROM menus m
            INNER JOIN role_menus rm ON m.id = rm.menu_id
            WHERE rm.role_id = ?
            ORDER BY m.sort ASC, m.id ASC
        ", [$roleId]);
        
        json_response($menus);
    }

    public function operations($roleId) {
        $operations = $this->db->fetchAll("
            SELECT o.* FROM operations o
            INNER JOIN role_operations ro ON o.id = ro.operation_id
            WHERE ro.role_id = ?
            ORDER BY o.id ASC
        ", [$roleId]);
        
        json_response($operations);
    }

    public function assignMenus($roleId) {
        $input = get_input();
        $menuIds = $input['menu_ids'] ?? [];
        
        if (!is_array($menuIds)) {
            json_error('menu_ids必须是数组');
        }
        
        $this->db->beginTransaction();
        
        try {
            $this->db->delete('role_menus', 'role_id = ?', [$roleId]);
            
            foreach ($menuIds as $menuId) {
                $this->db->insert('role_menus', [
                    'role_id' => $roleId,
                    'menu_id' => $menuId
                ]);
            }
            
            $this->db->commit();
            json_response(['success' => true]);
        } catch (Exception $e) {
            $this->db->rollback();
            json_error('分配失败: ' . $e->getMessage());
        }
    }

    public function assignOperations($roleId) {
        $input = get_input();
        $operationIds = $input['operation_ids'] ?? [];
        
        if (!is_array($operationIds)) {
            json_error('operation_ids必须是数组');
        }
        
        $this->db->beginTransaction();
        
        try {
            $this->db->delete('role_operations', 'role_id = ?', [$roleId]);
            
            foreach ($operationIds as $operationId) {
                $this->db->insert('role_operations', [
                    'role_id' => $roleId,
                    'operation_id' => $operationId
                ]);
            }
            
            $this->db->commit();
            json_response(['success' => true]);
        } catch (Exception $e) {
            $this->db->rollback();
            json_error('分配失败: ' . $e->getMessage());
        }
    }
}
