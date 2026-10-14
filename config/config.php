<?php
// Lê o .env (CHAVE=valor) e devolve as configurações do projeto.
$env = [];
$file = BASE_PATH . '/.env';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] !== '#' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
}

return [
    'app' => ['env' => $env['APP_ENV'] ?? 'production'],
    'db'  => [
        'host' => $env['DB_HOST'] ?? 'localhost',
        'name' => $env['DB_NAME'] ?? 'felito',
        'user' => $env['DB_USER'] ?? 'root',
        'pass' => $env['DB_PASS'] ?? '',
    ],
    'mercadopago' => ['token' => $env['MP_ACCESS_TOKEN'] ?? ''],
];
