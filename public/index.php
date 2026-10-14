<?php
// PORTA DE ENTRADA: toda requisição passa por aqui.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Controllers\{CatalogController, AuthController, OrderController};

// Tabela de rotas:  'MÉTODO /caminho' => [Controller, método]
// Para criar uma página nova: adicione uma linha aqui + um método no controller.
$routes = [
    'GET /'          => [CatalogController::class, 'index'],    // RF01, RF03
    'GET /entrar'    => [AuthController::class,    'login'],    // RF05  (TODO)
    'GET /cadastro'  => [AuthController::class,    'register'], // RF05  (TODO)
    'GET /pedidos'   => [OrderController::class,   'index'],    // Meus pedidos (TODO)
];

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$key  = $_SERVER['REQUEST_METHOD'] . ' ' . $path;

if (!isset($routes[$key])) {
    http_response_code(404);
    exit('Página não encontrada');
}

[$class, $method] = $routes[$key];
(new $class())->$method();
