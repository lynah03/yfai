<?php

namespace App\Service;

use App\Entity\Perfume;
use App\Enum\Concentration;
use App\Enum\MarketingGender;

final class RecommendationResultPresenter
{
    private const MOOD_LABELS = [
        'mysterious' => 'dark, magnetic and quietly powerful',
        'sensual' => 'warm, intimate and addictive',
        'powerful' => 'bold, confident and unforgettable',
        'clean' => 'fresh, elegant and quietly refined',
    ];

    /**
     * Match percentage is a display score for the consultation UI, not a statistical probability.
     *
     * @param array<int,array{score?:float|int}> $ranked
     * @return array<int,int>
     */
    public function calculateQuizMatchPercentages(array $ranked): array
    {
        $fallback = [96, 92, 88, 84];
        $scores = array_map(static fn(array $row): float => (float) ($row['score'] ?? 0), $ranked);

        if ($scores === []) {
            return [];
        }

        $highest = max($scores);
        $lowest = min($scores);
        $spread = $highest - $lowest;

        if ($spread < 0.01) {
            return array_slice($fallback, 0, count($scores));
        }

        $percentages = [];
        $previous = 100;

        foreach ($scores as $index => $score) {
            $normalized = ($score - $lowest) / $spread;
            $percent = (int) round(78 + ($normalized * 20) - ($index * 1.4));
            $percent = max(72, min(99, $percent));

            if ($index === 0) {
                $percent = max(96, $percent);
            } else {
                $percent = min($percent, $previous - 2);
                $percent = max(72, $percent);
            }

            $percentages[] = $percent;
            $previous = $percent;
        }

        return $percentages;
    }

    /**
     * @param array{perfume?:mixed,score?:float|int} $row
     * @param array<string,mixed> $input
     * @return array<string,mixed>|null
     */
    public function presentQuizResult(array $row, int $matchPercent, array $input): ?array
    {
        if (!isset($row['perfume']) || !$row['perfume'] instanceof Perfume) {
            return null;
        }

        $perfume = $row['perfume'];
        $concentration = $perfume->getConcentration();
        $notes = $this->serializeNotes($perfume);
        $accords = $this->serializeAccords($perfume);

        return [
            'perfumeId' => $perfume->getId(),
            'brand' => $perfume->getBrand()?->getName(),
            'name' => $perfume->getName(),
            'score' => round((float) ($row['score'] ?? 0), 2),
            'matchPercent' => $matchPercent,
            'shortDescription' => $perfume->getShortDescription(),
            'description' => $perfume->getDescription(),
            'image' => $this->resolveImageUrl($perfume->getImage()),
            'productUrl' => $perfume->getProductUrl(),
            'concentration' => $concentration instanceof Concentration ? $concentration->value : null,
            'price' => $this->serializePrice($perfume),
            'notes' => $notes,
            'accords' => $accords,
            'seasons' => $perfume->getSeasons(),
            'occasions' => $perfume->getOccasions(),
            'displayReason' => $this->buildDisplayReason($input, $notes, $accords, $perfume->getOccasions()),
        ];
    }

    /**
     * @param array{perfume:Perfume,score:float|int,reasons?:array<int,string>} $row
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function presentConciergeResult(array $row, array $input): array
    {
        $perfume = $row['perfume'];

        $concentration = $perfume->getConcentration();
        $marketingGender = $perfume->getMarketingGender();
        $image = $perfume->getImage();
        $reasons = $row['reasons'] ?? [];

        return [
            'perfumeId' => $perfume->getId(),
            'brandId' => $perfume->getBrand()?->getId(),
            'brand' => $perfume->getBrand()?->getName(),

            'name' => $perfume->getName(),
            'shortDescription' => $perfume->getShortDescription(),
            'description' => $perfume->getDescription(),

            'image' => $image,
            'imageUrl' => $this->resolveImageUrl($image),
            'productUrl' => $perfume->getProductUrl(),

            'concentration' => $concentration instanceof Concentration ? $concentration->value : null,
            'marketingGender' => $marketingGender instanceof MarketingGender ? $marketingGender->value : null,

            'listPriceCents' => $perfume->getListPriceCents(),
            'listPriceCurrency' => $perfume->getListPriceCurrency(),

            'score' => $row['score'],

            'conciergeReason' => $this->buildConciergeReason(
                perfumeName: (string) $perfume->getName(),
                input: $input,
                reasons: $reasons,
                concentration: $concentration instanceof Concentration ? $concentration->value : null
            ),
        ];
    }

    /**
     * @return array<int,array{name:string,layer:string}>
     */
    private function serializeNotes(Perfume $perfume): array
    {
        $notes = [];
        $layerOrder = ['TOP' => 0, 'HEART' => 1, 'BASE' => 2];

        foreach ($perfume->getPerfumeNotes() as $perfumeNote) {
            $noteName = $perfumeNote->getNote()?->getName();

            if (!$noteName) {
                continue;
            }

            $notes[] = [
                'name' => $noteName,
                'layer' => strtoupper($perfumeNote->getLayer()),
            ];
        }

        usort(
            $notes,
            static fn(array $a, array $b): int => ($layerOrder[$a['layer']] ?? 99) <=> ($layerOrder[$b['layer']] ?? 99)
        );

        return $notes;
    }

