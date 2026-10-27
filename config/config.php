<?php
declare(strict_types=1);

$values = [];
$file = BASE_PATH . '/.env';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
}

$read = static function (string $key, string $default) use ($values): string {
    $environment = function_exists('getenv') ? getenv($key) : false;
    return $environment !== false ? $environment : ($values[$key] ?? $default);
};
$appEnv = $read('APP_ENV', 'production');

return [
    'app' => [
        'env' => $appEnv,
        'pix_key' => $read('FELITO_PIX_KEY', ''),
        'https_only' => filter_var($read('APP_HTTPS_ONLY', $appEnv === 'local' ? 'false' : 'true'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false,
    ],
    'db' => [
        'host' => $read('DB_HOST', 'localhost'),
        'name' => $read('DB_NAME', 'felito'),
        'user' => $read('DB_USER', 'root'),
        'pass' => $read('DB_PASS', ''),
    ],
];
