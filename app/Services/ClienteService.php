<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Transaction;
use DomainException;
use PDOException;

/** Clientes do controle de vendas (não é a conta de acesso da loja online). */
final class ClienteService
{
    public function list(string $search = ''): array
    {
        $sql = "SELECT c.id, c.name, c.phone,
                       COUNT(DISTINCT s.order_no) AS orders_count,
                       COALESCE(SUM(CASE WHEN s.status NOT IN ('cancelado', 'resgate') THEN s.qty * s.unit_price END), 0) AS total_spent,
                       COALESCE(SUM(CASE WHEN s.status IN ('a_fazer', 'a_entregar', 'entregue') THEN s.qty * s.unit_price END), 0) AS open_amount
                FROM ctl_clients c LEFT JOIN ctl_sales s ON s.client_id = c.id";
        $params = [];
        if (trim($search) !== '') {
            $like = '%' . addcslashes(trim($search), '%_\\') . '%';
            $sql .= ' WHERE c.name LIKE ? OR c.phone LIKE ?';
            $params = [$like, $like];
        }
        $stmt = Database::pdo()->prepare($sql . ' GROUP BY c.id, c.name, c.phone ORDER BY c.name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, name, phone FROM ctl_clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Devolve o cliente com esse nome (cadastra se ainda não existir). */
    public function findOrCreate(string $name, string $phone = ''): int
    {
        $name = $this->cleanName($name);
        $phone = $this->cleanPhone($phone);
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id, phone FROM ctl_clients WHERE name = ?');
        $find->execute([$name]);
        $found = $find->fetch();
        if (!$found) {
            try {
                $pdo->prepare('INSERT INTO ctl_clients (name, phone) VALUES (?, ?)')->execute([$name, $phone]);
                return (int) $pdo->lastInsertId();
            } catch (PDOException $error) {
                if ($error->getCode() !== '23000') throw $error;
                $find->execute([$name]);
                $found = $find->fetch();
                if (!$found) throw $error;
            }
        }
        if ($phone !== '' && (string) $found['phone'] === '') {
            $pdo->prepare('UPDATE ctl_clients SET phone = ? WHERE id = ?')->execute([$phone, $found['id']]);
        }
        return (int) $found['id'];
    }

    /** Cria ou edita. Se o novo nome já existir em outro cadastro, os dois são mesclados. Devolve o id final. */
    public function save(?int $id, string $name, string $phone): int
    {
        $name = $this->cleanName($name);
        $phone = $this->cleanPhone($phone);
        $pdo = Database::pdo();
        return (int) Transaction::run(function () use ($pdo, $id, $name, $phone): int {
            $same = $pdo->prepare('SELECT id FROM ctl_clients WHERE name = ?');
            $same->execute([$name]);
            $otherId = $same->fetchColumn();
            if ($id === null) {
                if ($otherId !== false) throw new DomainException('Já existe um cliente com esse nome.');
                $pdo->prepare('INSERT INTO ctl_clients (name, phone) VALUES (?, ?)')->execute([$name, $phone]);
                return (int) $pdo->lastInsertId();
            }
            if ($otherId !== false && (int) $otherId !== $id) {
                $other = (int) $otherId;
                $pdo->prepare('UPDATE ctl_sales SET client_id = ? WHERE client_id = ?')->execute([$other, $id]);
                $pdo->prepare('UPDATE ctl_gifts SET client_id = ? WHERE client_id = ?')->execute([$other, $id]);
                $pdo->prepare("UPDATE ctl_clients SET phone = IF(phone = '', ?, phone) WHERE id = ?")->execute([$phone, $other]);
                $pdo->prepare('DELETE FROM ctl_clients WHERE id = ?')->execute([$id]);
                return $other;
            }
            $pdo->prepare('UPDATE ctl_clients SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $id]);
            return $id;
        });
    }

    public function delete(int $id): void
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ctl_sales WHERE client_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            throw new DomainException('Este cliente tem lançamentos e não pode ser removido. Para juntar cadastros repetidos, edite o nome para igualar ao outro.');
        }
        Transaction::run(function () use ($pdo, $id): void {
            $pdo->prepare('DELETE FROM ctl_gifts WHERE client_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM ctl_clients WHERE id = ?')->execute([$id]);
        });
    }

    /** Texto de cobrança para copiar e colar no WhatsApp. Devolve null se o cliente não tem valores em aberto. */
    public function accountMessage(int $clientId): ?string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT f.name AS flavor, z.name AS size, SUM(s.qty) AS qty, SUM(s.qty * s.unit_price) AS total
             FROM ctl_sales s JOIN ctl_flavors f ON f.id = s.flavor_id JOIN ctl_sizes z ON z.id = s.size_id
             WHERE s.client_id = ? AND s.status IN ('a_fazer', 'a_entregar', 'entregue') AND s.qty * s.unit_price > 0
             GROUP BY f.id, f.name, z.id, z.name, z.volume_ml ORDER BY f.name, z.volume_ml"
        );
        $stmt->execute([$clientId]);
        $lines = $stmt->fetchAll();
        if (!$lines) return null;
        $message = '';
        $total = 0.0;
        foreach ($lines as $line) {
            $message .= \money($line['total']) . ' por ' . (int) $line['qty'] . ' ' . $line['flavor'] . ' ' . $line['size'] . "\n";
            $total += (float) $line['total'];
        }
        $message .= "\nTotal " . \money($total);
        $pix = trim((string) \config('app.pix_key'));
        if ($pix !== '') $message .= "\nPix: " . $pix;
        return $message;
    }

    public static function whatsappLink(string $phone, string $text = ''): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 10) return null;
        if (!str_starts_with($digits, '55')) $digits = '55' . $digits;
        return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 120) throw new DomainException('Informe o nome do cliente (até 120 caracteres).');
        return $name;
    }

    private function cleanPhone(string $phone): string
    {
        $phone = trim($phone);
        if (mb_strlen($phone) > 30) throw new DomainException('O telefone deve ter no máximo 30 caracteres.');
        return $phone;
    }
}
