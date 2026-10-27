<?php
declare(strict_types=1);

namespace App\Core;

final class Money
{
    /** Aceita "17", "17.5", "17,50" e "1.500,00". Devolve null quando vazio ou inválido. */
    public static function parse(mixed $value): ?float
    {
        $text = trim(str_replace(['R$', ' '], '', (string) $value));
        if ($text === '') return null;
        if (str_contains($text, ',')) $text = str_replace(',', '.', str_replace('.', '', $text));
        if (!preg_match('/^\d{1,7}(?:\.\d{1,4})?$/', $text)) return null;
        return (float) $text;
    }
}
