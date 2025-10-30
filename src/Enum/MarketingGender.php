<?php
namespace App\Enum;

enum MarketingGender: string
{
    case MEN    = 'MEN';
    case WOMEN  = 'WOMEN';
    case UNISEX = 'UNISEX';

    public static function parse(?string $v): ?self
    {
        if ($v === null) return null;
        return self::tryFrom(strtoupper(trim($v)));
    }

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