    /**
     * @return array<int,string>
     */
    private function serializeAccords(Perfume $perfume): array
    {
        $accords = [];

        foreach ($perfume->getAccords() as $accord) {
            $label = $accord->getLabel() ?: $accord->getCode();

            if ($label) {
                $accords[] = $label;
            }
        }

        return array_values(array_unique($accords));
    }

    /**
     * @return array{amount:float,currency:string,formatted:string}|null
     */
    private function serializePrice(Perfume $perfume): ?array
    {
        $priceCents = $perfume->getListPriceCents();

        if ($priceCents === null) {
            return null;
        }

        $currency = $perfume->getListPriceCurrency();
        $amount = $priceCents / 100;

        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => $this->formatPrice($amount, $currency),
        ];
    }

    private function formatPrice(float $amount, string $currency): string
    {
        $formattedAmount = floor($amount) === $amount
            ? number_format($amount, 0, '.', ',')
            : number_format($amount, 2, '.', ',');

        if (strtoupper($currency) === 'EUR') {
            return '€'.$formattedAmount;
        }

        return $formattedAmount.' '.strtoupper($currency);
    }

    private function resolveImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return '/uploads/perfumes/'.$image;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<int,array{name:string,layer:string}> $notes
     * @param array<int,string> $accords
     * @param array<int,string> $occasions
     */
    private function buildDisplayReason(array $input, array $notes, array $accords, array $occasions): string
    {
        $preferredAccords = $this->normalizeStringList($input['preferred_accords'] ?? [], 16);
        $preferredNotes = $this->normalizeStringList($input['preferred_notes'] ?? [], 8);
        $allAccords = array_map(
            static fn(string $item): string => strtoupper(str_replace([' ', '-'], '_', $item)),
            array_merge($preferredAccords, $accords)
        );

        $noteNames = $preferredNotes !== []
            ? $preferredNotes
            : array_map(static fn(array $note): string => $note['name'], array_slice($notes, 0, 3));

        $noteText = $this->formatList(array_slice($noteNames, 0, 3));
        $occasionText = isset($input['preferred_occasions']) && is_array($input['preferred_occasions']) && $input['preferred_occasions'] !== []
            ? strtolower(str_replace('_', ' ', (string) $input['preferred_occasions'][0]))
            : ($occasions !== [] ? strtolower(str_replace('_', ' ', $occasions[0])) : null);

        $sentence = 'Selected because its notes and atmosphere closely reflect the profile you described.';

        if ($this->containsAny($allAccords, ['CITRUS', 'AROMATIC', 'FRESH'])) {
            $sentence = 'A luminous fresh direction, chosen for clean radiance, clarity and easy skin presence.';
        } elseif ($this->containsAny($allAccords, ['AMBERY', 'GOURMAND', 'VANILLA'])) {
            $sentence = 'Chosen for its warm amber profile, refined sweetness and sensual evening depth.';
        } elseif ($this->containsAny($allAccords, ['WOODY', 'LEATHERY', 'SMOKY'])) {
            $sentence = 'Selected for a dark textured character, with magnetic woods and a polished after-dark mood.';
        } elseif ($this->containsAny($allAccords, ['MUSKY', 'POWDERY', 'CLEAN'])) {
            $sentence = 'A soft close-to-skin signature, aligned with your preference for quiet polish and clean intimacy.';
        } elseif ($this->containsAny($allAccords, ['FLORAL'])) {
            $sentence = 'A refined floral direction, chosen for softness, elegance and a romantic sense of presence.';
        }

        if ($noteText !== '') {
            $sentence .= ' It echoes your attraction to '.$noteText.'.';
        }

        if ($occasionText) {
            $sentence .= ' Its mood also feels naturally suited to '.$occasionText.'.';
        }

        return $sentence;
    }

    /**
     * Creates a polished client-facing explanation for the AI Scent Concierge.
     *
     * This hides technical matcher details and turns them into luxury copy.
     *
     * @param array<string,mixed> $input
     * @param array<int,string> $reasons
     */
    private function buildConciergeReason(
        string $perfumeName,
        array $input,
        array $reasons,
        ?string $concentration
    ): string {
        $mood = isset($input['mood']) && is_string($input['mood'])
            ? strtolower(trim($input['mood']))
            : null;

        $moodLabel = $mood && isset(self::MOOD_LABELS[$mood])
            ? self::MOOD_LABELS[$mood]
            : 'distinctive and personal';

        $preferredNotes = $this->normalizeSimpleStringList($input['preferred_notes'] ?? []);
        $matchedNotes = $this->extractMatchedNotes($reasons, $preferredNotes);
        $noteText = $this->formatList($matchedNotes);

        $sentence = sprintf(
            'Selected for your %s profile',
            $moodLabel
        );

        if ($noteText !== '') {
            $sentence .= sprintf(
                ' and your attraction to %s',
                $noteText
            );
        }

        $sentence .= sprintf(
            ' — %s mirrors that energy with a scent signature that feels refined, intentional and close to your personal aura.',
            $perfumeName
        );

        if ($concentration) {
            $sentence .= sprintf(
                ' Its %s concentration also stays aligned with the presence you asked your concierge to keep in mind.',
                $this->humanizeEnumValue($concentration)
            );
        }

        return $sentence;
    }

    /**
     * @param array<int,string> $reasons
     * @param array<int,string> $preferredNotes
     * @return array<int,string>
     */
    private function extractMatchedNotes(array $reasons, array $preferredNotes): array
    {
        $excludedKeywords = [
            'marque',
            'brand',
            'concentration',
            'budget',
            'accord',
            'genre',
            'gender',
            'saison',
            'season',
            'occasion',
        ];

        $notes = [];

        foreach ($reasons as $reason) {
            if (!is_string($reason)) {
                continue;
            }

            $lowerReason = mb_strtolower($reason);

            foreach ($excludedKeywords as $keyword) {
                if (str_contains($lowerReason, $keyword)) {
                    continue 2;
                }
            }

            if (preg_match('/^[+-]?\d+(?:\.\d+)?\s+(.+?)(?:\s+\(|$)/u', $reason, $matches)) {
                $note = trim($matches[1]);

                if ($note !== '') {
                    $notes[] = $note;
                }
            }
        }

        if (!$notes) {
            $notes = $preferredNotes;
        }

        $notes = array_values(array_unique(array_filter($notes)));

        return array_slice($notes, 0, 3);
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private function normalizeStringList(mixed $value, int $maxItems): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim(strip_tags($item));
            $item = preg_replace('/\s+/', ' ', $item) ?? '';
            $item = mb_substr($item, 0, 80);

            if ($item === '') {
                continue;
            }

            $key = mb_strtolower($item);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $item;

            if (count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private function normalizeSimpleStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<int,string> $haystack
     * @param array<int,string> $needles
     */
    private function containsAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $items
     */
    private function formatList(array $items): string
    {
        $items = array_values(array_filter(array_map('trim', $items)));

        if (count($items) === 0) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        if (count($items) === 2) {
            return $items[0].' and '.$items[1];
        }

        return implode(', ', array_slice($items, 0, -1)).' and '.$items[count($items) - 1];
    }

    private function humanizeEnumValue(string $value): string
    {
        return strtolower(str_replace('_', ' ', $value));
    }
}
