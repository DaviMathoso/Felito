<?php
declare(strict_types=1);

// Testa o leitor real com putenv desabilitado, sem usar a configuração privada local.
$fixture = sys_get_temp_dir() . '/felito-config-' . bin2hex(random_bytes(8));
mkdir($fixture);
define('BASE_PATH', $fixture);
try {
    file_put_contents($fixture . '/.env', "  # comentário\n\nDB_HOST=arquivo\nDB_NAME=teste\nDB_USER=gestor\nDB_PASS=\"  senha#com=igual  \"\nAPP_ENV=local\n");
    $config = require '/var/www/html/config/config.php';
    $check = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $check(!function_exists('putenv'), 'Execute com putenv desabilitado.');
    $check($config['db']['host'] === 'processo', 'A variável do processo deve prevalecer.');
    $check($config['db']['name'] === 'teste', 'O .env deve preencher valores ausentes.');
    $check($config['db']['pass'] === '  senha#com=igual  ', 'Aspas, espaços, # e = não foram preservados.');
    $check($config['app']['https_only'] === false, 'Local deve aceitar HTTP.');
    file_put_contents($fixture . '/.env', "APP_ENV=production\nDB_PASS=''\n");
    $config = require '/var/www/html/config/config.php';
    $check($config['db']['pass'] === '', 'Senha vazia deve ser preservada.');
    $check($config['app']['https_only'] === true, 'Produção deve exigir HTTPS por padrão.');
    file_put_contents($fixture . '/.env', "APP_ENV=production\nAPP_HTTPS_ONLY=valor_invalido\n");
    $config = require '/var/www/html/config/config.php';
    $check($config['app']['https_only'] === true, 'Valor inválido não deve desativar HTTPS.');
    echo "OK: configuração sem putenv, prioridade, aspas, senha vazia e HTTPS.\n";
} finally {
    unlink($fixture . '/.env');
    rmdir($fixture);
}
