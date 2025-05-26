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


$router->dispatch($_SERVER['REQUEST_URI']);
