<?php
// Carregado por todo mundo: autoload das classes + 3 funções de ajuda.

spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', str_replace('App\\', '', $class)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function config(string $key)               // config('db.host')
{
    static $cfg = null;
    $cfg ??= require BASE_PATH . '/config/config.php';
    foreach (explode('.', $key) as $part) {
        $cfg = $cfg[$part] ?? null;
    }
    return $cfg;
}

function e($v): string                     // escapa texto antes de mostrar no HTML
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function view(string $name, array $data = []): void   // view('catalog', ['flavors' => $x])
{
    extract($data);
    ob_start();
    require BASE_PATH . "/views/$name.php";
    $content = ob_get_clean();
    require BASE_PATH . '/views/layout.php';
}
