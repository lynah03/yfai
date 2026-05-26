<?php

namespace App\Controller\Api;

use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class NoteSuggestionApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.api_get')]
        private readonly RateLimiterFactory $apiGetLimiter,
    ) {
    }

    #[Route('/notes/suggestions', name: 'notes_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $rateLimit = $this->apiGetLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rateLimit->consume(1)->isAccepted()) {
            return $this->json([
                'ok' => false,
                'error' => 'rate_limited',
                'message' => 'Too many requests. Please try again shortly.',
            ], 429);
        }

        $query = trim(mb_substr((string) $request->query->get('q', ''), 0, 60));
        $limit = max(1, min(12, (int) $request->query->get('limit', 8)));

        $builder = $this->entityManager
            ->getRepository(Note::class)
            ->createQueryBuilder('note')
            ->select('note.id, note.name, note.family')
            ->orderBy('note.name', 'ASC')
            ->setMaxResults($limit);

        if ($query !== '') {
            $builder
                ->andWhere('LOWER(note.name) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        return $this->json([
            'results' => array_map(
                static fn (array $note): array => [
                    'id' => (int) $note['id'],
                    'name' => (string) $note['name'],
                    'family' => $note['family'] ?? null,
                ],
                $builder->getQuery()->getArrayResult()
            ),
        ]);
    }
}
