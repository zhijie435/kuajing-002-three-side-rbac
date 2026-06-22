<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/RoleController.php';
require_once __DIR__ . '/../controllers/MenuController.php';
require_once __DIR__ . '/../controllers/PermissionController.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$routes = require_once __DIR__ . '/../routes/api.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = rtrim($uri, '/') ?: '/';

$matched = false;
foreach ($routes as $route) {
    list($routeMethod, $routePattern, $handler) = $route;

    if ($routeMethod !== $method) {
        continue;
    }

    $routeRegex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePattern);
    $routeRegex = '#^' . $routeRegex . '$#';

    if (preg_match($routeRegex, $uri, $matches)) {
        $matched = true;

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        list($controllerName, $actionName) = explode('@', $handler);
        $controller = new $controllerName();

        call_user_func_array([$controller, $actionName], $params);
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['code' => 1, 'message' => '接口不存在'], JSON_UNESCAPED_UNICODE);
}
