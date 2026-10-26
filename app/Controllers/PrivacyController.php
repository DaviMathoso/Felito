<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class PrivacyController
{
    public function page(): void
    {
        $user = \requireLogin();
        $pdo = Database::pdo();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $action = (string) ($_POST['action'] ?? 'request');
            if ($action === 'profile') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || $phone === '' || mb_strlen($phone) > 30 || !preg_match('/^[0-9+() .-]{8,30}$/', $phone)) {
                    \flash('error', 'Informe nome, e-mail e telefone válidos.');
                } else {
                    try {
                        $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?')->execute([$name, $email, $phone, $user['id']]);
                        $pdo->prepare("INSERT INTO privacy_requests (user_id, request_type, details, status) VALUES (?, 'correction', 'Dados atualizados pelo titular', 'completed')")->execute([$user['id']]);
                        $_SESSION['user']['name'] = $name; $_SESSION['user']['email'] = $email; $_SESSION['user']['phone'] = $phone;
                        \flash('success', 'Seus dados foram atualizados.');
                    } catch (\PDOException $error) {
                        if ((string) $error->getCode() === '23000') \flash('error', 'Este e-mail já está em uso.');
                        else throw $error;
                    }
                }
            } else {
                $type = (string) ($_POST['request_type'] ?? '');
                $details = trim((string) ($_POST['details'] ?? ''));
                if (!in_array($type, ['access', 'correction', 'deletion', 'portability'], true) || mb_strlen($details) > 1000) {
                    \flash('error', 'Selecione uma solicitação válida e limite os detalhes a 1000 caracteres.');
                } else {
                    $pdo->prepare('INSERT INTO privacy_requests (user_id, request_type, details) VALUES (?, ?, ?)')->execute([$user['id'], $type, $details ?: null]);
                    \flash('success', 'Solicitação registrada. A equipe fará o atendimento e atualizará o status.');
                }
            }
            \redirect('/privacidade');
        }
        $profile = $pdo->prepare('SELECT name, email, phone FROM users WHERE id = ?'); $profile->execute([$user['id']]);
        $consent = $pdo->prepare('SELECT terms_version, privacy_version, accepted_at FROM consent_records WHERE user_id = ? ORDER BY id DESC LIMIT 1'); $consent->execute([$user['id']]);
        $requests = $pdo->prepare('SELECT request_type, status, created_at, details FROM privacy_requests WHERE user_id = ? ORDER BY id DESC LIMIT 30'); $requests->execute([$user['id']]);
        \view('privacy', ['profile' => $profile->fetch(), 'consent' => $consent->fetch(), 'requests' => $requests->fetchAll()]);
    }

    public function export(): never
    {
        $user = \requireLogin();
        $pdo = Database::pdo();
        $orders = $pdo->prepare('SELECT id, status, pickup_date, note, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $orders->execute([$user['id']]);
        $orderRows = $orders->fetchAll();
        $orderIds = array_column($orderRows, 'id');
        $items = [];
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemQuery = $pdo->prepare("SELECT order_id, product_name, size_label, quantity, unit_price FROM order_items WHERE order_id IN ($placeholders) ORDER BY order_id, id");
            $itemQuery->execute($orderIds);
            $items = $itemQuery->fetchAll();
        }
        $points = $pdo->prepare('SELECT points_delta, reason, created_at FROM loyalty_movements WHERE user_id = ? ORDER BY id');
        $points->execute([$user['id']]);
        $consent = $pdo->prepare('SELECT terms_version, privacy_version, accepted_at FROM consent_records WHERE user_id = ? ORDER BY id');
        $consent->execute([$user['id']]);
        $redemptions = $pdo->prepare('SELECT r.status, r.created_at, w.name AS reward_name, w.points_cost FROM loyalty_redemptions r JOIN loyalty_rewards w ON w.id = r.reward_id WHERE r.user_id = ? ORDER BY r.id');
        $redemptions->execute([$user['id']]);
        $requests = $pdo->prepare('SELECT request_type, details, status, created_at FROM privacy_requests WHERE user_id = ? ORDER BY id');
        $requests->execute([$user['id']]);
        $payload = [
            'account' => ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']],
            'orders' => $orderRows, 'order_items' => $items,
            'consent_history' => $consent->fetchAll(), 'loyalty_history' => $points->fetchAll(),
            'loyalty_redemptions' => $redemptions->fetchAll(), 'privacy_requests' => $requests->fetchAll(),
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="felito-dados-pessoais.json"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public function terms(): void { \view('terms'); }
    public function policy(): void { \view('privacy_policy'); }

    public function manageRequests(): void
    {
        \requireAdmin();
        $pdo = Database::pdo();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $id = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if ($id && $id > 0 && in_array($status, ['completed', 'rejected'], true)) {
                $pdo->prepare("UPDATE privacy_requests SET status = ? WHERE id = ? AND status = 'open'")->execute([$status, $id]);
                \flash('success', 'Solicitação atualizada.');
            } else \flash('error', 'Solicitação inválida.');
            \redirect('/gestor/privacidade');
        }
        $requests = $pdo->query("SELECT r.id, r.request_type, r.details, r.status, r.created_at, u.name, u.email FROM privacy_requests r JOIN users u ON u.id = r.user_id ORDER BY (r.status = 'open') DESC, r.created_at DESC LIMIT 100")->fetchAll();
        \view('admin_privacy', ['requests' => $requests]);
    }
}
