<?php
namespace App\Controller\Web;

use App\Form\WebContactFormType;
use App\Service\PerfumeMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecommendationController extends AbstractController
{
 

    public function __construct(
        private readonly PerfumeMatcher $matcher,
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
    #[Route('/recommandation', name: 'recommendation_form', methods: ['GET','POST'])]
    public function recommandation(Request $request): Response
    {
        $recommendation = null;
        if ($request->isMethod('POST')) {
            $profile = implode(' ', [
                $request->request->get('gender',''),
                $request->request->get('experience',''),
                $request->request->get('purpose',''),
                $request->request->get('aesthetic',''),
                $request->request->get('mood',''),
                $request->request->get('preferred_notes',''),
                $request->request->get('family',''),
                $request->request->get('projection',''),
                $request->request->get('concentration',''),
                $request->request->get('budget','')
            ]);
            $recommendation = $this->matcher->recommendForNonUser($profile);
        }
        return $this->render('recommendation/index.html.twig', ['recommendation' => $recommendation]);
    }
    
    
}
