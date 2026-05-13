<?php

namespace App\Controller\Api;

use App\Entity\Brand;
use App\Service\PerfumeMatcher;
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
        private readonly PerfumeMatcher $matcher,
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

        $limit = isset($data['limit']) ? (int) $data['limit'] : 5;
        $offset = isset($data['offset']) ? (int) $data['offset'] : 0;
        $maxReasons = isset($data['maxReasons']) ? (int) $data['maxReasons'] : 8;

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $maxReasons = max(0, min(20, $maxReasons));

        $ranked = $this->matcher->recommendForNonUser(
            input: $data,
            limit: 500,
            offset: 0,
            maxReasons: $maxReasons
        );

        $brandResults = array_values(array_filter(
            $ranked,
            static function (array $row) use ($company): bool {
                $perfume = $row['perfume'];

                return $perfume->getBrand()?->getId() === $company->getId();
            }
        ));

        $brandResults = array_slice($brandResults, $offset, $limit);

        $results = array_map(static function (array $row): array {
            $perfume = $row['perfume'];

            return [
                'perfumeId' => $perfume->getId(),
                'brandId' => $perfume->getBrand()?->getId(),
                'brand' => $perfume->getBrand()?->getName(),
                'name' => $perfume->getName(),
                'score' => $row['score'],
                'reasons' => $row['reasons'] ?? [],
            ];
        }, $brandResults);

        return $this->json([
            'ok' => true,
            'customer' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'country' => $company->getCountry(),
            ],
            'count' => count($results),
            'limit' => $limit,
            'offset' => $offset,
            'results' => $results,
        ]);
    }
}