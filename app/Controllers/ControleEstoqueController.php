<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogoService;
use App\Services\InventoryService;
use App\Services\ProducaoService;
use DomainException;

/** Estoque por local: visão geral, produção (lotes e custos) e movimentação (transferências, entradas, histórico). */
final class ControleEstoqueController
{
    private const TABS = ['visao' => 'Visão geral', 'producao' => 'Produção', 'movimentacao' => 'Movimentação'];

    public function index(): void
    {
        $admin = \requireAdmin();
        $tab = (string) ($_GET['aba'] ?? $_POST['aba'] ?? 'visao');
        $tab = isset(self::TABS[$tab]) ? $tab : 'visao';
        $catalog = new CatalogoService();
        $stock = new InventoryService();
        $production = new ProducaoService();
        $batch = $_SESSION['ctl_batch'] ?? ['head' => [], 'lines' => []];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            $userId = (int) $admin['id'];
            $back = '/gestor/controle/estoque?aba=' . $tab;
            try {
                $action = (string) ($_POST['action'] ?? '');
                switch (true) {
                    case $action === 'adjust':
                        $stock->adjust((int) ($_POST['location_id'] ?? 0), (int) ($_POST['flavor_id'] ?? 0), (int) ($_POST['size_id'] ?? 0), (int) ($_POST['qty'] ?? -1), (string) ($_POST['note'] ?? ''), $userId);
                        \flash('success', 'Saldo ajustado.');
                        break;
                    case $action === 'loc_save':
                        $id = (int) ($_POST['location_id'] ?? 0);
                        $catalog->saveLocation($id > 0 ? $id : null, (string) ($_POST['location_name'] ?? ''), (string) ($_POST['color'] ?? ''));
                        \flash('success', 'Local salvo.');
                        break;
                    case $action === 'loc_default':
                        $catalog->setDefaultLocation((int) ($_POST['location_id'] ?? 0));
                        \flash('success', 'Local padrão atualizado.');
                        break;
                    case $action === 'loc_toggle':
                        \flash('success', $catalog->toggleLocation((int) ($_POST['location_id'] ?? 0)) ? 'Local ativado.' : 'Local desativado.');
                        break;
                    case $action === 'transfer':
                        $stock->transfer((int) ($_POST['from_id'] ?? 0), (int) ($_POST['to_id'] ?? 0), (int) ($_POST['flavor_id'] ?? 0), (int) ($_POST['size_id'] ?? 0), (int) ($_POST['qty'] ?? 0), (string) ($_POST['note'] ?? ''), $userId);
                        \flash('success', 'Transferência registrada.');
                        break;
                    case $action === 'entry':
                        $value = \App\Core\Money::parse($_POST['unit_value'] ?? '');
                        $stock->entry((int) ($_POST['location_id'] ?? 0), (int) ($_POST['flavor_id'] ?? 0), (int) ($_POST['size_id'] ?? 0), (int) ($_POST['qty'] ?? 0), $value, trim((string) ($_POST['note'] ?? '')) ?: 'Entrada manual', $userId);
                        \flash('success', 'Entrada registrada.');
                        break;
                    case $action === 'batch_update':
                        $production->update((int) ($_POST['batch_id'] ?? 0), $_POST);
                        \flash('success', 'Lote atualizado e custos recalculados.');
                        break;
                    case $action === 'batch_delete':
                        $production->delete((int) ($_POST['batch_id'] ?? 0), $userId);
                        \flash('success', 'Lote excluído e estoque revertido.');
                        break;
                    default:
                        // ações do carrinho de produção
                        $batch['head'] = [
                            'batch_no' => trim((string) ($_POST['batch_no'] ?? '')),
                            'batch_date' => (string) ($_POST['batch_date'] ?? ''),
                            'location_id' => (int) ($_POST['location_id'] ?? 0),
                            'total_cost' => trim((string) ($_POST['total_cost'] ?? '')),
                            'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200),
                        ];
                        if ($action === 'add') {
                            $line = ['flavor_id' => (int) ($_POST['flavor_id'] ?? 0), 'size_id' => (int) ($_POST['size_id'] ?? 0), 'qty' => (int) ($_POST['qty'] ?? 0), 'unit_price' => trim((string) ($_POST['unit_price'] ?? ''))];
                            if ($line['flavor_id'] < 1 || $line['size_id'] < 1 || $line['qty'] < 1 || $line['qty'] > 99999) throw new DomainException('Escolha sabor, tamanho e uma quantidade de 1 a 99999.');
                            if (count($batch['lines']) >= 80) throw new DomainException('Um lote pode ter no máximo 80 itens.');
                            $batch['lines'][] = $line;
                        } elseif (str_starts_with($action, 'remove-')) {
                            unset($batch['lines'][(int) substr($action, 7)]);
                            $batch['lines'] = array_values($batch['lines']);
                        } elseif ($action === 'clear') {
                            $batch['lines'] = [];
                        } elseif ($action === 'confirm') {
                            $production->create($batch['head'] + ['lines' => $batch['lines']], $userId);
                            unset($_SESSION['ctl_batch']);
                            \flash('success', 'Lote registrado e estoque atualizado.');
                            \redirect($back);
                        } else {
                            throw new DomainException('Ação inválida.');
                        }
                        $_SESSION['ctl_batch'] = $batch;
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
                if (isset($batch['head']) && $batch['head']) $_SESSION['ctl_batch'] = $batch;
            }
            \redirect($back);
        }

        $locationFilter = (int) ($_GET['local'] ?? 0);
        $data = [
            'title' => 'Estoque do controle — Felito',
            'tab' => $tab,
            'tabs' => self::TABS,
            'locations' => $catalog->locations(),
            'activeLocations' => $catalog->locations(true),
            'flavors' => $catalog->flavors(true),
            'sizes' => $catalog->sizes(true),
            'colors' => CatalogoService::COLORS,
        ];
        if ($tab === 'visao') {
            $data += [
                'locationFilter' => $locationFilter,
                'totals' => $stock->locationTotals(),
                'grid' => $stock->flavorGrid($locationFilter ?: null),
                'matrix' => $stock->matrix($locationFilter ?: null),
                'editingLocation' => !empty($_GET['editar_local']) ? $this->findLocation($catalog, (int) $_GET['editar_local']) : null,
            ];
        } elseif ($tab === 'producao') {
            $costs = [];
            $head = $batch['head'] ?: [];
            $qtyBySize = [];
            foreach ($batch['lines'] as $line) $qtyBySize[(int) $line['size_id']] = ($qtyBySize[(int) $line['size_id']] ?? 0) + (int) $line['qty'];
            $totalCost = \App\Core\Money::parse($head['total_cost'] ?? '') ?? 0.0;
            if ($totalCost > 0) $costs = $production->unitCosts($totalCost, $qtyBySize);
            $location = (int) ($head['location_id'] ?? 0) ?: $catalog->defaultLocationId();
            $data += [
                'batch' => $batch,
                'batchCosts' => $costs,
                'batchPrices' => $catalog->priceMap($location),
                'nextBatchNo' => $production->nextNumber(),
                'batches' => $production->list(),
                'defaultLocation' => $catalog->defaultLocationId(),
            ];
        } else {
            $data += [
                'history' => $stock->history(['type' => (string) ($_GET['tipo'] ?? ''), 'location_id' => $locationFilter], 150),
                'typeFilter' => (string) ($_GET['tipo'] ?? ''),
                'locationFilter' => $locationFilter,
                'moveLabels' => InventoryService::MOVE_LABELS,
            ];
        }
        \view('controle_estoque', $data);
    }

    private function findLocation(CatalogoService $catalog, int $id): ?array
    {
        foreach ($catalog->locations() as $location) if ((int) $location['id'] === $id) return $location;
        return null;
    }
}
