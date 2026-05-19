<?php

namespace App\Controller\Widget;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConciergeIntegrationDemoController extends AbstractController
{
    #[Route('/partner-demo/{partner}', name: 'concierge_partner_demo', methods: ['GET'], defaults: ['partner' => 'Dior'])]
    public function __invoke(string $partner): Response
    {
        return $this->render('widget/demo_partner.html.twig', [
            'partner' => $partner,
        ]);
    }
}
