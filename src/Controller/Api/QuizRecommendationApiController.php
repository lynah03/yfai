<?php

namespace App\Controller\Api;

use App\Entity\Perfume;
use App\Enum\Concentration;
use App\Service\PerfumeMatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class QuizRecommendationApiController extends AbstractController
{
    public function __construct(
        private readonly PerfumeMatcher $matcher,
    ) {
    }

    #[Route('/quiz/recommendations', name: 'quiz_recommendations', methods: ['POST'])]
    public function recommendations(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'ok' => false,
                'error' => 'invalid_json',
                'message' => 'Request body must be valid JSON.',
            ], 400);
        }

        [$input, $errors] = $this->sanitizeInput($data);

        if ($errors !== []) {
            return $this->json([
                'ok' => false,
                'error' => 'validation_failed',
                'details' => $errors,
            ], 400);
        }

        $limit = 5;
        $maxReasons = isset($data['maxReasons']) && is_numeric($data['maxReasons'])
            ? max(0, min(8, (int) $data['maxReasons']))
            : 5;

        $ranked = $this->matcher->recommendForNonUser($input, $limit, 0, $maxReasons);
        $matchPercents = $this->calculateMatchPercentages($ranked);

        $results = [];
        foreach ($ranked as $index => $row) {
            if (!isset($row['perfume']) || !$row['perfume'] instanceof Perfume) {
                continue;
            }

            $results[] = $this->serializePerfume(
                perfume: $row['perfume'],
                row: $row,
                matchPercent: $matchPercents[$index] ?? 80,
                input: $input
            );
        }

        return $this->json([
            'ok' => true,
            'count' => count($results),
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    private function sanitizeInput(array $data): array
    {
        $errors = [];
        $input = [];

        $arrayFields = [
            'preferred_notes' => 32,
            'disliked_notes' => 32,
            'preferred_brands' => 24,
            'preferred_accords' => 32,
            'preferred_seasons' => 8,
            'preferred_occasions' => 12,
        ];

        foreach ($arrayFields as $field => $maxItems) {
            $items = $this->normalizeStringList($data[$field] ?? [], $maxItems);

            if ($items !== []) {
                $input[$field] = $items;
            }
        }

        if (isset($data['concentration']) && is_string($data['concentration'])) {
            $concentration = strtoupper(trim($data['concentration']));
            if (in_array($concentration, ['EDC', 'EDT', 'EDP', 'PARFUM', 'EXTRAIT'], true)) {
                $input['concentration'] = $concentration;
            } elseif ($concentration !== '') {
                $errors[] = 'Unsupported concentration value.';
            }
        }

        if (isset($data['gender']) && is_string($data['gender'])) {
            $gender = strtoupper(trim($data['gender']));
            if (in_array($gender, ['FEMALE', 'MALE', 'NONBINARY', 'UNDISCLOSED'], true)) {
                $input['gender'] = $gender;
            } elseif ($gender !== '') {
                $errors[] = 'Unsupported gender value.';
            }
        }

        $budgetMin = $this->parseNullableNumber($data, 'budget_min', $errors);
        $budgetMax = $this->parseNullableNumber($data, 'budget_max', $errors);

        if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
            $errors[] = 'budget_min cannot be greater than budget_max.';
        }

        if ($budgetMin !== null) {
            $input['budget_min'] = $budgetMin;
        }

        if (array_key_exists('budget_max', $data)) {
            $input['budget_max'] = $budgetMax;
        }

        return [$input, $errors];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $errors
     */
    private function parseNullableNumber(array $data, string $field, array &$errors): ?float
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        if (!is_numeric($data[$field])) {
            $errors[] = sprintf('%s must be a number or null.', $field);
            return null;
        }

        return max(0.0, min(10000.0, (float) $data[$field]));
    }

    /**
     * Match percentage is a display score for the consultation UI, not a statistical probability.
     *
     * @param array<int,array{score:float|int}> $ranked
     * @return array<int,int>
     */
    private function calculateMatchPercentages(array $ranked): array
    {
        $fallback = [96, 92, 88, 84, 80];
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
     * @param array{score?:float|int,reasons?:array<int,string>} $row
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function serializePerfume(Perfume $perfume, array $row, int $matchPercent, array $input): array
    {
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
            'reasons' => $row['reasons'] ?? [],
            'displayReason' => $this->buildDisplayReason($input, $notes, $accords, $perfume->getOccasions()),
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
}
