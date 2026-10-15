<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class CatalogController
{
    public function index(): void
    {
        $products = Database::pdo()->query(
            'SELECT id, name, description, size_label, price, stock_qty, min_stock, is_featured
             FROM products WHERE is_active = 1 ORDER BY is_featured DESC, name'
        )->fetchAll();
        view('catalog', ['products' => $products]);
    }
}
