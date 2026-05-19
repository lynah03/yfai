<?php
namespace App\Controller\Web;

use App\Form\WebContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecommendationController extends AbstractController
{
 

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ){
    
    }
    #[Route('/', name: 'home', methods: ['GET','POST'])]
    public function index(Request $request): Response
    {
       $form = $this->createForm(WebContactFormType::class);
       $form->handleRequest($request);
       if($form->isSubmitted() && $form->isValid()){
           $data = $form->getData();
           $this->entityManager->persist($data);
           return $this->redirectToRoute('home');
       }
        return $this->render('web/index.html.twig',[
            'form' => $form->createView(),
        ]);
    }
    #[Route('/find-your-scent', name: 'recommendation_form', methods: ['GET'])]
    public function recommandation(): Response
    {
        return $this->render('recommendation/index.html.twig');
    }

    #[Route('/recommandation', name: 'recommendation_legacy_redirect', methods: ['GET'])]
    public function legacyRecommandation(): RedirectResponse
    {
        return $this->redirectToRoute('recommendation_form', [], Response::HTTP_MOVED_PERMANENTLY);
    }
    
    
}
