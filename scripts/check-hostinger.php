<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

try {
    echo 'PHP: ' . PHP_VERSION . PHP_EOL;
    foreach (['PDO', 'pdo_mysql', 'mbstring'] as $extension) echo $extension . ': ' . (extension_loaded($extension) ? 'OK' : 'AUSENTE') . PHP_EOL;
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('A extensao pdo_mysql nao esta habilitada.');
    $pdo = App\Core\Database::pdo();
    echo 'Conexao com MySQL: OK' . PHP_EOL;
    $pdo->query('SELECT id, name, description, image_path, size_label, price, stock_qty, min_stock, is_featured FROM products WHERE is_active = 1 LIMIT 1');
    echo 'Consulta do catalogo: OK' . PHP_EOL;
    $pdo->query('SELECT id, name, email, phone, password_hash, role FROM users LIMIT 1');
    echo 'Estrutura basica de usuarios: OK' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'ERRO: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
