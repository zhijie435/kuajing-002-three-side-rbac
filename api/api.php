<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$path = str_replace('/api.php', '', $path);
$path = trim($path, '/');

$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'code' => $code,
        'message' => 'success',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

switch ($resource) {
    case 'roles':
        require_once __DIR__ . '/controllers/RoleController.php';
        $controller = new RoleController($db);
        break;
    case 'menus':
        require_once __DIR__ . '/controllers/MenuController.php';
        $controller = new MenuController($db);
        break;
    case 'operations':
        require_once __DIR__ . '/controllers/OperationController.php';
        $controller = new OperationController($db);
        break;
    case 'role-menus':
        require_once __DIR__ . '/controllers/RoleMenuController.php';
        $controller = new RoleMenuController($db);
        break;
    case 'role-operations':
        require_once __DIR__ . '/controllers/RoleOperationController.php';
        $controller = new RoleOperationController($db);
        break;
    default:
        json_error('接口不存在', 404);
}

if ($method === 'GET' && $id === null) {
    $controller->index();
} elseif ($method === 'GET' && $id !== null && $action === null) {
    $controller->show($id);
} elseif ($method === 'POST' && $id === null) {
    $controller->store();
} elseif ($method === 'PUT' && $id !== null) {
    $controller->update($id);
} elseif ($method === 'DELETE' && $id !== null) {
    $controller->destroy($id);
} elseif ($method === 'POST' && $id !== null && $action !== null) {
    if (method_exists($controller, $action)) {
        $controller->$action($id);
    } else {
        json_error('方法不存在', 404);
    }
} else {
    json_error('不支持的请求方法', 405);
}
