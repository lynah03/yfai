<?php
    namespace App\Controller\Api;
    
    use App\Entity\Brand;
    use App\Entity\UserProfile;
    use App\Service\PerfumeMatcher;
    use App\Service\UserPreferenceManager;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;

// ⚠️ Attributes (et pas Annotations)
    use Symfony\Component\Routing\Attribute\Route;
    
    use App\Dto\GetRecommendationsRequest;
    use App\Dto\PutNotePreferencesRequest;
    use App\Dto\PutBrandPreferencesRequest;
    use App\Dto\PutConcentrationPreferencesRequest;
    use App\Dto\PutOccasionPreferencesRequest;
    use App\Dto\PutSeasonPreferencesRequest;
    use App\Dto\RecommendRequest; // <— AJOUT
    
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    use Symfony\Component\Validator\ConstraintViolationListInterface;

// Rate limiter
    use Symfony\Component\RateLimiter\RateLimiterFactory;
    use Symfony\Component\DependencyInjection\Attribute\Autowire;
    
    #[Route('/api', name:'api_')] // préfixe commun aux routes de ce contrôleur
    class TestApi extends AbstractController
    {
        public function __construct(
            private EntityManagerInterface $em,
            private PerfumeMatcher $matcher,
            // limiteurs nommés (déclarés dans ta config rate_limiter)
        ) {}
        
        #[Route('/brandsApi/{customerName}/recommandation', name: 'hello_world', methods: ['POST'])]
        public function ping(Request $request,$customerName): JsonResponse
        {
            $content = $request->getContent();
            $company = $this->em->getRepository(Brand::class)->findOneBy(['name' => $customerName]);
            if(!$company){
                return $this->json(['ok' => false, 'error' => 'Unknown customer'],404);
            }
            //
            return $this->json(['ok' => true, 'ts' => time(),'customerName' => $company->getName(),'country' => $company->getCountry(),'content' => $content],200);
        }
    }
