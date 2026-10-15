<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Controllers\{AdminController, AuthController, CatalogController, OrderController};

$routes = [
    'GET /' => [CatalogController::class, 'index'],
    'GET /carrinho' => [OrderController::class, 'cart'],
    'POST /carrinho' => [OrderController::class, 'cart'],
    'GET /checkout' => [OrderController::class, 'checkout'],
    'POST /checkout' => [OrderController::class, 'checkout'],
    'GET /pedidos' => [OrderController::class, 'index'],
    'GET /entrar' => [AuthController::class, 'login'],
    'POST /entrar' => [AuthController::class, 'login'],
    'GET /cadastro' => [AuthController::class, 'register'],
    'POST /cadastro' => [AuthController::class, 'register'],
    'POST /sair' => [AuthController::class, 'logout'],
    'GET /gestor' => [AdminController::class, 'dashboard'],
    'POST /gestor' => [AdminController::class, 'dashboard'],
    'GET /gestor/produtos' => [AdminController::class, 'products'],
    'POST /gestor/produtos' => [AdminController::class, 'products'],
    'GET /gestor/estoque' => [AdminController::class, 'stock'],
    'POST /gestor/estoque' => [AdminController::class, 'stock'],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/') $path = rtrim($path, '/');
$key = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . $path;

if (!isset($routes[$key])) {
    http_response_code(404);
    view('error', ['title' => 'Página não encontrada', 'message' => 'Este endereço não faz parte do catálogo Felito.']);
    exit;
}

[$controller, $method] = $routes[$key];
(new $controller())->$method();
