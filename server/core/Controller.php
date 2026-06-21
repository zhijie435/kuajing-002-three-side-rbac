<?php

class Controller
{
    protected const APP_TYPES = ['platform', 'merchant', 'warehouse'];

    protected const APP_TYPE_LABELS = [
        'platform'  => '平台端',
        'merchant' => '商家端',
        'warehouse' => '仓储端',
    ];

    protected function success($data = null, string $message = '操作成功', int $code = 0): void
    {
        $response = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->jsonResponse($response);
    }

    protected function error(string $message, int $code = 1, int $statusCode = 400, array $extra = []): void
    {
        $response = ['code' => $code, 'message' => $message];
        if (!empty($extra)) {
            $response['data'] = $extra;
        }
        $this->jsonResponse($response, $statusCode);
    }

    protected function validateAppType(string $appType): void
    {
        if (!in_array($appType, self::APP_TYPES, true)) {
            $this->error('无效的端类型', 1, 400);
        }
    }

    protected function getAppTypeLabel(string $appType): string
    {
        return self::APP_TYPE_LABELS[$appType] ?? $appType;
    }

    protected function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function jsonError(string $message, int $code = 400, int $statusCode = null): void
    {
        http_response_code($statusCode ?? $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code'    => $code,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function getQueryParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}
