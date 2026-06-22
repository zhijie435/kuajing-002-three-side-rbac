<?php
class RoleOperationController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $roleId = $_GET['role_id'] ?? '';
        
        if (!$roleId) {
            json_error('角色ID不能为空');
        }
        
        $operations = $this->db->fetchAll("
            SELECT o.* FROM operations o
            INNER JOIN role_operations ro ON o.id = ro.operation_id
            WHERE ro.role_id = ?
            ORDER BY o.id ASC
        ", [$roleId]);
        
        json_response($operations);
    }

    public function store() {
        $input = get_input();
        $roleId = $input['role_id'] ?? '';
        $operationIds = $input['operation_ids'] ?? [];
        
        if (!$roleId) {
            json_error('角色ID不能为空');
        }
        
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
