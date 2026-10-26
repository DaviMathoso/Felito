<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;

final class ProductService
{
    public function save(array $input, int $adminId, ?int $productId = null): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $imagePath = trim((string) ($input['image_path'] ?? ''));
        $size = trim((string) ($input['size_label'] ?? ''));
        $price = trim((string) ($input['price'] ?? ''));
        $minimum = filter_var($input['min_stock'] ?? null, FILTER_VALIDATE_INT);
        $featured = isset($input['is_featured']) ? 1 : 0;
        $active = isset($input['is_active']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 120 || $description === '' || mb_strlen($description) > 2000 || $size === '' || mb_strlen($size) > 80) {
            throw new DomainException('Preencha nome, descrição e apresentação do produto.');
        }
        if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $price) || (float) $price <= 0) {
            throw new DomainException('Informe um preço válido, por exemplo 32,00.');
        }
        if ($imagePath !== '' && !preg_match('#^/assets/products/[A-Za-z0-9._-]+\.(?:svg|png|jpe?g|webp)$#i', $imagePath)) {
            throw new DomainException('Use uma imagem dentro de /assets/products/ (SVG, PNG, JPG ou WebP).');
        }
        if ($minimum === false || $minimum < 0 || $minimum > 999999) {
            throw new DomainException('O estoque mínimo precisa ser um número igual ou maior que zero.');
        }

        $pdo = Database::pdo();
        if ($productId !== null) {
            $stmt = $pdo->prepare('UPDATE products SET name = ?, description = ?, image_path = ?, size_label = ?, price = ?, min_stock = ?, is_featured = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, $description, $imagePath ?: null, $size, $price, $minimum, $featured, $active, $productId]);
            if ($stmt->rowCount() === 0 && !$this->exists($productId)) {
                throw new DomainException('Produto não encontrado.');
            }
            return $productId;
        }

        $initialStock = filter_var($input['stock_qty'] ?? '0', FILTER_VALIDATE_INT);
        if ($initialStock === false || $initialStock < 0 || $initialStock > 999999) {
            throw new DomainException('Informe um estoque inicial válido.');
        }
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT INTO products (name, description, image_path, size_label, price, stock_qty, min_stock, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$name, $description, $imagePath ?: null, $size, $price, $initialStock, $minimum, $featured, $active]);
            $id = (int) $pdo->lastInsertId();
            if ($initialStock > 0) {
                $movement = $pdo->prepare("INSERT INTO stock_movements (product_id, admin_user_id, movement_type, quantity_delta, reason) VALUES (?, ?, 'initial', ?, 'Estoque inicial do cadastro')");
                $movement->execute([$id, $adminId, $initialStock]);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private function exists(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM products WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
