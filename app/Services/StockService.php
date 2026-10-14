<?php
namespace App\Services;

// Estoque (RF07, RF08, RF14, RF15)
class StockService
{
    public function lowStock(): array { throw new \LogicException('TODO'); }          // RF15: alerta de mínimo
    public function addEntry(int $flavorId, int $qty): void { throw new \LogicException('TODO'); }
    public function closeMonth(string $yyyyMm): void { throw new \LogicException('TODO'); } // RF14: histórico mensal
}
