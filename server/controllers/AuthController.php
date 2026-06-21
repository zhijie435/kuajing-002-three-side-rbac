<?php

class AuthController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function login()
    {
        $data = $this->getInput();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->json(['code' => 1, 'message' => '用户名和密码不能为空'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM `admin` WHERE username = :username AND status = 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->json(['code' => 1, 'message' => '用户名或密码错误'], 401);
        }

        $roleId = $user['role_id'];
        $appType = $user['app_type'];

        $roleStmt = $this->pdo->prepare('SELECT * FROM `role` WHERE id = :id');
        $roleStmt->execute([':id' => $roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        $menus = $this->getRoleMenus($roleId, $appType);
        $permissions = $this->getRolePermissions($roleId);

        $token = bin2hex(random_bytes(32));
        session_start();
        $_SESSION['token'] = $token;
        $_SESSION['admin_id'] = $user['id'];

        unset($user['password']);

        $this->json([
            'code' => 0,
            'data' => [
                'token' => $token,
                'user' => $user,
                'role' => $role,
                'menus' => $menus,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function info()
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            $this->json(['code' => 1, 'message' => '未提供认证令牌'], 401);
        }

        session_start();
        if (!isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
            $this->json(['code' => 1, 'message' => '令牌无效或已过期'], 401);
        }

        $adminId = $_SESSION['admin_id'];
        $stmt = $this->pdo->prepare('SELECT * FROM `admin` WHERE id = :id AND status = 1');
        $stmt->execute([':id' => $adminId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->json(['code' => 1, 'message' => '用户不存在或已禁用'], 401);
        }

        $roleId = $user['role_id'];
        $appType = $user['app_type'];

        $roleStmt = $this->pdo->prepare('SELECT * FROM `role` WHERE id = :id');
        $roleStmt->execute([':id' => $roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        $menus = $this->getRoleMenus($roleId, $appType);
        $permissions = $this->getRolePermissions($roleId);

        unset($user['password']);

        $this->json([
            'code' => 0,
            'data' => [
                'user' => $user,
                'role' => $role,
                'menus' => $menus,
                'permissions' => $permissions,
            ],
        ]);
    }

    private function getRoleMenus($roleId, $appType)
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT m.* FROM `menu` m INNER JOIN role_menu rm ON m.id = rm.menu_id WHERE rm.role_id = :role_id AND m.app_type = :app_type AND m.status = 1 ORDER BY m.sort_order ASC, m.id ASC');
        $stmt->execute([':role_id' => $roleId, ':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->buildTree($menus);
    }

    private function getRolePermissions($roleId)
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT p.* FROM `permission` p INNER JOIN role_permission rp ON p.id = rp.permission_id WHERE rp.role_id = :role_id AND p.status = 1 ORDER BY p.id ASC');
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
