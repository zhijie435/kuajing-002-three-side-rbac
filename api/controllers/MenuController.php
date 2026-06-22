<?php
class MenuController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $terminal = $_GET['terminal'] ?? '';
        
        $sql = "SELECT * FROM menus";
        $params = [];
        
        if ($terminal) {
            $sql .= " WHERE terminal = ?";
            $params[] = $terminal;
        }
        
        $sql .= " ORDER BY sort ASC, id ASC";
        $menus = $this->db->fetchAll($sql, $params);
        
        $tree = $this->buildTree($menus);
        
        json_response([
            'list' => $tree,
            'flat' => $menus
        ]);
    }

    private function buildTree($menus, $parentId = 0) {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['children'] = $this->buildTree($menus, $menu['id']);
                $tree[] = $menu;
            }
        }
        return $tree;
    }

    public function show($id) {
        $menu = $this->db->fetchOne("SELECT * FROM menus WHERE id = ?", [$id]);
        if (!$menu) {
            json_error('菜单不存在', 404);
        }
        
        $operations = $this->db->fetchAll("SELECT * FROM operations WHERE menu_id = ? ORDER BY id ASC", [$id]);
        $menu['operations'] = $operations;
        
        json_response($menu);
    }

    public function store() {
        $input = get_input();
        
        if (empty($input['name']) || empty($input['terminal'])) {
            json_error('菜单名称和终端类型不能为空');
        }
        
        $id = $this->db->insert('menus', [
            'parent_id' => $input['parent_id'] ?? 0,
            'name' => $input['name'],
            'path' => $input['path'] ?? '',
            'component' => $input['component'] ?? '',
            'icon' => $input['icon'] ?? '',
            'sort' => $input['sort'] ?? 0,
            'terminal' => $input['terminal'],
            'type' => $input['type'] ?? 2,
            'status' => $input['status'] ?? 1
        ]);
        
        if (!empty($input['operations']) && is_array($input['operations'])) {
            foreach ($input['operations'] as $op) {
                $this->db->insert('operations', [
                    'menu_id' => $id,
                    'name' => $op['name'],
                    'code' => $op['code'],
                    'description' => $op['description'] ?? ''
                ]);
            }
        }
        
        json_response(['id' => $id], 201);
    }

    public function update($id) {
        $input = get_input();
        
        $menu = $this->db->fetchOne("SELECT * FROM menus WHERE id = ?", [$id]);
        if (!$menu) {
            json_error('菜单不存在', 404);
        }
        
        $updateData = [];
        if (isset($input['parent_id'])) $updateData['parent_id'] = $input['parent_id'];
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['path'])) $updateData['path'] = $input['path'];
        if (isset($input['component'])) $updateData['component'] = $input['component'];
        if (isset($input['icon'])) $updateData['icon'] = $input['icon'];
        if (isset($input['sort'])) $updateData['sort'] = $input['sort'];
        if (isset($input['terminal'])) $updateData['terminal'] = $input['terminal'];
        if (isset($input['type'])) $updateData['type'] = $input['type'];
        if (isset($input['status'])) $updateData['status'] = $input['status'];
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->update('menus', $updateData, 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function destroy($id) {
        $menu = $this->db->fetchOne("SELECT * FROM menus WHERE id = ?", [$id]);
        if (!$menu) {
            json_error('菜单不存在', 404);
        }
        
        $children = $this->db->fetchAll("SELECT id FROM menus WHERE parent_id = ?", [$id]);
        if (!empty($children)) {
            json_error('存在子菜单，无法删除');
        }
        
        $this->db->delete('menus', 'id = ?', [$id]);
        
        json_response(['id' => $id]);
    }

    public function tree($terminal = null) {
        if (!$terminal) {
            $terminal = $_GET['terminal'] ?? '';
        }
        
        $sql = "SELECT * FROM menus";
        $params = [];
        
        if ($terminal) {
            $sql .= " WHERE terminal = ?";
            $params[] = $terminal;
        }
        
        $sql .= " ORDER BY sort ASC, id ASC";
        $menus = $this->db->fetchAll($sql, $params);
        
        foreach ($menus as &$menu) {
            $operations = $this->db->fetchAll("SELECT * FROM operations WHERE menu_id = ? AND status = 1", [$menu['id']]);
            $menu['operations'] = $operations;
        }
        
        $tree = $this->buildTreeWithOperations($menus);
        
        json_response($tree);
    }

    private function buildTreeWithOperations($menus, $parentId = 0) {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['children'] = $this->buildTreeWithOperations($menus, $menu['id']);
                $tree[] = $menu;
            }
        }
        return $tree;
    }
}
