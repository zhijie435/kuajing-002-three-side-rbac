<?php

class RolePermissionMatrixTest extends TestCase
{
    private TestableRoleController $ctrl;

    protected function setUp(): void
    {
        $this->resetData();
        $this->ctrl = $this->createRoleController();
    }

    private function assertSuccess(array $resp, string $msg = ''): void
    {
        $this->assertEquals(0, $resp['code'], $msg . ' | response: ' . json_encode($resp, JSON_UNESCAPED_UNICODE));
    }

    private function assertError(array $resp, int $expectedCode, string $msg = ''): void
    {
        $this->assertEquals($expectedCode, $resp['code'], $msg . ' | response: ' . json_encode($resp, JSON_UNESCAPED_UNICODE));
    }

    private function assertHttpStatus(int $expected): void
    {
        $this->assertEquals($expected, $this->ctrl->lastStatusCode);
    }

    private function getRoleMenuIds(int $roleId): array
    {
        $stmt = static::$pdo->prepare('SELECT menu_id FROM role_menu WHERE role_id = :rid ORDER BY menu_id');
        $stmt->execute([':rid' => $roleId]);
        return array_column($stmt->fetchAll(), 'menu_id');
    }

    private function getRolePermIds(int $roleId): array
    {
        $stmt = static::$pdo->prepare('SELECT permission_id FROM role_permission WHERE role_id = :rid ORDER BY permission_id');
        $stmt->execute([':rid' => $roleId]);
        return array_column($stmt->fetchAll(), 'permission_id');
    }

