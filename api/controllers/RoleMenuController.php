<?php
class RoleMenuController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $roleId = $_GET['role_id'] ?? '';
        
        if (!$roleId) {
            json_error('角色ID不能为空');
        }
        
        $menus = $this->db->fetchAll("
            SELECT m.* FROM menus m
            INNER JOIN role_menus rm ON m.id = rm.menu_id
            WHERE rm.role_id = ?
            ORDER BY m.sort ASC, m.id ASC
        ", [$roleId]);
        
        json_response($menus);
    }

    public function store() {
        $input = get_input();
        $roleId = $input['role_id'] ?? '';
        $menuIds = $input['menu_ids'] ?? [];
        
        if (!$roleId) {
            json_error('角色ID不能为空');
        }
        
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
}
