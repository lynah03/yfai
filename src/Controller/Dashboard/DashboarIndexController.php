<?php
    namespace App\Controller\Dashboard;
    

    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Attribute\Route;
    
    #[Route('/dashboard', name: 'dashboard_index_')]
    final class DashboarIndexController extends AbstractController
    {
        public function __construct(
            private EntityManagerInterface $em
        ) {}
        
        #[Route('/', name:'home', methods: ['GET'])]
        public function index(Request $r): Response
        {
        
        return $this->render('dashboard/dashboard_home/index.html.twig', [
            'controller_name' => 'DashboardIndexController',
        ]);
        }
    }
