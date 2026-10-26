<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;
use PDO;
use Throwable;

final class OrderService
{
    private const NEXT_STATUS = [
        'received' => ['preparing'],
        'preparing' => ['ready'],
        'ready' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    public function create(int $userId, array $cart, string $pickupDate, ?string $note): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $items = [];
            $totalCents = 0;
            ksort($cart, SORT_NUMERIC);

            $lock = $pdo->prepare('SELECT id, name, size_label, price, stock_qty FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
            foreach ($cart as $productId => $quantity) {
                $lock->execute([(int) $productId]);
                $product = $lock->fetch();
                if (!$product) {
                    throw new DomainException('Um produto do carrinho não está mais disponível. Revise seu carrinho.');
                }
                $quantity = (int) $quantity;
                if ($quantity < 1 || $quantity > 99) {
                    throw new DomainException('Revise as quantidades do carrinho e tente novamente.');
                }
                if ((int) $product['stock_qty'] < $quantity) {
                    throw new DomainException("Estoque insuficiente para {$product['name']}. Ajuste a quantidade e tente novamente.");
                }
                $priceCents = self::toCents((string) $product['price']);
                $totalCents += $priceCents * $quantity;
                $items[] = ['product' => $product, 'quantity' => $quantity, 'price_cents' => $priceCents];
            }
            if (!$items) {
                throw new DomainException('Adicione ao menos um sabor antes de finalizar.');
            }
            if ($pickupDate < date('Y-m-d')) {
                throw new DomainException('A data de retirada precisa ser hoje ou uma data futura.');
            }

            $insertOrder = $pdo->prepare("INSERT INTO orders (user_id, status, pickup_date, note, total, reserved_until) VALUES (?, 'received', ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY))");
            $insertOrder->execute([$userId, $pickupDate, $note ?: null, self::fromCents($totalCents)]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, size_label, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
            $updateStock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
            $movement = $pdo->prepare("INSERT INTO stock_movements (product_id, order_id, movement_type, quantity_delta, reason) VALUES (?, ?, 'sale', ?, ?)");

            foreach ($items as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                $insertItem->execute([$orderId, $product['id'], $product['name'], $product['size_label'], $quantity, self::fromCents($item['price_cents'])]);
                $updateStock->execute([$quantity, $product['id'], $quantity]);
                if ($updateStock->rowCount() !== 1) {
                    throw new DomainException("O estoque de {$product['name']} foi atualizado. Revise seu carrinho.");
                }
                $movement->execute([$product['id'], $orderId, -$quantity, "Venda do pedido #$orderId"]);
            }

            $pdo->commit();
            return $orderId;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function changeStatus(int $orderId, string $newStatus, int $adminId): void
    {
        if (!in_array($newStatus, ['preparing', 'ready', 'delivered'], true)) {
            throw new DomainException('Essa mudança de situação não é permitida.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $find->execute([$orderId]);
            $order = $find->fetch();
            if (!$order || !in_array($newStatus, self::NEXT_STATUS[$order['status']], true)) {
                throw new DomainException('A situação selecionada não é a próxima etapa deste pedido.');
            }
            $update = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $update->execute([$newStatus, $orderId]);
            if ($newStatus === 'delivered') {
                $this->awardLoyaltyPoints($pdo, $orderId);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function cancel(int $orderId, int $adminId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $find->execute([$orderId]);
            $order = $find->fetch();
            if (!$order || in_array($order['status'], ['delivered', 'cancelled', 'expired'], true)) {
                throw new DomainException('Este pedido já foi finalizado ou cancelado.');
            }

            $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
            $items->execute([$orderId]);
            $restock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
            $movement = $pdo->prepare("INSERT INTO stock_movements (product_id, order_id, admin_user_id, movement_type, quantity_delta, reason) VALUES (?, ?, ?, 'return', ?, ?)");
            foreach ($items->fetchAll() as $item) {
                $restock->execute([$item['quantity'], $item['product_id']]);
                $movement->execute([$item['product_id'], $orderId, $adminId, $item['quantity'], "Devolução do pedido #$orderId"]);
            }
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function expireDueReservations(): int
    {
        $pdo = Database::pdo();
        $due = $pdo->query("SELECT id FROM orders WHERE status IN ('received','preparing','ready') AND reserved_until <= UTC_TIMESTAMP() ORDER BY id LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
        $expired = 0;
        foreach ($due as $id) {
            $pdo->beginTransaction();
            try {
                $find = $pdo->prepare('SELECT user_id, status FROM orders WHERE id = ? FOR UPDATE');
                $find->execute([(int) $id]);
                $order = $find->fetch();
                if (!$order || !in_array($order['status'], ['received', 'preparing', 'ready'], true)) {
                    $pdo->commit();
                    continue;
                }
                $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id');
                $items->execute([(int) $id]);
                $restock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
                $movement = $pdo->prepare("INSERT INTO stock_movements (product_id, order_id, movement_type, quantity_delta, reason) VALUES (?, ?, 'return', ?, ?)");
                foreach ($items->fetchAll() as $item) {
                    $restock->execute([$item['quantity'], $item['product_id']]);
                    $movement->execute([$item['product_id'], (int) $id, $item['quantity'], "Reserva expirada do pedido #$id"]);
                }
                $pdo->prepare("UPDATE orders SET status = 'expired' WHERE id = ?")->execute([(int) $id]);
                $pdo->commit();
                $expired++;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }
        return $expired;
    }

    private function awardLoyaltyPoints(PDO $pdo, int $orderId): void
    {
        $order = $pdo->prepare('SELECT user_id, total FROM orders WHERE id = ?');
        $order->execute([$orderId]);
        $row = $order->fetch();
        if (!$row) return;
        $rate = (float) ($pdo->query('SELECT points_per_real FROM loyalty_settings WHERE id = 1')->fetchColumn() ?: 1);
        $points = (int) floor((float) $row['total'] * $rate);
        if ($points < 1) return;
        $insert = $pdo->prepare('INSERT IGNORE INTO loyalty_movements (user_id, order_id, points_delta, reason) VALUES (?, ?, ?, ?)');
        $insert->execute([(int) $row['user_id'], $orderId, $points, "Pontos pelo pedido #$orderId entregue"]);
    }

    public static function toCents(string $amount): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            throw new DomainException('Preço inválido no catálogo.');
        }
        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
