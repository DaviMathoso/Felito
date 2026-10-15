<?php
declare(strict_types=1);

// Executado somente no computador local. Nunca incluir no pacote público.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $name = trim((string) ($input['name'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $name === '' || mb_strlen($name) > 120 || strlen($password) < 12 || strlen($password) > 72) {
        throw new InvalidArgumentException('Informe nome, e-mail e senha entre 12 e 72 bytes.');
    }
    $literal = static fn(string $value): string => "CONVERT(0x" . bin2hex($value) . " USING utf8mb4)";
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "-- Execute uma vez, após importar schema.sql no banco selecionado.\n";
    echo "-- Não altera nem redefine contas existentes.\n";
    echo "SET time_zone = '+00:00';\n";
    echo "INSERT INTO users (name, email, phone, password_hash, role) VALUES (\n";
    echo '    ' . $literal($name) . ', ' . $literal($email) . ", '00000000000', " . $literal($hash) . ", 'admin'\n);\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
