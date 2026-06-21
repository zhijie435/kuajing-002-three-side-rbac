<?php

class Controller
{
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
