<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'felito';
$user = getenv('DB_USER') ?: 'felito';
$pass = getenv('DB_PASS') ?: 'felito_local';
$email = strtolower(trim(getenv('FELITO_ADMIN_EMAIL') ?: 'gestor@felito.local'));
$password = getenv('FELITO_ADMIN_PASSWORD') ?: 'FelitoLocal123!';
$adminName = trim(getenv('FELITO_ADMIN_NAME') ?: 'Gestor Felito');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Credenciais de desenvolvimento do gestor inválidas.\n");
    exit(1);
}

$lastError = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $insert = $pdo->prepare(
            "INSERT IGNORE INTO users (name, email, phone, password_hash, role)
             VALUES (?, ?, '00000000000', ?, 'admin')"
        );
        $insert->execute([$adminName, $email, password_hash($password, PASSWORD_DEFAULT)]);
        exit(0);
    } catch (Throwable $error) {
        $lastError = $error;
        usleep(500_000);
    }
}

fwrite(STDERR, 'Não foi possível inicializar o usuário gestor: ' . ($lastError?->getMessage() ?? 'erro desconhecido') . "\n");
exit(1);
