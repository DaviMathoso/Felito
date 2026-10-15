<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\OrderService;
use App\Services\StockService;
use DomainException;

final class AdminController
{
    public function dashboard(): void
    {
        $admin = \requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
            $action = (string) ($_POST['action'] ?? '');
            try {
                if (!$id || $id < 1) throw new DomainException('Pedido inválido.');
                $service = new OrderService();
                if ($action === 'cancel') $service->cancel((int) $id, (int) $admin['id']);
                elseif ($action === 'status') $service->changeStatus((int) $id, (string) ($_POST['status'] ?? ''), (int) $admin['id']);
                else throw new DomainException('Ação inválida.');
                \flash('success', $action === 'cancel' ? "Pedido #$id cancelado e estoque devolvido." : "Situação do pedido #$id atualizada.");
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect('/gestor' . (!empty($_POST['filter']) ? '?status=' . rawurlencode((string) $_POST['filter']) : ''));
        }

        $filter = (string) ($_GET['status'] ?? '');
        $statuses = ['received', 'preparing', 'ready', 'delivered', 'cancelled'];
        if (!in_array($filter, $statuses, true)) $filter = '';
        $sql = 'SELECT o.id, o.status, o.pickup_date, o.note, o.total, o.created_at,
                       u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
                FROM orders o JOIN users u ON u.id = o.user_id';
        if ($filter !== '') $sql .= ' WHERE o.status = ?';
        $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 100';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($filter !== '' ? [$filter] : []);
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
        $stats = Database::pdo()->query(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'received') AS received,
                    SUM(status IN ('received', 'preparing', 'ready')) AS active,
                    SUM(status = 'ready') AS ready
             FROM orders"
        )->fetch();
        \view('admin_orders', ['orders' => $orders, 'filter' => $filter, 'stats' => $stats]);
    }

    public function products(): void
    {
        \requireAdmin();
        $pdo = Database::pdo();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'deactivate') {
                $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || $id < 1) \failForm('/gestor/produtos', 'Produto inválido.');
                $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
                \flash('success', 'Produto ocultado do catálogo; o histórico foi preservado.');
                \redirect('/gestor/produtos');
            }
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $productId = ($id && $id > 0) ? (int) $id : null;
            try {
                $saved = (new \App\Services\ProductService())->save($_POST, (int) \currentUser()['id'], $productId);
                \flash('success', $productId ? 'Produto atualizado.' : 'Produto cadastrado.');
                \redirect('/gestor/produtos?editar=' . $saved);
            } catch (DomainException $error) {
                \failForm($productId ? '/gestor/produtos?editar=' . $productId : '/gestor/produtos', $error->getMessage());
            }
        }

        $editing = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
        $product = null;
        if ($editing && $editing > 0) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$editing]);
            $product = $stmt->fetch() ?: null;
        }
        $products = $pdo->query('SELECT id, name, size_label, price, stock_qty, min_stock, is_active, is_featured FROM products ORDER BY is_active DESC, name')->fetchAll();
        \view('admin_products', ['products' => $products, 'product' => $product]);
    }

    public function stock(): void
    {
        $admin = \requireAdmin();
        $pdo = Database::pdo();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \verifyCsrf();
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            try {
                if (!$productId || $productId < 1 || $quantity === false) throw new DomainException('Informe um produto e uma quantidade válida.');
                (new StockService())->change((int) $productId, (string) ($_POST['mode'] ?? ''), (int) $quantity, (string) ($_POST['reason'] ?? ''), (int) $admin['id']);
                \flash('success', 'Estoque atualizado e movimentação registrada.');
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect('/gestor/estoque');
        }
        $products = $pdo->query('SELECT id, name, size_label, stock_qty, min_stock, is_active FROM products ORDER BY name')->fetchAll();
        $movements = $pdo->query(
            'SELECT sm.id, sm.movement_type, sm.quantity_delta, sm.reason, sm.created_at,
                    p.name AS product_name, u.name AS admin_name, sm.order_id
             FROM stock_movements sm JOIN products p ON p.id = sm.product_id
             LEFT JOIN users u ON u.id = sm.admin_user_id
             ORDER BY sm.created_at DESC, sm.id DESC LIMIT 60'
        )->fetchAll();
        \view('admin_stock', ['products' => $products, 'movements' => $movements, 'lowStock' => (new StockService())->lowStock()]);
    }
}