    private function assignMenusViaController(int $roleId, array $menuIds): array
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody(['menu_ids' => $menuIds]);
        return $this->ctrl->callAssignMenus($roleId);
    }

    private function assignPermsViaController(int $roleId, array $permIds): array
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody(['permission_ids' => $permIds]);
        return $this->ctrl->callAssignPermissions($roleId);
    }

    private function assignMatrixViaController(int $roleId, array $menuIds, array $permIds): array
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody(['menu_ids' => $menuIds, 'permission_ids' => $permIds]);
        return $this->ctrl->callAssignMatrix($roleId);
    }

    private function getMenusViaController(int $roleId): array
    {
        $this->ctrl = $this->createRoleController();
        return $this->ctrl->callGetMenus($roleId);
    }

    private function getPermsViaController(int $roleId): array
    {
        $this->ctrl = $this->createRoleController();
        return $this->ctrl->callGetPermissions($roleId);
    }

    private function getMatrixViaController(int $roleId): array
    {
        $this->ctrl = $this->createRoleController();
        return $this->ctrl->callGetMatrix($roleId);
    }

    private function createRoleViaDb(string $name, string $code, string $appType): int
    {
        $stmt = static::$pdo->prepare('INSERT INTO `role` (name, code, app_type, description, status) VALUES (:name, :code, :app_type, :desc, 1)');
        $stmt->execute([':name' => $name, ':code' => $code, ':app_type' => $appType, ':desc' => '测试角色']);
        return (int) static::$pdo->lastInsertId();
    }

    private function disableMenuInDb(int $menuId): void
    {
        static::$pdo->prepare('UPDATE `menu` SET status = 0 WHERE id = :id')->execute([':id' => $menuId]);
    }

    private function enableMenuInDb(int $menuId): void
    {
        static::$pdo->prepare('UPDATE `menu` SET status = 1 WHERE id = :id')->execute([':id' => $menuId]);
    }

    private function disablePermInDb(int $permId): void
    {
        static::$pdo->prepare('UPDATE `permission` SET status = 0 WHERE id = :id')->execute([':id' => $permId]);
    }

    private function enablePermInDb(int $permId): void
    {
        static::$pdo->prepare('UPDATE `permission` SET status = 1 WHERE id = :id')->execute([':id' => $permId]);
    }

    // ================================================================
    // 1. 三端角色权限矩阵核心逻辑
    // ================================================================

    public function testPlatformRoleMatrixContainsOnlyPlatformMenusAndPerms(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $data = $resp['data'];
        $this->assertEquals('platform', $data['role']['app_type']);

        foreach ($data['matrix_rows'] as $row) {
            $this->assertContains($row['menu_id'], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
                '平台端矩阵不应包含其他端菜单');
            if ($row['permission_id'] !== null) {
                $this->assertContains($row['permission_id'], range(1, 20),
                    '平台端矩阵不应包含其他端权限');
            }
        }
    }

    public function testMerchantRoleMatrixContainsOnlyMerchantMenusAndPerms(): void
    {
        $resp = $this->getMatrixViaController(2);
        $this->assertSuccess($resp);

        $data = $resp['data'];
        $this->assertEquals('merchant', $data['role']['app_type']);

        foreach ($data['matrix_rows'] as $row) {
            $this->assertContains($row['menu_id'], [12, 13, 14, 15, 16, 17],
                '商家端矩阵不应包含其他端菜单');
            if ($row['permission_id'] !== null) {
                $this->assertContains($row['permission_id'], range(21, 24),
                    '商家端矩阵不应包含其他端权限');
            }
        }
    }

    public function testWarehouseRoleMatrixContainsOnlyWarehouseMenusAndPerms(): void
    {
        $resp = $this->getMatrixViaController(3);
        $this->assertSuccess($resp);

        $data = $resp['data'];
        $this->assertEquals('warehouse', $data['role']['app_type']);

        foreach ($data['matrix_rows'] as $row) {
            $this->assertContains($row['menu_id'], [18, 19, 20, 21],
                '仓储端矩阵不应包含其他端菜单');
            if ($row['permission_id'] !== null) {
                $this->assertContains($row['permission_id'], range(25, 27),
                    '仓储端矩阵不应包含其他端权限');
            }
        }
    }

    public function testMatrixStatsAreCorrectForPlatformAdmin(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $stats = $resp['data']['stats'];
        $this->assertEquals(11, $stats['total_menus']);
        $this->assertEquals(20, $stats['total_permissions']);
        $this->assertEquals(11, $stats['granted_menu_count']);
        $this->assertEquals(20, $stats['granted_permission_count']);
    }

    public function testMatrixStatsAreCorrectForMerchantAdmin(): void
    {
        $resp = $this->getMatrixViaController(2);
        $this->assertSuccess($resp);

        $stats = $resp['data']['stats'];
        $this->assertEquals(6, $stats['total_menus']);
        $this->assertEquals(4, $stats['total_permissions']);
        $this->assertEquals(6, $stats['granted_menu_count']);
        $this->assertEquals(4, $stats['granted_permission_count']);
    }

    public function testMatrixStatsAreCorrectForWarehouseAdmin(): void
    {
        $resp = $this->getMatrixViaController(3);
        $this->assertSuccess($resp);

        $stats = $resp['data']['stats'];
        $this->assertEquals(4, $stats['total_menus']);
        $this->assertEquals(3, $stats['total_permissions']);
        $this->assertEquals(4, $stats['granted_menu_count']);
        $this->assertEquals(3, $stats['granted_permission_count']);
    }

    public function testPlatformAdminAllMenusChecked(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        foreach ($resp['data']['matrix_rows'] as $row) {
            $this->assertTrue($row['menu_checked'], "菜单 {$row['menu_name']} 应为已授权");
        }
    }

    public function testPlatformAdminAllPermsChecked(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        foreach ($resp['data']['matrix_rows'] as $row) {
            if ($row['type'] === 'permission') {
                $this->assertTrue($row['permission_checked'], "权限 {$row['permission_name']} 应为已授权");
            }
        }
    }

    public function testMatrixRowsWithPermsHaveTypePermission(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $permRows = array_filter($resp['data']['matrix_rows'], fn($r) => $r['permission_id'] !== null);
        $menuOnlyRows = array_filter($resp['data']['matrix_rows'], fn($r) => $r['permission_id'] === null);

        foreach ($permRows as $row) {
            $this->assertEquals('permission', $row['type']);
        }
        foreach ($menuOnlyRows as $row) {
            $this->assertEquals('menu', $row['type']);
        }
    }

    public function testDirectoryMenusHaveNoPermissionsInMatrix(): void
    {
        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $dirRows = array_filter($resp['data']['matrix_rows'], fn($r) => in_array($r['menu_id'], [1, 6, 9]));
        foreach ($dirRows as $row) {
            $this->assertNull($row['permission_id'], "目录菜单 {$row['menu_name']} 不应有权限行");
            $this->assertEquals('menu', $row['type']);
        }
    }

    // ================================================================
    // 2. 跨端隔离 — 平台端角色不能授权商家端/仓储端资源
    // ================================================================

    public function testPlatformRoleCannotAssignMerchantMenus(): void
    {
        $resp = $this->assignMenusViaController(1, [12, 13]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testPlatformRoleCannotAssignMerchantPermissions(): void
    {
        $resp = $this->assignPermsViaController(1, [21, 22]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testMerchantRoleCannotAssignPlatformMenus(): void
    {
        $resp = $this->assignMenusViaController(2, [1, 2]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testMerchantRoleCannotAssignPlatformPermissions(): void
    {
        $this->assignMenusViaController(2, [12, 13]);

        $resp = $this->assignPermsViaController(2, [1, 2]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testWarehouseRoleCannotAssignPlatformMenus(): void
    {
        $resp = $this->assignMenusViaController(3, [1, 2]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testWarehouseRoleCannotAssignMerchantPermissions(): void
    {
        $this->assignMenusViaController(3, [18, 19]);

        $resp = $this->assignPermsViaController(3, [21, 22]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('其他端', $resp['message']);
    }

    public function testMatrixRejectsCrossAppTypeResources(): void
    {
        $resp = $this->assignMatrixViaController(1, [1, 2, 12], [1, 21]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('校验失败', $resp['message']);
    }

    public function testMatrixRejectsNonexistentMenu(): void
    {
        $resp = $this->assignMatrixViaController(1, [9999], []);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('不存在', $resp['message']);
    }

    public function testMatrixRejectsNonexistentPermission(): void
    {
        $resp = $this->assignMatrixViaController(1, [2], [9999]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('不存在', $resp['message']);
    }

    // ================================================================
    // 3. 菜单授权状态闭环
    // ================================================================

    public function testMenuAuthFullLifecycle(): void
    {
        $roleId = $this->createRoleViaDb('测试角色', 'test_lifecycle', 'platform');

        $resp = $this->getMenusViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEmpty($resp['data'], '新建角色应无菜单授权');

        $resp = $this->assignMenusViaController($roleId, [1, 2, 3]);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['menu_count']);

        $resp = $this->getMenusViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals([1, 2, 3], $resp['data']);

        $dbIds = $this->getRoleMenuIds($roleId);
        $this->assertEquals(['1', '2', '3'], $dbIds);

        $resp = $this->assignMenusViaController($roleId, [2, 3, 4, 5]);
        $this->assertSuccess($resp);
        $this->assertEquals(4, $resp['data']['menu_count']);

        $resp = $this->getMenusViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals([2, 3, 4, 5], $resp['data']);

        $dbIds = $this->getRoleMenuIds($roleId);
        $this->assertEquals(['2', '3', '4', '5'], $dbIds);

        $resp = $this->assignMenusViaController($roleId, []);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['menu_count']);

        $resp = $this->getMenusViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEmpty($resp['data']);

        $dbIds = $this->getRoleMenuIds($roleId);
        $this->assertEmpty($dbIds);
    }

    public function testMenuAuthReassignRemovesOldOnes(): void
    {
        $roleId = $this->createRoleViaDb('菜单覆盖测试', 'test_menu_replace', 'platform');

        $this->assignMenusViaController($roleId, [1, 2, 6, 7]);
        $dbIds = $this->getRoleMenuIds($roleId);
        $this->assertCount(4, $dbIds);

        $this->assignMenusViaController($roleId, [2, 3]);
        $dbIds = $this->getRoleMenuIds($roleId);
        $this->assertEquals(['2', '3'], $dbIds);
    }

    public function testMenuAuthRejectsDisabledMenu(): void
    {
        $this->disableMenuInDb(2);

        $resp = $this->assignMenusViaController(1, [2]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('禁用', $resp['message']);

        $this->enableMenuInDb(2);
    }

    public function testMenuAuthWithDuplicateIdsDeduplicates(): void
    {
        $roleId = $this->createRoleViaDb('去重测试', 'test_dedup', 'platform');

        $resp = $this->assignMenusViaController($roleId, [2, 2, 2, 3, 3]);
        $this->assertSuccess($resp);
        $this->assertEquals(2, $resp['data']['menu_count']);

        $resp = $this->getMenusViaController($roleId);
        $this->assertEquals([2, 3], $resp['data']);
    }

    public function testMenuAuthNonexistentRoleReturns404(): void
    {
        $resp = $this->assignMenusViaController(99999, [1, 2]);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    public function testGetMenusNonexistentRoleReturns404(): void
    {
        $resp = $this->getMenusViaController(99999);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    // ================================================================
    // 4. 操作权限授权状态闭环
    // ================================================================

    public function testPermissionAuthFullLifecycle(): void
    {
        $roleId = $this->createRoleViaDb('权限闭环测试', 'test_perm_lifecycle', 'platform');

        $this->assignMenusViaController($roleId, [2, 3]);

        $resp = $this->getPermsViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEmpty($resp['data'], '新建角色应无权限授权');

        $resp = $this->assignPermsViaController($roleId, [1, 2, 5]);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['permission_count']);

        $resp = $this->getPermsViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals([1, 2, 5], $resp['data']);

        $dbIds = $this->getRolePermIds($roleId);
        $this->assertEquals(['1', '2', '5'], $dbIds);

        $resp = $this->assignPermsViaController($roleId, [3, 4]);
        $this->assertSuccess($resp);
        $this->assertEquals(2, $resp['data']['permission_count']);

        $resp = $this->getPermsViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals([3, 4], $resp['data']);

        $dbIds = $this->getRolePermIds($roleId);
        $this->assertEquals(['3', '4'], $dbIds);

        $resp = $this->assignPermsViaController($roleId, []);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['permission_count']);

        $resp = $this->getPermsViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEmpty($resp['data']);

        $dbIds = $this->getRolePermIds($roleId);
        $this->assertEmpty($dbIds);
    }

    public function testPermissionAuthReassignRemovesOldOnes(): void
    {
        $roleId = $this->createRoleViaDb('权限覆盖测试', 'test_perm_replace', 'platform');

        $this->assignMenusViaController($roleId, [2, 3, 5]);
        $this->assignPermsViaController($roleId, [1, 2, 5, 13]);

        $dbIds = $this->getRolePermIds($roleId);
        $this->assertCount(4, $dbIds);

        $this->assignPermsViaController($roleId, [3, 4]);
        $dbIds = $this->getRolePermIds($roleId);
        $this->assertEquals(['3', '4'], $dbIds);
    }

    public function testPermissionAuthRejectsDisabledPermission(): void
    {
        $this->disablePermInDb(2);

        $resp = $this->assignPermsViaController(1, [2]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('禁用', $resp['message']);

        $this->enablePermInDb(2);
    }

    public function testPermissionAuthRejectsOrphanPermissionWhenMenuNotGranted(): void
    {
        $roleId = $this->createRoleViaDb('孤儿权限测试', 'test_orphan_perm', 'platform');

        $this->assignMenusViaController($roleId, [2]);

        $resp = $this->assignPermsViaController($roleId, [1, 5]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('所属菜单未授权', $resp['message']);
    }

    public function testPermissionAuthAllowsPermWhenMenuGranted(): void
    {
        $roleId = $this->createRoleViaDb('菜单已授权权限测试', 'test_menu_perm_ok', 'platform');

        $this->assignMenusViaController($roleId, [2, 3]);

        $resp = $this->assignPermsViaController($roleId, [1, 2, 5, 6]);
        $this->assertSuccess($resp);
    }

    public function testPermissionAuthNonexistentRoleReturns404(): void
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody(['permission_ids' => [1]]);
        $resp = $this->ctrl->callAssignPermissions(99999);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    public function testGetPermissionsNonexistentRoleReturns404(): void
    {
        $resp = $this->getPermsViaController(99999);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    // ================================================================
    // 5. 权限矩阵整体状态闭环
    // ================================================================

    public function testMatrixAuthFullLifecycle(): void
    {
        $roleId = $this->createRoleViaDb('矩阵闭环测试', 'test_matrix_lifecycle', 'platform');

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(0, $resp['data']['stats']['granted_permission_count']);

        $resp = $this->assignMatrixViaController($roleId, [1, 2, 3], [1, 2, 5]);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['menu_count']);
        $this->assertEquals(3, $resp['data']['permission_count']);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(3, $resp['data']['stats']['granted_permission_count']);

        $grantedMenuIds = $resp['data']['granted_menu_ids'];
        $grantedPermIds = $resp['data']['granted_permission_ids'];
        $this->assertContains(1, $grantedMenuIds);
        $this->assertContains(2, $grantedMenuIds);
        $this->assertContains(3, $grantedMenuIds);
        $this->assertContains(1, $grantedPermIds);
        $this->assertContains(2, $grantedPermIds);
        $this->assertContains(5, $grantedPermIds);

        $this->assertEquals(['1', '2', '3'], $this->getRoleMenuIds($roleId));
        $this->assertEquals(['1', '2', '5'], $this->getRolePermIds($roleId));

        $resp = $this->assignMatrixViaController($roleId, [2, 3, 4, 5], [3, 4, 6, 7, 8]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(4, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(5, $resp['data']['stats']['granted_permission_count']);

        $this->assertEquals(['2', '3', '4', '5'], $this->getRoleMenuIds($roleId));
        $this->assertEquals(['3', '4', '6', '7', '8'], $this->getRolePermIds($roleId));

        $resp = $this->assignMatrixViaController($roleId, [], []);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['menu_count']);
        $this->assertEquals(0, $resp['data']['permission_count']);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(0, $resp['data']['stats']['granted_permission_count']);

        $this->assertEmpty($this->getRoleMenuIds($roleId));
        $this->assertEmpty($this->getRolePermIds($roleId));
    }

    public function testMatrixAuthOverridesSeparateMenuAndPermAuth(): void
    {
        $roleId = $this->createRoleViaDb('矩阵覆盖测试', 'test_matrix_override', 'platform');

        $this->assignMenusViaController($roleId, [1, 2, 3, 4, 5]);
        $this->assignPermsViaController($roleId, [1, 2, 3, 4, 5, 6, 7, 8]);

        $this->assertEquals(5, count($this->getRoleMenuIds($roleId)));
        $this->assertEquals(8, count($this->getRolePermIds($roleId)));

        $resp = $this->assignMatrixViaController($roleId, [2, 3], [1, 5]);
        $this->assertSuccess($resp);

        $this->assertEquals(['2', '3'], $this->getRoleMenuIds($roleId));
        $this->assertEquals(['1', '5'], $this->getRolePermIds($roleId));
    }

    public function testMatrixRejectsPermWithoutMenuInSameRequest(): void
    {
        $roleId = $this->createRoleViaDb('矩阵菜单约束测试', 'test_matrix_perm_menu', 'platform');

        $resp = $this->assignMatrixViaController($roleId, [2], [1, 5]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('所属菜单未授权', $resp['message']);
    }

    public function testMatrixAcceptsPermWhenMenuIncludedInSameRequest(): void
    {
        $roleId = $this->createRoleViaDb('矩阵菜单包含测试', 'test_matrix_menu_incl', 'platform');

        $resp = $this->assignMatrixViaController($roleId, [2, 3], [1, 5]);
        $this->assertSuccess($resp);
    }

    public function testMatrixNonexistentRoleReturns404(): void
    {
        $resp = $this->assignMatrixViaController(99999, [1], [1]);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    public function testGetMatrixNonexistentRoleReturns404(): void
    {
        $resp = $this->getMatrixViaController(99999);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    public function testMatrixDisabledMenuRejected(): void
    {
        $this->disableMenuInDb(2);

        $resp = $this->assignMatrixViaController(1, [2], []);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('禁用', $resp['message']);

        $this->enableMenuInDb(2);
    }

    public function testMatrixDisabledPermRejected(): void
    {
        $this->disablePermInDb(1);

        $resp = $this->assignMatrixViaController(1, [2], [1]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('禁用', $resp['message']);

        $this->enablePermInDb(1);
    }

    // ================================================================
    // 6. 角色增删对授权状态的影响（删除闭环）
    // ================================================================

    public function testDeleteRoleCleansUpMenuAndPermAssociations(): void
    {
        $roleId = $this->createRoleViaDb('待删除角色', 'test_delete_role', 'platform');

        $this->assignMenusViaController($roleId, [1, 2, 3]);
        $this->assignPermsViaController($roleId, [1, 2, 5]);

        $this->assertNotEmpty($this->getRoleMenuIds($roleId));
        $this->assertNotEmpty($this->getRolePermIds($roleId));

        $this->ctrl = $this->createRoleController();
        $resp = $this->ctrl->callDelete($roleId);
        $this->assertSuccess($resp);

        $stmt = static::$pdo->prepare('SELECT COUNT(*) FROM role_menu WHERE role_id = :rid');
        $stmt->execute([':rid' => $roleId]);
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $stmt = static::$pdo->prepare('SELECT COUNT(*) FROM role_permission WHERE role_id = :rid');
        $stmt->execute([':rid' => $roleId]);
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $stmt = static::$pdo->prepare('SELECT COUNT(*) FROM `role` WHERE id = :id');
        $stmt->execute([':id' => $roleId]);
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    public function testDeleteNonexistentRoleReturns404(): void
    {
        $this->ctrl = $this->createRoleController();
        $resp = $this->ctrl->callDelete(99999);
        $this->assertError($resp, 1);
        $this->assertEquals(404, $this->ctrl->lastStatusCode);
    }

    // ================================================================
    // 7. 角色状态（启用/禁用）对矩阵的影响
    // ================================================================

    public function testDisabledRoleStillReturnsMatrix(): void
    {
        static::$pdo->prepare('UPDATE `role` SET status = 0 WHERE id = 1')->execute();

        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['role']['status']);

        static::$pdo->prepare('UPDATE `role` SET status = 1 WHERE id = 1')->execute();
    }

    // ================================================================
    // 8. 批量授权状态闭环
    // ================================================================

    public function testBatchMenuAuthSuccessForSameAppType(): void
    {
        $role1Id = $this->createRoleViaDb('批量菜单1', 'batch_menu_1', 'platform');
        $role2Id = $this->createRoleViaDb('批量菜单2', 'batch_menu_2', 'platform');

        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [$role1Id, $role2Id],
            'menu_ids' => [1, 2, 3],
        ]);
        $resp = $this->ctrl->callBatchAssignMenus();
        $this->assertSuccess($resp);

        $this->assertEquals(2, $resp['data']['total']);
        $this->assertEquals(2, $resp['data']['success_count']);
        $this->assertEquals(0, $resp['data']['fail_count']);

        $this->assertEquals(['1', '2', '3'], $this->getRoleMenuIds($role1Id));
        $this->assertEquals(['1', '2', '3'], $this->getRoleMenuIds($role2Id));
    }

    public function testBatchMenuAuthFailsForCrossAppType(): void
    {
        $platRoleId = $this->createRoleViaDb('批量平台', 'batch_plat', 'platform');
        $merchRoleId = $this->createRoleViaDb('批量商家', 'batch_merch', 'merchant');

        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [$platRoleId, $merchRoleId],
            'menu_ids' => [1, 2, 12],
        ]);
        $resp = $this->ctrl->callBatchAssignMenus();
        $this->assertSuccess($resp);

        $this->assertEquals(2, $resp['data']['total']);
        $this->assertTrue($resp['data']['fail_count'] > 0, '跨端角色批量菜单授权应有失败');
        $this->assertNotEmpty($resp['data']['fail_details']);
    }

    public function testBatchPermAuthSuccessForSameAppType(): void
    {
        $role1Id = $this->createRoleViaDb('批量权限1', 'batch_perm_1', 'platform');
        $role2Id = $this->createRoleViaDb('批量权限2', 'batch_perm_2', 'platform');

        $this->assignMenusViaController($role1Id, [2, 3]);
        $this->assignMenusViaController($role2Id, [2, 3]);

        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [$role1Id, $role2Id],
            'permission_ids' => [1, 2, 5],
        ]);
        $resp = $this->ctrl->callBatchAssignPermissions();
        $this->assertSuccess($resp);

        $this->assertEquals(2, $resp['data']['success_count']);
        $this->assertEquals(['1', '2', '5'], $this->getRolePermIds($role1Id));
        $this->assertEquals(['1', '2', '5'], $this->getRolePermIds($role2Id));
    }

    public function testBatchPermAuthFailsWhenMenuNotGranted(): void
    {
        $role1Id = $this->createRoleViaDb('批量无菜单', 'batch_no_menu', 'platform');
        $role2Id = $this->createRoleViaDb('批量有菜单', 'batch_has_menu', 'platform');

        $this->assignMenusViaController($role2Id, [2, 3]);

        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [$role1Id, $role2Id],
            'permission_ids' => [1, 2],
        ]);
        $resp = $this->ctrl->callBatchAssignPermissions();
        $this->assertSuccess($resp);

        $this->assertTrue($resp['data']['fail_count'] > 0, '无菜单的角色授权权限应失败');

        $failDetails = $resp['data']['fail_details'];
        $failedRoleIds = array_column($failDetails, 'role_id');
        $this->assertContains($role1Id, $failedRoleIds);

        $successRoleIds = [];
        foreach ([$role2Id] as $rid) {
            $permIds = $this->getRolePermIds($rid);
            if (!empty($permIds)) {
                $successRoleIds[] = $rid;
            }
        }
        $this->assertContains($role2Id, $successRoleIds);
    }

    public function testBatchAuthEmptyRoleIdsReturnsError(): void
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [],
            'menu_ids' => [1, 2],
        ]);
        $resp = $this->ctrl->callBatchAssignMenus();
        $this->assertError($resp, 1);
    }

    public function testBatchAuthNonexistentRoleShowsInFailDetails(): void
    {
        $realRoleId = $this->createRoleViaDb('批量真实角色', 'batch_real', 'platform');

        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'role_ids' => [$realRoleId, 99999],
            'menu_ids' => [1, 2],
        ]);
        $resp = $this->ctrl->callBatchAssignMenus();
        $this->assertSuccess($resp);

        $this->assertEquals(1, $resp['data']['success_count']);
        $this->assertEquals(1, $resp['data']['fail_count']);

        $failDetail = $resp['data']['fail_details'][0];
        $this->assertEquals(99999, $failDetail['role_id']);
        $this->assertStringContainsString('不存在', $failDetail['reason']);
    }

    // ================================================================
    // 9. 角色列表按端隔离
    // ================================================================

    public function testRoleIndexPlatformOnly(): void
    {
        $this->ctrl = $this->createRoleController();
        $resp = $this->ctrl->callIndex('platform');
        $this->assertSuccess($resp);

        foreach ($resp['data'] as $role) {
            $this->assertEquals('platform', $role['app_type']);
        }
    }

    public function testRoleIndexMerchantOnly(): void
    {
        $this->ctrl = $this->createRoleController();
        $resp = $this->ctrl->callIndex('merchant');
        $this->assertSuccess($resp);

        foreach ($resp['data'] as $role) {
            $this->assertEquals('merchant', $role['app_type']);
        }
    }

    public function testRoleIndexWarehouseOnly(): void
    {
        $this->ctrl = $this->createRoleController();
        $resp = $this->ctrl->callIndex('warehouse');
        $this->assertSuccess($resp);

        foreach ($resp['data'] as $role) {
            $this->assertEquals('warehouse', $role['app_type']);
        }
    }

    // ================================================================
    // 10. 角色创建唯一性校验（同端 code 唯一）
    // ================================================================

    public function testCreateRoleWithDuplicateCodeInSameAppTypeFails(): void
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'name' => '重复编码',
            'code' => 'platform_admin',
            'app_type' => 'platform',
        ]);
        $resp = $this->ctrl->callStore();
        $this->assertError($resp, 1);
        $this->assertStringContainsString('已存在', $resp['message']);
    }

    public function testCreateRoleWithSameCodeInDifferentAppTypeSucceeds(): void
    {
        $this->ctrl = $this->createRoleController();
        $this->ctrl->setJsonBody([
            'name' => '商家端管理员',
            'code' => 'platform_admin',
            'app_type' => 'merchant',
        ]);
        $resp = $this->ctrl->callStore();
        $this->assertSuccess($resp);
    }

    // ================================================================
    // 11. 端到端状态闭环：完整授权→部分撤销→再授权→完全清空
    // ================================================================

    public function testEndToEndStateClosedLoop(): void
    {
        $roleId = $this->createRoleViaDb('端到端闭环', 'e2e_closed_loop', 'platform');

        $resp = $this->assignMatrixViaController($roleId, [1, 2, 3, 6, 7], [1, 2, 3, 4, 5, 6, 7, 8, 17]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(5, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(9, $resp['data']['stats']['granted_permission_count']);

        $checkedMenuIds = array_unique(array_column(array_filter($resp['data']['matrix_rows'], fn($r) => $r['menu_checked']), 'menu_id'));
        $this->assertCount(5, $checkedMenuIds);

        $checkedPermRows = array_filter($resp['data']['matrix_rows'], fn($r) => $r['type'] === 'permission' && $r['permission_checked']);
        $this->assertCount(9, $checkedPermRows);

        $resp = $this->assignMatrixViaController($roleId, [2, 3], [1, 5]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(2, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(2, $resp['data']['stats']['granted_permission_count']);

        $grantedMenuIds = $resp['data']['granted_menu_ids'];
        $grantedPermIds = $resp['data']['granted_permission_ids'];
        $this->assertContains(2, $grantedMenuIds);
        $this->assertContains(3, $grantedMenuIds);
        $this->assertContains(1, $grantedPermIds);
        $this->assertContains(5, $grantedPermIds);

        $uncheckedPermRows = array_filter($resp['data']['matrix_rows'], fn($r) => $r['type'] === 'permission' && !$r['permission_checked']);
        $this->assertNotEmpty($uncheckedPermRows, '部分撤销后应存在未勾选的权限行');

        $resp = $this->assignMatrixViaController($roleId, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], range(1, 20));
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(11, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(20, $resp['data']['stats']['granted_permission_count']);

        foreach ($resp['data']['matrix_rows'] as $row) {
            $this->assertTrue($row['menu_checked'], "再授权后菜单 {$row['menu_name']} 应全部勾选");
            if ($row['type'] === 'permission') {
                $this->assertTrue($row['permission_checked'], "再授权后权限 {$row['permission_name']} 应全部勾选");
            }
        }

        $resp = $this->assignMatrixViaController($roleId, [], []);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(0, $resp['data']['stats']['granted_permission_count']);

        foreach ($resp['data']['matrix_rows'] as $row) {
            $this->assertFalse($row['menu_checked'], '完全清空后菜单应全部未勾选');
            if ($row['type'] === 'permission') {
                $this->assertFalse($row['permission_checked'], '完全清空后权限应全部未勾选');
            }
        }

        $this->assertEmpty($this->getRoleMenuIds($roleId));
        $this->assertEmpty($this->getRolePermIds($roleId));
    }

    // ================================================================
    // 12. 三端角色各自独立闭环
    // ================================================================

    public function testMerchantEndToEndClosedLoop(): void
    {
        $roleId = $this->createRoleViaDb('商家闭环', 'merchant_e2e', 'merchant');

        $resp = $this->assignMatrixViaController($roleId, [12, 13, 14], [21, 22]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(2, $resp['data']['stats']['granted_permission_count']);

        $resp = $this->assignMatrixViaController($roleId, [], []);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(0, $resp['data']['stats']['granted_permission_count']);
    }

    public function testWarehouseEndToEndClosedLoop(): void
    {
        $roleId = $this->createRoleViaDb('仓储闭环', 'warehouse_e2e', 'warehouse');

        $resp = $this->assignMatrixViaController($roleId, [18, 19, 20, 21], [25, 26, 27]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(4, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(3, $resp['data']['stats']['granted_permission_count']);

        $resp = $this->assignMatrixViaController($roleId, [19], [25]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(1, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(1, $resp['data']['stats']['granted_permission_count']);

        $resp = $this->assignMatrixViaController($roleId, [], []);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(0, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(0, $resp['data']['stats']['granted_permission_count']);
    }

    // ================================================================
    // 13. 菜单授权→操作授权联动验证
    // ================================================================

    public function testAssignMenusThenPermsMaintainsConsistency(): void
    {
        $roleId = $this->createRoleViaDb('联动测试', 'test_linkage', 'platform');

        $this->assignMenusViaController($roleId, [2, 3, 5]);

        $resp = $this->assignPermsViaController($roleId, [1, 2, 3, 4, 5, 6, 7, 8, 13, 14, 15, 16]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(3, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(12, $resp['data']['stats']['granted_permission_count']);

        $this->assignMenusViaController($roleId, [2]);
        $resp = $this->assignPermsViaController($roleId, [1, 2, 3, 4]);
        $this->assertSuccess($resp);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);
        $this->assertEquals(1, $resp['data']['stats']['granted_menu_count']);
        $this->assertEquals(4, $resp['data']['stats']['granted_permission_count']);

        $grantedMenuIds = $resp['data']['granted_menu_ids'];
        $this->assertContains(2, $grantedMenuIds);
        $this->assertNotContains(3, $grantedMenuIds);
    }

    public function testRemoveMenuThenPermAuthFailsForOrphanPerms(): void
    {
        $roleId = $this->createRoleViaDb('移除菜单测试', 'test_remove_menu', 'platform');

        $this->assignMenusViaController($roleId, [2, 3]);
        $this->assignPermsViaController($roleId, [1, 2, 5]);

        $this->assignMenusViaController($roleId, [2]);

        $resp = $this->assignPermsViaController($roleId, [1, 2, 5]);
        $this->assertError($resp, 1);
        $this->assertStringContainsString('所属菜单未授权', $resp['message']);
    }

    // ================================================================
    // 14. 矩阵数据一致性验证（granted_menu_ids 与 matrix_rows 同步）
    // ================================================================

    public function testMatrixGrantedIdsMatchMatrixRows(): void
    {
        $roleId = $this->createRoleViaDb('一致性测试', 'test_consistency', 'platform');

        $this->assignMatrixViaController($roleId, [2, 3, 5], [1, 5, 13]);

        $resp = $this->getMatrixViaController($roleId);
        $this->assertSuccess($resp);

        $grantedMenuIds = $resp['data']['granted_menu_ids'];
        $grantedPermIds = $resp['data']['granted_permission_ids'];

        $checkedMenuIdsFromRows = [];
        $checkedPermIdsFromRows = [];
        foreach ($resp['data']['matrix_rows'] as $row) {
            if ($row['menu_checked'] && !in_array($row['menu_id'], $checkedMenuIdsFromRows)) {
                $checkedMenuIdsFromRows[] = $row['menu_id'];
            }
            if ($row['type'] === 'permission' && $row['permission_checked']) {
                $checkedPermIdsFromRows[] = $row['permission_id'];
            }
        }

        sort($grantedMenuIds);
        sort($checkedMenuIdsFromRows);
        $this->assertEquals($grantedMenuIds, $checkedMenuIdsFromRows, 'granted_menu_ids 与矩阵行勾选状态应一致');

        sort($grantedPermIds);
        sort($checkedPermIdsFromRows);
        $this->assertEquals($grantedPermIds, $checkedPermIdsFromRows, 'granted_permission_ids 与矩阵行勾选状态应一致');
    }

    // ================================================================
    // 15. 禁用菜单/权限后矩阵不显示
    // ================================================================

    public function testDisabledMenuNotShownInMatrix(): void
    {
        $this->disableMenuInDb(2);

        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $menuIds = array_column($resp['data']['matrix_rows'], 'menu_id');
        $this->assertNotContains(2, $menuIds, '禁用菜单不应出现在矩阵中');

        $this->enableMenuInDb(2);
    }

    public function testDisabledPermNotShownInMatrix(): void
    {
        $this->disablePermInDb(1);

        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $permIds = array_filter(array_column($resp['data']['matrix_rows'], 'permission_id'));
        $this->assertNotContains(1, $permIds, '禁用权限不应出现在矩阵中');

        $this->enablePermInDb(1);
    }

    public function testDisabledMenuExcludedFromGrantedCount(): void
    {
        $this->disableMenuInDb(2);

        $resp = $this->getMatrixViaController(1);
        $this->assertSuccess($resp);

        $stats = $resp['data']['stats'];
        $this->assertEquals(10, $stats['total_menus'], '禁用菜单不计入总菜单数');

        $this->enableMenuInDb(2);
    }
}
