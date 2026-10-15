<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class AuthController
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $find = Database::pdo()->prepare('SELECT id, name, email, phone, password_hash, role FROM users WHERE email = ?');
            $find->execute([$email]);
            $user = $find->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                \failForm('/entrar', 'E-mail ou senha incorretos.', ['email' => $email]);
            }
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            \flash('success', 'Boas-vindas, ' . $user['name'] . '.');
            $next = $_SESSION['after_login'] ?? ($user['role'] === 'admin' ? '/gestor' : '/');
            unset($_SESSION['after_login']);
            if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/';
            \redirect($next);
        }
        \view('auth', ['mode' => 'login', 'old' => \consumeOldInput()]);
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $old = ['name' => $name, 'email' => $email, 'phone' => $phone];

            if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                \failForm('/cadastro', 'Informe um nome e um e-mail válidos.', $old);
            }
            if ($phone === '' || mb_strlen($phone) > 30 || !preg_match('/^[0-9+() .-]{8,30}$/', $phone)) {
                \failForm('/cadastro', 'Informe um telefone válido para confirmar a retirada.', $old);
            }
            if (strlen($password) < 10 || strlen($password) > 72) {
                \failForm('/cadastro', 'A senha precisa ter entre 10 e 72 caracteres.', $old);
            }

            try {
                $insert = Database::pdo()->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
                $insert->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            } catch (\PDOException $error) {
                if ((string) $error->getCode() === '23000') {
                    \failForm('/cadastro', 'Este e-mail já possui uma conta. Entre para continuar.', $old);
                }
                throw $error;
            }
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) Database::pdo()->lastInsertId(), 'name' => $name,
                'email' => $email, 'phone' => $phone, 'role' => 'customer',
            ];
            \flash('success', 'Sua conta foi criada. Agora você pode concluir a encomenda.');
            $next = $_SESSION['after_login'] ?? '/';
            unset($_SESSION['after_login']);
            if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/';
            \redirect($next);
        }
        \view('auth', ['mode' => 'register', 'old' => \consumeOldInput()]);
    }

    public function logout(): void
    {
        \verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        \redirect('/');
    }
}
