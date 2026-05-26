<?php

namespace App\Controller\Api;

use App\Service\BrandRecommendationService;
use App\Service\PartnerApiKeyManager;
use App\Service\PartnerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class BrandRecommendationApiController extends AbstractController
{
    public function __construct(
        private readonly BrandRecommendationService $brandRecommendationService,
        private readonly PartnerApiKeyManager $partnerApiKeyManager,
        private readonly PartnerResolver $partnerResolver,
    ) {
    }

    #[Route('/brandsApi/{customerName}/recommandation', name: 'brand_recommendation_legacy', methods: ['POST'])]
    #[Route('/partners/{customerName}/recommendations', name: 'brand_recommendation', methods: ['POST'])]
    public function recommendForBrand(Request $request, string $customerName): JsonResponse
    {
        $company = $this->partnerResolver->resolve($customerName);

        if (!$company) {
            return $this->json([
                'ok' => false,
                'error' => 'unknown_customer',
                'message' => sprintf('Unknown brand/customer "%s".', $customerName),
            ], 404);
        }

        if ($this->requiresPartnerApiKey($request)) {
            $partnerKey = $this->extractPartnerKey($request);

            if ($partnerKey === null || $this->partnerApiKeyManager->verifyForBrand($company, $partnerKey) === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_partner_key',
                    'message' => 'A valid partner API key is required.',
                ], 401);
            }
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'ok' => false,
                'error' => 'invalid_json',
                'message' => 'Request body must be valid JSON.',
            ], 400);
        }

        $recommendations = $this->brandRecommendationService->recommendForBrand($company, $data);

        return $this->json([
            'ok' => true,
            'customer' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'country' => $company->getCountry(),
            ],
            'count' => $recommendations['count'],
            'totalAvailable' => $recommendations['totalAvailable'],
            'limit' => $recommendations['limit'],
            'offset' => $recommendations['offset'],
            'maxReasons' => $recommendations['maxReasons'],
            'results' => $recommendations['results'],
        ]);
    }

    private function requiresPartnerApiKey(Request $request): bool
    {
        return $request->attributes->get('_route') === 'api_brand_recommendation';
    }

    private function extractPartnerKey(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');

        if (is_string($authorization) && preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $authorization, $matches) === 1) {
            $key = trim($matches[1]);

            if ($key !== '') {
                return $key;
            }
        }

        $headerKey = $request->headers->get('X-YFAI-Partner-Key');

        if (!is_string($headerKey)) {
            return null;
        }

        $headerKey = trim($headerKey);

        return $headerKey !== '' ? $headerKey : null;
    }
}
