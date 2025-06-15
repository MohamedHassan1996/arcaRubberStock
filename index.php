<?php

use Core\DB;

require_once __DIR__ . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header("Access-Control-Allow-Origin: *"); // You can restrict this to a specific domain
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$addPremssions = false;

if ($addPremssions) {
    // Step 1: Insert all permissions

    try {
        DB::beginTransaction();
        $allPermissions = [
        'all_users',
        'store_user',
        'show_user',
        'update_user',
        'destroy_user',

        'all_products',
        'store_product',
        'show_product',
        'update_product',
        'destroy_product',

        'all_product_codes',
        'store_product_code',
        'show_product_code',
        'update_product_code',
        'destroy_product_code',

        'all_role_poducts',
        'store_role_product',
        'show_role_product',
        'update_role_product',
        'destroy_role_product',

        'all_operator_orders',
        'store_operator_order',
        'show_operator_order',
        'update_operator_order',
        'destroy_operator_order',

        'all_stocks',
        'show_stock',
        'update_stock',

        'all_parameters',
        'store_parameter',
        'show_parameter',
        'update_parameter',
        'destroy_parameter'
    ];

    foreach ($allPermissions as $permission) {
        DB::raw("INSERT INTO permissions (`name`) VALUES (?)", [$permission]);
    }

    // Step 2: Insert all roles
    $allRoles = ['admin', 'operator', 'supervisor'];
    foreach ($allRoles as $role) {
        DB::raw("INSERT INTO roles (`name`) VALUES (?)", [$role]);
    }

    // Step 3: Fetch role IDs
    $adminRoleId = DB::raw("SELECT id FROM roles WHERE name = ?", ['admin'])[0]['id'];
    $operatorRoleId = DB::raw("SELECT id FROM roles WHERE name = ?", ['operator'])[0]['id'];
    $supervisorRoleId = DB::raw("SELECT id FROM roles WHERE name = ?", ['supervisor'])[0]['id'];

    // Step 4: Fetch all permissions (id, name)
    $permissions = DB::raw("SELECT id, name FROM permissions");

    // Build map: permission name => ID
    $permMap = [];
    foreach ($permissions as $perm) {
        $permMap[$perm['name']] = $perm['id'];
    }

    // Step 5: Assign all permissions to admin
    foreach ($permMap as $permissionId) {
        DB::raw("INSERT INTO role_has_permissions (role_id, permission_id) VALUES (?, ?)", [
            $adminRoleId, $permissionId
        ]);
    }

    // Step 6: Assign specific permissions to supervisor
    $supervisorPerms = [
        'all_operator_orders',
        'store_operator_order',
        'show_operator_order',
        'update_operator_order',
        'all_products'
    ];

    foreach ($supervisorPerms as $permName) {
        if (isset($permMap[$permName])) {
            DB::raw("INSERT INTO role_has_permissions (role_id, permission_id) VALUES (?, ?)", [
                $supervisorRoleId, $permMap[$permName]
            ]);
        }
    }

    // Step 7: Assign specific permissions to operator
    $operatorPerms = [
        'store_operator_order',
        'all_products'
    ];

    foreach ($operatorPerms as $permName) {
        if (isset($permMap[$permName])) {
            DB::raw("INSERT INTO role_has_permissions (role_id, permission_id) VALUES (?, ?)", [
                $operatorRoleId, $permMap[$permName]
            ]);
        }
    }

    DB::commit();
    } catch (\Throwable $th) {
        DB::rollBack();
        throw $th;
    }
}

$router = new Core\Router();

// Routes

// Auth
$router->post('api/auth/login', 'AuthController@login');
$router->post('api/auth/logout', 'AuthController@logout');

$router->get('api/users', 'UserController@index');
$router->get('api/users/{id}', 'UserController@show');
$router->post('api/users', 'UserController@store');
$router->put('api/users', 'UserController@update');
$router->delete('api/users/{id}', 'UserController@destroy');

// Products
$router->get('api/products', 'ProductController@index');
$router->get('api/products/{id}', 'ProductController@show');
$router->post('api/products', 'ProductController@store');
$router->put('api/products', 'ProductController@update');
$router->delete('api/products/{id}', 'ProductController@destroy');

// Product Codes
$router->get('api/product-codes', 'ProductCodeController@index');
$router->get('api/product-codes/{id}', 'ProductCodeController@show');
$router->post('api/product-codes', 'ProductCodeController@store');
$router->put('api/product-codes', 'ProductCodeController@update');
$router->delete('api/product-codes/{id}', 'ProductCodeController@destroy');

// Orders
$router->get('api/orders', 'OrderController@index');
$router->get('api/orders/{id}', 'OrderController@show');
$router->post('api/operator-orders', 'OperatorOrderController@store');
$router->put('api/operator-orders', 'OperatorOrderController@update');
$router->delete('api/operator-orders', 'OperatorOrderController@destroy');

//operator orders
$router->post('api/operator-orders', 'OperatorOrderController@store');

// orderItems

$router->get('api/order-items', 'OrderItemController@index');
$router->get('api/order-items/{id}', 'OrderItemController@show');
$router->put('api/order-items', 'OrderItemController@update');
$router->delete('api/order-items/{id}', 'OrderItemController@destroy');

// change order item status
$router->put('api/order-item-status', 'OrderItemStatusController@update');

// confirm order item
$router->put('api/order-item-confirm', 'ConfirmOrderItemController@update');

// stocks
$router->get('api/stocks', 'StockController@index');
$router->get('api/stocks/{id}', 'StockController@show');
$router->put('api/stocks', 'StockController@update');

// roleProduct
$router->get('api/role-products', 'RoleProductController@index');
$router->get('api/role-products/{id}', 'RoleProductController@show');
$router->post('api/role-products', 'RoleProductController@store');
$router->put('api/role-products', 'RoleProductController@update');
$router->delete('api/role-products/{id}', 'RoleProductController@destroy');

// parameter value
$router->get('api/parameters', 'ParameterValueController@index');
$router->get('api/parameters/{id}', 'ParameterValueController@show');
$router->post('api/parameters', 'ParameterValueController@store');
$router->put('api/parameters', 'ParameterValueController@update');
$router->delete('api/parameters/{id}', 'ParameterValueController@destroy');


$router->get('api/selects', 'SelectController@index');



$router->dispatch($_SERVER['REQUEST_URI']);
