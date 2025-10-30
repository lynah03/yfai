<?php
namespace App\Controller;

use App\Entity\Brand;
use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalog')]
final class CatalogController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/brands', methods: ['GET'])]
    public function brands(Request $r): JsonResponse
    {
        $q = trim((string)$r->query->get('query', ''));
        $limit = max(1, min(100, (int)$r->query->get('limit', 50)));

        $qb = $this->em->getRepository(Brand::class)->createQueryBuilder('b')
            ->select('b.id, b.name')
            ->orderBy('b.name', 'ASC')
            ->setMaxResults($limit);

        if ($q !== '') {
            $qb->andWhere('LOWER(b.name) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        return $this->json([
            'count'   => count($rows = $qb->getQuery()->getArrayResult()),
            'results' => $rows,
        ]);
    }

    #[Route('/notes', methods: ['GET'])]
    public function notes(Request $r): JsonResponse
    {
        $q = trim((string)$r->query->get('query', ''));
        $limit = max(1, min(100, (int)$r->query->get('limit', 50)));

        $qb = $this->em->getRepository(Note::class)->createQueryBuilder('n')
            ->select('n.id, n.name')
            ->orderBy('n.name', 'ASC')
            ->setMaxResults($limit);

        if ($q !== '') {
            $qb->andWhere('LOWER(n.name) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        return $this->json([
            'count'   => count($rows = $qb->getQuery()->getArrayResult()),
            'results' => $rows,
        ]);
    }
}
