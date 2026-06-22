<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'rbac_system');
define('DB_CHARSET', 'utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

function getDatabase()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

return getDatabase();
