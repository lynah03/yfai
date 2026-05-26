<?php

namespace App\Controller\Dashboard;

use App\Entity\Brand;
use App\Entity\PartnerApiKey;
use App\Repository\BrandRepository;
use App\Repository\PartnerApiKeyRepository;
use App\Service\PartnerApiKeyManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/integration', name: 'dashboard_integration_')]
final class IntegrationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, BrandRepository $brandRepository, PartnerApiKeyRepository $partnerApiKeyRepository): Response
    {
        /** @var Brand[] $brands */
        $brands = $brandRepository->findBy([], ['name' => 'ASC']);
        $selectedBrand = $this->resolveSelectedBrand($request, $brands);
        $origin = rtrim($request->getSchemeAndHttpHost(), '/');
        $generatedPartnerKey = $this->consumeGeneratedPartnerKey($request, $selectedBrand);

        // TODO: Replace request-derived origin with a configured public app URL for production/staging.
        // TODO: Add allowed domains, analytics, and server-side widget customization.
        // TODO: When dashboard users are mapped to partner brands, restrict this page to the assigned brand.
        $response = $this->render('dashboard/integration/index.html.twig', [
            'brands' => $brands,
            'selectedBrand' => $selectedBrand,
            'partnerApiKeys' => $selectedBrand ? $partnerApiKeyRepository->findForBrand($selectedBrand) : [],
            'generatedPartnerKey' => $generatedPartnerKey,
            'scriptSrc' => $origin.'/concierge.js',
            'widgetBaseUrl' => $origin.'/ai-scent-concierge',
        ]);

        if ($generatedPartnerKey !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    #[Route('/api-keys/generate', name: 'api_key_generate', methods: ['POST'])]
    public function generateApiKey(Request $request, BrandRepository $brandRepository, PartnerApiKeyManager $partnerApiKeyManager): RedirectResponse
    {
        $brand = $brandRepository->find($request->request->getInt('brand'));

        if (!$brand instanceof Brand) {
            $this->addFlash('danger', 'Brand not found.');

            return $this->redirectToRoute('dashboard_integration_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('generate_partner_api_key'.$brand->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'The API key could not be generated. Please try again.');

            return $this->redirectToBrand($brand);
        }

        $label = trim((string) $request->request->get('label', ''));
        $generation = $partnerApiKeyManager->generateForBrand($brand, $label !== '' ? $label : null);
        $apiKey = $generation['apiKey'];

        $request->getSession()->set('dashboard_integration_generated_partner_key', [
            'brandId' => $brand->getId(),
            'plainKey' => $generation['plainKey'],
            'prefix' => $apiKey->getKeyPrefix(),
            'label' => $apiKey->getLabel(),
        ]);

        $this->addFlash('success', 'Partner API key generated. Copy it now; it will not be shown again.');

        return $this->redirectToBrand($brand);
    }

    #[Route('/api-keys/{id}/revoke', name: 'api_key_revoke', methods: ['POST'])]
    public function revokeApiKey(Request $request, PartnerApiKey $apiKey, PartnerApiKeyManager $partnerApiKeyManager): RedirectResponse
    {
        $brand = $apiKey->getBrand();

        if (!$brand instanceof Brand) {
            $this->addFlash('danger', 'API key brand not found.');

            return $this->redirectToRoute('dashboard_integration_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('revoke_partner_api_key'.$apiKey->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'The API key could not be revoked. Please try again.');

            return $this->redirectToBrand($brand);
        }

        if (!$apiKey->isRevoked()) {
            $partnerApiKeyManager->revoke($apiKey);
            $this->addFlash('success', 'Partner API key revoked.');
        }

        return $this->redirectToBrand($brand);
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

    /**
     * @return array{brandId:int|null,plainKey:string,prefix:string,label:string|null}|null
     */
    private function consumeGeneratedPartnerKey(Request $request, ?Brand $selectedBrand): ?array
    {
        if (!$request->hasSession() || !$selectedBrand instanceof Brand) {
            return null;
        }

        $session = $request->getSession();
        $generatedPartnerKey = $session->remove('dashboard_integration_generated_partner_key');

        if (!is_array($generatedPartnerKey) || ($generatedPartnerKey['brandId'] ?? null) !== $selectedBrand->getId()) {
            return null;
        }

        return [
            'brandId' => $generatedPartnerKey['brandId'] ?? null,
            'plainKey' => (string) ($generatedPartnerKey['plainKey'] ?? ''),
            'prefix' => (string) ($generatedPartnerKey['prefix'] ?? ''),
            'label' => isset($generatedPartnerKey['label']) ? (string) $generatedPartnerKey['label'] : null,
        ];
    }

    private function redirectToBrand(Brand $brand): RedirectResponse
    {
        return $this->redirectToRoute('dashboard_integration_index', [
            'brand' => $brand->getId(),
        ], Response::HTTP_SEE_OTHER);
    }
}
