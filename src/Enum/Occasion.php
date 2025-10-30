<?php
namespace App\Enum;

enum Occasion: string
{
    case CASUAL  = 'CASUAL';
    case WORK    = 'WORK';
    case DATE    = 'DATE';
    case EVENING = 'EVENING';
    case FORMAL  = 'FORMAL';
    case SPORT   = 'SPORT';

    /** Convertit une chaîne en enum, en gérant alias et variantes courantes. */
    public static function parse(?string $value): ?self
    {
        if ($value === null) { return null; }
        $v = strtoupper(trim($value));
        $aliases = [
            // CASUAL
            'DAYTIME' => 'CASUAL', 'DAILY' => 'CASUAL', 'EVERYDAY' => 'CASUAL', 'CASUAL' => 'CASUAL',
            // WORK
            'OFFICE' => 'WORK', 'JOB' => 'WORK', 'BUSINESS' => 'WORK', 'WORK' => 'WORK',
            // DATE
            'DATE NIGHT' => 'DATE', 'ROMANTIC' => 'DATE', 'RENDEZ-VOUS' => 'DATE', 'DATE' => 'DATE',
            // EVENING
            'NIGHT OUT' => 'EVENING', 'PARTY' => 'EVENING', 'SOIRÉE' => 'EVENING', 'EVENING' => 'EVENING',
            // FORMAL
            'BLACK TIE' => 'FORMAL', 'CEREMONY' => 'FORMAL', 'FORMEL' => 'FORMAL', 'FORMAL' => 'FORMAL',
            // SPORT
            'GYM' => 'SPORT', 'WORKOUT' => 'SPORT', 'SPORTIF' => 'SPORT', 'SPORT' => 'SPORT',
        ];
        $v = $aliases[$v] ?? $v;
        return self::tryFrom($v);
    }

    /** Valeurs string pratiques pour des sélecteurs/formulaires. */
    public static function values(): array
    {
        return array_map(static fn(self $o) => $o->value, self::cases());
    }
}
