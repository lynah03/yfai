<?php
namespace App\Controller;

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

#[Route('/api')] // préfixe commun aux routes de ce contrôleur
class RecommendationApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PerfumeMatcher $matcher,
        // limiteurs nommés (déclarés dans ta config rate_limiter)
        #[Autowire(service: 'limiter.api_get')]  private RateLimiterFactory $apiGetLimiter,
        #[Autowire(service: 'limiter.api_post')] private RateLimiterFactory $apiPostLimiter,
    ) {}

    #[Route('/ping', name: 'api_ping', methods: ['GET'])]
    public function ping(Request $request): JsonResponse
    {
        $rl = $this->apiGetLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        return $this->json(['ok' => true, 'ts' => time()]);
    }

    #[Route('/users/{idOrName}/recommendations', name: 'api_recommendations_get', methods: ['GET'])]
    public function getRecommendations(string $idOrName, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $rl = $this->apiGetLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        // 1) Map query -> DTO
        $dto = GetRecommendationsRequest::fromQueryBag($request->query);

        // 2) Validation
        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json([
                'error'   => 'validation_failed',
                'details' => $this->violationsToArray($violations),
            ], 400);
        }

        // 3) Résoudre l'utilisateur
        $user = $this->resolveUser($idOrName);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // 4) maxReasons — défaut 8 si absent, clamp [0..50]
        $maxReasons = property_exists($dto, 'maxReasons')
            ? (int) ($dto->maxReasons ?? 8)
            : (int) $request->query->get('maxReasons', 8);

        if ($maxReasons < 0)  { $maxReasons = 0; }
        if ($maxReasons > 50) { $maxReasons = 50; }

        // 5) Matching paginé
        $ranked = $this->matcher->rankForUserPaged($user, $dto->limit, $dto->offset, $maxReasons);

        // 6) Mapping JSON
        $results = array_map(static function (array $row) {
            /** @var \App\Entity\Perfume $p */
            $p = $row['perfume'];
            return [
                'perfumeId' => $p->getId(),
                'brandId'   => $p->getBrand()?->getId(),
                'brand'     => $p->getBrand()?->getName(),
                'name'      => $p->getName(),
                'score'     => $row['score'],
                'reasons'   => $row['reasons'] ?? [],
            ];
        }, $ranked);

        return $this->json([
            'user'    => ['id' => $user->getId(), 'name' => $user->getName()],
            'count'   => count($results),
            'limit'   => $dto->limit,
            'offset'  => $dto->offset,
            'results' => $results,
        ]);
    }

    #[Route('/recommend', name: 'api_recommend', methods: ['POST'])]
    public function recommend(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        // 1) DTO from body
        $data = json_decode($request->getContent(), true) ?? [];
        $dto  = RecommendRequest::fromArray($data);

        // 2) Validation (inclut le Callback 'validate')
        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json([
                'error'   => 'validation_failed',
                'details' => $this->violationsToArray($violations),
            ], 400);
        }

        // 3) Résoudre l'utilisateur via userId OU user (name)
        $user = null;
        if ($dto->userId !== null) {
            $user = $this->em->getRepository(UserProfile::class)->find((int) $dto->userId);
        } elseif ($dto->user !== null) {
            $user = $this->em->getRepository(UserProfile::class)->findOneBy(['name' => $dto->user]);
        }

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // 4) Matching
        $ranked = $this->matcher->rankForUser($user, $dto->limit);

        $results = array_map(static fn(array $row) => [
            'perfumeId' => $row['perfume']->getId(),
            'brandId'   => $row['perfume']->getBrand()?->getId(),
            'brand'     => $row['perfume']->getBrand()?->getName(),
            'name'      => $row['perfume']->getName(),
            'score'     => $row['score'],
            'reasons'   => $row['reasons'],
        ], $ranked);

        return $this->json([
            'user'    => ['id' => $user->getId(), 'name' => $user->getName()],
            'count'   => count($results),
            'results' => $results,
        ]);
    }

    // ------------------------------------------------------------------
    //                       PUT PREFERENCES ENDPOINTS
    // ------------------------------------------------------------------

    #[Route('/users/{idOrName}/preferences/notes', name: 'api_put_prefs_notes', methods: ['PUT'])]
    public function putNotePreferences(
        string $idOrName,
        Request $request,
        ValidatorInterface $validator,
        UserPreferenceManager $prefMgr
    ): JsonResponse {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $user = $this->resolveUser($idOrName);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        $dto = PutNotePreferencesRequest::fromArray(json_decode($request->getContent(), true) ?? []);
        $viol = $validator->validate($dto);
        if (count($viol) > 0) {
            return $this->json(['error'=>'validation_failed','details'=>$this->violationsToArray($viol)], 400);
        }

        $result = $prefMgr->putNotes($user, $dto->items);

        return $this->json([
            'user'    => ['id'=>$user->getId(),'name'=>$user->getName()],
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
    }

    #[Route('/users/{idOrName}/preferences/brands', name: 'api_put_prefs_brands', methods: ['PUT'])]
    public function putBrandPreferences(
        string $idOrName,
        Request $request,
        ValidatorInterface $validator,
        UserPreferenceManager $prefMgr
    ): JsonResponse {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $user = $this->resolveUser($idOrName);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        $dto = PutBrandPreferencesRequest::fromArray(json_decode($request->getContent(), true) ?? []);
        $viol = $validator->validate($dto);
        if (count($viol) > 0) {
            return $this->json(['error'=>'validation_failed','details'=>$this->violationsToArray($viol)], 400);
        }

        $result = $prefMgr->putBrands($user, $dto->items);

        return $this->json([
            'user'    => ['id'=>$user->getId(),'name'=>$user->getName()],
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
    }

    #[Route('/users/{idOrName}/preferences/concentration', name: 'api_put_prefs_conc', methods: ['PUT'])]
    public function putConcentrationPreferences(
        string $idOrName,
        Request $request,
        ValidatorInterface $validator,
        UserPreferenceManager $prefMgr
    ): JsonResponse {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $user = $this->resolveUser($idOrName);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        $dto = PutConcentrationPreferencesRequest::fromArray(json_decode($request->getContent(), true) ?? []);
        $viol = $validator->validate($dto);
        if (count($viol) > 0) {
            return $this->json(['error'=>'validation_failed','details'=>$this->violationsToArray($viol)], 400);
        }

        $result = $prefMgr->putConcentration($user, $dto->items);

        return $this->json([
            'user'    => ['id'=>$user->getId(),'name'=>$user->getName()],
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
    }

    #[Route('/users/{idOrName}/preferences/occasions', name: 'api_put_prefs_occ', methods: ['PUT'])]
    public function putOccasionPreferences(
        string $idOrName,
        Request $request,
        ValidatorInterface $validator,
        UserPreferenceManager $prefMgr
    ): JsonResponse {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $user = $this->resolveUser($idOrName);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        $dto = PutOccasionPreferencesRequest::fromArray(json_decode($request->getContent(), true) ?? []);
        $viol = $validator->validate($dto);
        if (count($viol) > 0) {
            return $this->json(['error'=>'validation_failed','details'=>$this->violationsToArray($viol)], 400);
        }

        $result = $prefMgr->putOccasions($user, $dto->items);

        return $this->json([
            'user'    => ['id'=>$user->getId(),'name'=>$user->getName()],
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
    }

    #[Route('/users/{idOrName}/preferences/seasons', name: 'api_put_prefs_seas', methods: ['PUT'])]
    public function putSeasonPreferences(
        string $idOrName,
        Request $request,
        ValidatorInterface $validator,
        UserPreferenceManager $prefMgr
    ): JsonResponse {
        $rl = $this->apiPostLimiter->create($request->getClientIp() ?? 'anon');
        if (!$rl->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $user = $this->resolveUser($idOrName);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        $dto = PutSeasonPreferencesRequest::fromArray(json_decode($request->getContent(), true) ?? []);
        $viol = $validator->validate($dto);
        if (count($viol) > 0) {
            return $this->json(['error'=>'validation_failed','details'=>$this->violationsToArray($viol)], 400);
        }

        $result = $prefMgr->putSeasons($user, $dto->items);

        return $this->json([
            'user'    => ['id'=>$user->getId(),'name'=>$user->getName()],
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
    }

    // ------------------------------------------------------------------

    private function violationsToArray(ConstraintViolationListInterface $violations): array
    {
        $out = [];
        foreach ($violations as $v) {
            $out[] = [
                'field'   => $v->getPropertyPath(),
                'message' => $v->getMessage(),
                'code'    => $v->getCode(),
            ];
        }
        return $out;
    }

    private function resolveUser(string $idOrName): ?UserProfile
    {
        if (ctype_digit($idOrName)) {
            return $this->em->getRepository(UserProfile::class)->find((int)$idOrName);
        }
        return $this->em->getRepository(UserProfile::class)->findOneBy(['name' => $idOrName]);
    }
}
