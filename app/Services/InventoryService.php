<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Transaction;
use DomainException;

/** Estoque por local, sabor e tamanho, com histórico de todas as movimentações. */
final class InventoryService
{
    public const MOVE_LABELS = ['entrada' => 'Entrada', 'venda' => 'Venda', 'estorno' => 'Estorno', 'transferencia' => 'Transferência', 'ajuste' => 'Ajuste'];

    /** Linhas com saldo diferente de zero. */
    public function matrix(?int $locationId = null): array
    {
        $sql = 'SELECT st.location_id, l.name AS location_name, l.color, st.flavor_id, f.name AS flavor_name,
                       st.size_id, z.name AS size_name, z.volume_ml, st.qty, st.unit_value, st.qty * st.unit_value AS total_value
                FROM ctl_stock st
                JOIN ctl_locations l ON l.id = st.location_id
                JOIN ctl_flavors f ON f.id = st.flavor_id
                JOIN ctl_sizes z ON z.id = st.size_id
                WHERE st.qty <> 0' . ($locationId ? ' AND st.location_id = ?' : '') . '
                ORDER BY l.is_default DESC, l.name, f.name, z.volume_ml';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($locationId ? [$locationId] : []);
        return $stmt->fetchAll();
    }

    public function locationTotals(): array
    {
        return Database::pdo()->query(
            'SELECT l.id, l.name, l.color, l.is_default, l.is_active,
                    COALESCE(SUM(st.qty), 0) AS qty, COALESCE(SUM(st.qty * st.unit_value), 0) AS total_value
             FROM ctl_locations l LEFT JOIN ctl_stock st ON st.location_id = l.id
             GROUP BY l.id, l.name, l.color, l.is_default, l.is_active
             ORDER BY l.is_default DESC, l.name'
        )->fetchAll();
    }

