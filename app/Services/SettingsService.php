<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Pequenas configurações do controle (contadores e regras da fidelidade) guardadas em ctl_settings. */
final class SettingsService
{
    public function get(string $key, string $default = ''): string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM ctl_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO ctl_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $value]);
    }

    /** Reserva e devolve o próximo número de uma sequência (pedido ou lote), nunca repetindo um número já usado. */
    public function nextNumber(string $settingKey, string $usedSql): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT setting_value FROM ctl_settings WHERE setting_key = ? FOR UPDATE');
        $stmt->execute([$settingKey]);
        $stored = $stmt->fetchColumn();
        $next = max(1, (int) ($stored === false ? 1 : $stored), (int) $pdo->query($usedSql)->fetchColumn() + 1);
        $this->set($settingKey, (string) ($next + 1));
        return $next;
    }

    /** Garante que o contador fique acima de um número informado manualmente ou importado. */
    public function ensureAbove(string $settingKey, int $used): void
    {
        if ((int) $this->get($settingKey, '1') <= $used) $this->set($settingKey, (string) ($used + 1));
    }
}
