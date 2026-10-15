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
