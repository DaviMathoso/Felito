<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\Transaction;
use DateTimeImmutable;
use DomainException;

/**
 * Lançamentos de venda do gestor. Cada linha é um item; itens do mesmo pedido compartilham o order_no.
 * Regra de estoque: toda linha que não esteja "Cancelado" (inclui "Resgate") reserva a quantidade no local da venda.
 */
final class VendaService
{
    public const STATUSES = [
        'a_fazer' => 'A Fazer', 'a_entregar' => 'À Entregar', 'entregue' => 'Entregue',
        'pago' => 'Pago', 'cancelado' => 'Cancelado', 'resgate' => 'Resgate',
    ];
    /** Valores ainda a receber */
    public const OPEN = ['a_fazer', 'a_entregar', 'entregue'];
    /** Ainda não entregues */
    public const PENDING = ['a_fazer', 'a_entregar'];
    public const SORTS = [
        'pedido-desc' => 's.order_no DESC, s.id DESC', 'pedido-asc' => 's.order_no ASC, s.id ASC',
        'recent' => 's.id DESC', 'oldest' => 's.id ASC', 'sabor-asc' => 'f.name ASC, s.order_no DESC',
    ];

    private InventoryService $stock;
    private ClienteService $clients;
    private CatalogoService $catalog;
    private SettingsService $settings;

