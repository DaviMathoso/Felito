<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\Transaction;
use DateTimeImmutable;
use DomainException;

/**
 * Lotes de produção. Ao confirmar um lote, as unidades entram no estoque do local escolhido e o custo
 * total do lote é rateado por volume: custo por mL = custo total ÷ soma(qtd × mL); custo da unidade = mL × custo por mL + embalagem.
 */
final class ProducaoService
{
    private InventoryService $stock;
    private CatalogoService $catalog;
    private SettingsService $settings;

    public function __construct()
    {
        $this->stock = new InventoryService();
        $this->catalog = new CatalogoService();
        $this->settings = new SettingsService();
    }

    /** Custo unitário por tamanho. @param array<int,int> $qtyBySize size_id => quantidade @return array<int,float> */
    public function unitCosts(float $totalCost, array $qtyBySize): array
    {
        if ($totalCost <= 0 || !$qtyBySize) return [];
        $sizes = [];
        foreach ($this->catalog->sizes() as $size) $sizes[(int) $size['id']] = $size;
        $weighted = 0.0;
        foreach ($qtyBySize as $sizeId => $qty) $weighted += $qty * (float) ($sizes[$sizeId]['volume_ml'] ?? 0);
        if ($weighted <= 0) return [];
        $perMl = $totalCost / $weighted;
        $costs = [];
        foreach ($qtyBySize as $sizeId => $qty) {
            $size = $sizes[$sizeId] ?? null;
            if ($size) $costs[$sizeId] = round($perMl * (float) $size['volume_ml'] + (float) $size['packaging_cost'], 4);
        }
        return $costs;
    }

    public function nextNumber(): int
    {
        $pdo = Database::pdo();
        $stored = (int) $this->settings->get('next_batch_no', '1');
        $used = (int) $pdo->query('SELECT COALESCE(MAX(batch_no), 0) FROM ctl_batches')->fetchColumn();
        return max(1, $stored, $used + 1);
    }

    public function list(): array
    {
        $batches = Database::pdo()->query(
            'SELECT b.id, b.batch_no, b.batch_date, b.total_cost, b.note, l.name AS location_name, l.color AS location_color,
                    COALESCE((SELECT SUM(i.qty) FROM ctl_batch_items i WHERE i.batch_id = b.id), 0) AS units
             FROM ctl_batches b JOIN ctl_locations l ON l.id = b.location_id ORDER BY b.batch_date DESC, b.id DESC LIMIT 60'
        )->fetchAll();
        if (!$batches) return [];
        $ids = array_column($batches, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT i.batch_id, i.qty, i.unit_price, i.unit_cost, f.name AS flavor_name, z.name AS size_name
             FROM ctl_batch_items i JOIN ctl_flavors f ON f.id = i.flavor_id JOIN ctl_sizes z ON z.id = i.size_id
             WHERE i.batch_id IN ($in) ORDER BY f.name, z.volume_ml"
        );
        $stmt->execute($ids);
        $items = [];
        foreach ($stmt->fetchAll() as $item) $items[$item['batch_id']][] = $item;
        foreach ($batches as &$batch) $batch['items'] = $items[$batch['id']] ?? [];
        unset($batch);
        return $batches;
    }

