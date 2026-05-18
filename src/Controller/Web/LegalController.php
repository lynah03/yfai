<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/privacy', name: 'privacy_policy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('web/privacy.html.twig');
    }

    #[Route('/terms', name: 'terms_of_use', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('web/terms.html.twig');
    }
}