    public function __construct()
    {
        $this->stock = new InventoryService();
        $this->clients = new ClienteService();
        $this->catalog = new CatalogoService();
        $this->settings = new SettingsService();
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    // ------------------------------------------------------------------ consulta

    public function listLines(array $filter, int $limit = 500): array
    {
        [$where, $params] = $this->conditions($filter);
        $sort = self::SORTS[$filter['sort'] ?? ''] ?? self::SORTS['pedido-desc'];
        $sql = 'SELECT s.id, s.order_no, s.sale_date, s.qty, s.unit_price, s.status,
                       c.id AS client_id, c.name AS client_name, c.phone AS client_phone,
                       l.name AS location_name, l.color AS location_color, f.name AS flavor_name, z.name AS size_name
                FROM ctl_sales s
                JOIN ctl_clients c ON c.id = s.client_id
                JOIN ctl_locations l ON l.id = s.location_id
                JOIN ctl_flavors f ON f.id = s.flavor_id
                JOIN ctl_sizes z ON z.id = s.size_id'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY $sort LIMIT " . max(1, min($limit, 5000));
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*, c.name AS client_name, c.phone AS client_phone FROM ctl_sales s JOIN ctl_clients c ON c.id = s.client_id WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Números do cabeçalho da tela de vendas para o mês escolhido ('all' = tudo). */
    public function kpis(string $month): array
    {
        $pdo = Database::pdo();
        [$where, $params] = $this->conditions(['month' => $month]);
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT s.order_no) AS orders,
                    COALESCE(SUM(CASE WHEN s.status <> 'cancelado' THEN s.qty * s.unit_price END), 0) AS revenue
             FROM ctl_sales s" . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        );
        $stmt->execute($params);
        $totals = $stmt->fetch();

        $on = $where ? ' AND ' . implode(' AND ', $where) : '';
        $sizes = $pdo->prepare(
            "SELECT z.name, COALESCE(SUM(s.qty), 0) AS qty FROM ctl_sizes z
             LEFT JOIN ctl_sales s ON s.size_id = z.id AND s.status <> 'cancelado'$on
             WHERE z.is_active = 1 GROUP BY z.id, z.name, z.volume_ml ORDER BY z.volume_ml"
        );
        $sizes->execute($params);

        return [
            'orders' => (int) $totals['orders'],
            'revenue' => (float) $totals['revenue'],
            'by_size' => $sizes->fetchAll(),
            'pending' => (int) $pdo->query("SELECT COUNT(*) FROM ctl_sales WHERE status IN ('a_fazer', 'a_entregar')")->fetchColumn(),
        ];
    }

    public function months(): array
    {
        return Database::pdo()->query("SELECT DISTINCT DATE_FORMAT(sale_date, '%Y-%m') AS ym FROM ctl_sales ORDER BY ym DESC")->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Resumo mensal por sabor e por sabor + tamanho (não considera cancelados). */
    public function monthSummary(string $month): array
    {
        [$where, $params] = $this->conditions(['month' => $month]);
        $where[] = "s.status <> 'cancelado'";
        $base = 'FROM ctl_sales s JOIN ctl_flavors f ON f.id = s.flavor_id JOIN ctl_sizes z ON z.id = s.size_id WHERE ' . implode(' AND ', $where);
        $pdo = Database::pdo();
        $byFlavor = $pdo->prepare("SELECT f.name AS flavor, SUM(s.qty) AS qty, SUM(s.qty * s.unit_price) AS value $base GROUP BY f.id, f.name ORDER BY f.name");
        $byFlavor->execute($params);
        $bySize = $pdo->prepare("SELECT f.name AS flavor, z.name AS size, SUM(s.qty) AS qty, SUM(s.qty * s.unit_price) AS value $base GROUP BY f.id, f.name, z.id, z.name, z.volume_ml ORDER BY f.name, z.volume_ml");
        $bySize->execute($params);
        return ['by_flavor' => $byFlavor->fetchAll(), 'by_size' => $bySize->fetchAll()];
    }

    // ------------------------------------------------------------------ escrita

    /**
     * Lança um pedido com vários itens. $in: client_name, client_phone, location_id, sale_date,
     * lines[] = [flavor_id, size_id, qty, unit_price (vazio = preço do catálogo), status].
     * @return int número do pedido
     */
    public function createOrder(array $in, int $userId): int
    {
        $locationId = $this->validLocation((int) ($in['location_id'] ?? 0));
        $date = $this->parseDate((string) ($in['sale_date'] ?? ''));
        $rawLines = array_values((array) ($in['lines'] ?? []));
        if (!$rawLines) throw new DomainException('Adicione ao menos um item ao pedido.');
        if (count($rawLines) > 60) throw new DomainException('Um pedido pode ter no máximo 60 itens.');
        $lines = array_map(fn($raw) => $this->normalizeLine((array) $raw, $locationId), $rawLines);

        return (int) Transaction::run(function () use ($in, $locationId, $date, $lines, $userId): int {
            $pdo = Database::pdo();
            $clientId = $this->clients->findOrCreate((string) ($in['client_name'] ?? ''), (string) ($in['client_phone'] ?? ''));
            $orderNo = $this->settings->nextNumber('next_order_no', 'SELECT COALESCE(MAX(order_no), 0) FROM ctl_sales');
            $insert = $pdo->prepare(
                'INSERT INTO ctl_sales (order_no, sale_date, client_id, location_id, flavor_id, size_id, qty, unit_price, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($lines as $line) {
                $insert->execute([$orderNo, $date, $clientId, $locationId, $line['flavor_id'], $line['size_id'], $line['qty'], $line['unit_price'], $line['status'], $userId]);
                $saleId = (int) $pdo->lastInsertId();
                $this->syncStock(null, $line + ['id' => $saleId, 'order_no' => $orderNo, 'location_id' => $locationId], $userId);
            }
            return $orderNo;
        });
    }

    /** Edita uma linha inteira (cliente, local, produto, quantidade, valor, status, número do pedido e data). */
    public function updateLine(int $id, array $in, int $userId): void
    {
        $locationId = $this->validLocation((int) ($in['location_id'] ?? 0));
        $date = $this->parseDate((string) ($in['sale_date'] ?? ''));
        $orderNo = filter_var($in['order_no'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999]]);
        if ($orderNo === false) throw new DomainException('Informe um número de pedido válido.');
        $line = $this->normalizeLine($in, $locationId);

        Transaction::run(function () use ($id, $in, $locationId, $date, $orderNo, $line, $userId): void {
            $old = $this->lock($id);
            $clientId = $this->clients->findOrCreate((string) ($in['client_name'] ?? ''), (string) ($in['client_phone'] ?? ''));
            Database::pdo()->prepare(
                'UPDATE ctl_sales SET order_no = ?, sale_date = ?, client_id = ?, location_id = ?, flavor_id = ?, size_id = ?, qty = ?, unit_price = ?, status = ? WHERE id = ?'
            )->execute([$orderNo, $date, $clientId, $locationId, $line['flavor_id'], $line['size_id'], $line['qty'], $line['unit_price'], $line['status'], $id]);
            $this->syncStock($old, $line + ['id' => $id, 'order_no' => $orderNo, 'location_id' => $locationId], $userId);
            $this->settings->ensureAbove('next_order_no', $orderNo);
        });
    }

    public function changeStatus(int $id, string $status, int $userId): void
    {
        if (!isset(self::STATUSES[$status])) throw new DomainException('Situação inválida.');
        Transaction::run(function () use ($id, $status, $userId): void {
            $old = $this->lock($id);
            $price = (float) $old['unit_price'];
            if ($status === 'resgate') {
                $price = 0.0;
            } elseif ($old['status'] === 'resgate' && $price <= 0) {
                // saindo de "Resgate": volta ao preço do catálogo para que a cobrança faça sentido
                $price = $this->catalog->price((int) $old['flavor_id'], (int) $old['size_id'], (int) $old['location_id']) ?? 0.0;
            }
            Database::pdo()->prepare('UPDATE ctl_sales SET status = ?, unit_price = ? WHERE id = ?')->execute([$status, $price, $id]);
            $this->syncStock($old, array_merge($old, ['status' => $status, 'unit_price' => $price]), $userId);
        });
    }

    public function deleteLine(int $id, int $userId): void
    {
        Transaction::run(function () use ($id, $userId): void {
            $old = $this->lock($id);
            Database::pdo()->prepare('DELETE FROM ctl_sales WHERE id = ?')->execute([$id]);
            $this->syncStock($old, null, $userId);
        });
    }

    // ------------------------------------------------------------------ internos

    /** Compara o antes e o depois da linha e ajusta o estoque só quando necessário. */
    public function syncStock(?array $old, ?array $new, int $userId): void
    {
        $oldCounts = $old !== null && $old['status'] !== 'cancelado';
        $newCounts = $new !== null && $new['status'] !== 'cancelado';
        if ($oldCounts && $newCounts) {
            $same = true;
            foreach (['location_id', 'flavor_id', 'size_id', 'qty'] as $key) $same = $same && (int) $old[$key] === (int) $new[$key];
            if ($same) return;
        }
        if ($oldCounts) {
            $this->stock->restore((int) $old['location_id'], (int) $old['flavor_id'], (int) $old['size_id'], (int) $old['qty'], 'Estorno do pedido #' . $old['order_no'], (int) $old['id'], (int) $old['order_no'], $userId);
        }
        if ($newCounts) {
            $this->stock->consume((int) $new['location_id'], (int) $new['flavor_id'], (int) $new['size_id'], (int) $new['qty'], 'Venda do pedido #' . $new['order_no'], (int) $new['id'], (int) $new['order_no'], $userId);
        }
    }

    private function lock(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ctl_sales WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new DomainException('Lançamento não encontrado.');
    }

    /** @return array{flavor_id:int,size_id:int,qty:int,unit_price:float,status:string} */
    public function normalizeLine(array $raw, int $locationId): array
    {
        $flavorId = (int) ($raw['flavor_id'] ?? 0);
        $sizeId = (int) ($raw['size_id'] ?? 0);
        $qty = filter_var($raw['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        $status = (string) ($raw['status'] ?? 'a_fazer');
        if (!isset(self::STATUSES[$status])) throw new DomainException('Situação inválida.');
        if ($qty === false) throw new DomainException('Informe uma quantidade entre 1 e 9999.');

        $pdo = Database::pdo();
        $flavor = $pdo->prepare('SELECT name FROM ctl_flavors WHERE id = ?');
        $flavor->execute([$flavorId]);
        $flavorName = $flavor->fetchColumn();
        $size = $pdo->prepare('SELECT name FROM ctl_sizes WHERE id = ?');
        $size->execute([$sizeId]);
        $sizeName = $size->fetchColumn();
        if ($flavorName === false || $sizeName === false) throw new DomainException('Escolha o sabor e o tamanho.');

        $typed = trim((string) ($raw['unit_price'] ?? ''));
        $price = Money::parse($typed);
        if ($typed !== '' && $price === null) throw new DomainException('Valor unitário inválido. Use algo como 17,00.');
        if ($status === 'resgate') {
            $price = 0.0;
        } else {
            $price ??= $this->catalog->price($flavorId, $sizeId, $locationId);
            if ($price === null) throw new DomainException("Sem preço cadastrado para $flavorName $sizeName. Informe o valor unitário.");
        }
        return ['flavor_id' => $flavorId, 'size_id' => $sizeId, 'qty' => $qty, 'unit_price' => $price, 'status' => $status];
    }

    private function validLocation(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ctl_locations WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Escolha o local da venda.');
        return $id;
    }

    public function parseDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if (!$date || $date->format('Y-m-d') !== trim($value)) throw new DomainException('Informe uma data válida.');
        return $date->format('Y-m-d');
    }

    /** @return array{0:string[],1:array} condições SQL (alias s/c) e parâmetros para os filtros da tela */
    private function conditions(array $filter): array
    {
        $where = [];
        $params = [];
        $month = (string) ($filter['month'] ?? 'all');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $where[] = 's.sale_date >= ? AND s.sale_date < ?';
            $start = $month . '-01';
            array_push($params, $start, date('Y-m-d', strtotime($start . ' +1 month')));
        }
        $status = (string) ($filter['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        $search = trim((string) ($filter['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(s.client_id IN (SELECT id FROM ctl_clients WHERE name LIKE ? OR phone LIKE ?))';
            array_push($params, $like, $like);
        }
        return [$where, $params];
    }
}