    /**
     * @param array $in batch_no (opcional), batch_date, location_id, total_cost, note, lines[] = [flavor_id, size_id, qty, unit_price]
     * @return int id do lote
     */
    public function create(array $in, int $userId): int
    {
        $date = $this->date((string) ($in['batch_date'] ?? ''));
        $locationId = (int) ($in['location_id'] ?? 0);
        $cost = $this->cost($in['total_cost'] ?? '');
        $note = $this->note($in['note'] ?? '');
        $rawLines = array_values((array) ($in['lines'] ?? []));
        if (!$rawLines) throw new DomainException('Adicione ao menos um item ao lote.');
        if (count($rawLines) > 80) throw new DomainException('Um lote pode ter no máximo 80 itens.');

        $location = Database::pdo()->prepare('SELECT 1 FROM ctl_locations WHERE id = ?');
        $location->execute([$locationId]);
        if (!$location->fetchColumn()) throw new DomainException('Escolha o local que recebe a produção.');

        $prices = $this->catalog->priceMap($locationId);
        $lines = [];
        foreach ($rawLines as $raw) {
            $flavorId = (int) ($raw['flavor_id'] ?? 0);
            $sizeId = (int) ($raw['size_id'] ?? 0);
            $qty = filter_var($raw['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99999]]);
            if ($flavorId < 1 || $sizeId < 1 || $qty === false) throw new DomainException('Há um item do lote com sabor, tamanho ou quantidade inválidos.');
            $price = Money::parse($raw['unit_price'] ?? '') ?? ($prices[$flavorId . '|' . $sizeId] ?? 0.0);
            $lines[] = ['flavor_id' => $flavorId, 'size_id' => $sizeId, 'qty' => $qty, 'unit_price' => $price];
        }

        return (int) Transaction::run(function () use ($in, $date, $locationId, $cost, $note, $lines, $userId): int {
            $pdo = Database::pdo();
            $number = filter_var($in['batch_no'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999]]) ?: $this->nextNumber();
            $exists = $pdo->prepare('SELECT 1 FROM ctl_batches WHERE batch_no = ?');
            $exists->execute([$number]);
            if ($exists->fetchColumn()) throw new DomainException("O lote $number já existe. Use outro número.");

            $pdo->prepare('INSERT INTO ctl_batches (batch_no, batch_date, location_id, total_cost, note, created_by) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$number, $date, $locationId, $cost, $note, $userId]);
            $batchId = (int) $pdo->lastInsertId();

            $qtyBySize = [];
            foreach ($lines as $line) $qtyBySize[$line['size_id']] = ($qtyBySize[$line['size_id']] ?? 0) + $line['qty'];
            $costs = $this->unitCosts($cost, $qtyBySize);

            $insert = $pdo->prepare('INSERT INTO ctl_batch_items (batch_id, flavor_id, size_id, qty, unit_price, unit_cost) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($lines as $line) {
                $unitCost = $costs[$line['size_id']] ?? null;
                $insert->execute([$batchId, $line['flavor_id'], $line['size_id'], $line['qty'], $line['unit_price'], $unitCost]);
                // o valor da unidade em estoque é o custo; sem custo informado, usa o preço de venda
                $this->stock->entry($locationId, $line['flavor_id'], $line['size_id'], $line['qty'], $unitCost ?? $line['unit_price'], "Lote $number", $userId, $batchId);
            }
            (new SettingsService())->ensureAbove('next_batch_no', $number);
            return $batchId;
        });
    }

    /** Corrige dados do lote (número, data, custo e observação) e recalcula o custo unitário dos itens. */
    public function update(int $id, array $in): void
    {
        $date = $this->date((string) ($in['batch_date'] ?? ''));
        $cost = $this->cost($in['total_cost'] ?? '');
        $note = $this->note($in['note'] ?? '');
        $number = filter_var($in['batch_no'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999]]);
        if ($number === false) throw new DomainException('Informe um número de lote válido.');

        Transaction::run(function () use ($id, $date, $cost, $note, $number): void {
            $pdo = Database::pdo();
            $batch = $pdo->prepare('SELECT id FROM ctl_batches WHERE id = ? FOR UPDATE');
            $batch->execute([$id]);
            if (!$batch->fetchColumn()) throw new DomainException('Lote não encontrado.');
            $dup = $pdo->prepare('SELECT 1 FROM ctl_batches WHERE batch_no = ? AND id <> ?');
            $dup->execute([$number, $id]);
            if ($dup->fetchColumn()) throw new DomainException("O lote $number já existe. Use outro número.");

            $pdo->prepare('UPDATE ctl_batches SET batch_no = ?, batch_date = ?, total_cost = ?, note = ? WHERE id = ?')->execute([$number, $date, $cost, $note, $id]);
            $items = $pdo->prepare('SELECT id, size_id, qty FROM ctl_batch_items WHERE batch_id = ?');
            $items->execute([$id]);
            $rows = $items->fetchAll();
            $qtyBySize = [];
            foreach ($rows as $row) $qtyBySize[(int) $row['size_id']] = ($qtyBySize[(int) $row['size_id']] ?? 0) + (int) $row['qty'];
            $costs = $this->unitCosts($cost, $qtyBySize);
            $update = $pdo->prepare('UPDATE ctl_batch_items SET unit_cost = ? WHERE id = ?');
            foreach ($rows as $row) $update->execute([$costs[(int) $row['size_id']] ?? null, $row['id']]);
            (new SettingsService())->ensureAbove('next_batch_no', $number);
        });
    }

    /** Exclui o lote e desfaz no estoque as unidades que ele havia adicionado. */
    public function delete(int $id, ?int $userId): void
    {
        Transaction::run(function () use ($id, $userId): void {
            $pdo = Database::pdo();
            $batch = $pdo->prepare('SELECT batch_no, location_id FROM ctl_batches WHERE id = ? FOR UPDATE');
            $batch->execute([$id]);
            $head = $batch->fetch();
            if (!$head) throw new DomainException('Lote não encontrado.');
            $items = $pdo->prepare('SELECT flavor_id, size_id, qty FROM ctl_batch_items WHERE batch_id = ? ORDER BY flavor_id, size_id');
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                $this->stock->revertBatch((int) $head['location_id'], (int) $item['flavor_id'], (int) $item['size_id'], (int) $item['qty'], 'Exclusão do lote ' . $head['batch_no'], $id, $userId);
            }
            $pdo->prepare('DELETE FROM ctl_batches WHERE id = ?')->execute([$id]);
        });
    }

    private function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if (!$date || $date->format('Y-m-d') !== trim($value)) throw new DomainException('Informe uma data válida.');
        return $date->format('Y-m-d');
    }

    private function cost(mixed $value): float
    {
        if (trim((string) $value) === '') return 0.0;
        $cost = Money::parse($value);
        if ($cost === null || $cost > 9999999) throw new DomainException('Informe um custo total válido, por exemplo 180,00.');
        return $cost;
    }

    private function note(mixed $value): string
    {
        $note = trim((string) $value);
        if (mb_strlen($note) > 200) throw new DomainException('A observação deve ter no máximo 200 caracteres.');
        return $note;
    }
}