    /** Saldo por sabor (linhas) e tamanho (colunas), somando todos os locais ou só um. @return array{0:array,1:array} */
    public function flavorGrid(?int $locationId = null): array
    {
        $sql = 'SELECT f.id AS flavor_id, f.name AS flavor_name, st.size_id, SUM(st.qty) AS qty
                FROM ctl_stock st JOIN ctl_flavors f ON f.id = st.flavor_id'
             . ($locationId ? ' WHERE st.location_id = ?' : '') . ' GROUP BY f.id, f.name, st.size_id HAVING SUM(st.qty) <> 0 ORDER BY f.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($locationId ? [$locationId] : []);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['flavor_id']]['name'] = $row['flavor_name'];
            $rows[$row['flavor_id']]['sizes'][$row['size_id']] = (int) $row['qty'];
        }
        return $rows;
    }

    public function history(array $filter = [], int $limit = 150): array
    {
        $where = [];
        $params = [];
        if (!empty($filter['type']) && isset(self::MOVE_LABELS[$filter['type']])) {
            $where[] = 'm.move_type = ?';
            $params[] = $filter['type'];
        }
        if (!empty($filter['location_id'])) {
            $where[] = '(m.from_location_id = ? OR m.to_location_id = ?)';
            array_push($params, (int) $filter['location_id'], (int) $filter['location_id']);
        }
        $sql = 'SELECT m.id, m.moved_at, m.move_type, m.qty, m.note, m.order_no, f.name AS flavor_name, z.name AS size_name,
                       lf.name AS from_name, lt.name AS to_name, u.name AS user_name
                FROM ctl_stock_movements m
                JOIN ctl_flavors f ON f.id = m.flavor_id
                JOIN ctl_sizes z ON z.id = m.size_id
                LEFT JOIN ctl_locations lf ON lf.id = m.from_location_id
                LEFT JOIN ctl_locations lt ON lt.id = m.to_location_id
                LEFT JOIN users u ON u.id = m.user_id'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY m.id DESC LIMIT ' . max(1, min($limit, 500));
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------ ações do gestor

    public function entry(int $locationId, int $flavorId, int $sizeId, int $qty, ?float $unitValue, string $note, ?int $userId, ?int $batchId = null): void
    {
        if ($qty < 1 || $qty > 999999) throw new DomainException('Informe uma quantidade de entrada entre 1 e 999999.');
        Transaction::run(function () use ($locationId, $flavorId, $sizeId, $qty, $unitValue, $note, $userId, $batchId): void {
            $this->bump($locationId, $flavorId, $sizeId, $qty);
            if ($unitValue !== null && $unitValue > 0) $this->setUnitValue($locationId, $flavorId, $sizeId, $unitValue);
            $this->log('entrada', null, $locationId, $flavorId, $sizeId, $qty, $note, null, null, $batchId, $userId);
        });
    }

    /** Define o saldo final contado (o histórico guarda o saldo anterior). */
    public function adjust(int $locationId, int $flavorId, int $sizeId, int $newQty, string $note, ?int $userId): void
    {
        if ($newQty < 0 || $newQty > 999999) throw new DomainException('Informe um saldo entre 0 e 999999.');
        Transaction::run(function () use ($locationId, $flavorId, $sizeId, $newQty, $note, $userId): void {
            $pdo = Database::pdo();
            $this->bump($locationId, $flavorId, $sizeId, 0);
            $stmt = $pdo->prepare('SELECT qty FROM ctl_stock WHERE location_id = ? AND flavor_id = ? AND size_id = ? FOR UPDATE');
            $stmt->execute([$locationId, $flavorId, $sizeId]);
            $old = (int) $stmt->fetchColumn();
            $pdo->prepare('UPDATE ctl_stock SET qty = ? WHERE location_id = ? AND flavor_id = ? AND size_id = ?')
                ->execute([$newQty, $locationId, $flavorId, $sizeId]);
            $this->log('ajuste', null, $locationId, $flavorId, $sizeId, $newQty, trim($note) === '' ? "Ajuste manual (era $old)" : trim($note) . " (era $old)", null, null, null, $userId);
        });
    }

    public function transfer(int $fromId, int $toId, int $flavorId, int $sizeId, int $qty, string $note, ?int $userId): void
    {
        if ($fromId === $toId) throw new DomainException('Escolha locais de origem e destino diferentes.');
        if ($qty < 1 || $qty > 999999) throw new DomainException('Informe uma quantidade entre 1 e 999999.');
        Transaction::run(function () use ($fromId, $toId, $flavorId, $sizeId, $qty, $note, $userId): void {
            $pdo = Database::pdo();
            $this->bump($fromId, $flavorId, $sizeId, 0);
            $this->bump($toId, $flavorId, $sizeId, 0);
            $stmt = $pdo->prepare(
                'SELECT location_id, qty, unit_value FROM ctl_stock WHERE flavor_id = ? AND size_id = ? AND location_id IN (?, ?) ORDER BY location_id FOR UPDATE'
            );
            $stmt->execute([$flavorId, $sizeId, $fromId, $toId]);
            $rows = [];
            foreach ($stmt->fetchAll() as $row) $rows[(int) $row['location_id']] = $row;
            if ((int) $rows[$fromId]['qty'] < $qty) {
                $name = $pdo->prepare('SELECT name FROM ctl_locations WHERE id = ?');
                $name->execute([$fromId]);
                throw new DomainException('Estoque insuficiente em "' . $name->fetchColumn() . '". Disponível: ' . (int) $rows[$fromId]['qty'] . '.');
            }
            $this->bump($fromId, $flavorId, $sizeId, -$qty);
            $this->bump($toId, $flavorId, $sizeId, $qty);
            if ((float) $rows[$toId]['unit_value'] <= 0 && (float) $rows[$fromId]['unit_value'] > 0) {
                $this->setUnitValue($toId, $flavorId, $sizeId, (float) $rows[$fromId]['unit_value']);
            }
            $this->log('transferencia', $fromId, $toId, $flavorId, $sizeId, $qty, trim($note), null, null, null, $userId);
        });
    }

    // ------------------------------------------------------------------ usados por vendas e produção

    public function consume(int $locationId, int $flavorId, int $sizeId, int $qty, string $note, int $saleId, int $orderNo, ?int $userId): void
    {
        $this->bump($locationId, $flavorId, $sizeId, -$qty);
        $this->log('venda', $locationId, null, $flavorId, $sizeId, $qty, $note, $saleId, $orderNo, null, $userId);
    }

    public function restore(int $locationId, int $flavorId, int $sizeId, int $qty, string $note, int $saleId, int $orderNo, ?int $userId): void
    {
        $this->bump($locationId, $flavorId, $sizeId, $qty);
        $this->log('estorno', null, $locationId, $flavorId, $sizeId, $qty, $note, $saleId, $orderNo, null, $userId);
    }

    /** Remove do estoque o que um lote de produção havia adicionado. */
    public function revertBatch(int $locationId, int $flavorId, int $sizeId, int $qty, string $note, int $batchId, ?int $userId): void
    {
        $this->bump($locationId, $flavorId, $sizeId, -$qty);
        $this->log('estorno', $locationId, null, $flavorId, $sizeId, $qty, $note, null, null, $batchId, $userId);
    }

    // ------------------------------------------------------------------ internos

    private function bump(int $locationId, int $flavorId, int $sizeId, int $delta): void
    {
        Database::pdo()->prepare(
            'INSERT INTO ctl_stock (location_id, flavor_id, size_id, qty, unit_value) VALUES (?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE qty = qty + ?'
        )->execute([$locationId, $flavorId, $sizeId, $delta, $delta]);
    }

    private function setUnitValue(int $locationId, int $flavorId, int $sizeId, float $value): void
    {
        Database::pdo()->prepare('UPDATE ctl_stock SET unit_value = ? WHERE location_id = ? AND flavor_id = ? AND size_id = ?')
            ->execute([$value, $locationId, $flavorId, $sizeId]);
    }

    private function log(string $type, ?int $from, ?int $to, int $flavorId, int $sizeId, int $qty, string $note, ?int $saleId, ?int $orderNo, ?int $batchId, ?int $userId): void
    {
        Database::pdo()->prepare(
            'INSERT INTO ctl_stock_movements (move_type, from_location_id, to_location_id, flavor_id, size_id, qty, note, sale_id, order_no, batch_id, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$type, $from, $to, $flavorId, $sizeId, $qty, mb_substr($note, 0, 200), $saleId, $orderNo, $batchId, $userId]);
    }
}
