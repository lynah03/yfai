<?php

namespace App\Controller\Dashboard;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/integration', name: 'dashboard_integration_')]
final class IntegrationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, BrandRepository $brandRepository): Response
    {
        /** @var Brand[] $brands */
        $brands = $brandRepository->findBy([], ['name' => 'ASC']);
        $selectedBrand = $this->resolveSelectedBrand($request, $brands);
        $origin = rtrim($request->getSchemeAndHttpHost(), '/');

        // TODO: Replace request-derived origin with a configured public app URL for production/staging.
        // TODO: Add partner slugs, API keys, allowed domains, analytics, and server-side widget customization.
        // TODO: When dashboard users are mapped to partner brands, restrict this page to the assigned brand.
        return $this->render('dashboard/integration/index.html.twig', [
            'brands' => $brands,
            'selectedBrand' => $selectedBrand,
            'scriptSrc' => $origin.'/concierge.js',
            'widgetBaseUrl' => $origin.'/ai-scent-concierge',
        ]);
    }

    /**
     * @param Brand[] $brands
     */
    private function resolveSelectedBrand(Request $request, array $brands): ?Brand
    {
        $requestedId = $request->query->getInt('brand');

        foreach ($brands as $brand) {
            if ($brand->getId() === $requestedId) {
                return $brand;
            }
        }

        return $brands[0] ?? null;
    }
}
