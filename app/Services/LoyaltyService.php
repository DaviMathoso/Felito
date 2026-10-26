<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;
use Throwable;

final class LoyaltyService
{
    public function redeem(int $userId, int $rewardId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetch()) throw new DomainException('Conta não encontrada.');
            $rewardQuery = $pdo->prepare('SELECT id, name, points_cost FROM loyalty_rewards WHERE id = ? AND is_active = 1 FOR UPDATE');
            $rewardQuery->execute([$rewardId]);
            $reward = $rewardQuery->fetch();
            if (!$reward) throw new DomainException('Essa recompensa não está disponível.');
            $balanceQuery = $pdo->prepare('SELECT COALESCE(SUM(points_delta), 0) FROM loyalty_movements WHERE user_id = ?');
            $balanceQuery->execute([$userId]);
            if ((int) $balanceQuery->fetchColumn() < (int) $reward['points_cost']) {
                throw new DomainException('Você ainda não tem pontos suficientes para este brinde.');
            }
            $redemption = $pdo->prepare("INSERT INTO loyalty_redemptions (user_id, reward_id, status) VALUES (?, ?, 'pending')");
            $redemption->execute([$userId, $rewardId]);
            $movement = $pdo->prepare('INSERT INTO loyalty_movements (user_id, reward_id, points_delta, reason) VALUES (?, ?, ?, ?)');
            $movement->execute([$userId, $rewardId, -(int) $reward['points_cost'], 'Resgate solicitado: ' . $reward['name']]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function cancelRedemption(int $redemptionId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare("SELECT r.user_id, r.reward_id, r.status, w.points_cost FROM loyalty_redemptions r JOIN loyalty_rewards w ON w.id = r.reward_id WHERE r.id = ? FOR UPDATE");
            $find->execute([$redemptionId]);
            $row = $find->fetch();
            if (!$row || $row['status'] !== 'pending') throw new DomainException('Esse resgate não está pendente.');
            $pdo->prepare("UPDATE loyalty_redemptions SET status = 'cancelled' WHERE id = ?")->execute([$redemptionId]);
            $pdo->prepare('INSERT INTO loyalty_movements (user_id, reward_id, points_delta, reason) VALUES (?, ?, ?, ?)')->execute([(int) $row['user_id'], (int) $row['reward_id'], (int) $row['points_cost'], "Estorno do resgate #$redemptionId"]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
