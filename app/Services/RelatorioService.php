<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Relatórios de vendas. Contam só vendas efetivas: cancelados e resgates ficam de fora. */
final class RelatorioService
{
    private const VALID = "s.status NOT IN ('cancelado', 'resgate')";

    public function overview(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            "SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS ym, SUM(s.qty) AS units, SUM(s.qty * s.unit_price) AS revenue, COUNT(DISTINCT s.order_no) AS orders
             FROM ctl_sales s WHERE " . self::VALID . ' GROUP BY ym'
        )->fetchAll();
        $byMonth = [];
        foreach ($rows as $row) $byMonth[$row['ym']] = ['units' => (int) $row['units'], 'revenue' => (float) $row['revenue'], 'orders' => (int) $row['orders']];

        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
            $series[] = ['ym' => $ym] + ($byMonth[$ym] ?? ['units' => 0, 'revenue' => 0.0, 'orders' => 0]);
        }
        $revenue = array_sum(array_column($byMonth, 'revenue'));
        $orders = (int) $pdo->query('SELECT COUNT(DISTINCT s.order_no) FROM ctl_sales s WHERE ' . self::VALID)->fetchColumn();
        $current = $byMonth[date('Y-m')] ?? ['units' => 0, 'revenue' => 0.0, 'orders' => 0];
        $previous = $byMonth[date('Y-m', strtotime(date('Y-m-01') . ' -1 month'))] ?? ['units' => 0, 'revenue' => 0.0, 'orders' => 0];
        $variation = $previous['revenue'] > 0 ? ($current['revenue'] - $previous['revenue']) / $previous['revenue'] * 100 : null;

        return [
            'revenue' => (float) $revenue,
            'units' => array_sum(array_column($byMonth, 'units')),
            'orders' => $orders,
            'ticket' => $orders > 0 ? $revenue / $orders : 0.0,
            'current' => $current,
            'previous' => $previous,
            'variation' => $variation,
            'series' => $series,
            'status_counts' => $pdo->query('SELECT status, COUNT(*) AS n FROM ctl_sales GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR),
        ];
    }

    public function monthly(): array
    {
        $pdo = Database::pdo();
        $months = $pdo->query(
            "SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS ym, SUM(s.qty) AS units, SUM(s.qty * s.unit_price) AS revenue, COUNT(DISTINCT s.order_no) AS orders
             FROM ctl_sales s WHERE " . self::VALID . ' GROUP BY ym ORDER BY ym DESC'
        )->fetchAll();
        $bySize = [];
        foreach ($pdo->query(
            "SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS ym, z.name AS size, SUM(s.qty) AS units
             FROM ctl_sales s JOIN ctl_sizes z ON z.id = s.size_id WHERE " . self::VALID . ' GROUP BY ym, z.id, z.name'
        )->fetchAll() as $row) $bySize[$row['ym']][$row['size']] = (int) $row['units'];
        foreach ($months as &$month) {
            $month['ticket'] = (int) $month['orders'] > 0 ? (float) $month['revenue'] / (int) $month['orders'] : 0.0;
            $month['by_size'] = $bySize[$month['ym']] ?? [];
        }
        unset($month);
        return $months;
    }

    /** Ranking por sabor e por tamanho, opcionalmente dentro de um mês (YYYY-MM). */
    public function products(string $month = 'all'): array
    {
        [$period, $params] = $this->period($month);
        $pdo = Database::pdo();
        $byFlavor = $pdo->prepare(
            'SELECT f.name, SUM(s.qty) AS units, SUM(s.qty * s.unit_price) AS revenue
             FROM ctl_sales s JOIN ctl_flavors f ON f.id = s.flavor_id WHERE ' . self::VALID . $period . ' GROUP BY f.id, f.name ORDER BY revenue DESC, units DESC'
        );
        $byFlavor->execute($params);
        $bySize = $pdo->prepare(
            'SELECT z.name, SUM(s.qty) AS units, SUM(s.qty * s.unit_price) AS revenue
             FROM ctl_sales s JOIN ctl_sizes z ON z.id = s.size_id WHERE ' . self::VALID . $period . ' GROUP BY z.id, z.name, z.volume_ml ORDER BY z.volume_ml'
        );
        $bySize->execute($params);
        return ['flavors' => $byFlavor->fetchAll(), 'sizes' => $bySize->fetchAll()];
    }

    public function topClients(string $month = 'all', int $limit = 30): array
    {
        [$period, $params] = $this->period($month);
        $stmt = Database::pdo()->prepare(
            'SELECT c.name, SUM(s.qty) AS units, SUM(s.qty * s.unit_price) AS revenue, COUNT(DISTINCT s.order_no) AS orders
             FROM ctl_sales s JOIN ctl_clients c ON c.id = s.client_id WHERE ' . self::VALID . $period
             . ' GROUP BY c.id, c.name ORDER BY revenue DESC LIMIT ' . max(1, min($limit, 200))
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function period(string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) return ['', []];
        $start = $month . '-01';
        return [' AND s.sale_date >= ? AND s.sale_date < ?', [$start, date('Y-m-d', strtotime($start . ' +1 month'))]];
    }
}
