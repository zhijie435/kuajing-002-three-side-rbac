<?php
require_once __DIR__ . '/api/Database.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

$sql = file_get_contents(__DIR__ . '/database/init.sql');

$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $pdo->exec($statement);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "SQL: " . $statement . "\n";
        }
    }
}

echo "数据库初始化完成！\n";

$roles = $db->fetchAll("SELECT * FROM roles");
echo "\n角色列表：\n";
foreach ($roles as $role) {
    echo "- {$role['name']} ({$role['code']}) - {$role['terminal']}\n";
}

$menus = $db->fetchAll("SELECT * FROM menus ORDER BY terminal, id");
echo "\n菜单列表：\n";
foreach ($menus as $menu) {
    echo "- [{$menu['terminal']}] {$menu['name']} ({$menu['path']})\n";
}

$operations = $db->fetchAll("SELECT * FROM operations");
echo "\n操作权限列表：\n";
foreach ($operations as $op) {
    echo "- {$op['name']} ({$op['code']})\n";
}
