<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Transaction;
use DomainException;

/** Visão financeira por cliente: o que falta receber e o que falta entregar. */
final class FinanceiroService
{
    public const TABS = ['receber' => 'A receber', 'entregar' => 'A entregar', 'todas' => 'Todos em aberto'];

    /** @return array{clients:array,total_receive:float,total_units:int} */
    public function groups(string $tab, string $search = ''): array
    {
        $tab = isset(self::TABS[$tab]) ? $tab : 'receber';
        $where = [$this->tabCondition($tab)];
        $params = [];
        if (trim($search) !== '') {
            $where[] = '(c.name LIKE ? OR c.phone LIKE ?)';
            $like = '%' . addcslashes(trim($search), '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $stmt = Database::pdo()->prepare(
            'SELECT s.id, s.order_no, s.sale_date, s.qty, s.unit_price, s.status, s.client_id,
                    c.name AS client_name, c.phone AS client_phone, f.name AS flavor_name, z.name AS size_name, l.name AS location_name
             FROM ctl_sales s
             JOIN ctl_clients c ON c.id = s.client_id
             JOIN ctl_flavors f ON f.id = s.flavor_id
             JOIN ctl_sizes z ON z.id = s.size_id
             JOIN ctl_locations l ON l.id = s.location_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name, s.order_no, s.id'
        );
        $stmt->execute($params);

        $clients = [];
        foreach ($stmt->fetchAll() as $line) {
            $id = (int) $line['client_id'];
            $clients[$id] ??= ['id' => $id, 'name' => $line['client_name'], 'phone' => $line['client_phone'], 'lines' => [], 'to_receive' => 0.0, 'to_deliver' => 0];
            $clients[$id]['lines'][] = $line;
            $value = (float) $line['qty'] * (float) $line['unit_price'];
            if (in_array($line['status'], VendaService::OPEN, true)) $clients[$id]['to_receive'] += $value;
            if (in_array($line['status'], VendaService::PENDING, true)) $clients[$id]['to_deliver'] += (int) $line['qty'];
        }
        usort($clients, static fn($a, $b) => $b['to_receive'] <=> $a['to_receive'] ?: strcasecmp((string) $a['name'], (string) $b['name']));
        return [
            'clients' => $clients,
            'total_receive' => array_sum(array_column($clients, 'to_receive')),
            'total_units' => array_sum(array_column($clients, 'to_deliver')),
        ];
    }

    /** Muda a situação de todas as linhas do cliente que aparecem na aba escolhida. Devolve quantas linhas mudaram. */
    public function bulk(int $clientId, string $tab, string $status, int $userId): int
    {
        if (!isset(VendaService::STATUSES[$status])) throw new DomainException('Situação inválida.');
        $tab = isset(self::TABS[$tab]) ? $tab : 'receber';
        return (int) Transaction::run(function () use ($clientId, $tab, $status, $userId): int {
            $stmt = Database::pdo()->prepare('SELECT s.id FROM ctl_sales s WHERE s.client_id = ? AND ' . $this->tabCondition($tab) . ' ORDER BY s.id');
            $stmt->execute([$clientId]);
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $sales = new VendaService();
            foreach ($ids as $id) $sales->changeStatus((int) $id, $status, $userId);
            return count($ids);
        });
    }

    private function tabCondition(string $tab): string
    {
        return match ($tab) {
            'entregar' => "s.status IN ('a_fazer', 'a_entregar')",
            'todas' => "s.status IN ('a_fazer', 'a_entregar', 'entregue')",
            default => "s.status IN ('a_fazer', 'a_entregar', 'entregue') AND s.qty * s.unit_price > 0",
        };
    }
}
