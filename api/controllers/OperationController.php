<?php
class OperationController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $menuId = $_GET['menu_id'] ?? '';
        
        $sql = "SELECT o.*, m.name as menu_name FROM operations o
                LEFT JOIN menus m ON o.menu_id = m.id
                WHERE 1=1";
        $params = [];
        
        if ($menuId) {
            $sql .= " AND o.menu_id = ?";
            $params[] = $menuId;
        }
        
        $sql .= " ORDER BY o.id ASC";
        $operations = $this->db->fetchAll($sql, $params);
        
        json_response([
            'list' => $operations,
            'total' => count($operations)
        ]);
    }

    public function show($id) {
        $operation = $this->db->fetchOne("SELECT * FROM operations WHERE id = ?", [$id]);
        if (!$operation) {
            json_error('操作权限不存在', 404);
        }
        json_response($operation);
    }

    public function store() {
        $input = get_input();
        
        if (empty($input['name']) || empty($input['code']) || empty($input['menu_id'])) {
            json_error('操作名称、编码和所属菜单不能为空');
        }
        
        $existing = $this->db->fetchOne("SELECT id FROM operations WHERE code = ?", [$input['code']]);
        if ($existing) {
            json_error('操作编码已存在');
        }
        
        $id = $this->db->insert('operations', [
            'menu_id' => $input['menu_id'],
            'name' => $input['name'],
            'code' => $input['code'],
            'description' => $input['description'] ?? '',
            'status' => $input['status'] ?? 1
        ]);
        
        json_response(['id' => $id], 201);
    }

    public function update($id) {
        $input = get_input();
        
        $operation = $this->db->fetchOne("SELECT * FROM operations WHERE id = ?", [$id]);
        if (!$operation) {
            json_error('操作权限不存在', 404);
        }
        
        if (!empty($input['code'])) {
            $existing = $this->db->fetchOne("SELECT id FROM operations WHERE code = ? AND id != ?", [$input['code'], $id]);
            if ($existing) {
                json_error('操作编码已存在');
            }
        }
        
        $updateData = [];
        if (isset($input['menu_id'])) $updateData['menu_id'] = $input['menu_id'];
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['code'])) $updateData['code'] = $input['code'];
        if (isset($input['description'])) $updateData['description'] = $input['description'];
        if (isset($input['status'])) $updateData['status'] = $input['status'];
        
        $this->db->update('operations', $updateData, 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function destroy($id) {
        $operation = $this->db->fetchOne("SELECT * FROM operations WHERE id = ?", [$id]);
        if (!$operation) {
            json_error('操作权限不存在', 404);
        }
        
        $this->db->delete('operations', 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function byMenu($menuId) {
        $operations = $this->db->fetchAll("SELECT * FROM operations WHERE menu_id = ? AND status = 1 ORDER BY id ASC", [$menuId]);
        json_response($operations);
    }
}
