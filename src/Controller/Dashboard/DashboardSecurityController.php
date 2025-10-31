<?php

namespace App\Controller\Dashboard;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardSecurityController extends AbstractController
{
    public function __construct
    (
        private AuthenticationUtils $authenticationUtils,
        private TranslatorInterface $translator
    )
    {
    }

    #[Route('/dashboard/login', name: 'app_dashboard_login')]
    public function login(): Response
    {

          $error = $this->authenticationUtils->getLastAuthenticationError();
        /*
         * //dd($error);
        if ($error !== null && $error instanceof AuthenticationException) {
            // Handle the authentication error
            $errorMessage = $error->getMessage();
        } else {
            $errorMessage = null;
        }
         */
        
        if ($this->getUser()) {
            if($this->getUser()->getIsActive() === false) {
               $this->addFlash('danger', $this->translator->trans('app.dashboard.login.user_is_inactive',[],'dashboard'));
                return $this->redirectToRoute('app_dashboard_logout');
            }
           $this->getDefaultSuccessRedirectUrl($this->getUser());
        }

        return $this->render('dashboard/dashboard_security/login.html.twig', [
            'controller_name' => $this->translator->trans('app.dashboard.login.controller_name',[],'dashboard') ,
            'error' => $error,
        ]);
    }
    #[Route('/dashboard/logout', name: 'app_dashboard_logout')]
    public function logout(): Response
    {
        throw new \Exception('This should never be reached!');

        return $this->render('dashboard/dashboard_security/login.html.twig', [
            'controller_name' => $this->translator->trans('app.dashboard.logout.controller_name',[],'dashboard') ,
        ]);
    }
    
    protected function getDefaultSuccessRedirectUrl($user): RedirectResponse
    {
        return $this->redirectToRoute('dashboard_index_home');
    }
}
