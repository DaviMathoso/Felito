<?php
namespace App\Controllers;

use App\Core\Database;

// EXEMPLO COMPLETO: copie este padrão nos outros controllers.
// Controller só pega dados e escolhe a tela. Regras ficam nos Services.
class CatalogController
{
    public function index(): void
    {
        $flavors = Database::pdo()
            ->query('SELECT id, name, description, price, is_featured FROM flavors WHERE is_active = 1')
            ->fetchAll();

        view('catalog', ['flavors' => $flavors]);
    }
}
