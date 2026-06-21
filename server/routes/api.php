<?php

$routes = [
    ['POST', '/api/auth/login', 'AuthController@login'],
    ['GET', '/api/auth/info', 'AuthController@info'],

    ['GET', '/api/roles/{appType}', 'RoleController@index'],
    ['POST', '/api/roles', 'RoleController@store'],
    ['PUT', '/api/roles/{id}', 'RoleController@update'],
    ['DELETE', '/api/roles/{id}', 'RoleController@delete'],
    ['POST', '/api/roles/batch/menus', 'RoleController@batchAssignMenus'],
    ['POST', '/api/roles/batch/permissions', 'RoleController@batchAssignPermissions'],
    ['POST', '/api/roles/{id}/menus', 'RoleController@assignMenus'],
    ['POST', '/api/roles/{id}/permissions', 'RoleController@assignPermissions'],
    ['GET', '/api/roles/{id}/menus', 'RoleController@getMenus'],
    ['GET', '/api/roles/{id}/permissions', 'RoleController@getPermissions'],

    ['GET', '/api/menus/{appType}', 'MenuController@index'],
    ['GET', '/api/menus/{appType}/tree', 'MenuController@tree'],
    ['POST', '/api/menus', 'MenuController@store'],
    ['PUT', '/api/menus/{id}', 'MenuController@update'],
    ['DELETE', '/api/menus/{id}', 'MenuController@delete'],

    ['GET', '/api/permissions/{appType}', 'PermissionController@index'],
    ['POST', '/api/permissions', 'PermissionController@store'],
    ['PUT', '/api/permissions/{id}', 'PermissionController@update'],
    ['DELETE', '/api/permissions/{id}', 'PermissionController@delete'],
];

return $routes;
