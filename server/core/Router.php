<?php

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath      = dirname($_SERVER['SCRIPT_NAME']);

        if ($basePath !== '/' && str_starts_with($requestUri, $basePath)) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        $requestUri = '/' . trim($requestUri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->buildPattern($route['path']);
            if (!preg_match($pattern, $requestUri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            [$controllerClass, $action] = $route['handler'];

            if (!class_exists($controllerClass)) {
                http_response_code(500);
                echo json_encode(['code' => 500, 'message' => "Controller {$controllerClass} not found"]);
                return;
            }

            $controller = new $controllerClass();
            if (!method_exists($controller, $action)) {
                http_response_code(500);
                echo json_encode(['code' => 500, 'message' => "Method {$action} not found in {$controllerClass}"]);
                return;
            }

            $controller->$action($params);
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 404, 'message' => 'Route not found']);
    }

    private function buildPattern(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $patternParts = [];

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $paramName = substr($segment, 1, -1);
                $patternParts[] = '(?P<' . $paramName . '>[^/]+)';
            } else {
                $patternParts[] = preg_quote($segment, '/');
            }
        }

        return '/^\/' . implode('\/', $patternParts) . '$/';
    }
}
