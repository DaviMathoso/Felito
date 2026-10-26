<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;
use Throwable;

final class StockService
{
    public function lowStock(): array
    {
        return Database::pdo()->query(
            'SELECT id, name, stock_qty, min_stock FROM products WHERE stock_qty <= min_stock ORDER BY stock_qty, name'
        )->fetchAll();
    }

    public function syncAlerts(): void
    {
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO low_stock_alerts (product_id, active, created_at, resolved_at)
            SELECT id, 1, UTC_TIMESTAMP(), NULL FROM products WHERE is_active = 1 AND stock_qty <= min_stock
            ON DUPLICATE KEY UPDATE created_at = IF(low_stock_alerts.active = 0, UTC_TIMESTAMP(), low_stock_alerts.created_at), resolved_at = NULL, active = 1");
        $pdo->exec("UPDATE low_stock_alerts a JOIN products p ON p.id = a.product_id
            SET a.active = 0, a.resolved_at = UTC_TIMESTAMP() WHERE a.active = 1 AND (p.is_active = 0 OR p.stock_qty > p.min_stock)");
    }

    public function activeAlerts(): array
    {
        return Database::pdo()->query('SELECT p.id, p.name, p.stock_qty, p.min_stock, a.created_at FROM low_stock_alerts a JOIN products p ON p.id = a.product_id WHERE a.active = 1 ORDER BY a.created_at, p.name')->fetchAll();
    }

    public function closeMonth(string $yearMonth): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new DomainException('Selecione um mês válido.');
        }
        $periodStart = $yearMonth . '-01 00:00:00';
        $nextStart = (new \DateTimeImmutable($periodStart, new \DateTimeZone('UTC')))->modify('first day of next month')->format('Y-m-d H:i:s');
        if ($nextStart > gmdate('Y-m-d H:i:s')) {
            throw new DomainException('O fechamento fica disponível após o término do mês.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $products = $pdo->query('SELECT id, stock_qty FROM products ORDER BY id FOR UPDATE')->fetchAll();
            $period = $pdo->prepare("SELECT
                COALESCE(SUM(CASE WHEN quantity_delta > 0 THEN quantity_delta ELSE 0 END), 0) entries_qty,
                COALESCE(SUM(CASE WHEN quantity_delta < 0 THEN -quantity_delta ELSE 0 END), 0) exits_qty
                FROM stock_movements WHERE product_id = ? AND created_at >= ? AND created_at < ?");
            $after = $pdo->prepare('SELECT COALESCE(SUM(quantity_delta), 0) FROM stock_movements WHERE product_id = ? AND created_at >= ?');
            $save = $pdo->prepare('INSERT INTO monthly_stock_closures (product_id, period_month, opening_qty, entries_qty, exits_qty, closing_qty) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($products as $product) {
                $id = (int) $product['id'];
                $period->execute([$id, $periodStart, $nextStart]);
                $totals = $period->fetch();
                $after->execute([$id, $nextStart]);
                $opening = (int) $product['stock_qty'] - (int) $after->fetchColumn() - ((int) $totals['entries_qty'] - (int) $totals['exits_qty']);
                $closing = $opening + (int) $totals['entries_qty'] - (int) $totals['exits_qty'];
                $save->execute([$id, $yearMonth, $opening, (int) $totals['entries_qty'], (int) $totals['exits_qty'], $closing]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($error instanceof \PDOException && (string) $error->getCode() === '23000') {
                throw new DomainException('Esse mês já foi fechado.');
            }
            throw $error;
        }
    }

    public function monthlyClosures(): array
    {
        return Database::pdo()->query('SELECT c.period_month, c.opening_qty, c.entries_qty, c.exits_qty, c.closing_qty, c.closed_at, p.name AS product_name FROM monthly_stock_closures c JOIN products p ON p.id = c.product_id ORDER BY c.period_month DESC, p.name LIMIT 240')->fetchAll();
    }

    public function change(int $productId, string $mode, int $quantity, string $reason, int $adminId): void
    {
        $reason = trim($reason);
        if (!in_array($mode, ['entry', 'adjustment'], true) || $quantity < 0 || $quantity > 999999 || $reason === '' || mb_strlen($reason) > 200) {
            throw new DomainException('Informe uma quantidade válida e o motivo da movimentação.');
        }
        if ($mode === 'entry' && $quantity < 1) {
            throw new DomainException('A entrada precisa ser de pelo menos uma unidade.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT stock_qty FROM products WHERE id = ? FOR UPDATE');
            $find->execute([$productId]);
            $product = $find->fetch();
            if (!$product) throw new DomainException('Produto não encontrado.');

            $current = (int) $product['stock_qty'];
            $delta = $mode === 'entry' ? $quantity : $quantity - $current;
            $type = $mode === 'entry' ? 'entry' : 'adjustment';
            $pdo->prepare('UPDATE products SET stock_qty = ? WHERE id = ?')->execute([$current + $delta, $productId]);
            if ($delta !== 0) {
                $movement = $pdo->prepare('INSERT INTO stock_movements (product_id, admin_user_id, movement_type, quantity_delta, reason) VALUES (?, ?, ?, ?, ?)');
                $movement->execute([$productId, $adminId, $type, $delta, $reason]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
