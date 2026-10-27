<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use DomainException;
use PDOException;

/**
 * Programa de selos: cada pedido gera floor(total do pedido ÷ valor por selo) selos dentro do período configurado.
 * A cada "selos por brinde" acumulados nasce um voucher. Linhas em "Resgate" abatem selos; cancelados não pontuam.
 */
final class FidelidadeService
{
    public const PERIODS = ['days' => 'Últimos X dias', 'month' => 'Este mês', 'year' => 'Este ano', 'all' => 'Sem prazo (todo o histórico)'];
    public const SORTS = ['name-asc' => 'Nome A→Z', 'name-desc' => 'Nome Z→A', 'stamps-desc' => 'Selos (maior)', 'stamps-asc' => 'Selos (menor)', 'gifts-pending' => 'Prêmios pendentes'];

    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
    }

    public function config(): array
    {
        $type = $this->settings->get('fid_period_type', 'days');
        return [
            'stamp_value' => max(1.0, (float) $this->settings->get('fid_stamp_value', '15')),
            'stamps_per_gift' => max(1, (int) $this->settings->get('fid_stamps_per_gift', '10')),
            'period_type' => isset(self::PERIODS[$type]) ? $type : 'days',
            'window_days' => max(1, (int) $this->settings->get('fid_window_days', '90')),
        ];
    }

    public function saveConfig(array $in): void
    {
        $value = Money::parse($in['stamp_value'] ?? '');
        $perGift = filter_var($in['stamps_per_gift'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $days = filter_var($in['window_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        $type = (string) ($in['period_type'] ?? '');
        if ($value === null || $value < 1) throw new DomainException('O valor por selo precisa ser de pelo menos R$ 1,00.');
        if ($perGift === false) throw new DomainException('Informe quantos selos valem um brinde (de 1 a 1000).');
        if (!isset(self::PERIODS[$type])) throw new DomainException('Escolha o período considerado.');
        if ($type === 'days' && $days === false) throw new DomainException('Informe o número de dias da janela (de 1 a 3650).');
        $this->settings->set('fid_stamp_value', (string) $value);
        $this->settings->set('fid_stamps_per_gift', (string) $perGift);
        $this->settings->set('fid_period_type', $type);
        if ($days !== false) $this->settings->set('fid_window_days', (string) $days);
    }

    /** Data inicial da janela de pontuação (null = sem limite). */
    public function cutoff(array $config): ?string
    {
        return match ($config['period_type']) {
            'days' => date('Y-m-d', strtotime('-' . (int) $config['window_days'] . ' days')),
            'month' => date('Y-m-01'),
            'year' => date('Y-01-01'),
            default => null,
        };
    }

    /**
     * Situação de cada cliente. Também cria os vouchers que já foram conquistados e ainda não existem.
     * @return array<int,array<string,mixed>>
     */
    public function standings(string $search = '', string $sort = 'stamps-desc', ?int $onlyClient = null): array
    {
        $pdo = Database::pdo();
        $config = $this->config();
        $cutoff = $this->cutoff($config);

        $clients = [];
        $sql = 'SELECT id, name, phone FROM ctl_clients';
        $params = [];
        if ($onlyClient !== null) {
            $sql .= ' WHERE id = ?';
            $params[] = $onlyClient;
        } elseif (trim($search) !== '') {
            $sql .= ' WHERE name LIKE ? OR phone LIKE ?';
            $like = '%' . addcslashes(trim($search), '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY name');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $client) {
            $clients[(int) $client['id']] = $client + ['earned' => 0, 'redeemed' => 0, 'gifts_total' => 0, 'gifts_pending' => 0];
        }
        if (!$clients) return [];

        $orderSql = "SELECT client_id, order_no, SUM(qty * unit_price) AS total FROM ctl_sales WHERE status NOT IN ('resgate', 'cancelado')";
        $orderParams = [];
        if ($cutoff !== null) {
            $orderSql .= ' AND sale_date >= ?';
            $orderParams[] = $cutoff;
        }
        $orders = $pdo->prepare($orderSql . ' GROUP BY client_id, order_no');
        $orders->execute($orderParams);
        foreach ($orders->fetchAll() as $order) {
            $id = (int) $order['client_id'];
            if (isset($clients[$id])) $clients[$id]['earned'] += (int) floor((float) $order['total'] / $config['stamp_value']);
        }
        foreach ($pdo->query("SELECT client_id, COUNT(*) AS n FROM ctl_sales WHERE status = 'resgate' GROUP BY client_id")->fetchAll() as $row) {
            if (isset($clients[(int) $row['client_id']])) $clients[(int) $row['client_id']]['redeemed'] = (int) $row['n'];
        }
        foreach ($pdo->query("SELECT client_id, COUNT(*) AS total, SUM(status = 'pendente') AS pending FROM ctl_gifts GROUP BY client_id")->fetchAll() as $row) {
            if (isset($clients[(int) $row['client_id']])) {
                $clients[(int) $row['client_id']]['gifts_total'] = (int) $row['total'];
                $clients[(int) $row['client_id']]['gifts_pending'] = (int) $row['pending'];
            }
        }

        foreach ($clients as $id => &$client) {
            $due = intdiv((int) $client['earned'], $config['stamps_per_gift']);
            if ($due > $client['gifts_total']) {
                $created = $this->createVouchers($id, $due - $client['gifts_total']);
                $client['gifts_total'] += $created;
                $client['gifts_pending'] += $created;
            }
            $client['stamps'] = max(0, (int) $client['earned'] - (int) $client['redeemed'] * $config['stamps_per_gift']);
        }
        unset($client);

        $list = array_values($clients);
        usort($list, static fn($a, $b) => match ($sort) {
            'name-desc' => strcasecmp((string) $b['name'], (string) $a['name']),
            'stamps-asc' => $a['stamps'] <=> $b['stamps'] ?: strcasecmp((string) $a['name'], (string) $b['name']),
            'gifts-pending' => $b['gifts_pending'] <=> $a['gifts_pending'] ?: strcasecmp((string) $a['name'], (string) $b['name']),
            'name-asc' => strcasecmp((string) $a['name'], (string) $b['name']),
            default => $b['stamps'] <=> $a['stamps'] ?: strcasecmp((string) $a['name'], (string) $b['name']),
        });
        return $list;
    }

    public function gifts(int $clientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, voucher, status, created_at, redeemed_at FROM ctl_gifts WHERE client_id = ? ORDER BY id DESC');
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function redemptions(int $clientId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT s.order_no, s.sale_date, s.qty, f.name AS flavor_name, z.name AS size_name
             FROM ctl_sales s JOIN ctl_flavors f ON f.id = s.flavor_id JOIN ctl_sizes z ON z.id = s.size_id
             WHERE s.client_id = ? AND s.status = 'resgate' ORDER BY s.id DESC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function redeem(int $giftId): void
    {
        $stmt = Database::pdo()->prepare("UPDATE ctl_gifts SET status = 'resgatado', redeemed_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'pendente'");
        $stmt->execute([$giftId]);
        if ($stmt->rowCount() === 0) throw new DomainException('Este brinde não está mais pendente.');
    }

    public function undoRedeem(int $giftId): void
    {
        $stmt = Database::pdo()->prepare("UPDATE ctl_gifts SET status = 'pendente', redeemed_at = NULL WHERE id = ? AND status = 'resgatado'");
        $stmt->execute([$giftId]);
        if ($stmt->rowCount() === 0) throw new DomainException('Este brinde não está resgatado.');
    }

    private function createVouchers(int $clientId, int $amount): int
    {
        $pdo = Database::pdo();
        $insert = $pdo->prepare('INSERT INTO ctl_gifts (client_id, voucher) VALUES (?, ?)');
        $created = 0;
        for ($i = 0; $i < $amount; $i++) {
            for ($attempt = 0; $attempt < 6; $attempt++) {
                try {
                    $insert->execute([$clientId, 'BRIND-' . strtoupper(bin2hex(random_bytes(3)))]);
                    $created++;
                    break;
                } catch (PDOException $error) {
                    if ($error->getCode() !== '23000') throw $error;
                }
            }
        }
        return $created;
    }
}
