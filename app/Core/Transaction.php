<?php
declare(strict_types=1);

namespace App\Core;

/** Executa um bloco dentro de uma transação; se já houver uma aberta, apenas reaproveita. */
final class Transaction
{
    public static function run(callable $work): mixed
    {
        $pdo = Database::pdo();
        if ($pdo->inTransaction()) return $work();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
