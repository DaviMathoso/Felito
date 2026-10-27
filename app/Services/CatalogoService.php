<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\Transaction;
use DomainException;
use PDOException;

/** Locais de venda, sabores, tamanhos e tabela de preços do controle. */
final class CatalogoService
{
    public const COLORS = ['#335b3d', '#b94e1c', '#125b80', '#8b3027', '#6b4c9a', '#8a6d1d', '#00838f', '#353027'];

    public function flavors(bool $onlyActive = false): array
    {
        return Database::pdo()->query('SELECT id, name, is_active FROM ctl_flavors' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name')->fetchAll();
    }

    public function sizes(bool $onlyActive = false): array
    {
        return Database::pdo()->query('SELECT id, name, volume_ml, packaging_cost, is_active FROM ctl_sizes' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY volume_ml, name')->fetchAll();
    }

    public function locations(bool $onlyActive = false): array
    {
        return Database::pdo()->query('SELECT id, name, color, is_default, is_active FROM ctl_locations' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY is_default DESC, name')->fetchAll();
    }

    public function defaultLocationId(): int
    {
        $id = Database::pdo()->query('SELECT id FROM ctl_locations WHERE is_active = 1 ORDER BY is_default DESC, id LIMIT 1')->fetchColumn();
        if ($id === false) throw new DomainException('Cadastre ao menos um local de venda ativo.');
        return (int) $id;
    }

    public function saveFlavor(?int $id, string $name, bool $active): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) throw new DomainException('Informe o nome do sabor (até 120 caracteres).');
        return $this->persist($id, 'ctl_flavors', ['name' => $name, 'is_active' => $active ? 1 : 0], 'Já existe um sabor com esse nome.');
    }

    public function saveSize(?int $id, string $name, int $volume, mixed $packaging, bool $active): int
    {
        $name = trim($name);
        $cost = trim((string) $packaging) === '' ? 0.0 : Money::parse($packaging);
        if ($name === '' || mb_strlen($name) > 60) throw new DomainException('Informe o nome do tamanho (até 60 caracteres).');
        if ($volume < 1 || $volume > 100000) throw new DomainException('Informe o volume em mL (de 1 a 100000).');
        if ($cost === null) throw new DomainException('Informe um custo de embalagem válido, por exemplo 0,80.');
        return $this->persist($id, 'ctl_sizes', ['name' => $name, 'volume_ml' => $volume, 'packaging_cost' => $cost, 'is_active' => $active ? 1 : 0], 'Já existe um tamanho com esse nome.');
    }

    public function saveLocation(?int $id, string $name, string $color): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) throw new DomainException('Informe o nome do local (até 80 caracteres).');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = self::COLORS[0];
        return $this->persist($id, 'ctl_locations', ['name' => $name, 'color' => $color], 'Já existe um local com esse nome.');
    }

    public function setDefaultLocation(int $id): void
    {
        Transaction::run(function () use ($id): void {
            $pdo = Database::pdo();
            $exists = $pdo->prepare('SELECT 1 FROM ctl_locations WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) throw new DomainException('Local não encontrado.');
            $pdo->exec('UPDATE ctl_locations SET is_default = 0');
            $pdo->prepare('UPDATE ctl_locations SET is_default = 1, is_active = 1 WHERE id = ?')->execute([$id]);
        });
    }

    /** Ativa ou desativa um local (locais com histórico não são apagados). */
    public function toggleLocation(int $id): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT is_active, is_default FROM ctl_locations WHERE id = ?');
        $stmt->execute([$id]);
        $location = $stmt->fetch();
        if (!$location) throw new DomainException('Local não encontrado.');
        if ((int) $location['is_active'] === 0) {
            $pdo->prepare('UPDATE ctl_locations SET is_active = 1 WHERE id = ?')->execute([$id]);
            return true;
        }
        if ((int) $location['is_default'] === 1) throw new DomainException('Defina outro local como padrão antes de desativar este.');
        $others = $pdo->prepare('SELECT COUNT(*) FROM ctl_locations WHERE is_active = 1 AND id <> ?');
        $others->execute([$id]);
        if ((int) $others->fetchColumn() < 1) throw new DomainException('É preciso manter ao menos um local ativo.');
        $pdo->prepare('UPDATE ctl_locations SET is_active = 0 WHERE id = ?')->execute([$id]);
        return false;
    }

    public function toggleActive(string $table, int $id): void
    {
        if (!in_array($table, ['ctl_flavors', 'ctl_sizes'], true)) throw new DomainException('Cadastro inválido.');
        Database::pdo()->prepare("UPDATE $table SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    }

    /** Preços efetivos de um local: o preço próprio do local vale mais que o global. Chave "sabor|tamanho". */
    public function priceMap(int $locationId): array
    {
        $stmt = Database::pdo()->prepare('SELECT flavor_id, size_id, location_id, price FROM ctl_prices WHERE location_id IN (0, ?)');
        $stmt->execute([$locationId]);
        $global = [];
        $local = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['flavor_id'] . '|' . $row['size_id'];
            if ((int) $row['location_id'] === 0) $global[$key] = (float) $row['price'];
            else $local[$key] = (float) $row['price'];
        }
        return $local + $global;
    }

    /** Preços gravados somente para um local (0 = tabela global). */
    public function ownPrices(int $locationId): array
    {
        $stmt = Database::pdo()->prepare('SELECT flavor_id, size_id, price FROM ctl_prices WHERE location_id = ?');
        $stmt->execute([$locationId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) $map[$row['flavor_id'] . '|' . $row['size_id']] = (float) $row['price'];
        return $map;
    }

    public function price(int $flavorId, int $sizeId, int $locationId): ?float
    {
        return $this->priceMap($locationId)[$flavorId . '|' . $sizeId] ?? null;
    }

    /** @param array<string,string> $prices chave "sabor|tamanho" => valor digitado (vazio remove o preço) */
    public function savePrices(int $locationId, array $prices): int
    {
        $pdo = Database::pdo();
        $flavors = array_column($this->flavors(), 'id');
        $sizes = array_column($this->sizes(), 'id');
        $saved = 0;
        Transaction::run(function () use ($pdo, $locationId, $prices, $flavors, $sizes, &$saved): void {
            $upsert = $pdo->prepare('INSERT INTO ctl_prices (flavor_id, size_id, location_id, price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price)');
            $delete = $pdo->prepare('DELETE FROM ctl_prices WHERE flavor_id = ? AND size_id = ? AND location_id = ?');
            foreach ($prices as $key => $raw) {
                if (!preg_match('/^(\d+)\|(\d+)$/', (string) $key, $m)) continue;
                [$flavorId, $sizeId] = [(int) $m[1], (int) $m[2]];
                if (!in_array($flavorId, $flavors, true) || !in_array($sizeId, $sizes, true)) continue;
                if (trim((string) $raw) === '') {
                    $delete->execute([$flavorId, $sizeId, $locationId]);
                    continue;
                }
                $price = Money::parse($raw);
                if ($price === null || $price > 99999) throw new DomainException('Há um preço inválido na tabela. Use valores como 17,00.');
                $upsert->execute([$flavorId, $sizeId, $locationId, $price]);
                $saved++;
            }
        });
        return $saved;
    }

    private function persist(?int $id, string $table, array $data, string $duplicateMessage): int
    {
        $pdo = Database::pdo();
        try {
            if ($id !== null) {
                $set = implode(', ', array_map(static fn($column) => "$column = ?", array_keys($data)));
                $pdo->prepare("UPDATE $table SET $set WHERE id = ?")->execute([...array_values($data), $id]);
                return $id;
            }
            $columns = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($data));
            return (int) $pdo->lastInsertId();
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new DomainException($duplicateMessage);
            throw $error;
        }
    }
}
