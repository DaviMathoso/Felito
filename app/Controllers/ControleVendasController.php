<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csv;
use App\Services\CatalogoService;
use App\Services\ClienteService;
use App\Services\PlanilhaService;
use App\Services\VendaService;
use DomainException;

/** Controle de vendas (gestor): lançamentos, novo pedido, edição, exportação e importação. */
final class ControleVendasController
{
    public function index(): void
    {
        $admin = \requireAdmin();
        $sales = new VendaService();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            $back = '/gestor/controle?' . http_build_query($this->filterFrom($_POST));
            try {
                $id = (int) ($_POST['sale_id'] ?? 0);
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'status') {
                    $status = (string) ($_POST['status'] ?? '');
                    $sales->changeStatus($id, $status, (int) $admin['id']);
                    \flash('success', 'Situação alterada para "' . VendaService::label($status) . '".');
                } elseif ($action === 'delete') {
                    $sales->deleteLine($id, (int) $admin['id']);
                    \flash('success', 'Lançamento excluído e estoque ajustado.');
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect($back);
        }

        $filter = $this->filterFrom($_GET);
        $months = $sales->months();
        if ($filter['month'] !== 'all' && !in_array($filter['month'], $months, true)) {
            $months[] = $filter['month'];
            rsort($months);
        }
        \view('controle_vendas', [
            'title' => 'Controle de vendas — Felito',
            'filter' => $filter,
            'rows' => $sales->listLines($filter),
            'kpis' => $sales->kpis($filter['month']),
            'months' => $months,
            'locations' => (new CatalogoService())->locations(true),
            'summary' => $filter['month'] !== 'all' ? $sales->monthSummary($filter['month']) : null,
        ]);
    }

