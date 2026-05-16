<?php

namespace App\Service;

use App\Entity\Brand;
use App\Enum\Concentration;
use App\Enum\MarketingGender;

final class BrandRecommendationService
{
    private const MOOD_LABELS = [
        'mysterious' => 'dark, magnetic and quietly powerful',
        'sensual' => 'warm, intimate and addictive',
        'powerful' => 'bold, confident and unforgettable',
        'clean' => 'fresh, elegant and quietly refined',
    ];

    public function __construct(
        private readonly PerfumeMatcher $matcher,
    ) {
    }

    /**
     * @return array{
     *     count:int,
     *     totalAvailable:int,
     *     limit:int,
     *     offset:int,
     *     maxReasons:int,
     *     results:array<int,array<string,mixed>>
     * }
     */
    public function recommendForBrand(Brand $brand, array $input): array
    {
        $limit = isset($input['limit']) ? (int) $input['limit'] : 5;
        $offset = isset($input['offset']) ? (int) $input['offset'] : 0;
        $maxReasons = isset($input['maxReasons']) ? (int) $input['maxReasons'] : 8;

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $maxReasons = max(0, min(20, $maxReasons));

        $ranked = $this->matcher->recommendForNonUser(
            input: $input,
            limit: 500,
            offset: 0,
            maxReasons: $maxReasons
        );

        $brandResults = array_values(array_filter(
            $ranked,
            static function (array $row) use ($brand): bool {
                $perfume = $row['perfume'];

                return $perfume->getBrand()?->getId() === $brand->getId();
            }
        ));

        $totalAvailable = count($brandResults);
        $brandResults = array_slice($brandResults, $offset, $limit);

        $results = array_map(function (array $row) use ($input): array {
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
                'reasons' => $reasons,

                'conciergeReason' => $this->buildConciergeReason(
                    perfumeName: (string) $perfume->getName(),
                    input: $input,
                    reasons: $reasons,
                    concentration: $concentration instanceof Concentration ? $concentration->value : null
                ),
            ];
        }, $brandResults);

        return [
            'count' => count($results),
            'totalAvailable' => $totalAvailable,
            'limit' => $limit,
            'offset' => $offset,
            'maxReasons' => $maxReasons,
            'results' => $results,
        ];
    }

    private function resolveImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/')) {
            return $image;
        }

        return '/uploads/perfumes/'.$image;
    }

    /**
     * Creates a polished client-facing explanation for the AI Scent Concierge.
     *
     * This hides technical matcher details and turns them into luxury copy.
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

        $preferredNotes = $this->normalizeStringList($input['preferred_notes'] ?? []);
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
    private function normalizeStringList(mixed $value): array
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
