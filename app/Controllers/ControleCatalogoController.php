<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogoService;
use DomainException;

/** Sabores, tamanhos e tabela de preços (global ou por local de venda) usados pelo controle. */
final class ControleCatalogoController
{
    private const TABS = ['sabores' => 'Sabores', 'tamanhos' => 'Tamanhos', 'precos' => 'Preços'];

    public function index(): void
    {
        \requireAdmin();
        $catalog = new CatalogoService();
        $tab = (string) ($_GET['aba'] ?? $_POST['aba'] ?? 'sabores');
        $tab = isset(self::TABS[$tab]) ? $tab : 'sabores';
        $priceLocation = max(0, (int) ($_GET['local'] ?? $_POST['price_location'] ?? 0));

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            $back = '/gestor/controle/catalogo?aba=' . $tab . ($tab === 'precos' ? '&local=' . $priceLocation : '');
            try {
                $action = (string) ($_POST['action'] ?? '');
                $id = (int) ($_POST['id'] ?? 0);
                if ($action === 'flavor_save') {
                    $catalog->saveFlavor($id > 0 ? $id : null, (string) ($_POST['flavor_name'] ?? ''), !empty($_POST['is_active']) || $id === 0);
                    \flash('success', 'Sabor salvo.');
                } elseif ($action === 'flavor_toggle') {
                    $catalog->toggleActive('ctl_flavors', $id);
                    \flash('success', 'Sabor atualizado.');
                } elseif ($action === 'size_save') {
                    $catalog->saveSize($id > 0 ? $id : null, (string) ($_POST['size_name'] ?? ''), (int) ($_POST['volume_ml'] ?? 0), $_POST['packaging_cost'] ?? '', !empty($_POST['is_active']) || $id === 0);
                    \flash('success', 'Tamanho salvo.');
                } elseif ($action === 'size_toggle') {
                    $catalog->toggleActive('ctl_sizes', $id);
                    \flash('success', 'Tamanho atualizado.');
                } elseif ($action === 'prices_save') {
                    $saved = $catalog->savePrices($priceLocation, (array) ($_POST['price'] ?? []));
                    \flash('success', "Tabela de preços salva ($saved preço(s) definido(s)).");
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect($back);
        }

        $data = ['title' => 'Produtos do controle — Felito', 'tab' => $tab, 'tabs' => self::TABS];
        if ($tab === 'sabores') {
            $flavors = $catalog->flavors();
            $editId = (int) ($_GET['editar'] ?? 0);
            $data += ['flavors' => $flavors, 'editing' => $editId ? ($this->pick($flavors, $editId)) : null];
        } elseif ($tab === 'tamanhos') {
            $sizes = $catalog->sizes();
            $editId = (int) ($_GET['editar'] ?? 0);
            $data += ['sizes' => $sizes, 'editing' => $editId ? ($this->pick($sizes, $editId)) : null];
        } else {
            $data += [
                'flavors' => $catalog->flavors(true),
                'sizes' => $catalog->sizes(true),
                'locations' => $catalog->locations(true),
                'priceLocation' => $priceLocation,
                'own' => $catalog->ownPrices($priceLocation),
                'global' => $catalog->ownPrices(0),
            ];
        }
        \view('controle_catalogo', $data);
    }

    private function pick(array $rows, int $id): ?array
    {
        foreach ($rows as $row) if ((int) $row['id'] === $id) return $row;
        return null;
    }
}
