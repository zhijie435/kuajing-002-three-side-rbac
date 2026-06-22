<?php

abstract class TestCase extends PHPUnit\Framework\TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        static::initDatabase();
    }

    protected static function initDatabase(): void
    {
        static::$pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
            'root',
            'yuexingzu',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        static::$pdo->exec("CREATE DATABASE IF NOT EXISTS `rbac_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        static::$pdo->exec("USE `rbac_test`");

        static::resetSchema();
        static::seedData();
    }

    protected static function resetSchema(): void
    {
        $sql = <<<'SQL'
DROP TABLE IF EXISTS `role_permission`;
DROP TABLE IF EXISTS `role_menu`;
DROP TABLE IF EXISTS `role_permission`;
DROP TABLE IF EXISTS `permission`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(64) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `realname` VARCHAR(64) NOT NULL DEFAULT '',
    `app_type` ENUM('platform','merchant','warehouse') NOT NULL,
    `role_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username_app_type` (`username`, `app_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(64) NOT NULL,
    `app_type` ENUM('platform','merchant','warehouse') NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code_app_type` (`code`, `app_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(64) NOT NULL,
    `path` VARCHAR(255) NOT NULL DEFAULT '',
    `icon` VARCHAR(64) NOT NULL DEFAULT '',
    `component` VARCHAR(255) NOT NULL DEFAULT '',
    `app_type` ENUM('platform','merchant','warehouse') NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `type` ENUM('directory','menu','button') NOT NULL DEFAULT 'menu',
    `permission_key` VARCHAR(128) NOT NULL DEFAULT '',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_app_type` (`app_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permission` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `code` VARCHAR(128) NOT NULL,
    `menu_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `app_type` ENUM('platform','merchant','warehouse') NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code_app_type` (`code`, `app_type`),
    KEY `idx_menu_id` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_menu` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `menu_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permission` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        static::$pdo->exec($sql);
    }

    protected static function seedData(): void
    {
        $pdo = static::$pdo;

        $pdo->exec("INSERT INTO `role` (`id`, `name`, `code`, `app_type`, `description`) VALUES
(1, '平台管理员', 'platform_admin', 'platform', '平台端超级管理员'),
(2, '商家管理员', 'merchant_admin', 'merchant', '商家端管理员'),
(3, '仓储管理员', 'warehouse_admin', 'warehouse', '仓储端管理员')");

        $pdo->exec("INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `component`, `app_type`, `sort_order`, `type`, `permission_key`) VALUES
(1,  0, '系统管理', '/system',       'Setting',      '',                              'platform', 1, 'directory', ''),
(2,  1, '角色管理', '/system/role',   'User',         'system/role/index',             'platform', 1, 'menu', 'system:role:list'),
(3,  1, '菜单管理', '/system/menu',   'Menu',         'system/menu/index',             'platform', 2, 'menu', 'system:menu:list'),
(4,  1, '权限管理', '/system/permission', 'Lock',      'system/permission/index',       'platform', 3, 'menu', 'system:permission:list'),
(5,  1, '用户管理', '/system/admin',  'UserFilled',   'system/admin/index',            'platform', 4, 'menu', 'system:admin:list'),
(6,  0, '订单管理', '/order',         'Document',     '',                              'platform', 2, 'directory', ''),
(7,  6, '订单列表', '/order/list',    'List',         'order/list/index',              'platform', 1, 'menu', 'order:list:list'),
(8,  6, '退款管理', '/order/refund',  'Money',        'order/refund/index',            'platform', 2, 'menu', 'order:refund:list'),
(9,  0, '商品管理', '/product',       'Goods',        '',                              'platform', 3, 'directory', ''),
(10, 9, '商品列表', '/product/list',  'ShoppingCart', 'product/list/index',            'platform', 1, 'menu', 'product:list:list'),
(11, 9, '分类管理', '/product/category', 'Folder',    'product/category/index',        'platform', 2, 'menu', 'product:category:list')");

        $pdo->exec("INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `component`, `app_type`, `sort_order`, `type`, `permission_key`) VALUES
(12, 0, '订单管理', '/order',         'Document',     '',                              'merchant', 1, 'directory', ''),
(13, 12, '订单列表', '/order/list',   'List',         'order/list/index',              'merchant', 1, 'menu', 'merchant:order:list'),
(14, 12, '售后管理', '/order/aftersale','Service',     'order/aftersale/index',         'merchant', 2, 'menu', 'merchant:order:aftersale'),
(15, 0, '商品管理', '/product',       'Goods',        '',                              'merchant', 2, 'directory', ''),
(16, 15, '商品列表', '/product/list', 'ShoppingCart',  'product/list/index',           'merchant', 1, 'menu', 'merchant:product:list'),
(17, 15, '库存管理', '/product/stock','Box',           'product/stock/index',           'merchant', 2, 'menu', 'merchant:product:stock')");

        $pdo->exec("INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `component`, `app_type`, `sort_order`, `type`, `permission_key`) VALUES
(18, 0, '库存管理', '/inventory',     'Box',          '',                              'warehouse', 1, 'directory', ''),
(19, 18, '库存查询', '/inventory/query', 'Search',    'inventory/query/index',         'warehouse', 1, 'menu', 'warehouse:inventory:query'),
(20, 18, '入库管理', '/inventory/in',  'Download',    'inventory/in/index',            'warehouse', 2, 'menu', 'warehouse:inventory:in'),
(21, 18, '出库管理', '/inventory/out', 'Upload',      'inventory/out/index',           'warehouse', 3, 'menu', 'warehouse:inventory:out')");

        $pdo->exec("INSERT INTO `permission` (`id`, `name`, `code`, `menu_id`, `app_type`, `description`) VALUES
(1,  '角色查看', 'system:role:list',    2, 'platform', '查看角色列表'),
(2,  '角色新增', 'system:role:add',     2, 'platform', '新增角色'),
(3,  '角色编辑', 'system:role:edit',    2, 'platform', '编辑角色'),
(4,  '角色删除', 'system:role:delete',  2, 'platform', '删除角色'),
(5,  '菜单查看', 'system:menu:list',    3, 'platform', '查看菜单列表'),
(6,  '菜单新增', 'system:menu:add',     3, 'platform', '新增菜单'),
(7,  '菜单编辑', 'system:menu:edit',    3, 'platform', '编辑菜单'),
(8,  '菜单删除', 'system:menu:delete',  3, 'platform', '删除菜单'),
(9,  '权限查看', 'system:permission:list',   4, 'platform', '查看权限列表'),
(10, '权限新增', 'system:permission:add',    4, 'platform', '新增权限'),
(11, '权限编辑', 'system:permission:edit',   4, 'platform', '编辑权限'),
(12, '权限删除', 'system:permission:delete', 4, 'platform', '删除权限'),
(13, '用户查看', 'system:admin:list',   5, 'platform', '查看用户列表'),
(14, '用户新增', 'system:admin:add',    5, 'platform', '新增用户'),
(15, '用户编辑', 'system:admin:edit',   5, 'platform', '编辑用户'),
(16, '用户删除', 'system:admin:delete', 5, 'platform', '删除用户'),
(17, '订单查看', 'order:list:list',     7, 'platform', '查看订单列表'),
(18, '退款查看', 'order:refund:list',   8, 'platform', '查看退款列表'),
(19, '商品查看', 'product:list:list',   10, 'platform', '查看商品列表'),
(20, '分类查看', 'product:category:list', 11, 'platform', '查看分类列表')");

        $pdo->exec("INSERT INTO `permission` (`id`, `name`, `code`, `menu_id`, `app_type`, `description`) VALUES
(21, '订单查看', 'merchant:order:list',      13, 'merchant', '查看订单列表'),
(22, '售后查看', 'merchant:order:aftersale',  14, 'merchant', '查看售后列表'),
(23, '商品查看', 'merchant:product:list',     16, 'merchant', '查看商品列表'),
(24, '库存查看', 'merchant:product:stock',    17, 'merchant', '查看库存')");

        $pdo->exec("INSERT INTO `permission` (`id`, `name`, `code`, `menu_id`, `app_type`, `description`) VALUES
(25, '库存查询', 'warehouse:inventory:query', 19, 'warehouse', '查询库存'),
(26, '入库操作', 'warehouse:inventory:in',    20, 'warehouse', '入库管理操作'),
(27, '出库操作', 'warehouse:inventory:out',   21, 'warehouse', '出库管理操作')");

        $pdo->exec("INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),
(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),
(3,18),(3,19),(3,20),(3,21)");

        $pdo->exec("INSERT INTO `role_permission` (`role_id`, `permission_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),
(2,21),(2,22),(2,23),(2,24),
(3,25),(3,26),(3,27)");
    }

    protected function createRoleController(): TestableRoleController
    {
        return new TestableRoleController(static::$pdo);
    }

    protected function resetData(): void
    {
        static::$pdo->exec("DELETE FROM role_permission");
        static::$pdo->exec("DELETE FROM role_menu");
        static::$pdo->exec("DELETE FROM permission");
        static::$pdo->exec("DELETE FROM menu");
        static::$pdo->exec("DELETE FROM role");
        static::$pdo->exec("DELETE FROM admin");
        static::seedData();
    }
}
