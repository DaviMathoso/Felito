<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Csv;
use App\Core\Database;
use App\Core\Money;
use App\Core\Transaction;
use DomainException;

/** Exportação e importação em CSV/JSON, no formato do sistema atual do cliente (pedido;data;cliente;telefone;tam;qtd;sabor;valor;status). */
final class PlanilhaService
{
    public const MAX_ROWS = 5000;

    public function salesRows(array $filter): array
    {
        $rows = [['pedido', 'data', 'cliente', 'telefone', 'local', 'tam', 'qtd', 'sabor', 'valor', 'status']];
        foreach ((new VendaService())->listLines($filter, self::MAX_ROWS) as $line) {
            $rows[] = [$line['order_no'], $line['sale_date'], $line['client_name'], $line['client_phone'], $line['location_name'],
                       $line['size_name'], $line['qty'], $line['flavor_name'], number_format((float) $line['unit_price'], 2, ',', ''), VendaService::label($line['status'])];
        }
        return $rows;
    }

    public function clientRows(): array
    {
        $rows = [['id', 'nome', 'telefone']];
        foreach ((new ClienteService())->list() as $client) $rows[] = [$client['id'], $client['name'], $client['phone']];
        return $rows;
    }

    /**
     * Importa lançamentos. Reimportar o mesmo arquivo duplica os registros.
     * @return array{imported:int,skipped:int,notes:string[]}
     */
    public function importSales(string $content, string $extension, int $locationId, bool $adjustStock, int $userId): array
    {
        $records = $this->records($content, $extension, 'items');
        if (count($records) > self::MAX_ROWS) throw new DomainException('O arquivo tem mais de ' . self::MAX_ROWS . ' linhas. Divida em partes menores.');

        $pdo = Database::pdo();
        $catalog = new CatalogoService();
        $clients = new ClienteService();
        $sales = new VendaService();
        $settings = new SettingsService();

        $location = $pdo->prepare('SELECT 1 FROM ctl_locations WHERE id = ?');
        $location->execute([$locationId]);
        if (!$location->fetchColumn()) throw new DomainException('Escolha o local que receberá os lançamentos importados.');

        return Transaction::run(function () use ($records, $locationId, $adjustStock, $userId, $pdo, $catalog, $clients, $sales, $settings): array {
            $locations = [];
            foreach ($catalog->locations() as $row) $locations[$this->key($row['name'])] = (int) $row['id'];
            $flavors = [];
            foreach ($catalog->flavors() as $row) $flavors[$this->key($row['name'])] = (int) $row['id'];
            $sizes = [];
            foreach ($catalog->sizes() as $row) $sizes[$this->key($row['name'])] = (int) $row['id'];

            $insert = $pdo->prepare(
                'INSERT INTO ctl_sales (order_no, sale_date, client_id, location_id, flavor_id, size_id, qty, unit_price, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $imported = 0;
            $skipped = 0;
            $notes = [];
            $highest = 0;
            $autoNumber = null;
            $number = 0;

            foreach ($records as $record) {
                $number++;
                $clientName = trim((string) ($record['cliente'] ?? ''));
                $flavorName = trim((string) ($record['sabor'] ?? ''));
                $sizeName = trim((string) ($record['tam'] ?? ''));
                $qty = filter_var($record['qtd'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
                $status = $this->statusFrom((string) ($record['status'] ?? ''));
                if ($clientName === '' || $flavorName === '' || $sizeName === '' || $qty === false || $status === null) {
                    $skipped++;
                    if (count($notes) < 8) $notes[] = "Linha $number ignorada (cliente, sabor, tamanho, quantidade ou status inválidos).";
                    continue;
                }

                $flavorKey = $this->key($flavorName);
                if (!isset($flavors[$flavorKey])) {
                    $pdo->prepare('INSERT INTO ctl_flavors (name) VALUES (?)')->execute([mb_substr($flavorName, 0, 120)]);
                    $flavors[$flavorKey] = (int) $pdo->lastInsertId();
                }
                $sizeKey = $this->key($sizeName);
                if (!isset($sizes[$sizeKey])) {
                    $pdo->prepare('INSERT INTO ctl_sizes (name, volume_ml) VALUES (?, 1)')->execute([mb_substr($sizeName, 0, 60)]);
                    $sizes[$sizeKey] = (int) $pdo->lastInsertId();
                    if (count($notes) < 8) $notes[] = "Tamanho novo \"$sizeName\" criado com volume 1 mL. Ajuste o volume em Produtos para o custo ficar correto.";
                }

                $rowLocation = $locations[$this->key((string) ($record['local'] ?? ''))] ?? $locationId;
                $orderNo = (int) preg_replace('/\D+/', '', (string) ($record['pedido'] ?? ''));
                if ($orderNo < 1) {
                    $autoNumber ??= $settings->nextNumber('next_order_no', 'SELECT COALESCE(MAX(order_no), 0) FROM ctl_sales');
                    $orderNo = $autoNumber;
                }
                $price = $status === 'resgate' ? 0.0 : (Money::parse($record['valor'] ?? '') ?? 0.0);
                $date = $this->dateFrom((string) ($record['data'] ?? ''));
                $clientId = $clients->findOrCreate($clientName, (string) ($record['telefone'] ?? ''));

                $insert->execute([$orderNo, $date, $clientId, $rowLocation, $flavors[$flavorKey], $sizes[$sizeKey], $qty, $price, $status, $userId]);
                $saleId = (int) $pdo->lastInsertId();
                if ($adjustStock) {
                    $sales->syncStock(null, ['id' => $saleId, 'order_no' => $orderNo, 'location_id' => $rowLocation, 'flavor_id' => $flavors[$flavorKey], 'size_id' => $sizes[$sizeKey], 'qty' => $qty, 'status' => $status], $userId);
                }
                $highest = max($highest, $orderNo);
                $imported++;
            }
            if ($highest > 0) $settings->ensureAbove('next_order_no', $highest);
            return ['imported' => $imported, 'skipped' => $skipped, 'notes' => $notes];
        });
    }

    /** @return array{imported:int,skipped:int,notes:string[]} */
    public function importClients(string $content, string $extension): array
    {
        $records = $this->records($content, $extension, 'clients');
        if (count($records) > self::MAX_ROWS) throw new DomainException('O arquivo tem mais de ' . self::MAX_ROWS . ' linhas.');
        $clients = new ClienteService();
        $pdo = Database::pdo();
        return Transaction::run(function () use ($records, $clients, $pdo): array {
            $imported = 0;
            $skipped = 0;
            $find = $pdo->prepare('SELECT 1 FROM ctl_clients WHERE name = ?');
            foreach ($records as $record) {
                $name = trim((string) ($record['nome'] ?? $record['name'] ?? ''));
                $phone = trim((string) ($record['telefone'] ?? $record['phone'] ?? ''));
                if ($name === '' || mb_strlen($name) > 120) {
                    $skipped++;
                    continue;
                }
                $find->execute([$name]);
                if ($find->fetchColumn()) {
                    $skipped++;
                    $clients->findOrCreate($name, $phone);
                    continue;
                }
                $clients->findOrCreate($name, $phone);
                $imported++;
            }
            return ['imported' => $imported, 'skipped' => $skipped, 'notes' => []];
        });
    }

    // ------------------------------------------------------------------ internos

    /** Transforma CSV ou JSON em lista de registros associativos com chaves em minúsculas. */
    private function records(string $content, string $extension, string $jsonKey): array
    {
        $content = trim($content);
        if ($content === '') throw new DomainException('O arquivo está vazio.');
        if ($extension === 'json') {
            $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content, true);
            if (!is_array($data)) throw new DomainException('O JSON não pôde ser lido.');
            $list = array_is_list($data) ? $data : ($data[$jsonKey] ?? []);
            if (!is_array($list)) throw new DomainException("O JSON não tem a lista \"$jsonKey\".");
            return array_map(static fn($row) => array_change_key_case(is_array($row) ? $row : [], CASE_LOWER), $list);
        }
        $rows = Csv::parse($content);
        if (count($rows) < 2) throw new DomainException('O CSV precisa ter o cabeçalho e ao menos uma linha.');
        $header = array_shift($rows);
        $records = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($header as $index => $name) $record[$name] = $row[$index] ?? '';
            $records[] = $record;
        }
        return $records;
    }

    private function key(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
    }

    private function statusFrom(string $text): ?string
    {
        $plain = strtr($this->key($text), ['à' => 'a', 'á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
        if ($plain === '') return 'a_fazer';
        return match ($plain) {
            'a fazer', 'afazer' => 'a_fazer',
            'a entregar', 'aentregar' => 'a_entregar',
            'entregue' => 'entregue',
            'pago' => 'pago',
            'cancelado' => 'cancelado',
            'resgate', 'resgatado' => 'resgate',
            default => null,
        };
    }

    private function dateFrom(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return "$m[1]-$m[2]-$m[3]";
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $text, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        return date('Y-m-d');
    }
}
