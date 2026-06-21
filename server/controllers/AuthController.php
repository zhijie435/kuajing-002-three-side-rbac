<?php

class AuthController extends Controller
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require_once __DIR__ . '/../config/database.php';
    }

    public function login()
    {
        $data = $this->getJsonBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->error('用户名和密码不能为空', 1, 400);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM `admin` WHERE username = :username AND status = 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->error('用户名或密码错误', 1, 401);
        }

        $roleId = intval($user['role_id']);
        $appType = $user['app_type'];

        $role = $this->getRoleById($roleId);
        if (!$role || intval($role['status']) !== 1) {
            $this->error('角色不存在或已禁用', 1, 401);
        }

        $menus = $this->getRoleMenus($roleId, $appType);
        $permissions = $this->getRolePermissions($roleId);

        $token = bin2hex(random_bytes(32));
        session_start();
        $_SESSION['token'] = $token;
        $_SESSION['admin_id'] = $user['id'];

        unset($user['password']);

        $this->success([
            'token' => $token,
            'user' => $user,
            'role' => $role,
            'menus' => $menus,
            'permissions' => $permissions,
        ]);
    }

    public function info()
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            $this->error('未提供认证令牌', 1, 401);
        }

        session_start();
        if (!isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
            $this->error('令牌无效或已过期', 1, 401);
        }

        $adminId = intval($_SESSION['admin_id']);
        $stmt = $this->pdo->prepare('SELECT * FROM `admin` WHERE id = :id AND status = 1');
        $stmt->execute([':id' => $adminId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->error('用户不存在或已禁用', 1, 401);
        }

        $roleId = intval($user['role_id']);
        $appType = $user['app_type'];

        $role = $this->getRoleById($roleId);
        if (!$role || intval($role['status']) !== 1) {
            $this->error('角色不存在或已禁用', 1, 401);
        }

        $menus = $this->getRoleMenus($roleId, $appType);
        $permissions = $this->getRolePermissions($roleId);

        unset($user['password']);

        $this->success([
            'user' => $user,
            'role' => $role,
            'menus' => $menus,
            'permissions' => $permissions,
        ]);
    }

    private function getRoleById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `role` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ?: null;
    }

    private function getRoleMenus(int $roleId, string $appType): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT m.* FROM `menu` m INNER JOIN role_menu rm ON m.id = rm.menu_id WHERE rm.role_id = :role_id AND m.app_type = :app_type AND m.status = 1 ORDER BY m.sort_order ASC, m.id ASC');
        $stmt->execute([':role_id' => $roleId, ':app_type' => $appType]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->buildMenuTree($menus);
    }

    private function getRolePermissions(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT p.* FROM `permission` p INNER JOIN role_permission rp ON p.id = rp.permission_id WHERE rp.role_id = :role_id AND p.status = 1 ORDER BY p.id ASC');
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['children'] = $this->buildMenuTree($menus, $menu['id']);
                $tree[] = $menu;
            }
        }
        return $tree;
    }
}
