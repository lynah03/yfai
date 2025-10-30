<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthController extends AbstractController
{
    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(Connection $db): JsonResponse
    {
        $db->executeQuery('SELECT 1');
        return $this->json(['ok' => true, 'ts' => time()]);
    }
}
