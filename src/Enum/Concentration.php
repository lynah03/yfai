<?php
namespace App\Enum;

enum Concentration: string
{
    case EDC     = 'EDC';
    case EDT     = 'EDT';
    case EDP     = 'EDP';
    case PARFUM  = 'PARFUM';
    case EXTRAIT = 'EXTRAIT';

    public static function parse(?string $value): ?self
    {
        if ($value === null) { return null; }
        $v = strtoupper(trim($value));
        $aliases = [
            'COLOGNE' => 'EDC',
            'EAU DE COLOGNE' => 'EDC',
            'EAU DE TOILETTE' => 'EDT',
            'EAU DE PARFUM' => 'EDP',
            'EXTRAIT DE PARFUM' => 'EXTRAIT',
            'EXTRAIT PARFUM' => 'EXTRAIT',
            'PARFUM EXTRAIT' => 'EXTRAIT',
            'PERFUME EXTRACT' => 'EXTRAIT',
        ];
        $v = $aliases[$v] ?? $v;
        return self::tryFrom($v);
    }

    public static function values(): array
    {
        return array_map(static fn(self $c) => $c->value, self::cases());
    }

    public function strength(): int
    {
        return match($this) {
            self::EDC => 1,
            self::EDT => 2,
            self::EDP => 3,
            self::PARFUM => 4,
            self::EXTRAIT => 5,
        };
    }
}



