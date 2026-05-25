<?php

namespace App\Controller\Api;

use App\Service\PerfumeMatcher;
use App\Service\RecommendationResultPresenter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class QuizRecommendationApiController extends AbstractController
{
    public function __construct(
        private readonly PerfumeMatcher $matcher,
        private readonly RecommendationResultPresenter $resultPresenter,
        #[Autowire(service: 'limiter.api_post')]
        private readonly RateLimiterFactory $apiPostLimiter,
    ) {
    }

    #[Route('/quiz/recommendations', name: 'quiz_recommendations', methods: ['POST'])]
    public function recommendations(Request $request): JsonResponse
    {
        $rateLimit = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rateLimit->consume(1)->isAccepted()) {
            return $this->json([
                'ok' => false,
                'error' => 'rate_limited',
                'message' => 'Too many requests. Please try again shortly.',
            ], 429);
        }

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

        $limit = 4;
        $maxReasons = isset($data['maxReasons']) && is_numeric($data['maxReasons'])
            ? max(0, min(8, (int) $data['maxReasons']))
            : 5;

        $ranked = $this->matcher->recommendForNonUser($input, $limit, 0, $maxReasons);
        $matchPercents = $this->resultPresenter->calculateQuizMatchPercentages($ranked);

        $results = [];
        foreach ($ranked as $index => $row) {
            $result = $this->resultPresenter->presentQuizResult(
                row: $row,
                matchPercent: $matchPercents[$index] ?? 80,
                input: $input
            );

            if ($result === null) {
                continue;
            }

            $results[] = $result;
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
}
