<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ClienteService;
use App\Services\FidelidadeService;
use App\Services\FinanceiroService;
use App\Services\VendaService;
use DomainException;

/** Clientes, financeiro (a receber / a entregar) e programa de fidelidade. */
final class ControleClientesController
{
    public function index(): void
    {
        \requireAdmin();
        $clients = new ClienteService();
        $search = mb_substr(trim((string) ($_GET['q'] ?? $_POST['q'] ?? '')), 0, 80);
        $back = '/gestor/controle/clientes' . ($search !== '' ? '?q=' . urlencode($search) : '');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            try {
                $action = (string) ($_POST['action'] ?? '');
                $id = (int) ($_POST['client_id'] ?? 0);
                if ($action === 'save') {
                    $clients->save($id > 0 ? $id : null, (string) ($_POST['client_name'] ?? ''), (string) ($_POST['client_phone'] ?? ''));
                    \flash('success', 'Cliente salvo.');
                } elseif ($action === 'delete') {
                    $clients->delete($id);
                    \flash('success', 'Cliente removido.');
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect($back);
        }

        $account = null;
        if (!empty($_GET['conta'])) {
            $client = $clients->find((int) $_GET['conta']);
            if ($client) {
                $message = $clients->accountMessage((int) $client['id']);
                $account = ['client' => $client, 'message' => $message, 'whatsapp' => $message ? ClienteService::whatsappLink((string) $client['phone'], $message) : null];
            }
        }
        \view('controle_clientes', [
            'title' => 'Clientes — Felito',
            'search' => $search,
            'clients' => $clients->list($search),
            'editing' => !empty($_GET['editar']) ? $clients->find((int) $_GET['editar']) : null,
            'account' => $account,
        ]);
    }

    public function finance(): void
    {
        $admin = \requireAdmin();
        $finance = new FinanceiroService();
        $tab = (string) ($_GET['aba'] ?? $_POST['aba'] ?? 'receber');
        $tab = isset(FinanceiroService::TABS[$tab]) ? $tab : 'receber';
        $search = mb_substr(trim((string) ($_GET['q'] ?? $_POST['q'] ?? '')), 0, 80);
        $back = '/gestor/controle/financeiro?' . http_build_query(['aba' => $tab, 'q' => $search]);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            try {
                $action = (string) ($_POST['action'] ?? '');
                $status = (string) ($_POST['status'] ?? '');
                if ($action === 'bulk') {
                    $count = $finance->bulk((int) ($_POST['client_id'] ?? 0), $tab, $status, (int) $admin['id']);
                    \flash('success', "$count lançamento(s) alterado(s) para \"" . VendaService::label($status) . '".');
                } elseif ($action === 'line') {
                    (new VendaService())->changeStatus((int) ($_POST['sale_id'] ?? 0), $status, (int) $admin['id']);
                    \flash('success', 'Situação alterada.');
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect($back);
        }

        $data = $finance->groups($tab, $search);
        $clients = new ClienteService();
        foreach ($data['clients'] as &$client) {
            $message = $client['to_receive'] > 0 ? $clients->accountMessage((int) $client['id']) : null;
            $client['message'] = $message;
            $client['whatsapp'] = $message ? ClienteService::whatsappLink((string) $client['phone'], $message) : ClienteService::whatsappLink((string) $client['phone']);
        }
        unset($client);
        \view('controle_financeiro', ['title' => 'Financeiro — Felito', 'tab' => $tab, 'search' => $search, 'finance' => $data]);
    }

    public function fidelity(): void
    {
        \requireAdmin();
        $loyalty = new FidelidadeService();
        $search = mb_substr(trim((string) ($_GET['q'] ?? $_POST['q'] ?? '')), 0, 80);
        $sort = (string) ($_GET['ordem'] ?? $_POST['ordem'] ?? 'stamps-desc');
        $sort = isset(FidelidadeService::SORTS[$sort]) ? $sort : 'stamps-desc';
        $clientId = (int) ($_GET['cliente'] ?? $_POST['cliente'] ?? 0);
        $query = ['q' => $search, 'ordem' => $sort];
        if ($clientId > 0) $query['cliente'] = $clientId;
        $back = '/gestor/controle/fidelidade?' . http_build_query($query);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verifyCsrf();
            try {
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'config') {
                    $loyalty->saveConfig($_POST);
                    \flash('success', 'Regras do programa atualizadas.');
                } elseif ($action === 'redeem') {
                    $loyalty->redeem((int) ($_POST['gift_id'] ?? 0));
                    \flash('success', 'Brinde marcado como resgatado.');
                } elseif ($action === 'undo') {
                    $loyalty->undoRedeem((int) ($_POST['gift_id'] ?? 0));
                    \flash('success', 'Resgate desfeito.');
                } else {
                    throw new DomainException('Ação inválida.');
                }
            } catch (DomainException $error) {
                \flash('error', $error->getMessage());
            }
            \redirect($back);
        }

        $detail = null;
        if ($clientId > 0) {
            $rows = $loyalty->standings('', $sort, $clientId);
            if ($rows) $detail = ['summary' => $rows[0], 'gifts' => $loyalty->gifts($clientId), 'redemptions' => $loyalty->redemptions($clientId)];
        }
        \view('controle_fidelidade', [
            'title' => 'Fidelidade — Felito',
            'config' => $loyalty->config(),
            'standings' => $loyalty->standings($search, $sort),
            'search' => $search,
            'sort' => $sort,
            'detail' => $detail,
        ]);
    }
}
