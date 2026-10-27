<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\RelatorioService;
use App\Services\VendaService;

/** Relatórios de vendas (visão geral, mensal, produtos e clientes) e resumo mensal. */
final class ControleRelatoriosController
{
    private const TABS = ['visao' => 'Visão geral', 'mensal' => 'Por mês', 'produtos' => 'Produtos', 'clientes' => 'Clientes'];

    public function index(): void
    {
        \requireAdmin();
        $reports = new RelatorioService();
        $sales = new VendaService();
        $tab = (string) ($_GET['aba'] ?? 'visao');
        $tab = isset(self::TABS[$tab]) ? $tab : 'visao';
        $month = (string) ($_GET['mes'] ?? 'all');
        if ($month !== 'all' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = 'all';

        $data = ['title' => 'Relatórios de vendas — Felito', 'tab' => $tab, 'tabs' => self::TABS, 'month' => $month, 'months' => $sales->months()];
        if ($tab === 'visao') {
            $data['overview'] = $reports->overview();
        } elseif ($tab === 'mensal') {
            $data['monthly'] = $reports->monthly();
        } elseif ($tab === 'produtos') {
            $data['products'] = $reports->products($month);
        } else {
            $data['clients'] = $reports->topClients($month);
        }
        \view('controle_relatorios', $data);
    }
}
