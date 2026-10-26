<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\LoyaltyService;
use DomainException;

final class LoyaltyController
{
    public function index(): void
    {
        $user = \requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $id = filter_var($_POST['reward_id'] ?? null, FILTER_VALIDATE_INT);
            try {
                if (!$id || $id < 1) throw new DomainException('Selecione uma recompensa válida.');
                (new LoyaltyService())->redeem((int) $user['id'], (int) $id);
                \flash('success', 'Resgate solicitado. A equipe confirmará a entrega do brinde.');
            } catch (DomainException $error) { \flash('error', $error->getMessage()); }
            \redirect('/fidelidade');
        }
        $pdo = Database::pdo();
        $balance = $pdo->prepare('SELECT COALESCE(SUM(points_delta), 0) FROM loyalty_movements WHERE user_id = ?');
        $balance->execute([$user['id']]);
        $rewards = $pdo->query('SELECT id, name, description, points_cost FROM loyalty_rewards WHERE is_active = 1 ORDER BY points_cost, name')->fetchAll();
        $movements = $pdo->prepare('SELECT points_delta, reason, created_at FROM loyalty_movements WHERE user_id = ? ORDER BY id DESC LIMIT 30');
        $movements->execute([$user['id']]);
        $redemptions = $pdo->prepare('SELECT r.id, r.status, r.created_at, w.name AS reward_name FROM loyalty_redemptions r JOIN loyalty_rewards w ON w.id = r.reward_id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 20');
        $redemptions->execute([$user['id']]);
        \view('loyalty', ['balance' => (int) $balance->fetchColumn(), 'rewards' => $rewards, 'movements' => $movements->fetchAll(), 'redemptions' => $redemptions->fetchAll()]);
    }

    public function manage(): void
    {
        \requireAdmin();
        $pdo = Database::pdo();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $action = (string) ($_POST['action'] ?? '');
            try {
                if ($action === 'settings') {
                    $rate = filter_var($_POST['points_per_real'] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($rate === false || $rate < 0 || $rate > 1000) throw new DomainException('Informe uma taxa entre 0 e 1000 pontos por real.');
                    $pdo->prepare('UPDATE loyalty_settings SET points_per_real = ? WHERE id = 1')->execute([$rate]);
                    \flash('success', 'Taxa de pontos atualizada.');
                } elseif ($action === 'reward') {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $description = trim((string) ($_POST['description'] ?? ''));
                    $cost = filter_var($_POST['points_cost'] ?? null, FILTER_VALIDATE_INT);
                    if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 500 || $cost === false || $cost < 1 || $cost > 10000000) throw new DomainException('Revise o nome, a descrição e o custo em pontos do brinde.');
                    $pdo->prepare('INSERT INTO loyalty_rewards (name, description, points_cost) VALUES (?, ?, ?)')->execute([$name, $description, $cost]);
                    \flash('success', 'Recompensa cadastrada.');
                } elseif ($action === 'deactivate') {
                    $id = filter_var($_POST['reward_id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) throw new DomainException('Recompensa inválida.');
                    $pdo->prepare('UPDATE loyalty_rewards SET is_active = 0 WHERE id = ?')->execute([$id]);
                    \flash('success', 'Recompensa ocultada; o histórico foi preservado.');
                } elseif ($action === 'delivered' || $action === 'cancel') {
                    $id = filter_var($_POST['redemption_id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) throw new DomainException('Resgate inválido.');
                    if ($action === 'cancel') (new LoyaltyService())->cancelRedemption((int) $id);
                    else {
                        $stmt = $pdo->prepare("UPDATE loyalty_redemptions SET status = 'delivered' WHERE id = ? AND status = 'pending'");
                        $stmt->execute([$id]);
                        if ($stmt->rowCount() !== 1) throw new DomainException('Esse resgate não está pendente.');
                    }
                    \flash('success', $action === 'cancel' ? 'Resgate cancelado e pontos devolvidos.' : 'Brinde marcado como entregue.');
                } else throw new DomainException('Ação inválida.');
            } catch (DomainException $error) { \flash('error', $error->getMessage()); }
            \redirect('/gestor/fidelidade');
        }
        $settings = $pdo->query('SELECT points_per_real FROM loyalty_settings WHERE id = 1')->fetch();
        $rewards = $pdo->query('SELECT * FROM loyalty_rewards ORDER BY is_active DESC, points_cost, name')->fetchAll();
        $redemptions = $pdo->query("SELECT r.id, r.status, r.created_at, w.name AS reward_name, w.points_cost, u.name AS customer_name, u.email AS customer_email FROM loyalty_redemptions r JOIN loyalty_rewards w ON w.id = r.reward_id JOIN users u ON u.id = r.user_id ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 100")->fetchAll();
        \view('admin_loyalty', ['settings' => $settings, 'rewards' => $rewards, 'redemptions' => $redemptions]);
    }
}