    public function create(): void
    {
        $admin = \requireAdmin();
        $catalog = new CatalogoService();
        $cart = $_SESSION['ctl_cart'] ?? ['head' => [], 'lines' => []];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            $cart['head'] = [
                'client_name' => mb_substr(trim((string) ($_POST['client_name'] ?? '')), 0, 120),
                'client_phone' => mb_substr(trim((string) ($_POST['client_phone'] ?? '')), 0, 30),
                'location_id' => (int) ($_POST['location_id'] ?? 0),
                'sale_date' => (string) ($_POST['sale_date'] ?? ''),
            ];
            $action = (string) ($_POST['action'] ?? '');
            try {
                if ($action === 'add') {
                    $line = [
                        'flavor_id' => (int) ($_POST['flavor_id'] ?? 0), 'size_id' => (int) ($_POST['size_id'] ?? 0),
                        'qty' => (int) ($_POST['qty'] ?? 0), 'unit_price' => trim((string) ($_POST['unit_price'] ?? '')),
                        'status' => (string) ($_POST['status'] ?? 'a_fazer'),
                    ];
                    (new VendaService())->normalizeLine($line, $cart['head']['location_id'] ?: $catalog->defaultLocationId());
                    if (count($cart['lines']) >= 60) throw new DomainException('Um pedido pode ter no máximo 60 itens.');
                    $cart['lines'][] = $line;
                    \flash('success', 'Item adicionado ao pedido.');
                } elseif (str_starts_with($action, 'remove-')) {
                    unset($cart['lines'][(int) substr($action, 7)]);
                    $cart['lines'] = array_values($cart['lines']);
                } elseif ($action === 'clear') {
                    $cart['lines'] = [];
                } elseif ($action === 'finalize') {
                    $orderNo = (new VendaService())->createOrder($cart['head'] + ['lines' => $cart['lines']], (int) $admin['id']);
                    unset($_SESSION['ctl_cart']);
                    \flash('success', "Pedido #$orderNo lançado com sucesso.");
                    \redirect('/gestor/controle?month=' . urlencode(substr((string) $cart['head']['sale_date'], 0, 7)));
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            $_SESSION['ctl_cart'] = $cart;
            \redirect('/gestor/controle/novo');
        }

        $locations = $catalog->locations(true);
        $prices = [];
        foreach ($locations as $location) $prices[(int) $location['id']] = $catalog->priceMap((int) $location['id']);
        \view('controle_novo', [
            'title' => 'Novo pedido — Felito',
            'cart' => $cart,
            'flavors' => $catalog->flavors(true),
            'sizes' => $catalog->sizes(true),
            'locations' => $locations,
            'prices' => $prices,
            'defaultLocation' => $catalog->defaultLocationId(),
            'clientNames' => array_column((new ClienteService())->list(), 'name'),
        ]);
    }

    public function edit(): void
    {
        $admin = \requireAdmin();
        $sales = new VendaService();
        $catalog = new CatalogoService();
        $id = (int) ($_GET['id'] ?? $_POST['sale_id'] ?? 0);
        $sale = $sales->find($id);
        if (!$sale) {
            \flash('error', 'Lançamento não encontrado.');
            \redirect('/gestor/controle');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            try {
                $sales->updateLine($id, $_POST, (int) $admin['id']);
                \flash('success', 'Lançamento atualizado.');
                \redirect('/gestor/controle?month=' . urlencode(substr((string) ($_POST['sale_date'] ?? ''), 0, 7)));
            } catch (DomainException $error) {
                \failForm('/gestor/controle/editar?id=' . $id, $error->getMessage(), $_POST);
            }
        }

        \view('controle_editar', [
            'title' => 'Editar lançamento — Felito',
            'sale' => $sale,
            'old' => \consumeOldInput(),
            'flavors' => $catalog->flavors(),
            'sizes' => $catalog->sizes(),
            'locations' => $catalog->locations(),
        ]);
    }

    public function export(): void
    {
        \requireAdmin();
        $planilha = new PlanilhaService();
        if (($_GET['tipo'] ?? 'vendas') === 'clientes') {
            Csv::download('felito-clientes-' . date('Ymd') . '.csv', $planilha->clientRows());
        }
        Csv::download('felito-vendas-' . date('Ymd') . '.csv', $planilha->salesRows($this->filterFrom($_GET)));
    }

    public function import(): void
    {
        $admin = \requireAdmin();
        \verifyCsrf();
        try {
            $file = $_FILES['file'] ?? null;
            if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new DomainException('Escolha um arquivo .csv ou .json para importar.');
            }
            if ((int) $file['size'] > 2 * 1024 * 1024) throw new DomainException('O arquivo deve ter até 2 MB.');
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'json'], true)) throw new DomainException('Use um arquivo .csv ou .json.');
            $content = (string) file_get_contents((string) $file['tmp_name']);

            $planilha = new PlanilhaService();
            $result = ($_POST['tipo'] ?? '') === 'clientes'
                ? $planilha->importClients($content, $extension)
                : $planilha->importSales($content, $extension, (int) ($_POST['location_id'] ?? 0), !empty($_POST['adjust_stock']), (int) $admin['id']);
            \flash('success', "Importação concluída: {$result['imported']} registro(s) importado(s) e {$result['skipped']} ignorado(s).");
            foreach ($result['notes'] as $note) \flash('info', $note);
        } catch (DomainException $error) {
            \flash('error', $error->getMessage());
        }
        \redirect('/gestor/controle?month=all');
    }

    /** Lê e valida os filtros da tela (funciona com $_GET e com os campos ocultos do $_POST). */
    private function filterFrom(array $source): array
    {
        $month = array_key_exists('month', $source) ? (string) $source['month'] : date('Y-m');
        if ($month !== 'all' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
        $status = (string) ($source['status'] ?? '');
        $sort = (string) ($source['sort'] ?? '');
        return [
            'month' => $month,
            'status' => isset(VendaService::STATUSES[$status]) ? $status : '',
            'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 80),
            'sort' => isset(VendaService::SORTS[$sort]) ? $sort : 'pedido-desc',
        ];
    }
}
