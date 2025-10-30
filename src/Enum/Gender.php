<?php
namespace App\Enum;

enum Gender: string
{
    case FEMALE      = 'FEMALE';
    case MALE        = 'MALE';
    case NONBINARY   = 'NONBINARY';
    case UNDISCLOSED = 'UNDISCLOSED';

    /** Convertit une chaîne en enum, en gérant alias et abréviations. */
    public static function parse(?string $value): ?self
    {
        if ($value === null) { return null; }
        $v = strtoupper(trim($value));
        $aliases = [
            'F' => 'FEMALE', 'WOMAN' => 'FEMALE', 'WOMEN' => 'FEMALE', 'FEMALE' => 'FEMALE',
            'M' => 'MALE',   'MAN' => 'MALE',   'MEN'   => 'MALE',   'MALE'   => 'MALE',
            'NB' => 'NONBINARY', 'NON-BINARY' => 'NONBINARY', 'NON BINARY' => 'NONBINARY', 'ENBY' => 'NONBINARY',
            'N/A' => 'UNDISCLOSED', 'UNKNOWN' => 'UNDISCLOSED', 'PREFERS NOT TO SAY' => 'UNDISCLOSED', 'UNSPECIFIED' => 'UNDISCLOSED',
        ];
        $v = $aliases[$v] ?? $v;
        return self::tryFrom($v);
    }

    /** Valeurs string pratiques pour des sélecteurs/formulaires. */
    public static function values(): array
    {
        return array_map(static fn(self $g) => $g->value, self::cases());
    }
}
