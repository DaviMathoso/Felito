<?php
declare(strict_types=1);

use App\Core\Database;

date_default_timezone_set('America/Sao_Paulo');
$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
if (PHP_SAPI !== 'cli') {
    if (config('app.env') !== 'local') {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        $initialBufferLevel = ob_get_level();
        ob_start();
        set_exception_handler(static function (Throwable $error) use ($initialBufferLevel): void {
            error_log((string) $error);
            while (ob_get_level() > $initialBufferLevel) ob_end_clean();
            http_response_code(500);
            view('error', ['title' => 'Não foi possível concluir', 'message' => 'Ocorreu um problema ao processar sua solicitação. Tente novamente em instantes.']);
        });
    }
    if (config('app.https_only') && !$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]+)?$/iD', $host)) {
            http_response_code(400);
            exit;
        }
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
        exit;
    }
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

function config(string $key): mixed
{
    static $cfg;
    $cfg ??= require BASE_PATH . '/config/config.php';
    $value = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return null;
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(string|int|float $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function statusLabel(string $status): string
{
    return match ($status) {
        'received' => 'Recebido', 'preparing' => 'Em preparo', 'ready' => 'Pronto para retirada',
        'delivered' => 'Entregue', 'cancelled' => 'Cancelado', 'expired' => 'Expirado', default => 'Em atualização',
    };
}

function localDateTime(string $value, string $format = 'd/m/Y H:i'): string
{
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
        ->format($format);
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . "/views/$name.php";
    $content = ob_get_clean();
    require BASE_PATH . '/views/layout.php';
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        http_response_code(403);
        view('error', ['title' => 'Formulário expirado', 'message' => 'Atualize a página e tente novamente.']);
        exit;
    }
}

function currentUser(): ?array { return $_SESSION['user'] ?? null; }

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        flash('info', 'Entre ou crie uma conta para continuar.');
        redirect('/entrar');
    }
    return $user;
}

function requireAdmin(): array
{
    $user = currentUser();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/gestor';
        flash('error', 'Entre com uma conta de gestor para acessar esta área.');
        redirect('/entrar');
    }
    if ($user['role'] !== 'admin') {
        flash('error', 'Esta área é exclusiva do gestor.');
        redirect('/');
    }
    return $user;
}

function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type' => $type, 'message' => $message]; }
function consumeFlashes(): array { $messages = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $messages; }
function consumeOldInput(): array { $input = $_SESSION['_old'] ?? []; unset($_SESSION['_old']); return $input; }

function failForm(string $path, string $message, array $old = []): never
{
    $_SESSION['_old'] = $old;
    flash('error', $message);
    redirect($path);
}

function pdo(): PDO { return Database::pdo(); }
