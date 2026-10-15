<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\OrderService;
use DomainException;

final class OrderController
{
    public function cart(): void
    {
        $cart = $_SESSION['cart'] ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $action = (string) ($_POST['action'] ?? 'update');
            if ($action === 'add') {
                $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
                $product = null;
                if ($id !== false && $id > 0) {
                    $find = Database::pdo()->prepare('SELECT id, name, stock_qty FROM products WHERE id = ? AND is_active = 1');
                    $find->execute([$id]);
                    $product = $find->fetch();
                }
                if (!$product || (int) $product['stock_qty'] < 1) {
                    \flash('error', 'Este sabor está esgotado ou não está mais disponível.');
                } else {
                    $cart[$id] = min(99, (int) ($cart[$id] ?? 0) + 1);
                    \flash('success', $product['name'] . ' foi adicionado ao carrinho.');
                }
            } elseif ($action === 'remove') {
                $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
                if ($id !== false) unset($cart[$id]);
                \flash('success', 'Item removido do carrinho.');
            } elseif ($action === 'update') {
                $quantities = $_POST['items'] ?? [];
                if (is_array($quantities)) {
                    foreach ($quantities as $id => $quantity) {
                        if (!ctype_digit((string) $id) || filter_var($quantity, FILTER_VALIDATE_INT) === false) continue;
                        $id = (int) $id;
                        $quantity = (int) $quantity;
                        if ($quantity < 1) unset($cart[$id]);
                        else $cart[$id] = min(99, $quantity);
                    }
                }
                \flash('success', 'Quantidades atualizadas.');
            }
            $_SESSION['cart'] = $cart;
            \redirect('/carrinho');
        }

        $products = [];
        if ($cart) {
            $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn(int $id): bool => $id > 0));
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = Database::pdo()->prepare("SELECT id, name, size_label, price, stock_qty, is_active FROM products WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $products = $stmt->fetchAll();
            }
        }
        \view('cart', ['products' => $products, 'cart' => $cart]);
    }

    public function checkout(): void
    {
        $user = \requireLogin();
        $cart = $_SESSION['cart'] ?? [];
        if (!$cart) {
            \flash('info', 'Seu carrinho está vazio. Escolha um sabor para começar.');
            \redirect('/');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $date = trim((string) ($_POST['pickup_date'] ?? ''));
            $note = trim((string) ($_POST['note'] ?? ''));
            $old = ['pickup_date' => $date, 'note' => $note];
            $validDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$validDate || $validDate->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
                \failForm('/checkout', 'Escolha uma data válida, a partir de hoje.', $old);
            }
            if (mb_strlen($note) > 500) {
                \failForm('/checkout', 'A observação pode ter até 500 caracteres.', $old);
            }
            try {
                $orderId = (new OrderService())->create((int) $user['id'], $cart, $date, $note);
                unset($_SESSION['cart']);
                \redirect('/pedidos?novo=' . $orderId);
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
                \redirect('/carrinho');
            }
        }

        $ids = array_values(array_map('intval', array_keys($cart)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT id, name, size_label, price, stock_qty, is_active FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        \view('checkout', ['products' => $stmt->fetchAll(), 'cart' => $cart, 'old' => \consumeOldInput()]);
    }

    public function index(): void
    {
        $user = \requireLogin();
        $stmt = Database::pdo()->prepare(
            'SELECT id, status, pickup_date, note, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$user['id']]);
        $orders = $stmt->fetchAll();
        if ($orders) {
            $ids = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $items = Database::pdo()->prepare("SELECT order_id, product_name, size_label, quantity, unit_price FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
            $items->execute($ids);
            $grouped = [];
            foreach ($items as $item) $grouped[$item['order_id']][] = $item;
            foreach ($orders as &$order) $order['items'] = $grouped[$order['id']] ?? [];
            unset($order);
        }
        \view('orders', ['orders' => $orders, 'newOrderId' => (int) ($_GET['novo'] ?? 0)]);
    }
}
