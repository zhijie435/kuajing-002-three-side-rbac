<?php

class ControllerExitException extends Error
{
    public array $response;
    public int $statusCode;

    public function __construct(array $response, int $statusCode)
    {
        $this->response = $response;
        $this->statusCode = $statusCode;
        parent::__construct('Controller exited');
    }
}

class TestableRoleController extends RoleController
{
    public array $lastResponse = [];
    public int $lastStatusCode = 200;

    public function __construct(PDO $pdo)
    {
        $ref = new ReflectionProperty(RoleController::class, 'pdo');
        $ref->setAccessible(true);
        $ref->setValue($this, $pdo);
    }

    protected function jsonResponse(array $data, int $code = 200): void
    {
        $this->lastResponse = $data;
        $this->lastStatusCode = $code;
        throw new ControllerExitException($data, $code);
    }

    protected function getJsonBody(): array
    {
        return $this->testJsonBody ?? [];
    }

    private array $testJsonBody = [];

    public function setJsonBody(array $body): void
    {
        $this->testJsonBody = $body;
    }

    private function capture(callable $fn): array
    {
        try {
            $fn();
        } catch (ControllerExitException $e) {
            // already captured
        }
        return $this->lastResponse;
    }

    public function callIndex(string $appType): array
    {
        return $this->capture(fn() => $this->index($appType));
    }

    public function callStore(): array
    {
        return $this->capture(fn() => $this->store());
    }

    public function callUpdate(int $id): array
    {
        return $this->capture(fn() => $this->update($id));
    }

    public function callDelete(int $id): array
    {
        return $this->capture(fn() => $this->delete($id));
    }

    public function callGetMenus(int $id): array
    {
        return $this->capture(fn() => $this->getMenus($id));
    }

    public function callGetPermissions(int $id): array
    {
        return $this->capture(fn() => $this->getPermissions($id));
    }

    public function callGetMatrix(int $id): array
    {
        return $this->capture(fn() => $this->getMatrix($id));
    }

    public function callAssignMenus(int $id): array
    {
        return $this->capture(fn() => $this->assignMenus($id));
    }

    public function callAssignPermissions(int $id): array
    {
        return $this->capture(fn() => $this->assignPermissions($id));
    }

    public function callAssignMatrix(int $id): array
    {
        return $this->capture(fn() => $this->assignMatrix($id));
    }

    public function callBatchAssignMenus(): array
    {
        return $this->capture(fn() => $this->batchAssignMenus());
    }

    public function callBatchAssignPermissions(): array
    {
        return $this->capture(fn() => $this->batchAssignPermissions());
    }
}
