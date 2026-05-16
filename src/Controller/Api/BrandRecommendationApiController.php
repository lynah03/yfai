<?php

namespace App\Controller\Api;

use App\Entity\Brand;
use App\Service\BrandRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class BrandRecommendationApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BrandRecommendationService $brandRecommendationService,
    ) {
    }

    #[Route('/brandsApi/{customerName}/recommandation', name: 'brand_recommendation_legacy', methods: ['POST'])]
    #[Route('/partners/{customerName}/recommendations', name: 'brand_recommendation', methods: ['POST'])]
    public function recommendForBrand(Request $request, string $customerName): JsonResponse
    {
        /** @var Brand|null $company */
        $company = $this->em
            ->getRepository(Brand::class)
            ->findOneBy(['name' => $customerName]);

        if (!$company) {
            return $this->json([
                'ok' => false,
                'error' => 'unknown_customer',
                'message' => sprintf('Unknown brand/customer "%s".', $customerName),
            ], 404);
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
}