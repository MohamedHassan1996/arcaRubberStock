<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$router = new Core\Router();

// Routes

// Auth
$router->post('api/auth/login', 'AuthController@login');
$router->post('api/auth/logout', 'AuthController@logout');

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
$router->get('api/operator-orders', 'OperatorOrderController@index');
$router->get('api/operator-orders/{id}', 'OperatorOrderController@show');
$router->post('api/operator-orders', 'OperatorOrderController@store');
$router->put('api/operator-orders', 'OperatorOrderController@update');
$router->delete('api/operator-orders', 'OperatorOrderController@destroy');

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





$router->dispatch($_SERVER['REQUEST_URI']);
