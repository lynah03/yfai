<?php

namespace App\Controller\Widget;

use App\Entity\Brand;
use App\Service\BrandRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ai-scent-concierge', name: 'ai_scent_concierge_')]
final class AiScentConciergeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BrandRecommendationService $brandRecommendationService,
    ) {
    }

    #[Route('/{customerName}', name: 'show', methods: ['GET'])]
    public function show(string $customerName): Response
    {
        $brand = $this->findBrand($customerName);

        if (!$brand) {
            return $this->prepareEmbeddableResponse($this->render('widget/ai_scent_concierge/error.html.twig', [
                'customerName' => $customerName,
            ], new Response(status: Response::HTTP_NOT_FOUND)));
        }

        return $this->prepareEmbeddableResponse($this->render('widget/ai_scent_concierge/index.html.twig', [
            'brand' => $brand,
        ]));
    }

    #[Route('/{customerName}/recommend', name: 'recommend', methods: ['POST'])]
    public function recommend(Request $request, string $customerName): JsonResponse
    {
        $brand = $this->findBrand($customerName);

        if (!$brand) {
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

        $data['preferred_brands'] = [$brand->getName()];
        $data['limit'] = isset($data['limit']) ? (int) $data['limit'] : 3;
        $data['maxReasons'] = isset($data['maxReasons']) ? (int) $data['maxReasons'] : 5;

        $recommendations = $this->brandRecommendationService->recommendForBrand($brand, $data);

        return $this->json([
            'ok' => true,
            'customer' => [
                'id' => $brand->getId(),
                'name' => $brand->getName(),
                'country' => $brand->getCountry(),
            ],
            'count' => $recommendations['count'],
            'totalAvailable' => $recommendations['totalAvailable'],
            'limit' => $recommendations['limit'],
            'offset' => $recommendations['offset'],
            'maxReasons' => $recommendations['maxReasons'],
            'results' => $recommendations['results'],
        ]);
    }

    private function findBrand(string $customerName): ?Brand
    {
        return $this->em
            ->getRepository(Brand::class)
            ->findOneBy(['name' => $customerName]);
    }

    private function prepareEmbeddableResponse(Response $response): Response
    {
        // TODO: replace open framing with per-partner allowed domains when the partner security phase is added.
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $response;
    }
}
