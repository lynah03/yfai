<?php
namespace App\Enum;

enum Season: string
{
    case SPRING = 'SPRING';
    case SUMMER = 'SUMMER';
    case FALL   = 'FALL';
    case WINTER = 'WINTER';

    /** Convertit une chaîne en enum, en gérant alias EN/FR. */
    public static function parse(?string $value): ?self
    {
        if ($value === null) { return null; }
        $v = strtoupper(trim($value));
        $aliases = [
            // EN
            'AUTUMN' => 'FALL',
            // FR
            'PRINTEMPS' => 'SPRING',
            'ÉTÉ' => 'SUMMER', 'ETE' => 'SUMMER',
            'AUTOMNE' => 'FALL',
            'HIVER' => 'WINTER',
        ];
        $v = $aliases[$v] ?? $v;
        return self::tryFrom($v);
    }

    /** Valeurs string pour formulaires/sérialisation */
    public static function values(): array
    {
        return array_map(static fn(self $s) => $s->value, self::cases());
    }
}
